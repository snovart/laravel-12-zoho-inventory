// resources/js/zoho/inventory/router.js
// ============================================================
// Vue Router configuration for Zoho Inventory module
// ============================================================
// Purpose:
//  - Handles all frontend routes under the `/inventory` prefix
//  - Mirrors the structure of Zoho Inventory (Sales Orders, etc.)
//  - Each page is a Vue Single File Component (SFC)
//  - Works together with Laravel routes that point to the same Blade view
// ============================================================

import { createRouter, createWebHistory } from 'vue-router'

// ------------------------------------------------------------
// Import all page components
// ------------------------------------------------------------
// Each page represents a separate view in our SPA.
// The import paths are relative to this `router.js` file.
// Avoid using aliases (like @inventory) unless properly configured in vite.config.js
import SalesOrdersListPage  from './pages/SalesOrdersListPage.vue'   // Page that shows the list of Sales Orders
import SalesOrderCreatePage from './pages/SalesOrderCreatePage.vue'  // Page for creating a new Sales Order
import SalesOrderViewPage   from './pages/SalesOrderViewPage.vue'    // Page for viewing/editing a specific Sales Order

// ------------------------------------------------------------
// Define route mappings
// ------------------------------------------------------------
// Each route defines a `path`, a `component`, and an optional `name`.
// The `meta.title` is used to set the document title dynamically.
// `props: true` allows route params (like :id) to be passed to the component as props.
const routes = [
  // Redirect the root of the SPA to the main list
  { path: '/', redirect: '/salesorders' },

  // List of all Sales Orders
  {
    path: '/salesorders',
    name: 'so.list',
    component: SalesOrdersListPage,
    meta: { title: 'Sales Orders' },
  },

  // Create a new Sales Order
  {
    path: '/salesorders/new',
    name: 'so.new',
    component: SalesOrderCreatePage,
    meta: { title: 'New Sales Order' },
  },

  // View or edit a specific Sales Order by ID
  {
    path: '/salesorders/:id',
    name: 'so.view',
    component: SalesOrderViewPage,
    props: true, // automatically injects route param "id" as a prop
    meta: { title: 'Sales Order' },
  },
]

// ------------------------------------------------------------
// Create and export router instance
// ------------------------------------------------------------
// `createWebHistory('/inventory/')` tells Vue Router that all URLs
// are relative to the `/inventory` prefix (defined in Laravel routes).
// This is essential for correct routing when using Laravel + Vue together.
export default createRouter({
  history: createWebHistory('/inventory/'), // trailing slash is important
  routes,
})

// ------------------------------------------------------------
// Notes:
// 1. Laravel routes/web.php should direct all paths under `/inventory`
//    (like /inventory/salesorders, /inventory/salesorders/new, etc.)
//    to the same Blade file (zoho/inventory/index.blade.php).
//
// 2. That Blade file loads the Vue app via @vite().
//
// 3. This router handles all client-side navigation between pages
//    without reloading the entire page (true SPA behavior).
// ============================================================
