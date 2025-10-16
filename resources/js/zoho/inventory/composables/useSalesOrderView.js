// resources/js/zoho/inventory/composables/useSalesOrderView.js
// ============================================================
// useSalesOrderView()
// ------------------------------------------------------------
// Purpose:
//  - Fetch a single Sales Order by its ID from the backend
//  - Expose reactive state: order / loading / error
//  - Provide helper methods: fetchOne(id) and refresh()
// ============================================================

import { ref } from 'vue';
import { getSalesOrder } from '@inventory/api/Api';

export function useSalesOrderView(initialId = null) {
  const order = ref(null);
  // Start as loading=true so the page doesn't render the card with null
  const loading = ref(true);
  const error = ref(null);
  const storedId = ref(initialId);

  /** Fetch a single order by ID (or reuse the last stored ID) */
  async function fetchOne(id) {
    const effectiveId = id ?? storedId.value;
    if (!effectiveId) {
      error.value = 'Missing Sales Order ID';
      order.value = null;
      loading.value = false;
      return;
    }

    loading.value = true;
    error.value = null;

    try {
      const res = await getSalesOrder(effectiveId);
      // Expected backend response: { status: 'ok', data: {...} }
      order.value = res?.data ?? null;
      storedId.value = effectiveId;
    } catch (e) {
      error.value =
        e?.response?.data?.message ||
        e?.message ||
        'Failed to load Sales Order';
      order.value = null;
    } finally {
      loading.value = false;
    }
  }

  /** Refresh using the last loaded ID */
  async function refresh() {
    await fetchOne();
  }

  return { order, loading, error, fetchOne, refresh };
}
