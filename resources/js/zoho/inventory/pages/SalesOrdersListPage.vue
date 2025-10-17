<!-- resources/js/zoho/inventory/pages/SalesOrdersListPage.vue -->
<script setup>
// ============================================================
// SalesOrdersListPage.vue
// ------------------------------------------------------------
// Page that displays a searchable/paged Sales Orders list.
// Uses useSalesOrdersList() to fetch rows and passes them into
// SalesOrdersTable for presentation.
// ============================================================

import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useSalesOrdersList } from '@inventory/composables/useSalesOrdersList';
import SalesOrdersTable from '@inventory/components/SalesOrdersTable.vue';
import Pagination from '@inventory/components/Pagination.vue'; // ✅ Added pagination component

// Composable: holds state, params and loader
const {
  rows,
  pageContext,
  loading,
  error,
  params,
  load,
  setPerPage,
  setQuery,
  prevPage,
  nextPage,
} = useSalesOrdersList({ per_page: 25, sort_column: 'date', sort_order: 'D' });

// Local search box model
const searchText = ref('');

// Manual search trigger
function onSearch() {
  setQuery(searchText.value);
}

// Change number of rows per page
function onPerPageChange(e) {
  setPerPage(e.target.value);
}

// Initial data load
onMounted(() => {
  load();
});
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-6">
    <!-- Header -->
    <header class="max-w-6xl mx-auto mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Sales Orders</h1>

      <div class="flex items-center gap-4">
        <label class="text-gray-600 text-sm">Per page</label>
        <select class="ui-input w-24" :value="params.per_page" @change="onPerPageChange">
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>

        <RouterLink
          :to="{ name: 'so.new' }"
          class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500"
        >
          New
        </RouterLink>
      </div>
    </header>

    <!-- Main content -->
    <main class="max-w-6xl mx-auto space-y-4">
      <!-- Search bar -->
      <div class="flex items-center gap-2">
        <input
          v-model="searchText"
          type="text"
          class="ui-input w-[420px]"
          placeholder="Search in list…"
          @keyup.enter="onSearch"
        />
        <button
          class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100"
          @click="onSearch"
        >
          Search
        </button>
      </div>

      <!-- Error message -->
      <div
        v-if="error"
        class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700"
      >
        {{ error }}
      </div>

      <!-- Table -->
      <SalesOrdersTable :rows="rows" :loading="loading" />

      <!-- Pagination -->
      <Pagination
        :page="pageContext?.page ?? params.page"
        :hasMore="pageContext?.has_more_page ?? false"
        :loading="loading"
        @prev="prevPage"
        @next="nextPage"
      />
    </main>
  </div>
</template>
