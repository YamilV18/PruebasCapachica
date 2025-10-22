<?php


namespace App\Observers;

use App\Models\Emprendedor;
use Illuminate\Support\Facades\Storage;

class EmprendedorObserver
{
    public function deleted(Emprendedor $emprendedor): void
    {
        // si usas SoftDeletes y no quieres borrar físicamente en delete simple, sal aquí.
        // if (method_exists($plan, 'isForceDeleting') && !$plan->isForceDeleting()) return;

        $folder = "emprendedores/{$emprendedor->id}";
        if (Storage::disk('media')->exists($folder)) {
            Storage::disk('media')->deleteDirectory($folder);
        }
    }
}
