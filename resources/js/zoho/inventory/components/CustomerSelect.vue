<script setup>
// Smart async select for Zoho Contacts
// - Debounced search to /api/zoho/contacts
// - Keyboard navigation (↑/↓/Enter/Escape)
// - Emits the chosen contact object
// - Supports infinite "Next page" loading

import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { searchContacts } from '@inventory/api/Api'

const props = defineProps({
  modelValue: { type: Object, default: null }, // selected contact object
  placeholder: { type: String, default: 'Select or search a customer' },
  perPage: { type: Number, default: 20 }
})
const emit = defineEmits(['update:modelValue', 'select'])

const open = ref(false)
const q = ref('')
const loading = ref(false)
const error = ref('')
const items = ref([])
const page = ref(1)
const hasMore = ref(false)
const activeIndex = ref(-1) // keyboard focus
const rootEl = ref(null)
let debounceTimer = null

function resetList() {
  items.value = []
  page.value = 1
  hasMore.value = false
  activeIndex.value = -1
}

async function runSearch(pageToLoad = 1) {
  const term = q.value.trim()
  if (!term) {
    resetList()
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await searchContacts(term, pageToLoad, props.perPage)
    const list = Array.isArray(res?.data) ? res.data : []
    if (pageToLoad === 1) items.value = list
    else items.value = [...items.value, ...list]

    const pc = res?.page_context || {}
    page.value = Number(pc.page || pageToLoad)
    hasMore.value = !!pc.has_more_page
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Search failed'
    if (pageToLoad === 1) items.value = []
    hasMore.value = false
  } finally {
    loading.value = false
  }
}

function onInput() {
  // debounce 300ms
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => runSearch(1), 300)
}

function openDropdown() {
  open.value = true
  if (q.value.trim()) onInput()
}

function closeDropdown() {
  open.value = false
  activeIndex.value = -1
}

function selectItem(item) {
  emit('update:modelValue', item)
  emit('select', item)
  closeDropdown()
}

function onKeydown(e) {
  if (!open.value) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, items.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, 0)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (activeIndex.value >= 0 && items.value[activeIndex.value]) {
      selectItem(items.value[activeIndex.value])
    }
  } else if (e.key === 'Escape') {
    closeDropdown()
  }
}

async function loadMore() {
  if (loading.value || !hasMore.value) return
  await runSearch(page.value + 1)
}

// click-outside to close
function onClickOutside(ev) {
  if (!rootEl.value) return
  if (!rootEl.value.contains(ev.target)) closeDropdown()
}

onMounted(() => {
  document.addEventListener('click', onClickOutside)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside)
})

const displayLabel = computed(() => {
  if (!props.modelValue) return ''
  const n = props.modelValue.contact_name || ''
  const e = props.modelValue.email || ''
  return e ? `${n} — ${e}` : n
})
</script>

<template>
  <div ref="rootEl" class="relative">
    <!-- Control -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <input
          :placeholder="placeholder"
          class="ui-input w-full"
          :value="open ? q : displayLabel"
          @focus="openDropdown"
          @input="(e)=>{q = e.target.value; onInput()}"
          @keydown="onKeydown"
        />
      </div>
      <button
        type="button"
        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100"
        @click="open ? closeDropdown() : openDropdown()"
      >
        {{ open ? 'Close' : 'Search' }}
      </button>
    </div>

    <!-- Dropdown -->
    <div
      v-if="open"
      class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow"
    >
      <div class="p-2">
        <input
          v-model="q"
          type="text"
          class="ui-input w-full"
          placeholder="Type to search…"
          @input="onInput"
          @keydown="onKeydown"
        />
      </div>

      <div v-if="error" class="px-3 pb-2 text-sm text-red-600">{{ error }}</div>

      <ul class="max-h-64 overflow-auto divide-y divide-gray-100">
        <li
          v-for="(c, i) in items"
          :key="c.contact_id || i"
          class="px-3 py-2 cursor-pointer hover:bg-indigo-50"
          :class="i === activeIndex ? 'bg-indigo-50' : ''"
          @mouseenter="activeIndex = i"
          @mouseleave="activeIndex = -1"
          @click="selectItem(c)"
        >
          <div class="text-sm text-gray-900 font-medium">{{ c.contact_name || '—' }}</div>
          <div class="text-xs text-gray-500">
            <span v-if="c.email">{{ c.email }}</span>
            <span v-if="c.email && c.company_name" class="mx-1">•</span>
            <span v-if="c.company_name">{{ c.company_name }}</span>
          </div>
        </li>

        <li v-if="!loading && items.length === 0" class="px-3 py-3 text-sm text-gray-500">
          No results.
        </li>
      </ul>

      <div class="flex items-center justify-between px-3 py-2">
        <span class="text-xs text-gray-500" v-if="loading">Loading…</span>
        <button
          v-if="hasMore && !loading"
          type="button"
          class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-100"
          @click="loadMore"
        >
          Next page →
        </button>
      </div>
    </div>
  </div>
</template>
