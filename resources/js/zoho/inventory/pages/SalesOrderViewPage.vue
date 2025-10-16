<!-- resources/js/zoho/inventory/pages/SalesOrderViewPage.vue -->
<script setup>
// ============================================================
// SalesOrderViewPage.vue
// ------------------------------------------------------------
// Purpose:
//  - Fetch a single Sales Order by id (from route prop)
//  - Render it using the presentational component SalesOrderCard
//  - Show loading/error/empty states
// ============================================================

import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import SalesOrderCard from '@inventory/components/SalesOrderCard.vue';
import { useSalesOrderView } from '@inventory/composables/useSalesOrderView';

// Route param is passed via props from router (props: true)
const props = defineProps({
  id: { type: String, required: true },
});

// Composable for fetching a single SO
const { order, loading, error, fetchOne } = useSalesOrderView();

// Load on mount
onMounted(() => {
  fetchOne(props.id);
});
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-6">
    <!-- Header -->
    <header class="max-w-6xl mx-auto mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          Sales Order
          <span v-if="order?.salesorder_number" class="text-gray-500 font-normal">
            #{{ order.salesorder_number }}
          </span>
        </h1>
        <p class="text-sm text-gray-600 mt-1">
          Detailed view of a Sales Order from Zoho Inventory.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <RouterLink
          :to="{ name: 'so.list' }"
          class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
        >
          Back to list
        </RouterLink>
        <RouterLink
          :to="{ name: 'so.new' }"
          class="px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500"
        >
          New order
        </RouterLink>
      </div>
    </header>

    <!-- Body -->
    <main class="max-w-6xl mx-auto">
      <!-- Loading -->
      <div
        v-if="loading"
        class="rounded-xl border border-gray-200 bg-white p-6 text-gray-600 italic"
      >
        Loading sales order…
      </div>

      <!-- Error -->
      <div
        v-else-if="error"
        class="rounded-xl border border-red-200 bg-red-50 p-6 text-red-700"
      >
        Failed to load: {{ error }}
      </div>

      <!-- Content -->
      <SalesOrderCard v-else-if="order" :order="order" />

      <!-- Empty (not found) -->
      <div v-else class="rounded-xl border border-gray-200 bg-white p-6 text-gray-600">
        Sales Order not found.
      </div>
    </main>
  </div>
</template>
