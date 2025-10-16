<?php

return [
    // Accounts (OAuth) + Inventory API endpoints (EU)
    'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.eu'),
    'inventory_base_url' => env('ZOHOINV_BASE_URL', 'https://inventory.zoho.eu/api/v1'),

    // OAuth credentials
    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),

    // Organization context
    'organization_id' => env('ZOHOINV_ORGANIZATION_ID'),

    // Networking / behavior
    'timeout_ms' => (int) env('ZOHOINV_TIMEOUT_MS', 20000),
    'retry_policy' => env('ZOHOINV_RETRY_POLICY', 'standard'),
    'log_level' => env('ZOHOINV_LOG_LEVEL', 'info'),
];
