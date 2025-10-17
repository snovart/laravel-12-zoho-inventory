<script setup>
/**
 * SalesOrderCreatePage.vue
 * ------------------------------------------------------------
 * Page that renders the Sales Order creation form.
 * Now resets the Pinia order store on mount so that items from
 * the previous draft don't leak into a new order.
 */
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import CustomerSection from '@inventory/components/CustomerSection.vue'
import ItemsTable from '@inventory/components/ItemsTable.vue'
import SummaryBar from '@inventory/components/SummaryBar.vue'
import { useOrderStore } from '@inventory/stores/order'

// Reset state when opening "New" page to avoid stale draft lines
const order = useOrderStore()
onMounted(() => {
  // Hard reset: clears customer, items, totals, flags
  order.reset()
})

function handleSaveDraft () { console.log('Save as Draft clicked') }
function handleSaveSend  () { console.log('Save & Send clicked') }
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-6">
    <header class="max-w-6xl mx-auto mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Sales Order</h1>
        <p class="text-sm text-gray-600 mt-1">
          Create a Sales Order for Zoho Inventory. This page will closely follow Zoho’s UI.
        </p>
      </div>

      <!-- link back to /salesorders -->
      <RouterLink
        :to="{ name: 'so.list' }"
        class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
      >
        Back to list
      </RouterLink>
    </header>

    <main class="max-w-6xl mx-auto bg-white shadow rounded-xl p-8 space-y-8">
      <section>
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Customer</h2>
        <CustomerSection />
      </section>

      <section>
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Items</h2>
        <ItemsTable />
      </section>

      <section>
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Summary</h2>
        <SummaryBar
          @save-draft="handleSaveDraft"
          @save-send="handleSaveSend"
        />
      </section>
    </main>
  </div>
</template>
