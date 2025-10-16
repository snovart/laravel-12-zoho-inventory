// resources/js/zoho/inventory/stores/order.js
// ============================================================
// Pinia Store: useOrderStore
// ------------------------------------------------------------
// Purpose:
//  - Hold the Sales Order draft state (customer, items, flags, totals)
//  - Provide simple actions to mutate items and recompute totals
// Notes:
//  - Each line item may carry `zoho_item_id` (required for Zoho Inventory)
//  - Totals are naive and will be refined later (discounts, taxes, etc.)
// ============================================================

import { defineStore } from 'pinia';

export const useOrderStore = defineStore('order', {
  state: () => ({
    // Basic customer info captured on the form
    customer: {
      name: '',
      email: '',
      phone: '',
      // Optionally we may store resolved Zoho contact_id later
      // contact_id: '',
    },

    // Line items of the Sales Order
    items: [
      // Example shape:
      // {
      //   id: 123,                 // local row uid
      //   zoho_item_id: '8503...', // Zoho Inventory item_id
      //   name: 'USB-C Cable 1m',
      //   sku: 'USBC-1M',
      //   qty: 1,
      //   rate: 9.99,
      //   tax: 0,                  // plain tax amount (not percentage) for now
      // }
    ],

    // Whether to auto-create Purchase Orders for out-of-stock items
    createPurchaseOrders: false,

    // Aggregated totals (kept simple for now)
    totals: {
      subtotal: 0,
      tax_total: 0,
      grand_total: 0,
    },
  }),

  getters: {
    itemCount: (state) => state.items.length,
  },

  actions: {
    // Merge partial payload into customer object
    setCustomer(payload) {
      this.customer = { ...this.customer, ...payload };
    },

    // Replace whole items array (bulk ops)
    setItems(list) {
      this.items = Array.isArray(list) ? list : [];
    },

    // Add a single line; defaults applied then overridden by payload
    addItem(line) {
      this.items.push({
        qty: 1,
        rate: 0,
        tax: 0,
        zoho_item_id: '',
        ...line,
      });
    },

    // Optional convenience: add or increase if same identity exists
    // Priority for identity:
    //  1) zoho_item_id (stable id from Zoho)
    //  2) fallback to (sku + rate) pair if no zoho_item_id
    addOrIncrease(line) {
      const zId = line?.zoho_item_id ?? '';
      let existing = null;

      if (zId) {
        existing = this.items.find((i) => i.zoho_item_id === zId);
      } else if (line?.sku != null && line?.rate != null) {
        existing = this.items.find(
          (i) => (i.sku || '') === (line.sku || '') && Number(i.rate) === Number(line.rate),
        );
      }

      if (existing) {
        existing.qty = Number(existing.qty || 0) + Number(line.qty || 1);
      } else {
        this.addItem(line);
      }

      this.recomputeTotals();
    },

    // Toggle Purchase Order creation flag
    toggleCreatePO(flag) {
      this.createPurchaseOrders = Boolean(flag);
    },

    // Recompute totals (naive: subtotal=sum(qty*rate), tax_total=sum(tax), grand=subtotal+tax)
    recomputeTotals() {
      const subtotal = this.items.reduce(
        (sum, it) => sum + (Number(it.qty) * Number(it.rate) || 0),
        0,
      );
      const taxTotal = this.items.reduce(
        (sum, it) => sum + (Number(it.tax) || 0),
        0,
      );
      this.totals.subtotal = subtotal;
      this.totals.tax_total = taxTotal;
      this.totals.grand_total = subtotal + taxTotal;
    },

    // Reset draft to initial values
    reset() {
      this.$reset();
    },
  },
});
