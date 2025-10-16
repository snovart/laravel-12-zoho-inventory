<!-- resources/js/zoho/inventory/components/CustomerSection.vue -->
<script setup>
// ------------------------------------------------------------
// CustomerSection.vue
// ------------------------------------------------------------
// Responsibility:
//  - Bind customer fields directly to Pinia store
//  - Minimal UI only; validations & search will be added later
// ------------------------------------------------------------
import { useOrderStore } from '@inventory/stores/order'

const order = useOrderStore() // central store (customer, items, totals)
</script>

<template>
  <div class="space-y-4">
    <!-- Name -->
    <div>
        <label class="ui-label">Customer name</label>
        <input v-model="order.customer.name" type="text" class="ui-input" placeholder="Enter customer full name" />
    </div>

    <!-- Email -->
    <div>
        <label class="ui-label">Email</label>
        <input v-model="order.customer.email" type="email" class="ui-input" placeholder="name@example.com" />
    </div>

    <!-- Phone -->
    <div>
        <label class="ui-label">Phone</label>
        <input v-model="order.customer.phone" type="tel" class="ui-input" placeholder="+380 ..." />
    </div>

    <!-- Helper row: reserved for actions -->
    <div class="pt-2 flex items-center gap-3 text-sm text-gray-500">
      <span>Later: search existing customer, quick-create in Zoho, validations.</span>
    </div>
  </div>
</template>
