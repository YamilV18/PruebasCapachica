<?php

namespace App\Providers;

use App\Models\Emprendedor;
use App\Models\Evento;
use App\Models\Plan;
use App\Models\Servicio;
use App\Observers\EmprendedorObserver;
use App\Observers\EventoObserver;
use App\Observers\PlanObserver;
use App\Observers\ServicioObserver;
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
        Plan::observe(PlanObserver::class);
        Emprendedor::observe(EmprendedorObserver::class);
        Servicio::observe(ServicioObserver::class);
        Evento::observe(EventoObserver::class);

    }
}
