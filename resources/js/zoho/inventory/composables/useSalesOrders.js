// resources/js/zoho/inventory/composables/useSalesOrders.js
// Composable to create Sales Orders via API (Save & Send).
// Keeps small UI state (busy/message/error) outside components.

import { ref } from 'vue';
import { createSalesOrder } from '@/zoho/inventory/api/Api';

export function useSalesOrders() {
  const busy = ref(false);
  const message = ref('');
  const error = ref(null);

  async function saveAndSend(payload) {
    busy.value = true;
    message.value = '';
    error.value = null;

    try {
      const res = await createSalesOrder(payload);
      message.value = res?.message ?? 'Sales Order sent.';
      return res;
    } catch (e) {
      const apiMsg = e?.response?.data?.message || e?.message || 'Request failed';
      error.value = apiMsg;
      message.value = `Save error: ${apiMsg}`;
      throw e;
    } finally {
      busy.value = false;
    }
  }

  return { busy, message, error, saveAndSend };
}
