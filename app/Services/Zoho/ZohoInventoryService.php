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

    protected function http(): PendingRequest
    {
        $token = $this->auth->getAccessToken();

        return Http::withHeaders([
                'Authorization'             => 'Zoho-oauthtoken ' . $token,
                'X-com-zoho-organizationid' => $this->organizationId,
                'Accept'                    => 'application/json',
                'Content-Type'              => 'application/json',
            ])
            ->baseUrl(rtrim($this->inventoryBaseUrl, '/'))
            ->timeout($this->timeoutMs / 1000)
            ->withQueryParameters(['organization_id' => $this->organizationId]);
    }

    protected function request(string $method, string $path, array $options = []): array
    {
        $url   = '/' . ltrim($path, '/');
        $query = (array)($options['query'] ?? []);
        $json  = (array)($options['json']  ?? []);

        $client = $this->http();

        // $this->logOutbound('REQ', $method, $url, $query, $json);

        $response = match (strtoupper($method)) {
            'GET'    => $client->get($url, $query),
            'POST'   => $client->withQueryParameters($query)->post($url, $json),
            'PUT'    => $client->withQueryParameters($query)->put($url, $json),
            'DELETE' => $client->withQueryParameters($query)->delete($url, $json),
            default  => throw new RuntimeException('Unsupported HTTP method: ' . $method),
        };

        // $this->logInbound('RESP', $method, $url, $response->status(), $response->json(), $response->body());

        if (!$response->ok()) {
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
                // Log::info('[Zoho] ensureCustomer: found by email', ['email' => $email, 'contact_id' => $id]);
                return $id;
            }
        }

        // 2) Safe exact by name (Zoho иногда возвращает несвязанный список)
        if ($name !== '') {
            // используем search_text, затем фильтруем точным сравнением contact_name
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
                // Log::info('[Zoho] ensureCustomer: exact name match', ['name' => $name, 'contact_id' => $id]);
                return $id;
            }

            // Log::info('[Zoho] ensureCustomer: no exact name match, will create', [
            //     'name' => $name,
            //     'candidates_count' => count($contacts),
            // ]);
        }

        // 3) Create new contact
        $payload = [
            'contact_name' => $name !== '' ? $name : ($email ?: 'Unnamed Contact'),
            'contact_type' => 'customer',
        ];
        if ($email !== '') $payload['email'] = $email;
        if ($phone !== '') $payload['phone'] = $phone;

        // $this->logOutbound('REQ', 'POST', '/contacts', [], $payload);
        $resp = $this->http()->post('/contacts', $payload);
        // $this->logInbound('RESP', 'POST', '/contacts', $resp->status(), $resp->json(), $resp->body());

        if ($resp->ok()) {
            $contact = $resp->json()['contact'] ?? null;
            if (!empty($contact['contact_id'])) {
                $id = (string)$contact['contact_id'];
                // Log::info('[Zoho] ensureCustomer: created OK', ['contact_id' => $id]);
                return $id;
            }
        } else {
            $msg = (string) (($resp->json()['message'] ?? '') ?: '');
            if (stripos($msg, 'The contact has been added') === false) {
                throw new RuntimeException('Zoho API error: ' . ($msg ?: $resp->body()));
            }
            // Log::warning('[Zoho] ensureCustomer: non-2xx but “contact added” — will re-query', ['message' => $msg]);
        }

        // 4) Re-query (email приоритетно; иначе — точное имя)
        if ($email !== '') {
            $found = $this->request('GET', '/contacts', [
                'query' => ['email' => $email, 'page' => 1, 'per_page' => 2],
            ]);
            $contacts = $found['contacts'] ?? [];
            if (!empty($contacts[0]['contact_id'])) {
                $id = (string)$contacts[0]['contact_id'];
                // Log::info('[Zoho] ensureCustomer: re-query by email got id', ['contact_id' => $id]);
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
                    // Log::info('[Zoho] ensureCustomer: re-query exact name got id', ['contact_id' => $id]);
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
        // $this->logOutbound('REQ', 'GET', '/contacts/' . $sentCustomer);
        $this->request('GET', '/contacts/' . $sentCustomer);

        // post
        // $this->logOutbound('REQ', 'POST', '/salesorders', [], $body);
        $post = $this->http()->post('/salesorders', $body);
        // $this->logInbound('RESP', 'POST', '/salesorders', $post->status(), $post->json(), $post->body());

        $so = null;

        if ($post->ok()) {
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
                // Log::warning('[Zoho] createSO: non-2xx but created; found by reference', [
                //     'reference'     => $reference,
                //     'salesorder_id' => $so['salesorder_id'] ?? null,
                // ]);
            } else {
                throw new RuntimeException('Zoho HTTP error: ' . ($msg ?: $post->body()));
            }
        }

        // validate binding
        $gotCustomer = isset($so['customer_id']) ? (string)$so['customer_id'] : '';
        // Log::info('[Zoho] createSO: customer check', [
        //     'sent_customer_id' => $sentCustomer,
        //     'got_customer_id'  => $gotCustomer,
        // ]);

        if ($gotCustomer !== $sentCustomer) {
            throw new RuntimeException(
                'Sales Order created with a different customer: sent ' . $sentCustomer .
                ', got ' . $gotCustomer .
                '. Likely a non-exact name match picked another contact.'
            );
        }

        // Log::info('[Zoho] createSO: final object', [
        //     'salesorder_id'     => $so['salesorder_id']     ?? null,
        //     'salesorder_number' => $so['salesorder_number'] ?? null,
        //     'reference_number'  => $so['reference_number']  ?? null,
        // ]);

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

    /**
     * List Sales Orders from Zoho Inventory (supports paging/search/sort).
     *
     * $opts = [
     *   'page'        => 1,
     *   'per_page'    => 25,
     *   'search'      => null,     // full-text search (reference, number, customer, etc.)
     *   'status'      => 'Status.All',
     *   'sort_column' => 'date',   // created_time | date | salesorder_number
     *   'sort_order'  => 'D',      // A | D
     * ]
     *
     * @return array { salesorders: [], page_context: {} }
     */
    public function listSalesOrders(array $params = []): array
    {
        // $params may include: page, per_page, search_text, salesorder_number, reference_number, sort_column, sort_order
        $data = $this->request('GET', '/salesorders', ['query' => $params]);
        return $data ?? [];
    }

    /** Fetch a single Sales Order by id (full object). */
    public function getSalesOrder(string $salesorderId): array
    {
        $data = $this->request('GET', '/salesorders/' . $salesorderId);
        return $data['salesorder'] ?? [];
    }

}
