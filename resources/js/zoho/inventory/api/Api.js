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
  salesorders: '/api/zoho/salesorders', // POST create SO
};

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
