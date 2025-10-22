<?php

namespace App\Observers;

use App\Models\Evento;
use App\Models\Plan;
use Illuminate\Support\Facades\Storage;

class EventoObserver
{
    public function deleted(Evento $evento): void
    {
        // si usas SoftDeletes y no quieres borrar físicamente en delete simple, sal aquí.
        // if (method_exists($plan, 'isForceDeleting') && !$plan->isForceDeleting()) return;

        $folder = "eventos/{$evento->id}";
        if (Storage::disk('media')->exists($folder)) {
            Storage::disk('media')->deleteDirectory($folder);
        }
    }
}
