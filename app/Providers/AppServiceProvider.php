<?php

namespace App\Providers;

use App\Models\Submission;
use App\Policies\EntregaPolicy;
use App\Services\LuisFigueroa\Mensajeria\PublicadorAmqp;
use App\Services\LuisFigueroa\Mensajeria\PublicadorMensajes;
use App\Services\LuisFigueroa\RevisorPedidos;
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
        // Tema 3.1/3.2: the application talks to the broker only through this port.
        $this->app->bind(PublicadorMensajes::class, PublicadorAmqp::class);

        $this->app->bind(RevisorPedidos::class, fn () => new RevisorPedidos(config('luisfigueroa.revision.retardo_segundos')));
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
