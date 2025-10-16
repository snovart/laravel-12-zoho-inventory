// resources/js/zoho/inventory/composables/useItemDetails.js
// ============================================================
// useItemDetails()
// ------------------------------------------------------------
// Purpose:
//  - Fetch a single Zoho Inventory item by ID via Laravel API
//  - Normalize a few commonly-used fields
//  - Cache results in-memory by item_id to avoid repeat calls
//
// API contract (from Api.js -> GET /api/zoho/items/:id):
//  - Expected response: { status: 'ok', data: { ...item } }
//
// Exposed:
//  - loading: ref<boolean>
//  - error:   ref<string | null>
//  - last:    ref<object | null>  (last fetched item)
//  - getById(id: string): Promise<object|null>
//    * returns a plain object with normalized fields where available
//    * also updates `last`
// ============================================================

import { ref } from 'vue';
import { getItemDetails } from '@inventory/api/Api';

// Simple in-memory cache (per tab). Key: item_id, Value: normalized item.
const cache = new Map();

/** Extract a normalized shape we will commonly use in the UI. */
function normalizeItem(raw = {}) {
  const available =
    raw.available_stock ??
    raw.actual_available_stock ??
    raw.stock_on_hand ??
    raw.quantity_on_hand ??
    null;

  return {
    // core identity
    item_id: raw.item_id ?? raw.id ?? '',
    name: raw.name ?? raw.item_name ?? '',
    sku: raw.sku ?? raw.product_code ?? '',
    rate: Number(raw.rate ?? raw.selling_price ?? raw.unit_price ?? 0),

    // inventory flags
    track_inventory: Boolean(raw.track_inventory),
    can_be_purchased: Boolean(raw.can_be_purchased),

    // availability (best-effort)
    available_stock: available,

    // keep the whole raw payload as well for edge cases
    _raw: raw,
  };
}

export function useItemDetails() {
  const loading = ref(false);
  const error = ref(null);
  const last = ref(null);

  /**
   * Fetch details for a single item by ID.
   * Uses cache if present. On success returns normalized item.
   */
  async function getById(id) {
    const itemId = String(id || '').trim();
    if (!itemId) {
      error.value = 'Missing item_id';
      return null;
    }

    // Serve from cache if we have it
    if (cache.has(itemId)) {
      const cached = cache.get(itemId);
      last.value = cached;
      error.value = null;
      return cached;
    }

    loading.value = true;
    error.value = null;

    try {
      const res = await getItemDetails(itemId); // { status, data }
      const raw = res?.data ?? null;
      if (!raw) {
        throw new Error('Item not found');
      }

      const normalized = normalizeItem(raw);
      cache.set(itemId, normalized);
      last.value = normalized;
      return normalized;
    } catch (e) {
      error.value =
        e?.response?.data?.message || e?.message || 'Failed to load item details';
      last.value = null;
      return null;
    } finally {
      loading.value = false;
    }
  }

  return { loading, error, last, getById };
}
