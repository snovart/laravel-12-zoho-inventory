<!-- resources/js/zoho/inventory/components/SummaryBar.vue -->
<script setup>
/**
 * SummaryBar.vue
 * ------------------------------------------------------------
 * - Shows live totals from the order store (subtotal, tax, total)
 * - Provides actions: Recompute, Check API (health), Save & Send
 * - On successful Sales Order creation:
 *     • shows order number
 *     • redirects to /salesorders/:id
 */

import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useOrderStore } from '@inventory/stores/order';

// Composable for health check (keeps API calls consistent)
import { useHealth } from '@inventory/composables/useHealth';

// API call for creating Sales Orders (will be moved to a composable later if needed)
import { createSalesOrder } from '@inventory/api/Api';

const router = useRouter();
const order = useOrderStore();
const { totals, itemCount } = storeToRefs(order);

// Local UI state
const busy = ref(false);
const msg  = ref('');

// Format helper
const fmt = (n) => Number(n || 0).toFixed(2);

// Recompute totals manually (store already recomputes on edits)
const recompute = () => order.recomputeTotals();

// Health check via composable
const { data: healthData, loading: healthLoading, error: healthError, checkHealth } = useHealth();

async function doHealth() {
  msg.value = '';
  await checkHealth();
  if (healthError.value) {
    msg.value = `Health error: ${healthError.value}`;
  } else {
    const orgName = healthData.value?.organization?.name ?? '—';
    msg.value = `Health OK: ${orgName}`;
  }
}

/**
 * Save & Send the current draft to backend:
 *  - POST /api/zoho/salesorders
 *  - expects { ok, data: { salesorder_id, salesorder_number }, message }
 *  - on success: show number and redirect to /salesorders/:id
 */
async function saveAndSend() {
  msg.value = '';
  busy.value = true;

  try {
    // Minimal payload that matches controller validation
    const payload = {
      customer: { ...order.customer },
      items:    [...order.items],
      createPurchaseOrders: order.createPurchaseOrders,
    };

    const res = await createSalesOrder(payload);
    const soId   = res?.data?.salesorder_id ?? null;
    const soNo   = res?.data?.salesorder_number ?? null;
    const notice = res?.message ?? 'Sales Order created.';

    // Show a short success message (kept simple)
    msg.value = soNo ? `${notice} (#${soNo}). Redirecting…` : `${notice}. Redirecting…`;

    // Redirect to view page when we have an ID
    if (soId) {
      // small delay so the user sees the message
      setTimeout(() => {
        router.push({ name: 'so.view', params: { id: soId } });
      }, 600);
    }
  } catch (e) {
    const apiMsg = e?.response?.data?.message || e?.message || 'Request failed';
    msg.value = `Save error: ${apiMsg}`;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
    <div class="text-sm text-gray-600">
      <span class="font-medium text-gray-700">Lines:</span>
      {{ itemCount }}
    </div>

    <div class="flex gap-6 text-sm">
      <div>
        <span class="font-medium text-gray-700">Subtotal:</span>
        ${{ fmt(totals.subtotal) }}
      </div>
      <div>
        <span class="font-medium text-gray-700">Tax:</span>
        ${{ fmt(totals.tax_total) }}
      </div>
      <div>
        <span class="font-medium text-gray-700">Total:</span>
        <span class="text-gray-900 font-semibold">${{ fmt(totals.grand_total) }}</span>
      </div>
    </div>

    <div class="flex gap-3">
      <button
        type="button"
        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100"
        @click="recompute"
        :disabled="busy"
      >
        Recompute
      </button>

      <button
        type="button"
        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-60"
        @click="doHealth"
        :disabled="busy || healthLoading"
        title="GET /api/zoho/health"
      >
        {{ healthLoading ? 'Checking…' : 'Check API' }}
      </button>

      <button
        type="button"
        class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
        @click="saveAndSend"
        :disabled="busy || itemCount === 0"
        title="POST /api/zoho/salesorders"
      >
        Save &amp; Send
      </button>
    </div>
  </div>

  <p
    v-if="msg"
    class="mt-2 text-sm"
    :class="msg.startsWith('Save error') || msg.startsWith('Health error') ? 'text-red-600' : 'text-emerald-600'"
  >
    {{ msg }}
  </p>
</template>
