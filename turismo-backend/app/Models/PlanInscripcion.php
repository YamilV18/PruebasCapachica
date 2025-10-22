<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanInscripcion extends Model
{
    use HasFactory;

    protected $table = 'plan_inscripciones';

    protected $fillable = [
        'plan_id',
        'user_id',
        'estado',
        'notas',
        'fecha_inscripcion',
        'fecha_inicio_plan',
        'fecha_fin_plan',
        'notas_usuario',
        'requerimientos_especiales',
        'numero_participantes',
        'precio_pagado',
        'metodo_pago',
        'comentarios_adicionales',
    ];

    protected $casts = [
        'fecha_inscripcion' => 'datetime',
        'fecha_inicio_plan' => 'datetime',
        'fecha_fin_plan' => 'datetime',
        'precio_pagado' => 'decimal:2',
        'galeria' => 'array',
    ];

    // Estados de la inscripción
    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_CONFIRMADA = 'confirmada';
    const ESTADO_CANCELADA = 'cancelada';
    const ESTADO_COMPLETADA = 'completada';
    const ESTADO_EN_PROGRESO = 'en_progreso';

    // Métodos de pago
    const METODO_EFECTIVO = 'efectivo';
    const METODO_TRANSFERENCIA = 'transferencia';
    const METODO_TARJETA = 'tarjeta';
    const METODO_YAPE = 'yape';
    const METODO_PLIN = 'plin';

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    // Calcular precio total considerando número de participantes
    public function getPrecioTotalCalculadoAttribute(): float
    {
        $precioBase = $this->plan->precio_total ?? 0;
        return $precioBase * $this->numero_participantes;
    }

    // Verificar si la inscripción está activa
    public function estaActiva(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_CONFIRMADA,
            self::ESTADO_EN_PROGRESO
        ]);
    }

    // Verificar si puede ser cancelada
    public function puedeSerCancelada(): bool
    {
        if (!$this->fecha_inicio_plan) {
            return $this->estado === self::ESTADO_PENDIENTE || $this->estado === self::ESTADO_CONFIRMADA;
        }

        // No se puede cancelar si ya comenzó
        return $this->fecha_inicio_plan->isFuture() &&
               ($this->estado === self::ESTADO_PENDIENTE || $this->estado === self::ESTADO_CONFIRMADA);
    }

    // Obtener días restantes para el inicio
    public function getDiasParaInicioAttribute(): ?int
    {
        if (!$this->fecha_inicio_plan) {
            return null;
        }

        return now()->diffInDays($this->fecha_inicio_plan, false);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->whereIn('estado', [
            self::ESTADO_CONFIRMADA,
            self::ESTADO_EN_PROGRESO
        ]);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeConfirmadas($query)
    {
        return $query->where('estado', self::ESTADO_CONFIRMADA);
    }

    public function scopeDelUsuario($query, $usuarioId)
    {
        return $query->where('user_id', $usuarioId);
    }

    public function scopeDelPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }

    public function scopeProximasAIniciar($query, $dias = 7)
    {
        return $query->where('fecha_inicio_plan', '<=', now()->addDays($dias))
                    ->where('fecha_inicio_plan', '>=', now())
                    ->activas();
    }

    public function scopeEnProgreso($query)
    {
        return $query->where('fecha_inicio_plan', '<=', now())
                    ->where('fecha_fin_plan', '>=', now())
                    ->activas();
    }
}
