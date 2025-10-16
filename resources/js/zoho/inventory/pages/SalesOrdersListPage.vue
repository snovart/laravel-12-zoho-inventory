<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="max-w-6xl mx-auto py-10 px-6">
    <header class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Sales Orders</h1>

      <!-- link to /salesorders/new -->
      <RouterLink
        :to="{ name: 'so.new' }"
        class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500"
      >
        New
      </RouterLink>
    </header>

    <div class="bg-white rounded-xl shadow p-6 text-gray-500 italic">
      Table placeholder (list of sales orders) — will replicate Zoho list.
    </div>
  </div>
</template>
