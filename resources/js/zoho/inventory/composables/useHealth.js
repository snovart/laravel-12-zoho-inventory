// resources/js/zoho/inventory/composables/useHealth.js
// ============================================================
// useHealth()
// ------------------------------------------------------------
// Composable that wraps the backend health endpoint and exposes:
//  - data:   response payload (org info, etc.)
//  - loading / error flags
//  - run():  trigger the request
//  - checkHealth(): alias of run() for components that call it by name
// ============================================================

import { ref } from 'vue';
// IMPORTANT: keep same API import path as in your project
import { checkHealth as apiCheckHealth } from '@inventory/api/Api';

export function useHealth() {
  const data = ref(null);
  const loading = ref(false);
  const error = ref(null);

  async function run() {
    loading.value = true;
    error.value = null;
    try {
      data.value = await apiCheckHealth();
    } catch (e) {
      // pull a readable message safely
      error.value = e?.response?.data?.message || e?.message || 'Health check failed';
    } finally {
      loading.value = false;
    }
  }

  // Alias to keep existing components working (SummaryBar expects checkHealth)
  const checkHealth = run;

  return { data, loading, error, run, checkHealth };
}
