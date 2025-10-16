// resources/js/zoho/inventory/composables/useHealth.js
// ============================================================
// useHealth()
// ------------------------------------------------------------
// Composable that wraps the backend health endpoint and exposes:
//  - data:   response payload (org info, etc.)
//  - loading / error flags
//  - run():  trigger the request
// ============================================================

import { ref } from 'vue';
// ВАЖНО: путь и имя файла API — как у тебя в проекте.
// Используем тот же экспорт, что и в api/Api.js
import { checkHealth } from '@inventory/api/Api';

export function useHealth() {
  const data = ref(null);
  const loading = ref(false);
  const error = ref(null);

  async function run() {
    loading.value = true;
    error.value = null;
    try {
      data.value = await checkHealth();
    } catch (e) {
      // аккуратно достанем message
      error.value = e?.response?.data?.message || e?.message || 'Health check failed';
    } finally {
      loading.value = false;
    }
  }

  return { data, loading, error, run };
}
