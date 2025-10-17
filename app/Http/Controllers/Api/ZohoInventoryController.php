<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Zoho\ZohoInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Throwable;

/**
 * ZohoInventoryController
 *
 * Provides REST API endpoints for Zoho Inventory integration:
 *  - GET /api/zoho/health
 *  - GET /api/zoho/items?q=...
 *  - POST /api/zoho/salesorders
 */
class ZohoInventoryController extends Controller
{
    /**
     * GET /api/zoho/health
     * Checks connectivity and retrieves basic organization info.
     */
    public function health(ZohoInventoryService $inventory): JsonResponse
    {
        $org = $inventory->healthcheck();

        return response()->json([
            'status' => 'ok',
            'organization' => [
                'id'   => $org['organization_id'] ?? null,
                'name' => $org['name'] ?? null,
            ],
        ]);
    }

    /**
     * GET /api/zoho/items?q={query}
     * Performs a search for items in Zoho Inventory by keyword.
     */
    public function items(Request $request, ZohoInventoryService $inventory): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['error' => 'Missing query parameter q'], 400);
        }

        try {
            $items = $inventory->itemsSearch($q);

            // Optional debug log (safe content only)
            Log::debug('Zoho Inventory item search', [
                'query' => $q,
                'count' => is_array($items) ? count($items) : 0,
            ]);

            return response()->json([
                'status' => 'ok',
                'query'  => $q,
                'data'   => $items,
            ]);
        } catch (Throwable $e) {
            Log::error('Zoho Inventory item search failed', [
                'query'   => $q,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create Sales Order in Zoho Inventory.
     * Accepts extended payload:
     *   - createPurchaseOrders: bool
     *   - purchasePlan: [{ item_id: string, quantity: number }]
     *
     * On success may also create Purchase Orders based on purchasePlan.
     */
    public function createSalesOrder(Request $request, ZohoInventoryService $zoho): JsonResponse
    {
        // --- Validate incoming payload (keep existing shape) -----------------
        $validated = $request->validate([
            'customer.name'  => ['required','string','max:255'],
            'customer.email' => ['required','email','max:255'],
            'customer.phone' => ['nullable','string','max:255'],

            'items'                  => ['required','array','min:1'],
            'items.*.item_id'        => ['required','string'],
            'items.*.name'           => ['required','string'],
            'items.*.sku'            => ['nullable','string'],
            'items.*.qty'            => ['required','numeric','min:0.0001'],
            'items.*.rate'           => ['required','numeric'],
            'items.*.tax'            => ['nullable','numeric'],
            
            'createPurchaseOrders'   => ['sometimes','boolean'],
            'purchasePlan'           => ['sometimes','array'],
            'purchasePlan.*.item_id' => ['required_with:createPurchaseOrders','string'],
            'purchasePlan.*.quantity'=> ['required_with:createPurchaseOrders','numeric','min:0.0001'],
        ]);

        $createPO     = (bool) Arr::get($validated, 'createPurchaseOrders', false);
        $purchasePlan = Arr::get($validated, 'purchasePlan', []);

        try {
            // --- Create SO in Zoho -----------------------------------------
            $soResponse = $zoho->createSalesOrder($validated);

            Log::info('Zoho SO create: response', [
                'endpoint' => 'salesorders',
                'response' => $soResponse,
            ]);

            // Normalize for frontend (keep previous contract)
            $soId   = data_get($soResponse, 'salesorder.salesorder_id');
            $soNo   = data_get($soResponse, 'salesorder.salesorder_number');
            $statusMessage = 'Sales Order created';

            $result = [
                'status'  => 'ok',
                'message' => $soNo ? "{$statusMessage} (#{$soNo})" : $statusMessage,
                'data'    => [
                    'salesorder_id'      => $soId,
                    'salesorder_number'  => $soNo,
                    'raw'                => $soResponse, // leave raw for inspection if needed
                ],
            ];

            // --- Optionally create POs based on purchase plan --------------
            if ($createPO && is_array($purchasePlan) && count($purchasePlan) > 0) {
                $poReport = $zoho->createPurchaseOrdersFromPlan($purchasePlan);

                Log::info('Zoho PO create: summary', [
                    'summary' => $poReport,
                ]);

                $result['purchase_orders'] = $poReport;
                if (!empty($poReport['created'])) {
                    $result['message'] .= ' • Purchase Orders created';
                }
                if (!empty($poReport['skipped'])) {
                    $result['message'] .= ' • Some items skipped (no vendor)';
                }
            }

            return response()->json($result, 201);
        } catch (\Throwable $e) {
            Log::error('Zoho SO create: exception', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage() ?: 'Failed to create Sales Order',
            ], 422);
        }
    }

    /**
     * GET /api/zoho/salesorders
     * Paginated list of Sales Orders from Zoho Inventory.
     */
    public function listSalesOrders(Request $request, ZohoInventoryService $inventory): JsonResponse
    {
        try {
            $page       = (int) $request->query('page', 1);
            $perPage    = (int) $request->query('per_page', 25);
            $sortCol    = (string) $request->query('sort_column', 'date');
            $sortOrder  = (string) $request->query('sort_order', 'D');
            $q          = trim((string) $request->query('q', ''));

            $query = [
                'page'        => $page,
                'per_page'    => $perPage,
                'sort_column' => $sortCol,
                'sort_order'  => $sortOrder,
            ];

            // Main fuzzy search (substring across multiple fields)
            if ($q !== '') {
                $query['search_text'] = $q;

                // Optional: try an exact match by sales order number if user typed digits like "00024"
                if (preg_match('/^\d+$/', $q)) {
                    $query['salesorder_number'] = 'SO-' . str_pad($q, 5, '0', STR_PAD_LEFT);
                }
            }

            $res = $inventory->listSalesOrders($query); // -> hits Zoho /salesorders

            return response()->json([
                'status'       => 'ok',
                'data'         => $res['salesorders'] ?? [],
                'filters'      => [
                    'page'        => (string) $page,
                    'per_page'    => (string) $perPage,
                    'sort_column' => $sortCol,
                    'sort_order'  => $sortOrder,
                    'q'           => $q,
                ],
                'page_context' => $res['page_context'] ?? [
                    'page' => $page, 'per_page' => $perPage, 'has_more_page' => false, 'report_name' => 'Sales Orders',
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('[Zoho] listSalesOrders failed', ['message' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/zoho/salesorders/{id}
     * Full details for a single Sales Order.
     */
    public function getSalesOrder(string $id, ZohoInventoryService $inventory): JsonResponse
    {
        try {
            $so = $inventory->getSalesOrder($id);

            if (empty($so)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sales Order not found.',
                ], 404);
            }

            return response()->json([
                'status' => 'ok',
                'data'   => $so,
            ]);
        } catch (Throwable $e) {
            Log::error('[Zoho] getSalesOrder failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function getItem(string $id, \App\Services\Zoho\ZohoInventoryService $inventory): \Illuminate\Http\JsonResponse
    {
        try {
            $raw = $inventory->getItem($id);

            if (empty($raw)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Item not found.',
                ], 404);
            }

            // Normalize/minify the payload for the frontend
            $item = [
                'item_id'               => $raw['item_id'] ?? $id,
                'name'                  => $raw['name'] ?? ($raw['item_name'] ?? ''),
                'sku'                   => $raw['sku'] ?? ($raw['product_code'] ?? ''),
                'rate'                  => $raw['rate'] ?? ($raw['selling_price'] ?? 0),
                'track_inventory'       => (bool)($raw['track_inventory'] ?? false),
                'can_be_sold'           => (bool)($raw['can_be_sold'] ?? true),
                'can_be_purchased'      => (bool)($raw['can_be_purchased'] ?? false),

                // Stock-related fields appear only for inventory-tracked items.
                // We pass them if present; otherwise they will be null.
                'available_stock'       => $raw['available_stock']        ?? ($raw['actual_available_stock'] ?? null),
                'actual_available_stock'=> $raw['actual_available_stock'] ?? null,
                'physical_stock'        => $raw['physical_stock']         ?? null,
            ];

            return response()->json([
                'status' => 'ok',
                'data'   => $item,
            ]);
        } catch (Throwable $e) {
            Log::error('[Zoho] getItem failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

}
