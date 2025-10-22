<?php

namespace App\Services;

use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Categoria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ServicioService
{
    public function getAll($paginate = true, $perPage = 15): LengthAwarePaginator|Collection
    {
        if ($paginate) {
            return Servicio::paginate($perPage);
        }
        
        return Servicio::all();
    }

    public function getById(int $id): ?Servicio
    {
        return Servicio::find($id);
    }

    public function create(array $data): Servicio
    {
        return Servicio::create($data);
    }

    public function update(int $id, array $data): ?Servicio
    {
        $servicio = Servicio::find($id);
        
        if (!$servicio) {
            return null;
        }
        
        $servicio->update($data);
        return $servicio;
    }

    public function delete(int $id): bool
    {
        $servicio = Servicio::find($id);
        
        if (!$servicio) {
            return false;
        }
        
        return $servicio->delete();
    }

    public function getByEmprendedor(int $emprendedorId): Collection
    {
        return Servicio::where('emprendedor_id', $emprendedorId)->get();
    }

    public function getByCategoria(int $categoriaId): Collection
    {
        return Servicio::whereHas('categorias', function($query) use ($categoriaId) {
            $query->where('categorias.id', $categoriaId);
        })->get();
    }

    public function getActiveOnly(): Collection
    {
        return Servicio::where('estado', true)->get();
    }

    public function searchByName(string $name): Collection
    {
        return Servicio::where('nombre', 'like', "%{$name}%")->get();
    }

    public function searchByDescription(string $description): Collection
    {
        return Servicio::where('descripcion', 'like', "%{$description}%")->get();
    }

    public function cambiarEstado(int $id, bool $estado): ?Servicio
    {
        $servicio = Servicio::find($id);
        
        if (!$servicio) {
            return null;
        }
        
        $servicio->update(['estado' => $estado]);
        return $servicio;
    }

    public function getEstadisticas(): array
    {
        return [
            'total' => Servicio::count(),
            'activos' => Servicio::where('estado', true)->count(),
            'inactivos' => Servicio::where('estado', false)->count(),
        ];
    }

    public function getWithRelations(int $id): ?Servicio
    {
        return Servicio::with(['emprendedor', 'categorias'])->find($id);
    }

    public function getByUbicacionReferencia(string $ubicacion): Collection
    {
        return Servicio::where('ubicacion_referencia', 'like', "%{$ubicacion}%")->get();
    }
}
