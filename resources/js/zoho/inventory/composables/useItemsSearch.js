// resources/js/zoho/inventory/composables/useItemsSearch.js
// ============================================================
// useItemsSearch()
// ------------------------------------------------------------
// - Composable for item search via backend API (/api/zoho/items)
// - Returns: results, loading, error, search(q), clear()
// - Used in ItemsTable.vue (search + add item workflow)
// ============================================================

import { ref } from 'vue';
import { http, API } from '@inventory/api/Api';

export function useItemsSearch() {
  const results = ref([]);
  const loading = ref(false);
  const error = ref('');

  // --- Search handler -------------------------------------------------
  async function search(q) {
    const term = String(q ?? '').trim();
    if (!term) {
      results.value = [];
      error.value = '';
      return;
    }

    loading.value = true;
    error.value = '';
    try {
      const { data } = await http.get(API.items, { params: { q: term } });
      // Backend returns { status:'ok', data:[...] }
      results.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) {
      results.value = [];
      error.value =
        e?.response?.data?.message ||
        e?.message ||
        'Item search failed';
    } finally {
      loading.value = false;
    }
  }

  // --- Reset all values -----------------------------------------------
  function clear() {
    results.value = [];
    error.value = '';
  }

  return { results, loading, error, search, clear };
}
