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
 * - IMPORTANT: We derive the Purchase Order plan locally (via usePurchasePlan)
 *   and inject it into the payload so createPurchaseOrders + purchasePlan
 *   are always correct even if the store does not persist them.
 */

import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useOrderStore } from '@inventory/stores/order';

// Composable for health check (keeps API calls consistent)
import { useHealth } from '@inventory/composables/useHealth';

// API call for creating Sales Orders
import { createSalesOrder } from '@inventory/api/Api';

// PO plan (derived from current items/flags)
import { usePurchasePlan } from '@inventory/composables/usePurchasePlan';

const router = useRouter();
const order = useOrderStore();
const { totals, itemCount } = storeToRefs(order);

// Derive the purchase plan reactively
const { plan } = usePurchasePlan();

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
 * Normalize API response so we can read fields regardless of whether
 * Api.js returns the whole AxiosResponse or already its .data.
 */
function pickBody(res) {
  // If it's an AxiosResponse -> res.data; otherwise assume res is already the body
  const body = res && typeof res === 'object' && 'data' in res ? res.data : res;
  return body || {};
}

/**
 * Extract SO identifiers defensively from multiple possible shapes:
 *  - body.data.salesorder_id / body.data.salesorder_number
 *  - body.data.raw.salesorder_id / body.data.raw.salesorder_number
 *  - body.salesorder_id / body.salesorder_number
 */
function extractSoIds(body) {
  const data = body?.data ?? {};
  const raw  = data?.raw ?? body?.raw ?? {};

  const soId =
    data?.salesorder_id ??
    raw?.salesorder_id ??
    body?.salesorder_id ??
    null;

  const soNo =
    data?.salesorder_number ??
    raw?.salesorder_number ??
    body?.salesorder_number ??
    null;

  return { soId, soNo };
}

/**
 * Save & Send the current draft to backend:
 *  - POST /api/zoho/salesorders
 *  - expects { status, message, data: { salesorder_id, salesorder_number } }
 *  - on success: show number and redirect to /salesorders/:id
 */
async function saveAndSend() {
  msg.value = '';

  // Frontend safety checks (keep old behavior)
  if (itemCount.value === 0) {
    msg.value = 'Save error: Add at least one item.';
    return;
  }
  const name  = (order.customer?.name  || '').trim();
  const email = (order.customer?.email || '').trim();
  if (!name || !email) {
    msg.value = 'Save error: The customer.name and customer.email fields are required.';
    return;
  }

  // Build PO plan for payload based on current items and create_po flags.
  // We intentionally do this here to avoid relying on store persistence.
  const planArray = Array.isArray(plan.value)
    ? plan.value
        .filter(p => p && p.item_id && Number(p.shortage_qty) > 0)
        .map(p => ({
          item_id: String(p.item_id),
          quantity: Number(p.shortage_qty),
        }))
    : [];

  // True if there is anything to purchase
  const createPO = planArray.length > 0;

  busy.value = true;

  try {
    // Minimal payload that matches controller validation
    const payload = {
      customer: { ...order.customer },
      items:    [...order.items],
      // Use derived plan/flag instead of relying on unset store fields
      createPurchaseOrders: createPO,
      purchasePlan: planArray,
    };

    const res  = await createSalesOrder(payload);
    const body = pickBody(res);

    const { soId, soNo } = extractSoIds(body);

    const notice = body?.message || 'Sales Order created';
    msg.value = soNo ? `${notice}. Redirecting…` : `${notice}. Redirecting…`;

    if (soId) {
      setTimeout(() => {
        router.push({ name: 'so.view', params: { id: soId } });
      }, 600);
    } else {
      // Fallback: if ID not returned, keep message and do not redirect
      msg.value = body?.message || 'Sales Order created, but no ID returned.';
    }
  } catch (e) {
    const apiMsg =
      e?.response?.data?.message ||
      e?.message ||
      'Request failed';
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
