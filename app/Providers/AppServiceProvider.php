<?php

namespace App\Providers;

use App\Services\ForeignData;
use App\Services\GoogleTranslate;
use App\Services\ZohoAuth;
use App\Services\ZohoCrm;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZohoAuth::class, function () {
            $cfg = config('services.zoho');

            return new ZohoAuth($cfg['client_id'], $cfg['client_secret'], $cfg['refresh_token'], $cfg['accounts_url']);
        });

        $this->app->singleton(ZohoCrm::class, function ($app) {
            $cfg = config('services.zoho');

            return new ZohoCrm(
                $app->make(ZohoAuth::class),
                $cfg['api_domain'],
                $cfg['api_version'],
                (int) config('foreign.cache_ttl_seconds', 60),
            );
        });

        $this->app->singleton(GoogleTranslate::class, fn () => new GoogleTranslate(config('services.google_translate.key') ?: null));

        $this->app->singleton(ForeignData::class, fn ($app) => new ForeignData(
            $app->make(ZohoCrm::class),
            config('foreign.workdrive_download_url'),
            config('foreign.blocked_statuses'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
