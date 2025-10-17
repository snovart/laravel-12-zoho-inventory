<!-- resources/js/zoho/inventory/components/CustomerSection.vue -->
<script setup>
// ------------------------------------------------------------
// CustomerSection.vue
// ------------------------------------------------------------
// Responsibility:
//  - Bind customer fields directly to Pinia store
//  - Add smart customer picker (search in Zoho contacts)
//  - After picking, fetch full details to fill email if missing
// ------------------------------------------------------------
import { useOrderStore } from '@inventory/stores/order'
import CustomerSelect from '@inventory/components/CustomerSelect.vue'
import { getContact } from '@inventory/api/Api' // <-- add this import

const order = useOrderStore()

/** Pick the best email from a full contact payload */
function pickEmail(full) {
  // 1) direct email on contact
  if (full?.email) return full.email
  // 2) primary contact person
  const persons = Array.isArray(full?.contact_persons) ? full.contact_persons : []
  const primary = persons.find(p => p?.is_primary_contact || p?.is_primary_contact_person)
  if (primary?.email) return primary.email
  // 3) any first person with email
  const any = persons.find(p => p?.email)
  if (any?.email) return any.email
  return ''
}

/** When a contact is picked from dropdown */
async function onPicked(contact) {
  const name = contact?.contact_name || ''
  let email = contact?.email || ''

  // If email is missing, fetch full details and try again
  if (!email && contact?.contact_id) {
    try {
      const resp = await getContact(String(contact.contact_id)) // expects { status:'ok', data:{...} }
      const full = resp?.data || {}
      email = pickEmail(full)
    } catch {
      // swallow – we still keep the name
    }
  }

  order.setCustomer({ name, email })
}
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="ui-label">Customer</label>
      <CustomerSelect
        :model-value="null"
        placeholder="Select or search a customer"
        @select="onPicked"
      />
      <p class="mt-2 text-xs text-gray-500">
        Picking a contact will fill Name and Email below. You can still edit them.
      </p>
    </div>

    <div>
      <label class="ui-label">Customer name</label>
      <input v-model="order.customer.name" type="text" class="ui-input" placeholder="Enter customer full name" />
    </div>

    <div>
      <label class="ui-label">Email</label>
      <input v-model="order.customer.email" type="email" class="ui-input" placeholder="name@example.com" />
    </div>

    <div>
      <label class="ui-label">Phone</label>
      <input v-model="order.customer.phone" type="tel" class="ui-input" placeholder="+380 ..." />
    </div>

    <div class="pt-2 flex items-center gap-3 text-sm text-gray-500">
      <span>Later: validations, quick-create in Zoho, etc.</span>
    </div>
  </div>
</template>
