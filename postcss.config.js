// postcss.config.js
// ============================================================
// PostCSS Configuration for TailwindCSS v4
// ============================================================
// Purpose:
//  - Integrates TailwindCSS with the PostCSS build pipeline.
//  - Ensures Tailwind’s utilities are processed and compiled
//    when Vite builds the frontend assets.
// ============================================================

export default {
  plugins: {
    // TailwindCSS plugin for PostCSS
    // Processes @import, @theme, and @source directives inside app.css
    '@tailwindcss/postcss': {},
  },
}

// ------------------------------------------------------------
// Notes:
// 1. PostCSS runs automatically under Vite when compiling CSS assets.
// 2. This configuration is minimal because Tailwind v4 manages most of
//    the build logic internally.
// 3. If you later need autoprefixing or custom transformations,
//    you can extend the "plugins" object here.
// ============================================================
