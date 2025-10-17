<!-- resources/js/zoho/inventory/components/Pagination.vue -->
<script setup>
/**
 * Универсальная пагинация с Prev/Next и отображением текущей страницы.
 * Работает по модели «has_more_page» (как у Zoho).
 *
 * Props:
 *  - page: Number          — текущая страница (>=1)
 *  - hasMore: Boolean      — есть ли следующая страница
 *  - loading: Boolean      — блокировать кнопки во время загрузки
 *
 * Emits:
 *  - prev        — клик по Prev
 *  - next        — клик по Next
 *  - changePage  — когда нужно перейти на конкретную страницу (на будущее)
 */
const props = defineProps({
  page: { type: Number, default: 1 },
  hasMore: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['prev', 'next', 'changePage']);

function prev() {
  if (!props.loading && props.page > 1) emit('prev');
}
function next() {
  if (!props.loading && props.hasMore) emit('next');
}
</script>

<template>
  <div class="flex items-center justify-between pt-2">
    <div class="text-xs text-gray-500">
      Page {{ page }}
    </div>

    <div class="flex items-center gap-2">
      <button
        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-60"
        :disabled="loading || page <= 1"
        @click="prev"
      >
        ← Prev
      </button>

      <button
        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-60"
        :disabled="loading || !hasMore"
        @click="next"
      >
        Next →
      </button>
    </div>
  </div>
</template>
