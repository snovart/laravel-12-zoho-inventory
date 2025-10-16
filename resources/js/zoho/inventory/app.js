// resources/js/zoho/inventory/app.js
// ============================================================
// Main entry point for Zoho Inventory SPA
// ============================================================
// Purpose:
//  - Bootstraps the Vue 3 application for the Zoho Inventory module
//  - Registers Pinia (state management) and Vue Router
//  - Mounts the app inside the <div id="app"> element
//    defined in resources/views/zoho/inventory/index.blade.php
// ============================================================

import { createApp } from 'vue';        // Core Vue API
import { createPinia } from 'pinia';    // State management (replacement for Vuex)
import router from './router';          // Client-side routing configuration
import App from './App.vue';            // Root component that renders <router-view />

// Log to console for debugging / confirmation of boot sequence
// console.log('Inventory SPA booting…');

// ------------------------------------------------------------
// Create the Vue application instance
// ------------------------------------------------------------
// - The `App` component serves as the root (see App.vue)
// - `createApp()` initializes the Vue runtime
const app = createApp(App);

// ------------------------------------------------------------
// 🔌 Register global plugins
// ------------------------------------------------------------
// 1. Pinia — centralized state store for shared data
// 2. Router — handles SPA navigation between pages
app.use(createPinia());
app.use(router);

// ------------------------------------------------------------
// Wait until the router is ready before mounting
// ------------------------------------------------------------
// - Ensures that asynchronous navigation guards and initial routes
//   are fully resolved before the app renders
// - Prevents rendering issues (e.g., empty screens) on first load
router.isReady().then(() => app.mount('#app'));

// ------------------------------------------------------------
// Notes:
// 1. The Vue app will mount into <div id="app"> from index.blade.php.
// 2. All routes defined in router.js will dynamically render inside it.
// 3. This entry point is bundled via Vite (see @vite directive in Blade).
// ============================================================
