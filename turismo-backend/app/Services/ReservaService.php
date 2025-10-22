<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\User;
use App\Models\Emprendedor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ReservaService
{
    public function getAll($paginate = true, $perPage = 15): LengthAwarePaginator|Collection
    {
        if ($paginate) {
            return Reserva::paginate($perPage);
        }
        
        return Reserva::all();
    }

    public function getById(int $id): ?Reserva
    {
        return Reserva::find($id);
    }

    public function create(array $data): Reserva
    {
        // Generar código de reserva si no se proporciona
        if (!isset($data['codigo_reserva'])) {
            $data['codigo_reserva'] = 'RES-' . strtoupper(uniqid());
        }
        
        return Reserva::create($data);
    }

    public function update(int $id, array $data): ?Reserva
    {
        $reserva = Reserva::find($id);
        
        if (!$reserva) {
            return null;
        }
        
        $reserva->update($data);
        return $reserva;
    }

    public function delete(int $id): bool
    {
        $reserva = Reserva::find($id);
        
        if (!$reserva) {
            return false;
        }
        
        return $reserva->delete();
    }

    public function getByUser(int $userId): Collection
    {
        return Reserva::where('usuario_id', $userId)->get();
    }

    public function getByEstado(string $estado): Collection
    {
        return Reserva::where('estado', $estado)->get();
    }

    public function getByEmprendedor(int $emprendedorId): Collection
    {
        return Reserva::whereHas('servicios', function($query) use ($emprendedorId) {
            $query->where('emprendedor_id', $emprendedorId);
        })->get();
    }

    public function searchByCodigo(string $codigo): Collection
    {
        return Reserva::where('codigo_reserva', 'like', "%{$codigo}%")->get();
    }

    public function getByDateRange(Carbon $fechaInicio, Carbon $fechaFin): Collection
    {
        return Reserva::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
    }

    public function cambiarEstado(int $id, string $estado): ?Reserva
    {
        $reserva = Reserva::find($id);
        
        if (!$reserva) {
            return null;
        }
        
        $reserva->update(['estado' => $estado]);
        return $reserva;
    }

    public function getEstadisticas(): array
    {
        return [
            'total' => Reserva::count(),
            'pendientes' => Reserva::where('estado', 'pendiente')->count(),
            'confirmadas' => Reserva::where('estado', 'confirmada')->count(),
            'canceladas' => Reserva::where('estado', 'cancelada')->count(),
            'completadas' => Reserva::where('estado', 'completada')->count(),
        ];
    }

    public function getRecientes(int $limit = 10): Collection
    {
        return Reserva::latest()->limit($limit)->get();
    }

    public function getWithServicios(int $id): ?Reserva
    {
        return Reserva::with('servicios')->find($id);
    }
}
