// resources/js/zoho/inventory/api/Api.js
// ============================================================
// Axios API client for Zoho Inventory module
// ============================================================

import axios from 'axios';

// Shared Axios instance for Inventory SPA
export const http = axios.create({
  // baseURL: import.meta.env.VITE_API_BASE_URL ?? '/', // enable if BE on another domain
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Centralized endpoints
export const API = {
  health: '/api/zoho/health',
  items: '/api/zoho/items',
  salesorders: '/api/zoho/salesorders', // POST create SO, GET list
};

// --- Helpers -------------------------------------------------
const soShowUrl = (id) => `${API.salesorders}/${encodeURIComponent(id)}`;

// --- Calls ---------------------------------------------------

/** Simple backend availability check */
export async function checkHealth() {
  const { data } = await http.get(API.health);
  return data;
}

/** Create Sales Order draft on backend (payload comes from Pinia store) */
export async function createSalesOrder(payload) {
  const { data } = await http.post(API.salesorders, payload);
  return data;
}

/** search items in Zoho Inventory by keyword */
export async function searchItems(query) {
  const { data } = await http.get(API.items, { params: { q: query } });
  // backend returns { status:'ok', query, data:[...] }
  return Array.isArray(data?.data) ? data.data : [];
}

/** list sales orders (supports params: page, per_page, query, sort_column, sort_order, etc.) */
export async function listSalesOrders(params = {}) {
  const { data } = await http.get(API.salesorders, { params });
  // отдаём как есть (контроллер вернёт status/data/page_context)
  return data;
}

/** get single sales order by ID */
export async function getSalesOrder(id) {
  const { data } = await http.get(soShowUrl(id));
  // контроллер вернёт { status:'ok', data:{...} } — возвращаем как есть
  return data;
}
