# Laravel 12 + Zoho Inventory (Vue 3 SPA)

This project provides a modern Laravel 12 backend integrated with the **Zoho Inventory API**, and a Vue 3 (Composition API) SPA frontend.  
It includes endpoints, composables, and UI components to simulate Zoho’s “Sales Orders” workflow.

---

## 📁 Project structure

resources/js/zoho/inventory/
│
├── api/
│   └── Api.js                   # Centralized API layer (axios client)
│
├── composables/
│   ├── useHealth.js             # GET /api/zoho/health
│   ├── useItemDetails.js        # GET /api/zoho/items/:id
│   ├── useItemsSearch.js        # Search items
│   ├── usePurchasePlan.js       # Compute PO plan from order items
│   ├── useSalesOrderView.js     # GET /api/zoho/salesorders/:id
│   ├── useSalesOrders.js        # POST /api/zoho/salesorders
│   └── useSalesOrdersList.js    # GET /api/zoho/salesorders
│
├── components/
│   ├── CustomerSection.vue      # Basic customer info form
│   ├── ItemsTable.vue           # Item list with qty/price inputs
│   ├── SummaryBar.vue           # Totals + actions (Save & Send, Health)
│   └── SalesOrdersTable.vue     # Table of sales orders list
│
├── pages/
│   ├── SalesOrderListPage.vue   # List page (uses SalesOrdersTable)
│   └── SalesOrderCreatePage.vue # Create form (Customer + Items + Summary)
│
└── stores/
    └── order.js                 # Pinia store for current order

---

## ⚙️ Backend routes (Laravel)

GET    /api/zoho/health
GET    /api/zoho/items?q=term
GET    /api/zoho/items/{id}
GET    /api/zoho/salesorders
GET    /api/zoho/salesorders/{id}
POST   /api/zoho/salesorders

All routes are handled by `App\Http\Controllers\Api\ZohoInventoryController`,  
which proxies requests to `App\Services\Zoho\ZohoInventoryService`.

---

## 🧠 Key frontend logic

- **Pinia store** `useOrderStore()` holds customer data, items, and totals.
- **ItemsTable** dynamically adds products fetched from `/api/zoho/items`.
- **SummaryBar** performs save/send to create a Sales Order in Zoho via Laravel.
- **useHealth()** calls the `/health` endpoint to test API connectivity.
- **usePurchasePlan()** derives purchase order requirements from shortages.

---

## 🧪 Running locally

1. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Set environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure Zoho API credentials**
   ```
   ZOHO_CLIENT_ID=
   ZOHO_CLIENT_SECRET=
   ZOHO_REFRESH_TOKEN=
   ZOHOINV_ORGANIZATION_ID=
   ZOHOINV_BASE_URL=https://inventory.zoho.eu/api/v1
   ```

4. **Run migrations & serve**
   ```bash
   php artisan migrate
   php artisan serve
   ```

5. **Build frontend**
   ```bash
   npm run dev
   ```

---

## 🧩 CI/CD

See `.github/workflows/ci.yml` for automated pipeline:
- Checks out repository
- Installs PHP + Node dependencies
- Runs migrations (SQLite)
- Builds frontend
- Runs tests (if present)
- Uploads built assets as GitHub artifact

---

## 🧾 License
MIT — free to use, modify, and distribute.
