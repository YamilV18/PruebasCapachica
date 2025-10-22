<?php

namespace App\Observers;

use App\Models\Plan;
use App\Models\Servicio;
use Illuminate\Support\Facades\Storage;

class ServicioObserver
{
    public function deleted(Servicio $servicio): void
    {
        // si usas SoftDeletes y no quieres borrar físicamente en delete simple, sal aquí.
        // if (method_exists($plan, 'isForceDeleting') && !$plan->isForceDeleting()) return;

        $folder = "servicios/{$servicio->id}";
        if (Storage::disk('media')->exists($folder)) {
            Storage::disk('media')->deleteDirectory($folder);
        }
    }
}
