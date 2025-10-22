<?php

namespace App\Services;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoriasService
{
    public function getAll($paginate = true, $perPage = 15): LengthAwarePaginator|Collection
    {
        if ($paginate) {
            return Categoria::paginate($perPage);
        }
        
        return Categoria::all();
    }

    public function getById(int $id): ?Categoria
    {
        return Categoria::find($id);
    }

    public function getByIdWithServicios(int $id): ?Categoria
    {
        return Categoria::with('servicios')->find($id);
    }

    public function create(array $data): Categoria
    {
        return Categoria::create($data);
    }

    public function update(int $id, array $data): ?Categoria
    {
        $categoria = Categoria::find($id);
        
        if (!$categoria) {
            return null;
        }
        
        $categoria->update($data);
        return $categoria;
    }

    public function delete(int $id): bool
    {
        $categoria = Categoria::find($id);
        
        if (!$categoria) {
            return false;
        }
        
        return $categoria->delete();
    }

    public function searchByName(string $name): Collection
    {
        return Categoria::where('nombre', 'like', "%{$name}%")->get();
    }

    public function getActiveOnly(): Collection
    {
        return Categoria::where('estado', true)->get();
    }
}
