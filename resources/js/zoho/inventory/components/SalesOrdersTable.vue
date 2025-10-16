<!-- resources/js/zoho/inventory/components/SalesOrdersTable.vue -->
<script setup>
// ============================================================
// SalesOrdersTable.vue
// ------------------------------------------------------------
// Presentational table for a list of Sales Orders.
// Props:
//  - rows:        array of { id, number, reference, customer, date, status, total }
//  - loading:     bool (optional) to show inline loading state
// Slots:
//  - empty:       custom empty state
// Notes:
//  - No requests here; navigation is delegated via <RouterLink>.
// ============================================================

import { RouterLink } from 'vue-router';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

function money(v) {
  const n = Number(v);
  if (Number.isNaN(n)) return '—';
  return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
  <div class="overflow-x-auto bg-white border rounded-xl">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-gray-700">
        <tr>
          <th class="px-3 py-2 text-left w-40">Date</th>
          <th class="px-3 py-2 text-left w-36">Sales Order#</th>
          <th class="px-3 py-2 text-left">Reference#</th>
          <th class="px-3 py-2 text-left">Customer</th>
          <th class="px-3 py-2 text-left w-28">Status</th>
          <th class="px-3 py-2 text-right w-32">Amount</th>
          <th class="px-3 py-2 w-10"></th>
        </tr>
      </thead>

      <tbody>
        <!-- Loading row -->
        <tr v-if="loading">
          <td class="px-3 py-6 text-gray-500 italic" colspan="7">Loading…</td>
        </tr>

        <!-- Data rows -->
        <tr
          v-for="row in rows"
          :key="row.id || row.salesorder_id"
          class="border-t"
        >
          <td class="px-3 py-2 text-gray-700">{{ row.date || '—' }}</td>

          <td class="px-3 py-2">
            <RouterLink
              :to="{ name: 'so.view', params: { id: row.id || row.salesorder_id } }"
              class="text-indigo-600 hover:text-indigo-500"
            >
              {{ row.number || row.salesorder_number || '—' }}
            </RouterLink>
          </td>

          <td class="px-3 py-2 text-gray-700">
            {{ row.reference || row.reference_number || '' }}
          </td>

          <td class="px-3 py-2 text-gray-900">
            {{ row.customer || row.customer_name || '—' }}
          </td>

          <td class="px-3 py-2 text-gray-700">
            {{ row.status || '—' }}
          </td>

          <td class="px-3 py-2 text-right text-gray-900">
            {{ money(row.total) }}
          </td>

          <td class="px-3 py-2"></td>
        </tr>

        <!-- Empty state -->
        <tr v-if="!loading && (!rows || rows.length === 0)">
          <td class="px-3 py-6 text-gray-500" colspan="7">
            <slot name="empty">No sales orders found.</slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
