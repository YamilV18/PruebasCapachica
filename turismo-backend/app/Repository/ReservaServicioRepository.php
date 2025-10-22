<?php

namespace App\Repository;

use App\Models\ReservaServicio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReservaServicioRepository
{
    protected $model;

    public function __construct(ReservaServicio $reservaServicio)
    {
        $this->model = $reservaServicio;
    }

    /**
     * Obtener todos los servicios reservados
     */
    public function getAll(): Collection
    {
        return $this->model->with(['reserva.usuario', 'servicio', 'emprendedor'])->get();
    }

    /**
     * Encontrar un servicio reservado por ID
     */
    public function findById(int $id): ?ReservaServicio
    {
        return $this->model->with(['reserva.usuario', 'servicio', 'emprendedor'])->find($id);
    }

    /**
     * Crear un nuevo servicio reservado
     */
    public function create(array $data): ReservaServicio
    {
        return $this->model->create($data);
    }

    /**
     * Actualizar un servicio reservado existente
     */
    public function update(int $id, array $data): bool
    {
        $servicio = $this->findById($id);
        if (!$servicio) {
            return false;
        }
        
        return $servicio->update($data);
    }

    /**
     * Eliminar un servicio reservado
     */
    public function delete(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    /**
     * Obtener servicios reservados por reserva
     */
    public function getByReserva(int $reservaId): Collection
    {
        return $this->model->where('reserva_id', $reservaId)
            ->with(['servicio', 'emprendedor'])
            ->get();
    }

    /**
     * Obtener servicios reservados por emprendedor
     */
    public function getByEmprendedor(int $emprendedorId): Collection
    {
        return $this->model->where('emprendedor_id', $emprendedorId)
            ->with(['reserva.usuario', 'servicio'])
            ->get();
    }

    /**
     * Obtener servicios reservados por servicio
     */
    public function getByServicio(int $servicioId): Collection
    {
        return $this->model->where('servicio_id', $servicioId)
            ->with(['reserva.usuario', 'emprendedor'])
            ->get();
    }
    
    /**
     * Verificar disponibilidad de un servicio (no hay solapamiento)
     */
    public function verificarDisponibilidad(
        int $servicioId, 
        string $fechaInicio, 
        ?string $fechaFin, 
        string $horaInicio, 
        string $horaFin,
        ?int $reservaServicioId = null
    ): bool {
        // Verificar primero si hay solapamiento de horarios
        $haySolapamiento = ReservaServicio::verificarSolapamiento(
            $servicioId,
            $fechaInicio,
            $fechaFin,
            $horaInicio,
            $horaFin,
            $reservaServicioId
        );
        
        if ($haySolapamiento) {
            return false;
        }
        
        // Verificar disponibilidad de cupos
        $servicio = app(ServicioRepository::class)->findById($servicioId);
        
        if (!$servicio) {
            return false;
        }
        
        // Contar cuántas reservas hay para este servicio en este horario
        $reservasCount = ReservaServicio::where('servicio_id', $servicioId)
            ->where('fecha_inicio', $fechaInicio)
            ->where('hora_inicio', $horaInicio)
            ->whereIn('estado', [ReservaServicio::ESTADO_PENDIENTE, ReservaServicio::ESTADO_CONFIRMADO])
            ->count();
        
        // Verificar si hay cupos disponibles
        return $reservasCount < $servicio->capacidad;
    }
    
    /**
     * Obtener servicios reservados por rango de fechas
     */
    public function getByRangoFechas(string $fechaInicio, string $fechaFin): Collection
    {
        return $this->model
            ->where(function($query) use ($fechaInicio, $fechaFin) {
                $query->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
                    ->orWhereBetween('fecha_fin', [$fechaInicio, $fechaFin])
                    ->orWhere(function($q) use ($fechaInicio, $fechaFin) {
                        $q->where('fecha_inicio', '<=', $fechaInicio)
                          ->where(function($inner) use ($fechaFin) {
                              $inner->where('fecha_fin', '>=', $fechaFin)
                                    ->orWhereNull('fecha_fin');
                          });
                    });
            })
            ->with(['reserva.usuario', 'servicio', 'emprendedor'])
            ->get();
    }
    
    /**
     * Cambiar el estado de un servicio reservado
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        return $this->update($id, ['estado' => $estado]);
    }
}