<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This PHP install has no CA bundle configured; use the Mozilla bundle shipped in storage/certs.
        $ca = storage_path('certs/cacert.pem');
        if (is_file($ca) && ! ini_get('curl.cainfo')) {
            \Illuminate\Support\Facades\Http::globalOptions(['verify' => $ca]);
        }

        // Cache authenticated User lookup locally for 60s (flushed on any Account/Profile edit) to avoid a Singapore DB round-trip on every click.
        \Illuminate\Support\Facades\Auth::provider('cached-eloquent', function ($app, array $config) {
            return new class($app['hash'], $config['model']) extends \Illuminate\Auth\EloquentUserProvider
            {
                public function retrieveById($identifier)
                {
                    return \Illuminate\Support\Facades\Cache::remember(
                        "kpi:auth_user:{$identifier}",
                        900,
                        fn () => parent::retrieveById($identifier)
                    );
                }
            };
        });
    }
}
