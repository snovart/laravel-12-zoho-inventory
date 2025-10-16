<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Zoho\ZohoInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            // Log::debug('Zoho Inventory item search', [
            //     'query' => $q,
            //     'count' => is_array($items) ? count($items) : 0,
            // ]);

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
     * POST /api/zoho/salesorders
     * Creates a Sales Order in Zoho Inventory.
     */
    public function createSalesOrder(Request $request, ZohoInventoryService $zi)
    {
        // Validate SPA payload
        $payload = $request->validate([
            'customer' => ['required', 'array'],
            'customer.name'  => ['required', 'string', 'max:255'],
            'customer.email' => ['nullable', 'email'],
            'customer.phone' => ['nullable', 'string', 'max:50'],

            'items'   => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'string'],   // IMPORTANT: item_id is required
            'items.*.name'    => ['required', 'string', 'max:255'],
            'items.*.sku'     => ['nullable', 'string', 'max:255'],
            'items.*.qty'     => ['required', 'numeric', 'min:0.001'],
            'items.*.rate'    => ['required', 'numeric', 'min:0'],
            'items.*.tax'     => ['nullable', 'numeric', 'min:0'],
            'createPurchaseOrders' => ['sometimes', 'boolean'],
        ]);

        try {
            // Single entry point that handles reference_number + Zoho quirks
            $res = $zi->createSalesOrder($payload);

            return response()->json([
                'ok' => true,
                'message' => $res['message'] ?? 'Sales Order created',
                'data' => [
                    'salesorder_id'     => $res['salesorder_id'] ?? null,
                    'salesorder_number' => $res['salesorder_number'] ?? null,
                    'reference_number'  => $res['reference_number'] ?? null,
                    'customer_id'       => $res['customer_id'] ?? null,
                ],
            ], 201);

        } catch (Throwable $e) {
            Log::error('Zoho Inventory sales order creation failed', [
                'customer_name' => $payload['customer']['name'] ?? null,
                'items_count'   => count($payload['items'] ?? []),
                'message'       => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Zoho API error: ' . $e->getMessage(),
            ], 422);
        }
    }

}
