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
 * - Shows stock badges and a "Create PO" toggle when qty exceeds stock
 * - NEW: plugs usePurchasePlan() and shows a tiny Purchase Orders summary
 */

import { computed, ref, watch, nextTick, onMounted } from 'vue';
import { useOrderStore } from '@inventory/stores/order';
import { useItemsSearch } from '@inventory/composables/useItemsSearch';
import { useItemDetails } from '@inventory/composables/useItemDetails';
import { usePurchasePlan } from '@inventory/composables/usePurchasePlan'; // NEW

// Pinia store
const store = useOrderStore();

// Reactive reference to items in store
const items = computed(() => store.items);

// Keep refs to Qty inputs so we can focus/select them
const qtyRefs = ref([]); // filled via :ref in template

// Search composable (API-backed)
const { results, loading, error, search, clear } = useItemsSearch();
const q = ref(''); // query text

// Single-item details composable (API-backed)
const { getById: getItemById } = useItemDetails(); // exposes async getById(item_id)

// Purchase plan (derived from store; reacts automatically)
const { plan, totalLines, totalShortQty } = usePurchasePlan(); // NEW

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

/** Compute shortage: if tracked and stock is known, returns qty - stock when positive; else 0 */
function shortfall(row) {
  const tracked = row?.track_inventory === true;
  const stock = Number(row?.available_stock ?? row?.actual_available_stock);
  const qty = Number(row?.qty ?? 0);
  if (!tracked) return 0;
  if (!Number.isFinite(stock)) return 0;
  return Math.max(0, qty - stock);
}

/** Recalculate default Create PO flags based on shortage */
function recomputePOFlags() {
  store.items.forEach((row) => {
    const sf = shortfall(row);
    if (sf > 0) {
      // If shortage and purchasable → default on; respect user's manual override if already set.
      if (row.can_be_purchased) {
        if (typeof row.create_po === 'undefined') {
          row.create_po = true;
        }
      } else {
        // Not purchasable: ensure flag is off
        row.create_po = false;
      }
    } else {
      // No shortage → force off
      row.create_po = false;
    }
  });
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

  // Ensure PO flags are consistent on mount
  recomputePOFlags();
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
    create_po: false,
  });
  store.recomputeTotals();
  recomputePOFlags();

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
  recomputePOFlags();
}

function onCellChange() {
  store.recomputeTotals();
  // Qty / rate / tax might change shortage conditions
  recomputePOFlags();
}

function doSearch() {
  if (!q.value.trim()) return;
  search(q.value.trim());
}

function clearSearch() {
  q.value = '';
  clear();
}

/**
 * Map API search result to an order line.
 * Additionally, enrich the line with item details via composable (track_inventory, can_be_purchased, stock).
 * Then merge/append, recompute totals, and focus Qty.
 */
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
    // Enriched fields (filled below if details are available)
    track_inventory: undefined,
    can_be_purchased: undefined,
    available_stock: undefined,
    // Purchase Order toggle (default is defined after enrichment)
    create_po: undefined,
  };

  // Enrich with details from /api/zoho/items/{id} via composable.
  // This is best-effort: failure should not block adding the row.
  try {
    if (newLine.item_id) {
      const details = await getItemById(newLine.item_id); // expects plain item object
      if (details) {
        newLine.track_inventory  = !!details.track_inventory;
        newLine.can_be_purchased = !!details.can_be_purchased;
        newLine.available_stock  = details.available_stock ?? details.actual_available_stock ?? null;
      }
    }
  } catch (e) {
    // Non-blocking enrich failure
    console.warn('Item details enrich failed:', e);
  }

  // Decide default PO toggle for this row
  try {
    const sf = shortfall(newLine);
    if (sf > 0 && newLine.can_be_purchased) {
      newLine.create_po = true;
    } else {
      newLine.create_po = false;
    }
  } catch {
    newLine.create_po = false;
  }

  const merged = tryMergeLine(newLine);
  if (!merged) store.addItem(newLine);

  store.recomputeTotals();
  recomputePOFlags(); // ensure consistency
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
              <!-- Badges and PO toggle -->
              <div class="mt-1 text-xs text-gray-500 flex flex-wrap items-center gap-2">
                <span
                  v-if="row.available_stock !== undefined && row.available_stock !== null"
                  class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5"
                >
                  Stock: <span class="ml-1 font-medium text-gray-700">{{ row.available_stock }}</span>
                </span>

                <span v-if="row.track_inventory" class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700">
                  tracked
                </span>

                <span v-if="row.can_be_purchased" class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-emerald-700">
                  purchasable
                </span>

                <span
                  v-if="shortfall(row) > 0"
                  class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-rose-700 font-medium"
                  title="Quantity exceeds available stock"
                >
                  short by {{ shortfall(row) }}
                </span>

                <!-- Create PO toggle appears only when there is a shortage and the item is purchasable -->
                <label
                  v-if="shortfall(row) > 0 && row.can_be_purchased"
                  class="ml-2 inline-flex items-center gap-1 text-gray-700 cursor-pointer select-none"
                  title="Create a Purchase Order for the shortage"
                >
                  <input
                    v-model="row.create_po"
                    type="checkbox"
                    class="h-3.5 w-3.5 rounded border-gray-300"
                  />
                  <span>Create PO</span>
                </label>
              </div>
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

    <!-- Purchase Orders summary (derived; not yet creating POs) -->
    <div class="rounded-lg border border-amber-200 bg-amber-50/40 p-3" v-if="totalLines > 0">
      <div class="text-sm text-amber-900 font-medium mb-1">
        Purchase Orders
      </div>
      <div class="text-sm text-amber-900">
        {{ totalLines }} line{{ totalLines === 1 ? '' : 's' }} will be purchased
        (shortage total: {{ totalShortQty }}).
      </div>
      <ul class="mt-2 text-xs text-amber-900/90 list-disc pl-5">
        <li v-for="p in plan" :key="p.id">
          {{ p.name }} ({{ p.sku }}) — shortage {{ p.shortage_qty }}
        </li>
      </ul>
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
