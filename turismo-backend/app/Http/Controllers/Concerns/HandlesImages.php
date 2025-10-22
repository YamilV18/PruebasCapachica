<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesImages
{
    protected function storeImage(UploadedFile $file, string $folder): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = Str::uuid().'.'.$ext;
        $path = trim($folder, '/').'/'.$name;

        Storage::disk('media')->putFileAs(dirname($path), $file, basename($path));
        return $path; // guarda este path en la BD
    }

    protected function deleteImage(?string $path): void
    {
        if ($path && !filter_var($path, FILTER_VALIDATE_URL)) {
            Storage::disk('media')->delete($path);
        }
    }
}
