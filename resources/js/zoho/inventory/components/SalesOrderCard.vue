<!-- resources/js/zoho/inventory/components/SalesOrderCard.vue -->
<script setup>
// ============================================================
// SalesOrderCard.vue
// ------------------------------------------------------------
// Purpose:
//  - Present a Sales Order object in a clean, readable card
//  - Show key fields and (optionally) line items
//
// Props:
//  - order: plain object returned by GET /api/zoho/salesorders/:id
//
// Notes:
//  - The component is purely presentational (no requests inside).
//  - It tolerates missing fields and renders fallbacks.
// ============================================================

const props = defineProps({
  order: {
    type: Object,
    required: true,
  },
});

// Safe helpers
const fmt = (v, fallback = '—') => (v ?? v === 0 ? v : fallback);
const money = (v) => {
  const n = Number(v);
  if (Number.isNaN(n)) return '—';
  return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
  <div class="bg-white rounded-xl shadow p-6 space-y-6">
    <!-- Header meta -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div class="space-y-1">
        <div class="text-xs uppercase text-gray-500">Sales Order #</div>
        <div class="text-base font-medium text-gray-900">
          {{ fmt(order.salesorder_number) }}
        </div>
      </div>

      <div class="space-y-1">
        <div class="text-xs uppercase text-gray-500">Reference #</div>
        <div class="text-base text-gray-900">
          {{ fmt(order.reference_number) }}
        </div>
      </div>

      <div class="space-y-1">
        <div class="text-xs uppercase text-gray-500">Date</div>
        <div class="text-base text-gray-900">
          {{ fmt(order.date) }}
        </div>
      </div>

      <div class="space-y-1">
        <div class="text-xs uppercase text-gray-500">Customer</div>
        <div class="text-base text-gray-900">
          {{ fmt(order.customer_name) }}
        </div>
      </div>

      <div class="space-y-1">
        <div class="text-xs uppercase text-gray-500">Status</div>
        <div class="text-base text-gray-900">
          {{ fmt(order.status) }}
        </div>
      </div>

      <div class="space-y-1">
        <div class="text-xs uppercase text-gray-500">Total</div>
        <div class="text-base font-semibold text-gray-900">
          {{ money(order.total) }}
        </div>
      </div>
    </div>

    <!-- Line items -->
    <div v-if="Array.isArray(order.line_items) && order.line_items.length" class="overflow-x-auto">
      <table class="min-w-full text-sm border rounded-lg overflow-hidden">
        <thead class="bg-gray-50 text-gray-700">
          <tr>
            <th class="px-3 py-2 text-left w-10">#</th>
            <th class="px-3 py-2 text-left">Item</th>
            <th class="px-3 py-2 text-left">SKU</th>
            <th class="px-3 py-2 text-right w-24">Qty</th>
            <th class="px-3 py-2 text-right w-28">Rate</th>
            <th class="px-3 py-2 text-right w-28">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(li, idx) in order.line_items"
            :key="li.line_item_id || li.item_id || idx"
            class="border-t"
          >
            <td class="px-3 py-2 text-gray-500">{{ idx + 1 }}</td>
            <td class="px-3 py-2 text-gray-900">
              {{ li.name || li.item_name || '—' }}
            </td>
            <td class="px-3 py-2 text-gray-700">
              {{ li.sku || li.product_code || '—' }}
            </td>
            <td class="px-3 py-2 text-right">
              {{ fmt(li.quantity) }}
            </td>
            <td class="px-3 py-2 text-right">
              {{ money(li.rate) }}
            </td>
            <td class="px-3 py-2 text-right font-medium">
              {{ money(li.item_total || (Number(li.quantity) * Number(li.rate))) }}
            </td>
          </tr>

          <!-- Totals block without top borders between rows -->
          <tr class="bg-gray-50">
            <td colspan="5" class="px-3 py-2 text-right font-medium">Sub Total</td>
            <td class="px-3 py-2 text-right font-semibold">
              {{ money(order.sub_total ?? order.subtotal ?? order.total - (order.tax_total ?? 0)) }}
            </td>
          </tr>
          <tr v-if="order.tax_total != null" class="bg-gray-50">
            <td colspan="5" class="px-3 py-2 text-right font-medium">Tax</td>
            <td class="px-3 py-2 text-right font-semibold">{{ money(order.tax_total) }}</td>
          </tr>
          <tr class="bg-gray-100">
            <td colspan="5" class="px-3 py-2 text-right font-semibold">Total</td>
            <td class="px-3 py-2 text-right font-bold">{{ money(order.total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Fallback when no items -->
    <div
      v-else
      class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500"
    >
      No line items in this Sales Order.
    </div>
  </div>
</template>
