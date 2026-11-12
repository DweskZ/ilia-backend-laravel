<?php

namespace App\Providers;

use App\Models\Submission;
use App\Policies\EntregaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        Gate::policy(Submission::class, EntregaPolicy::class);

        // In production TLS is terminated by the proxy / platform; generate https links.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
