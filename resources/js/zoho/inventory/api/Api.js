// resources/js/zoho/inventory/api/Api.js
// ============================================================
// Axios API client for Zoho Inventory module
// ============================================================

import axios from 'axios';

// Shared Axios instance for the Inventory SPA
export const http = axios.create({
  // baseURL: import.meta.env.VITE_API_BASE_URL ?? '/', // enable if BE is on another domain
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
  salesorders: '/api/zoho/salesorders', // POST creates an SO, GET lists SOs
};

// --- Helpers -------------------------------------------------
const soShowUrl = (id) => `${API.salesorders}/${encodeURIComponent(id)}`;
const itemShowUrl = (id) => `${API.items}/${encodeURIComponent(id)}`;

// --- Calls ---------------------------------------------------

/** Simple backend availability check */
export async function checkHealth() {
  const { data } = await http.get(API.health);
  return data;
}

/** Create Sales Order draft on the backend (payload comes from Pinia store) */
export async function createSalesOrder(payload) {
  const { data } = await http.post(API.salesorders, payload);
  return data;
}

/** Search items in Zoho Inventory by keyword */
export async function searchItems(query) {
  const { data } = await http.get(API.items, { params: { q: query } });
  // Backend returns { status:'ok', query, data:[...] }
  return Array.isArray(data?.data) ? data.data : [];
}

/** List sales orders (supports params: page, per_page, query, sort_column, sort_order, etc.) */
export async function listSalesOrders(params = {}) {
  const { data } = await http.get(API.salesorders, { params });
  // Return as-is (controller responds with status/data/page_context)
  return data;
}

/** Get a single sales order by ID */
export async function getSalesOrder(id) {
  const { data } = await http.get(soShowUrl(id));
  // Controller returns { status:'ok', data:{...} } — pass through as-is
  return data;
}

/** Get a single item by item_id — used to retrieve stock flags/levels if available */
export async function getItemDetails(id) {
  const { data } = await http.get(itemShowUrl(id));
  // Controller returns { status:'ok', data:{...normalized item...} }
  return data;
}
