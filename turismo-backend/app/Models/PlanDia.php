<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlanDia extends Model
{
    use HasFactory;

    protected $table = 'plan_dias';

    protected $fillable = [
        'plan_id',
        'numero_dia',
        'titulo',
        'descripcion',
        'hora_inicio',
        'hora_fin',
        'duracion_estimada_minutos',
        'notas_adicionales',
        'orden',
    ];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'galeria' => 'array',

    ];

    // Relación con el plan
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Relación con los servicios del día
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'plan_dia_servicios')
                    ->withPivot([
                        'hora_inicio',
                        'hora_fin',
                        'duracion_minutos',
                        'notas',
                        'orden',
                        'es_opcional',
                        'precio_adicional'
                    ])
                    ->withTimestamps()
                    ->orderBy('pivot_orden');
    }

    // Relación directa con plan_dia_servicios para más control
    public function planDiaServicios(): HasMany
    {
        return $this->hasMany(PlanDiaServicio::class);
    }

    // Obtener la duración total del día en minutos
    public function getDuracionTotalAttribute(): int
    {
        if ($this->hora_inicio && $this->hora_fin) {
            $inicio = \Carbon\Carbon::parse($this->hora_inicio);
            $fin = \Carbon\Carbon::parse($this->hora_fin);
            return $fin->diffInMinutes($inicio);
        }

        return $this->duracion_estimada_minutos ?? 0;
    }

    // Obtener el precio total de servicios opcionales del día
    public function getPrecioServiciosOpcionalesAttribute(): float
    {
        return $this->servicios()
                    ->wherePivot('es_opcional', true)
                    ->sum('plan_dia_servicios.precio_adicional') ?? 0;
    }

    // Scope para ordenar por número de día
    public function scopeOrdenadoPorDia($query)
    {
        return $query->orderBy('numero_dia');
    }

    // Scope para obtener días de un plan específico
    public function scopeDelPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }
}
