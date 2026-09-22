<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        if (!app()->runningInConsole() && app()->bound('request')) {
            $request = request();
            $host = $request ? $request->getHost() : '';
            $isProductionOrServerless = app()->environment('production') 
                || isset($_ENV['VERCEL']) 
                || isset($_SERVER['VERCEL']) 
                || str_contains($host, 'vercel.app') 
                || str_contains($host, 'onrender.com') 
                || ($request && ($request->header('x-forwarded-proto') === 'https' || $request->server('HTTP_X_FORWARDED_PROTO') === 'https' || $request->isSecure()));

            if ($isProductionOrServerless) {
                URL::forceScheme('https');
            }
        }
    }
}
