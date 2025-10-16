<!-- resources/js/zoho/inventory/components/SummaryBar.vue -->
<script setup>
/**
 * SummaryBar.vue
 * ------------------------------------------------------------
 * - Shows live totals from the order store (subtotal, tax, total)
 * - Provides actions: Recompute, Check API, Save & Send
 * - Validation:
 *     • disabled when there are no items
 *     • on click validates customer.name and customer.email
 * - Payload on save:
 *     • customer, items (from store)
 *     • createPurchaseOrders: boolean
 *     • purchasePlan: [{ item_id, quantity }]
 * - Success UX: inline message + redirect to { name: 'so.view', params: { id } }
 */

import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useOrderStore } from '@inventory/stores/order';

// Health check via composable (как было)
import { useHealth } from '@inventory/composables/useHealth';

// API
import { createSalesOrder } from '@inventory/api/Api';

// План закупок (из флагов create_po в строках)
import { usePurchasePlan } from '@inventory/composables/usePurchasePlan';

const router = useRouter();
const order = useOrderStore();
const { totals, itemCount } = storeToRefs(order);

const { plan } = usePurchasePlan(); // [{ id, item_id, name, sku, shortage_qty }]
const createPurchaseOrders = computed(() =>
  order.items.some(r => r?.create_po === true)
);

// UI state
const busy = ref(false);
const msg  = ref('');

// helpers
const fmt = (n) => Number(n || 0).toFixed(2);
const isEmpty = (s) => !String(s ?? '').trim();

const recompute = () => order.recomputeTotals();

const {
  data: healthData,
  loading: healthLoading,
  error: healthError,
  checkHealth,
} = useHealth();

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

/** client-side validation before save */
function validateBeforeSave() {
  if (itemCount.value === 0) {
    msg.value = 'Save error: add at least one item.';
    return false;
  }
  if (isEmpty(order.customer?.name)) {
    msg.value = 'Save error: The customer.name field is required.';
    return false;
  }
  if (isEmpty(order.customer?.email)) {
    msg.value = 'Save error: The customer.email field is required.';
    return false;
  }
  return true;
}

/**
 * Save & Send:
 * - формируем payload с purchasePlan
 * - выводим сообщение внизу
 * - редиректим на просмотр при success
 */
async function saveAndSend() {
  msg.value = '';

  if (!validateBeforeSave()) return;

  busy.value = true;

  // формируем purchasePlan для бэкенда: [{ item_id, quantity }]
  const purchasePlan = plan.value.map(p => ({
    item_id:  p.item_id,
    quantity: p.shortage_qty,
  }));

  const payload = {
    customer: { ...order.customer },
    items:    order.items.map(row => ({
      item_id: row.item_id || row.zoho_item_id || '',
      qty:     Number(row.qty || 0),
      rate:    Number(row.rate || 0),
      tax:     Number(row.tax || 0),
      name:    row.name ?? '',
      sku:     row.sku ?? '',
    })),
    createPurchaseOrders: createPurchaseOrders.value,
    purchasePlan,
  };

  try {
    const res = await createSalesOrder(payload);

    // вытаскиваем ID/номер из разных возможных форм
    const soId = res?.data?.salesorder_id ?? res?.data?.id ?? res?.id ?? null;
    const soNo = res?.data?.salesorder_number ?? res?.number ?? null;
    const notice = res?.message ?? 'Sales Order created.';

    msg.value = soNo ? `${notice} (#${soNo}). Redirecting…` : `${notice}. Redirecting…`;

    if (soId) {
      setTimeout(() => {
        // возвращаем прежнее поведение: именованный роут so.view
        router.push({ name: 'so.view', params: { id: soId } });
      }, 500);
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
