<script setup>
/* View/Edit page (placeholder): will fetch by id and show form in read/write mode */
const props = defineProps({ id: { type: String, required: true } })
</script>

<template>
  <div class="max-w-6xl mx-auto py-10 px-6">
    <h1 class="text-2xl font-bold mb-6">Sales Order #{{ id }}</h1>
    <div class="bg-white rounded-xl shadow p-6 text-gray-500 italic">
      Read/Edit view placeholder for order {{ id }}.
    </div>
  </div>
</template>
