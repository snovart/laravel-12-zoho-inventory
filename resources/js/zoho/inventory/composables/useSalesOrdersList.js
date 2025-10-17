// resources/js/zoho/inventory/composables/useSalesOrdersList.js
// ============================================================
// useSalesOrdersList()
// ------------------------------------------------------------
// Fetch and hold a paginated Sales Orders list. Exposes:
// - rows, pageContext, loading, error
// - params: { q, page, per_page, sort_column, sort_order }
// - load(), setPage(n), setPerPage(n), setQuery(q)
// - prevPage(), nextPage()
// ============================================================

import { ref, reactive, computed } from 'vue';
import { listSalesOrders } from '@inventory/api/Api';

export function useSalesOrdersList(initial = {}) {
  const rows = ref([]);
  const pageContext = ref({
    page: 1,
    per_page: 25,
    has_more_page: false,
    report_name: 'Sales Orders',
  });

  const loading = ref(false);
  const error = ref(null);

  const params = reactive({
    q: '',
    page: 1,
    per_page: 25,
    sort_column: 'date',
    sort_order: 'D',
    ...initial,
  });

  async function load() {
    loading.value = true;
    error.value = null;
    try {
      const res = await listSalesOrders({
        page: params.page,
        per_page: params.per_page,
        q: params.q,
        sort_column: params.sort_column,
        sort_order: params.sort_order,
      });

      rows.value = Array.isArray(res?.data) ? res.data : [];
      if (res?.page_context) {
        pageContext.value = { ...pageContext.value, ...res.page_context };
      } else {
        pageContext.value.page = params.page;
        pageContext.value.per_page = params.per_page;
        pageContext.value.has_more_page = false;
      }
    } catch (e) {
      rows.value = [];
      error.value =
        e?.response?.data?.message ||
        e?.message ||
        'Failed to load Sales Orders';
    } finally {
      loading.value = false;
    }
  }

  function setPage(n) {
    params.page = Math.max(1, Number(n) || 1);
    return load();
  }

  function setPerPage(n) {
    params.per_page = Number(n) || 25;
    params.page = 1;
    return load();
  }

  function setQuery(q) {
    params.q = String(q ?? '');
    params.page = 1;
    return load();
  }

  // --- Pagination helpers ------------------------------------
  const canPrev = computed(() => params.page > 1);
  const canNext = computed(() => !!(pageContext.value?.has_more_page));

  function prevPage() {
    if (!canPrev.value) return;
    params.page -= 1;
    return load();
    }

  function nextPage() {
    if (!canNext.value) return;
    params.page += 1;
    return load();
  }

  return {
    rows,
    pageContext,
    loading,
    error,
    params,
    load,
    setPage,
    setPerPage,
    setQuery,
    // pagination
    canPrev,
    canNext,
    prevPage,
    nextPage,
  };
}
