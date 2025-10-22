<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservaServicio extends Model
{
    use HasFactory;

    protected $table = 'reserva_servicios';

    protected $fillable = [
        'reserva_id',
        'servicio_id',
        'emprendedor_id',
        'fecha_inicio',
        'fecha_fin',
        'hora_inicio',
        'hora_fin',
        'duracion_minutos',
        'cantidad',
        'precio',
        'estado',
        'notas_cliente',
        'notas_emprendedor',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'precio' => 'decimal:2',
        'galeria' => 'array',
    ];

    // Estados del servicio reservado
    const ESTADO_EN_CARRITO = 'en_carrito';
    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_CONFIRMADO = 'confirmado';
    const ESTADO_CANCELADO = 'cancelado';
    const ESTADO_COMPLETADO = 'completado';

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function emprendedor(): BelongsTo
    {
        return $this->belongsTo(Emprendedor::class);
    }

    /**
     * Verifica si este servicio reservado se solapa con otro
     */
    public static function verificarSolapamiento($servicioId, $fechaInicio, $fechaFin, $horaInicio, $horaFin, $reservaId = null)
    {
        $query = self::where('servicio_id', $servicioId)
            ->where(function($q) use ($fechaInicio, $fechaFin) {
                if ($fechaFin) {
                    // Caso con rango de fechas
                    $q->where(function($inner) use ($fechaInicio, $fechaFin) {
                        // Fecha inicio dentro del rango existente
                        $inner->where('fecha_inicio', '<=', $fechaInicio)
                              ->where(function($i) use ($fechaInicio) {
                                  $i->where('fecha_fin', '>=', $fechaInicio)
                                    ->orWhereNull('fecha_fin');
                              });
                    })->orWhere(function($inner) use ($fechaInicio, $fechaFin) {
                        // Fecha fin dentro del rango existente
                        $inner->where('fecha_inicio', '<=', $fechaFin)
                              ->where(function($i) use ($fechaFin) {
                                  $i->where('fecha_fin', '>=', $fechaFin)
                                    ->orWhereNull('fecha_fin');
                              });
                    })->orWhere(function($inner) use ($fechaInicio, $fechaFin) {
                        // Rango existente dentro del nuevo rango
                        $inner->where('fecha_inicio', '>=', $fechaInicio)
                              ->where(function($i) use ($fechaFin) {
                                  $i->where('fecha_fin', '<=', $fechaFin)
                                    ->orWhereNull('fecha_fin');
                              });
                    });
                } else {
                    // Caso de un solo día
                    $q->where(function($inner) use ($fechaInicio) {
                        $inner->where('fecha_inicio', '=', $fechaInicio)
                              ->orWhere(function($i) use ($fechaInicio) {
                                  $i->where('fecha_inicio', '<=', $fechaInicio)
                                    ->where('fecha_fin', '>=', $fechaInicio);
                              });
                    });
                }
            })
            ->where(function($q) use ($horaInicio, $horaFin) {
                $q->where(function($inner) use ($horaInicio, $horaFin) {
                    // Hora inicio dentro del rango existente
                    $inner->where('hora_inicio', '<', $horaInicio)
                          ->where('hora_fin', '>', $horaInicio);
                })->orWhere(function($inner) use ($horaInicio, $horaFin) {
                    // Hora fin dentro del rango existente
                    $inner->where('hora_inicio', '<', $horaFin)
                          ->where('hora_fin', '>', $horaFin);
                })->orWhere(function($inner) use ($horaInicio, $horaFin) {
                    // Horario existente dentro del nuevo rango
                    $inner->where('hora_inicio', '>=', $horaInicio)
                          ->where('hora_fin', '<=', $horaFin);
                })->orWhere(function($inner) use ($horaInicio, $horaFin) {
                    // Horario nuevo dentro del rango existente
                    $inner->where('hora_inicio', '<=', $horaInicio)
                          ->where('hora_fin', '>=', $horaFin);
                });
            })
            // Excluir servicios en estado de carrito al verificar solapamiento
            ->whereIn('estado', [self::ESTADO_PENDIENTE, self::ESTADO_CONFIRMADO]);

        // Excluir la reserva actual si estamos actualizando
        if ($reservaId) {
            $query->where('id', '!=', $reservaId);
        }

        return $query->exists();
    }
    /**
     * Obtiene el subtotal de este servicio reservado (precio * cantidad)
     */
    public function getSubtotalAttribute(): float
    {
        return ($this->precio ?? 0) * ($this->cantidad ?? 1);
    }
}
