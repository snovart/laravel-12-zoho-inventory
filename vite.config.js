// vite.config.js
// ============================================================
// Vite Configuration for Laravel + Vue 3 + TailwindCSS
// ============================================================
// Purpose:
//  - Integrates Vite as the frontend build tool for Laravel 12
//  - Enables Vue 3 Single File Components (SFC)
//  - Adds TailwindCSS via official plugin
//  - Configures clean path aliases for imports
//  - Defines our entry points (CSS + main JS for Zoho Inventory SPA)
// ============================================================

import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

// ------------------------------------------------------------
// Vite Configuration
// ------------------------------------------------------------
export default defineConfig({
  plugins: [
    // --------------------------------------------------------
    // 🪄 Laravel Vite plugin
    // --------------------------------------------------------
    // - Handles asset compilation & versioning
    // - Provides automatic hot reloading (HMR)
    // - Detects Blade changes and reloads the browser
    laravel({
      input: [
        'resources/css/app.css',                 // Tailwind global styles
        'resources/js/zoho/inventory/app.js',    // Vue SPA entry point
      ],
      refresh: true, // Enables auto-reload when backend/frontend files change
    }),

    // --------------------------------------------------------
    // Vue 3 plugin
    // --------------------------------------------------------
    // - Enables support for .vue Single File Components
    // - Required for our SPA frontend to work properly
    vue(),

    // --------------------------------------------------------
    // TailwindCSS plugin
    // --------------------------------------------------------
    // - Provides modern utility-first CSS framework
    // - Works seamlessly with Laravel & Vite
    tailwindcss(),
  ],

  // ------------------------------------------------------------
  // Path Aliases
  // ------------------------------------------------------------
  // - Simplify imports in Vue components & JS modules.
  // - Example usage:
  //     import MyComp from '@inventory/components/MyComp.vue'
  // ------------------------------------------------------------
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),                  // Global JS alias
      '@inventory': fileURLToPath(new URL('./resources/js/zoho/inventory', import.meta.url)), // Module alias
    },
  },
})

// ------------------------------------------------------------
// Notes:
// 1. Vite runs with `npm run dev` (for local hot-reload) or `npm run build` (for production).
// 2. Laravel automatically injects compiled assets using the @vite() directive inside Blade templates.
// 3. TailwindCSS is configured via `resources/css/app.css` and automatically recompiled by Vite.
// ============================================================
