// tailwind.config.js
// ============================================================
// TailwindCSS Configuration
// ============================================================
// Purpose:
//  - Defines Tailwind's scanning paths and customization settings
//  - Controls which files are parsed for class names
//  - Allows project-wide theme extensions and plugin registration
// ============================================================

/** @type {import('tailwindcss').Config} */
export default {
  // ------------------------------------------------------------
  // Content Sources
  // ------------------------------------------------------------
  // Tailwind scans these files to detect which CSS classes are in use.
  // It then generates only the required styles for optimized builds.
  // Add more paths if you introduce new frontend directories.
  // ------------------------------------------------------------
  content: [
    './resources/**/*.blade.php',  // Blade templates
    './resources/**/*.vue',        // Vue Single File Components
    './resources/**/*.js',         // JS modules and composables
  ],

  // ------------------------------------------------------------
  // Theme Customization
  // ------------------------------------------------------------
  // Use this section to extend or override the default Tailwind design tokens:
  // colors, spacing, typography, etc.
  // Example:
  // extend: {
  //   colors: {
  //     primary: '#3b82f6', // custom blue
  //   },
  // },
  // ------------------------------------------------------------
  theme: {
    extend: {},
  },

  // ------------------------------------------------------------
  // Plugins
  // ------------------------------------------------------------
  // Add Tailwind plugins here (e.g., forms, typography, aspect-ratio).
  // Example:
  // plugins: [require('@tailwindcss/forms')],
  // ------------------------------------------------------------
  plugins: [],
}

// ------------------------------------------------------------
// Notes:
// 1. Tailwind will rebuild automatically when used via `npm run dev`.
// 2. The generated CSS is injected through Vite (see @vite directive in Blade).
// 3. You can inspect available utilities via `npx tailwindcss -i ./resources/css/app.css -o ./public/css/app.css`
// ============================================================
