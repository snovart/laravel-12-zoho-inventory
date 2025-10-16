<!-- resources/js/zoho/inventory/components/ItemsTable.vue -->
<script setup>
/**
 * ItemsTable.vue
 * ------------------------------------------------------------
 * - Renders Sales Order line items from Pinia (useOrderStore)
 * - Provides search via composable useItemsSearch()
 * - Adds items from results and merges lines by SKU+Rate
 * - Auto-closes results after Add and focuses Qty of the last row
 * - Recomputes totals on every change
 */

import { computed, ref, watch, nextTick, onMounted } from 'vue';
import { useOrderStore } from '@inventory/stores/order';
import { useItemsSearch } from '@inventory/composables/useItemsSearch';

// Pinia store
const store = useOrderStore();

// Reactive reference to items in store
const items = computed(() => store.items);

// Keep refs to Qty inputs so we can focus/select them
const qtyRefs = ref([]); // filled via :ref in template

// Search composable (API-backed)
const { results, loading, error, search, clear } = useItemsSearch();
const q = ref(''); // query text

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------

/** Merge strategy: same SKU + same Rate => increase qty */
function tryMergeLine(newLine) {
  const target = store.items.find(
    (it) => (it.sku || '') === (newLine.sku || '') && Number(it.rate) === Number(newLine.rate),
  );
  if (target) {
    target.qty = Number(target.qty || 0) + Number(newLine.qty || 1);
    return true;
  }
  return false;
}

// Normalize any legacy rows (zoho_item_id -> item_id) to satisfy backend validation
onMounted(() => {
  let changed = false;
  store.items = store.items.map((it) => {
    if (!it.item_id && it.zoho_item_id) {
      changed = true;
      return { ...it, item_id: it.zoho_item_id };
    }
    return it;
  });
  if (changed) store.recomputeTotals();
});

// ------------------------------------------------------------
// Handlers
// ------------------------------------------------------------

function addRow() {
  store.addItem({
    id: Date.now(),
    name: '',
    sku: '',
    qty: 1,
    rate: 0,
    tax: 0,
    // item_id intentionally empty for manual rows; will fail validation if sent
  });
  store.recomputeTotals();

  // Focus last Qty after new empty row
  nextTick(() => {
    const i = store.items.length - 1;
    const el = qtyRefs.value[i];
    el?.focus?.();
    el?.select?.();
  });
}

function removeRow(id) {
  store.setItems(store.items.filter((i) => i.id !== id));
  store.recomputeTotals();
}

function onCellChange() {
  store.recomputeTotals();
}

function doSearch() {
  if (!q.value.trim()) return;
  search(q.value.trim());
}

function clearSearch() {
  q.value = '';
  clear();
}

/** Map API result to line format, merge or append, then focus Qty and hide results */
async function addFromSearch(result) {
  const price = Number(result?.rate ?? result?.selling_price ?? result?.unit_price ?? 0);

  const newLine = {
    id: Date.now(),
    // IMPORTANT: backend expects items[*].item_id
    item_id: result?.item_id ?? result?.id ?? '',
    name: result?.name ?? result?.item_name ?? '',
    sku: result?.sku ?? result?.product_code ?? '',
    qty: 1,
    rate: price,
    tax: 0,
  };

  const merged = tryMergeLine(newLine);
  if (!merged) store.addItem(newLine);

  store.recomputeTotals();
  clear();

  await nextTick();
  const indexToFocus = store.items.length - 1;
  const el = qtyRefs.value[indexToFocus];
  el?.focus?.();
  el?.select?.();
}

// Auto-hide results when query is cleared manually
watch(q, (val) => {
  if (!val) clear();
});
</script>

<template>
  <div class="space-y-4">
    <!-- Toolbar: search -->
    <div class="flex items-center gap-2">
      <input
        v-model="q"
        type="text"
        class="ui-input w-72"
        placeholder="Search items…"
        @keyup.enter="doSearch"
      />
      <button
        type="button"
        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-60"
        :disabled="loading || !q.trim()"
        @click="doSearch"
      >
        {{ loading ? 'Searching…' : 'Search' }}
      </button>
      <button
        type="button"
        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100"
        @click="clearSearch"
      >
        Clear
      </button>
      <div v-if="error" class="text-sm text-red-600 ml-2">{{ error }}</div>
    </div>

    <!-- Results panel -->
    <div v-if="results.length" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
      <div class="text-sm text-gray-700 mb-2">
        Found {{ results.length }} result{{ results.length === 1 ? '' : 's' }}:
      </div>

      <ul class="divide-y divide-gray-200">
        <li
          v-for="(r, i) in results.slice(0, 10)"
          :key="(r.id ?? r.item_id ?? i) + '-res'"
          class="py-2 flex items-center justify-between"
        >
          <div class="min-w-0">
            <div class="text-sm font-medium text-gray-900 truncate">
              {{ r.name ?? r.item_name ?? '—' }}
            </div>
            <div class="text-xs text-gray-500">
              SKU: {{ r.sku ?? r.product_code ?? '—' }}
              <span class="mx-2">•</span>
              Price:
              {{ Number(r.rate ?? r.selling_price ?? r.unit_price ?? 0).toFixed(2) }}
            </div>
          </div>
          <button
            type="button"
            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500"
            @click="addFromSearch(r)"
          >
            Add
          </button>
        </li>
      </ul>

      <div v-if="results.length > 10" class="mt-2 text-xs text-gray-500">
        Showing first 10 results.
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-white border rounded-xl">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-700">
          <tr>
            <th class="px-3 py-2 text-left w-10">#</th>
            <th class="px-3 py-2 text-left">Item name</th>
            <th class="px-3 py-2 text-left">SKU</th>
            <th class="px-3 py-2 text-right w-28">Qty</th>
            <th class="px-3 py-2 text-right w-32">Rate</th>
            <th class="px-3 py-2 text-right w-28">Tax</th>
            <th class="px-3 py-2 text-right w-32">Amount</th>
            <th class="px-3 py-2 w-12"></th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="(row, idx) in items"
            :key="row.id || idx"
            class="border-t"
          >
            <td class="px-3 py-2 text-gray-500">{{ idx + 1 }}</td>

            <td class="px-3 py-2">
              <input
                v-model="row.name"
                @input="onCellChange"
                type="text"
                class="ui-input"
                placeholder="Item name"
              />
            </td>

            <td class="px-3 py-2">
              <input
                v-model="row.sku"
                @input="onCellChange"
                type="text"
                class="ui-input"
                placeholder="SKU"
              />
            </td>

            <td class="px-3 py-2 text-right">
              <input
                :ref="el => qtyRefs[idx] = el"
                v-model.number="row.qty"
                @input="onCellChange"
                type="number"
                min="0"
                step="1"
                class="ui-input"
              />
            </td>

            <td class="px-3 py-2 text-right">
              <input
                v-model.number="row.rate"
                @input="onCellChange"
                type="number"
                min="0"
                step="0.01"
                class="ui-input"
              />
            </td>

            <td class="px-3 py-2 text-right">
              <input
                v-model.number="row.tax"
                @input="onCellChange"
                type="number"
                min="0"
                step="0.01"
                class="ui-input"
              />
            </td>

            <td class="px-3 py-2 text-right text-gray-900">
              {{
                (Number(row.qty || 0) * Number(row.rate || 0) + Number(row.tax || 0)).toFixed(2)
              }}
            </td>

            <td class="px-3 py-2 text-right">
              <button
                type="button"
                @click="removeRow(row.id)"
                class="px-2 py-1 text-sm rounded-md border border-gray-300 hover:bg-gray-50"
                title="Remove row"
              >
                ×
              </button>
            </td>
          </tr>

          <tr v-if="items.length === 0" class="border-t">
            <td class="px-3 py-6 text-center text-gray-500" colspan="8">
              No items yet. Click “Add row”.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer: actions + totals -->
    <div class="flex items-center justify-end">
      <div class="text-right">
        <div class="text-sm text-gray-600">Subtotal / Tax are computed in the store</div>
        <div class="text-lg font-semibold">
          Total: {{ store.totals.grand_total.toFixed(2) }}
        </div>
      </div>
    </div>
  </div>
</template>
