// resources/js/zoho/inventory/composables/usePurchasePlan.js
// ------------------------------------------------------------
// Derives a local "purchase plan" from order lines:
// - include only lines with shortage (qty > stock, when tracked)
// - and where the user enabled `create_po === true`
// Exposes a computed list and a few helpers for the UI.

import { computed } from 'vue';
import { useOrderStore } from '@inventory/stores/order';

function shortfall(row) {
  const tracked = row?.track_inventory === true;
  const stock = Number(row?.available_stock ?? row?.actual_available_stock);
  const qty = Number(row?.qty ?? 0);
  if (!tracked) return 0;
  if (!Number.isFinite(stock)) return 0;
  return Math.max(0, qty - stock);
}

export function usePurchasePlan() {
  const store = useOrderStore();

  // Plan entries for items that require a purchase
  const plan = computed(() => {
    return (store.items || [])
      .map((row) => {
        const sf = shortfall(row);
        return {
          id: row.id,
          item_id: row.item_id,
          name: row.name,
          sku: row.sku,
          shortage_qty: sf,
          create_po: !!row.create_po,
          // room for future fields, e.g. preferred_vendor_id
        };
      })
      .filter((p) => p.create_po && p.shortage_qty > 0);
  });

  // Totals & helpers
  const totalLines = computed(() => plan.value.length);
  const totalShortQty = computed(() =>
    plan.value.reduce((sum, p) => sum + Number(p.shortage_qty || 0), 0),
  );

  return {
    plan,
    totalLines,
    totalShortQty,
  };
}
