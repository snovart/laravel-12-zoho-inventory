<!-- resources/js/zoho/inventory/App.vue -->
<!-- ============================================================
 Root Vue Component for Zoho Inventory SPA
===============================================================
 Purpose:
  - Acts as the single root container for the entire SPA.
  - All route components (pages) will be rendered here dynamically
    through <router-view>.
  - Keeps the app lightweight and focused — global UI (e.g. navbars)
    can be added here later if needed.
=============================================================== -->

<template>
  <!--
    The <router-view> tag is a built-in Vue Router component.
    It renders whichever page (component) matches the current route.
    Example:
      /salesorders       → renders SalesOrdersListPage.vue
      /salesorders/new   → renders SalesOrderCreatePage.vue
      /salesorders/123   → renders SalesOrderViewPage.vue
  -->
  <router-view />
</template>

<!--
  Notes:
  - This component is automatically mounted inside <div id="app">
    defined in resources/views/zoho/inventory/index.blade.php.
  - Vue Router swaps out the contents of <router-view>
    without reloading the page — enabling full SPA navigation.
  - You can later add global UI elements here (top bar, side menu, etc.)
    that should persist across all routes.
-->
