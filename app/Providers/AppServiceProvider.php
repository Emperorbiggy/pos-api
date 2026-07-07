<?php

namespace App\Providers;

use App\Services\OIRS\Contracts\OIRSServiceInterface;
use App\Services\OIRS\OIRSService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OIRSServiceInterface::class, OIRSService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by(
            $request->user('api')?->id ?: $request->ip()
        ));
    }
}
