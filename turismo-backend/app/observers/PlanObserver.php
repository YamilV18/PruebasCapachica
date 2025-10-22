<?php

namespace App\Observers;

use App\Models\Plan;
use Illuminate\Support\Facades\Storage;

class PlanObserver
{
    public function deleted(Plan $plan): void
    {
        // si usas SoftDeletes y no quieres borrar físicamente en delete simple, sal aquí.
        // if (method_exists($plan, 'isForceDeleting') && !$plan->isForceDeleting()) return;

        $folder = "planes/{$plan->id}";
        if (Storage::disk('media')->exists($folder)) {
            Storage::disk('media')->deleteDirectory($folder);
        }
    }
}
