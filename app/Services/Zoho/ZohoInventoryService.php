<?php

namespace App\Services\Zoho;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * ZohoInventoryService with deep logging & safe name lookup
 *
 * - Всегда передаём organization_id и в query, и в заголовке.
 * - Логируем ВСЕ шаги (поиск/создание контакта и создание SO).
 * - По имени принимаем ТОЛЬКО точное совпадение contact_name;
 *   иначе — создаём контакт, чтобы не привязывать «левого» customer.
 */
class ZohoInventoryService
{
    protected ZohoAuthService $auth;
    protected string $inventoryBaseUrl;
    protected string $organizationId;
    protected int $timeoutMs;
    protected string $retryPolicy;
    protected string $logLevel;

    public function __construct(ZohoAuthService $auth, array $config)
    {
        $this->auth             = $auth;
        $this->inventoryBaseUrl = (string) ($config['inventory_base_url'] ?? '');
        $this->organizationId   = (string) ($config['organization_id'] ?? '');
        $this->timeoutMs        = (int)    ($config['timeout_ms'] ?? 20000);
        $this->retryPolicy      = (string) ($config['retry_policy'] ?? 'standard');
        $this->logLevel         = (string) ($config['log_level'] ?? 'info');
    }

    // ------------------------------------------------------------------
    // Low-level helpers + logging
    // ------------------------------------------------------------------

    /**
     * Build a configured HTTP client for Zoho Inventory.
     * Adds conditional retry/backoff based on $this->retryPolicy.
     */
    protected function http(): PendingRequest
    {
        $token = $this->auth->getAccessToken();

        $client = Http::withHeaders([
                'Authorization'             => 'Zoho-oauthtoken ' . $token,
                'X-com-zoho-organizationid' => $this->organizationId,
                'Accept'                    => 'application/json',
                'Content-Type'              => 'application/json',
            ])
            ->baseUrl(rtrim($this->inventoryBaseUrl, '/'))
            ->timeout($this->timeoutMs / 1000)
            ->withQueryParameters(['organization_id' => $this->organizationId]);

        // Add retry/backoff depending on policy
        [$times, $sleepMs] = $this->retryConfig();
        if ($times > 0) {
            // Retry on 429 and typical transient 5xx.
            // Laravel's Http::retry callback receives ($exception, $request, $response).
            $shouldRetry = function ($exception, $request, $response) {
                try {
                    $status = $response ? $response->status() : null;
                } catch (\Throwable $e) {
                    $status = null;
                }
                return in_array($status, [429, 500, 502, 503, 504], true);
            };

            $client = $client->retry($times, $sleepMs, $shouldRetry);
        }

        return $client;
    }

    /**
     * Map retry policy to concrete numbers.
     * - none        => no retries
     * - standard    => 3 retries, 300ms backoff
     * - aggressive  => 5 retries, 600ms backoff
     */
    protected function retryConfig(): array
    {
        switch (strtolower($this->retryPolicy)) {
            case 'none':
                return [0, 0];
            case 'aggressive':
                return [5, 600];
            case 'standard':
            default:
                return [3, 300];
        }
    }

    protected function request(string $method, string $path, array $options = []): array
    {
        $url   = '/' . ltrim($path, '/');
        $query = (array)($options['query'] ?? []);
        $json  = (array)($options['json']  ?? []);

        $client = $this->http();

        $this->logOutbound('REQ', $method, $url, $query, $json);

        $response = match (strtoupper($method)) {
            'GET'    => $client->get($url, $query),
            'POST'   => $client->withQueryParameters($query)->post($url, $json),
            'PUT'    => $client->withQueryParameters($query)->put($url, $json),
            'DELETE' => $client->withQueryParameters($query)->delete($url, $json),
            default  => throw new RuntimeException('Unsupported HTTP method: ' . $method),
        };

        $this->logInbound('RESP', $method, $url, $response->status(), $response->json(), $response->body());

        // IMPORTANT: use ->successful() (2xx) instead of ->ok() (only 200).
        if (!$response->successful()) {
            $body = $response->json();
            $msg  = is_array($body) ? ($body['message'] ?? $response->body()) : $response->body();
            throw new RuntimeException('Zoho API error: ' . $msg);
        }

        $data = $response->json();
        if (isset($data['code']) && $data['code'] !== 0) {
            throw new RuntimeException('Zoho API error: ' . ($data['message'] ?? 'Unknown'));
        }

        return $data;
    }

    protected function logOutbound(string $tag, string $method, string $url, array $query = [], array $json = []): void
    {
        $payloadPreview = $this->truncate(json_encode($json, JSON_UNESCAPED_UNICODE), 2048);
        $queryPreview   = $this->truncate(json_encode($query, JSON_UNESCAPED_UNICODE), 2048);

        Log::info("[Zoho][$tag] {$method} {$url}", [
            'organization_id' => $this->organizationId,
            'query'           => $queryPreview,
            'json'            => $payloadPreview,
        ]);
    }

    protected function logInbound(string $tag, string $method, string $url, int $status, $json, string $raw): void
    {
        $jsonPreview = $this->truncate(is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : '', 2048);
        $rawPreview  = $jsonPreview ?: $this->truncate($raw, 2048);

        Log::info("[Zoho][$tag] {$method} {$url}", [
            'status'   => $status,
            'response' => $rawPreview,
        ]);
    }

    protected function truncate(?string $text, int $limit): string
    {
        $text = (string)($text ?? '');
        if (mb_strlen($text) <= $limit) return $text;
        return mb_substr($text, 0, $limit) . '… (truncated)';
    }

    // ------------------------------------------------------------------
    // Contacts
    // ------------------------------------------------------------------

    /**
     * Ensure a customer exists and return contact_id.
     * Email → exact; Name → exact match only (safe); else create + re-query.
     */
    public function ensureCustomer(array $customer): string
    {
        $email = trim((string) ($customer['email'] ?? ''));
        $name  = trim((string) ($customer['name']  ?? ''));
        $phone = trim((string) ($customer['phone'] ?? ''));

        if ($email === '' && $name === '') {
            throw new RuntimeException('Contact lookup requires at least a name or an email.');
        }

        // 1) Exact by email
        if ($email !== '') {
            $found = $this->request('GET', '/contacts', [
                'query' => ['email' => $email, 'page' => 1, 'per_page' => 2],
            ]);
            $contacts = $found['contacts'] ?? [];
            if (!empty($contacts[0]['contact_id'])) {
                $id = (string)$contacts[0]['contact_id'];
                Log::info('[Zoho] ensureCustomer: found by email', ['email' => $email, 'contact_id' => $id]);
                return $id;
            }
        }

        // 2) Safe exact by name (Zoho sometimes returns unrelated list)
        if ($name !== '') {
            $found = $this->request('GET', '/contacts', [
                'query' => ['search_text' => $name, 'page' => 1, 'per_page' => 50],
            ]);
            $contacts = $found['contacts'] ?? [];

            $normalized = static function ($s) {
                return mb_strtolower(trim((string)$s));
            };

            $needle = $normalized($name);
            $exact  = null;

            foreach ($contacts as $c) {
                if ($normalized($c['contact_name'] ?? '') === $needle) {
                    $exact = $c;
                    break;
                }
            }

            if ($exact && !empty($exact['contact_id'])) {
                $id = (string)$exact['contact_id'];
                Log::info('[Zoho] ensureCustomer: exact name match', ['name' => $name, 'contact_id' => $id]);
                return $id;
            }

            Log::info('[Zoho] ensureCustomer: no exact name match, will create', [
                'name' => $name,
                'candidates_count' => count($contacts),
            ]);
        }

        // 3) Create new contact
        $payload = [
            'contact_name' => $name !== '' ? $name : ($email ?: 'Unnamed Contact'),
            'contact_type' => 'customer',
        ];
        if ($email !== '') $payload['email'] = $email;
        if ($phone !== '') $payload['phone'] = $phone;

        $this->logOutbound('REQ', 'POST', '/contacts', [], $payload);
        $resp = $this->http()->post('/contacts', $payload);
        $this->logInbound('RESP', 'POST', '/contacts', $resp->status(), $resp->json(), $resp->body());

        if ($resp->successful()) {
            $contact = $resp->json()['contact'] ?? null;
            if (!empty($contact['contact_id'])) {
                $id = (string)$contact['contact_id'];
                Log::info('[Zoho] ensureCustomer: created OK', ['contact_id' => $id]);
                return $id;
            }
        } else {
            $msg = (string) (($resp->json()['message'] ?? '') ?: '');
            if (stripos($msg, 'The contact has been added') === false) {
                throw new RuntimeException('Zoho API error: ' . ($msg ?: $resp->body()));
            }
            Log::warning('[Zoho] ensureCustomer: non-2xx but “contact added” — will re-query', ['message' => $msg]);
        }

        // 4) Re-query (email preferred; otherwise exact name)
        if ($email !== '') {
            $found = $this->request('GET', '/contacts', [
                'query' => ['email' => $email, 'page' => 1, 'per_page' => 2],
            ]);
            $contacts = $found['contacts'] ?? [];
            if (!empty($contacts[0]['contact_id'])) {
                $id = (string)$contacts[0]['contact_id'];
                Log::info('[Zoho] ensureCustomer: re-query by email got id', ['contact_id' => $id]);
                return $id;
            }
        }

        if ($name !== '') {
            $found = $this->request('GET', '/contacts', [
                'query' => ['search_text' => $name, 'page' => 1, 'per_page' => 50],
            ]);
            $contacts = $found['contacts'] ?? [];

            $normalized = static function ($s) {
                return mb_strtolower(trim((string)$s));
            };
            $needle = $normalized($name);

            foreach ($contacts as $c) {
                if ($normalized($c['contact_name'] ?? '') === $needle && !empty($c['contact_id'])) {
                    $id = (string)$c['contact_id'];
                    Log::info('[Zoho] ensureCustomer: re-query exact name got id', ['contact_id' => $id]);
                    return $id;
                }
            }
        }

        throw new RuntimeException('Failed to obtain contact_id after contact creation.');
    }

    public function findOrCreateContact(array $customer): array
    {
        return ['contact_id' => $this->ensureCustomer($customer)];
    }

    // ------------------------------------------------------------------
    // Items
    // ------------------------------------------------------------------

    public function itemsSearch(string $q, int $page = 1, int $perPage = 50): array
    {
        $data = $this->request('GET', '/items', [
            'query' => ['search_text' => $q, 'page' => $page, 'per_page' => $perPage],
        ]);
        return $data['items'] ?? [];
    }

    // ------------------------------------------------------------------
    // Healthcheck
    // ------------------------------------------------------------------

    public function healthcheck(): array
    {
        $data = $this->request('GET', '/organizations');
        return ($data['organizations'] ?? [])[0] ?? [];
    }

    // ------------------------------------------------------------------
    // Sales Orders
    // ------------------------------------------------------------------

    public function createSalesOrderInZoho(array $body): array
    {
        $sentCustomer = (string) ($body['customer_id'] ?? '');
        $reference    = (string) ($body['reference_number'] ?? '');
        if ($sentCustomer === '' || $reference === '') {
            throw new RuntimeException('Internal error: customer_id/reference_number missing before SO creation.');
        }

        // sanity: verify contact exists in this org
        $this->logOutbound('REQ', 'GET', '/contacts/' . $sentCustomer);
        $this->request('GET', '/contacts/' . $sentCustomer);

        // post
        $this->logOutbound('REQ', 'POST', '/salesorders', [], $body);
        $post = $this->http()->post('/salesorders', $body);
        $this->logInbound('RESP', 'POST', '/salesorders', $post->status(), $post->json(), $post->body());

        $so = null;

        if ($post->successful()) {
            $so = $post->json()['salesorder'] ?? null;
            if (!$so) {
                throw new RuntimeException('Zoho API: empty salesorder in a successful response.');
            }
        } else {
            $msg = (string) (($post->json()['message'] ?? '') ?: '');
            if (stripos($msg, 'Sales Order has been created') !== false) {
                $found = $this->request('GET', '/salesorders', [
                    'query' => ['reference_number' => $reference, 'page' => 1, 'per_page' => 1],
                ]);
                $so = ($found['salesorders'] ?? [])[0] ?? null;
                if (!$so) {
                    throw new RuntimeException('Zoho HTTP error: SO was created but cannot be found by reference_number.');
                }
                Log::warning('[Zoho] createSO: non-2xx but created; found by reference', [
                    'reference'     => $reference,
                    'salesorder_id' => $so['salesorder_id'] ?? null,
                ]);
            } else {
                throw new RuntimeException('Zoho HTTP error: ' . ($msg ?: $post->body()));
            }
        }

        // validate binding
        $gotCustomer = isset($so['customer_id']) ? (string)$so['customer_id'] : '';
        Log::info('[Zoho] createSO: customer check', [
            'sent_customer_id' => $sentCustomer,
            'got_customer_id'  => $gotCustomer,
        ]);

        if ($gotCustomer !== $sentCustomer) {
            throw new RuntimeException(
                'Sales Order created with a different customer: sent ' . $sentCustomer .
                ', got ' . $gotCustomer .
                '. Likely a non-exact name match picked another contact.'
            );
        }

        Log::info('[Zoho] createSO: final object', [
            'salesorder_id'     => $so['salesorder_id']     ?? null,
            'salesorder_number' => $so['salesorder_number'] ?? null,
            'reference_number'  => $so['reference_number']  ?? null,
        ]);

        return $so;
    }

    public function createSalesOrder(array $payload): array
    {
        $customer  = $payload['customer'] ?? [];
        $contactId = $this->ensureCustomer($customer);

        $lines = [];
        foreach (($payload['items'] ?? []) as $i) {
            $itemId = $i['item_id'] ?? $i['zoho_item_id'] ?? null;
            if (!$itemId) {
                throw new RuntimeException('Each line must contain item_id.');
            }
            $lines[] = [
                'item_id'        => (string) $itemId,
                'quantity'       => isset($i['qty'])  ? (float) $i['qty']  : 1.0,
                'rate'           => isset($i['rate']) ? (float) $i['rate'] : 0.0,
                'tax_percentage' => isset($i['tax'])  ? (float) $i['tax']  : 0.0,
            ];
        }
        if (!$lines) {
            throw new RuntimeException('Sales Order must contain at least one line item.');
        }

        $reference = sprintf('SO-%s-%s', date('YmdHis'), substr(bin2hex(random_bytes(8)), 0, 8));

        $body = [
            'customer_id'      => (string) $contactId,
            'reference_number' => $reference,
            'date'             => now()->format('Y-m-d'),
            'line_items'       => $lines,
            'notes'            => 'Created via Inventory SPA',
        ];

        $so = $this->createSalesOrderInZoho($body);

        return [
            'ok'                => true,
            'salesorder_id'     => $so['salesorder_id']     ?? null,
            'salesorder_number' => $so['salesorder_number'] ?? null,
            'customer_id'       => $so['customer_id']       ?? null,
            'reference_number'  => $so['reference_number']  ?? $reference,
            'message'           => 'Sales Order successfully created in Zoho Inventory.',
        ];
    }

    // ------------------------------------------------------------------
    // Sales Orders: listing & single fetch
    // ------------------------------------------------------------------

    public function listSalesOrders(array $params = []): array
    {
        $data = $this->request('GET', '/salesorders', ['query' => $params]);
        return $data ?? [];
    }

    public function getSalesOrder(string $salesorderId): array
    {
        $data = $this->request('GET', '/salesorders/' . $salesorderId);
        return $data['salesorder'] ?? [];
    }

    public function getItem(string $itemId): array
    {
        $data = $this->request('GET', '/items/' . $itemId);
        return $data['item'] ?? [];
    }

    // ------------------------------------------------------------------
    // Purchase Orders (NEW)
    // ------------------------------------------------------------------

    /**
     * Create Purchase Orders from a purchase plan.
     * The plan is an array of rows: [{ item_id: string, quantity: number }]
     * Rows are grouped by preferred_vendor_id/vendor_id; one PO is created per vendor.
     *
     * @param array $plan
     * @param array $options  Optional: ['salesorder_id' => '...'] to mention SO in PO reference/notes.
     * @return array
     */
    public function createPurchaseOrdersFromPlan(array $plan, array $options = []): array
    {
        $result = [
            'created' => [],
            'skipped' => [],
        ];

        if (empty($plan) || !is_array($plan)) {
            return $result;
        }

        // Local item cache to reduce API calls
        $itemCache = [];

        // Group rows by vendor and aggregate duplicate items
        $byVendor = []; // vendor_id => ['items' => [item_id => ['quantity'=>float, 'rate'=>float|null]], 'vendor_id'=>string]

        foreach ($plan as $row) {
            $itemId = (string) ($row['item_id'] ?? '');
            $qty    = (float)  ($row['quantity'] ?? 0);

            if ($itemId === '' || $qty <= 0) {
                $result['skipped'][] = [
                    'item_id'  => $itemId ?: null,
                    'quantity' => $qty,
                    'reason'   => 'bad_row',
                ];
                continue;
            }

            try {
                if (!isset($itemCache[$itemId])) {
                    $itemCache[$itemId] = $this->getItem($itemId);
                }
                $item = $itemCache[$itemId];
            } catch (\Throwable $e) {
                Log::warning('[Zoho] getItem failed while building PO plan', [
                    'item_id' => $itemId,
                    'message' => $e->getMessage(),
                ]);
                $result['skipped'][] = [
                    'item_id'  => $itemId,
                    'quantity' => $qty,
                    'reason'   => 'get_item_failed',
                ];
                continue;
            }

            $vendorId = $item['preferred_vendor_id'] ?? $item['vendor_id'] ?? null;
            if (!$vendorId) {
                $result['skipped'][] = [
                    'item_id'  => $itemId,
                    'quantity' => $qty,
                    'reason'   => 'no_preferred_vendor',
                ];
                continue;
            }

            $rate = null;
            if (isset($item['purchase_rate']) && is_numeric($item['purchase_rate'])) {
                $rate = (float) $item['purchase_rate'];
            } elseif (isset($item['rate']) && is_numeric($item['rate'])) {
                $rate = (float) $item['rate'];
            }

            if (!isset($byVendor[$vendorId])) {
                $byVendor[$vendorId] = ['vendor_id' => $vendorId, 'items' => []];
            }
            if (!isset($byVendor[$vendorId]['items'][$itemId])) {
                $byVendor[$vendorId]['items'][$itemId] = ['quantity' => 0.0, 'rate' => $rate];
            }
            $byVendor[$vendorId]['items'][$itemId]['quantity'] += $qty;
            if ($byVendor[$vendorId]['items'][$itemId]['rate'] === null && $rate !== null) {
                $byVendor[$vendorId]['items'][$itemId]['rate'] = $rate;
            }
        }

        if (!$byVendor) {
            Log::info('[Zoho] purchasePlan contained no vendor-bound lines; nothing to create');
            return $result;
        }

        $soId = isset($options['salesorder_id']) ? (string)$options['salesorder_id'] : '';

        foreach ($byVendor as $vendorId => $bucket) {
            $rows = [];
            foreach ($bucket['items'] as $iid => $meta) {
                $row = [
                    'item_id'  => $iid,
                    'quantity' => (float) $meta['quantity'],
                ];
                if ($meta['rate'] !== null) {
                    $row['rate'] = (float) $meta['rate'];
                }
                $rows[] = $row;
            }

            $payload = $this->buildPurchaseOrderPayload($vendorId, $rows, $soId);

            try {
                // request() now treats 201 as success because of ->successful()
                $resp = $this->request('POST', '/purchaseorders', ['json' => $payload]);

                $po   = $resp['purchaseorder'] ?? $resp ?? [];
                $poId = $po['purchaseorder_id']     ?? null;
                $poNo = $po['purchaseorder_number'] ?? null;

                Log::info('[Zoho] PO created: summary', [
                    'vendor_id' => $vendorId,
                    'purchaseorder_id' => $poId,
                    'purchaseorder_number' => $poNo,
                ]);

                $result['created'][] = [
                    'purchaseorder_id'     => $poId,
                    'purchaseorder_number' => $poNo,
                    'vendor_id'            => $vendorId,
                    'lines'                => array_values($rows),
                ];
            } catch (\Throwable $e) {
                Log::error('[Zoho] create PO failed', [
                    'vendor_id' => $vendorId,
                    'payload'   => $payload,
                    'message'   => $e->getMessage(),
                ]);

                foreach ($rows as $li) {
                    $result['skipped'][] = [
                        'item_id'  => $li['item_id'],
                        'quantity' => $li['quantity'],
                        'reason'   => 'po_create_failed',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Build minimal Zoho Purchase Order payload for a single vendor.
     * Extend here if you need: delivery date, warehouse, custom fields, etc.
     *
     * @param string $vendorId
     * @param array  $rows       Each row: ['item_id'=>..., 'quantity'=>..., 'rate'?=>...]
     * @param string $soId       Optional Sales Order id to reference in PO.
     * @return array
     */
    private function buildPurchaseOrderPayload(string $vendorId, array $rows, string $soId = ''): array
    {
        $lineItems = [];
        foreach ($rows as $r) {
            $one = [
                'item_id'  => $r['item_id'],
                'quantity' => (float) $r['quantity'],
            ];
            if (isset($r['rate'])) {
                $one['rate'] = (float) $r['rate']; // pass purchase rate when we know it
            }
            $lineItems[] = $one;
        }

        $payload = [
            'vendor_id'  => $vendorId,
            'line_items' => $lineItems,
        ];

        // Light backlink to SO is useful for operators
        if ($soId !== '') {
            $payload['reference_number'] = 'SO:' . $soId . ' / ' . now()->format('YmdHis');
            $payload['notes']            = 'Auto-created from Sales Order ' . $soId;
        }

        return $payload;
    }
}
