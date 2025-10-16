<?php

namespace App\Providers;

use App\Services\Zoho\ZohoAuthService;
use App\Services\Zoho\ZohoInventoryService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ZohoServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Pull unified config
        $this->app->singleton(ZohoAuthService::class, function ($app) {
            $cfg = config('zoho');

            return new ZohoAuthService([
                'accounts_url'  => (string) ($cfg['accounts_url'] ?? ''),
                'client_id'     => (string) ($cfg['client_id'] ?? ''),
                'client_secret' => (string) ($cfg['client_secret'] ?? ''),
                'refresh_token' => (string) ($cfg['refresh_token'] ?? ''),
            ]);
        });

        $this->app->singleton(ZohoInventoryService::class, function ($app) {
            $cfg  = config('zoho');
            $auth = $app->make(ZohoAuthService::class);

            return new ZohoInventoryService($auth, [
                'inventory_base_url' => (string) ($cfg['inventory_base_url'] ?? ''),
                'organization_id'    => (string) ($cfg['organization_id'] ?? ''),
                'timeout_ms'         => (int)    ($cfg['timeout_ms'] ?? 20000),
                'retry_policy'       => (string) ($cfg['retry_policy'] ?? 'standard'),
                'log_level'          => (string) ($cfg['log_level'] ?? 'info'),
            ]);
        });
    }

    /**
     * Defer loading until resolved.
     */
    public function provides(): array
    {
        return [
            ZohoAuthService::class,
            ZohoInventoryService::class,
        ];
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // nothing here for now
    }
}
