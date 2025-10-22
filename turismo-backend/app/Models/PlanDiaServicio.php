<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanDiaServicio extends Model
{
    use HasFactory;

    protected $table = 'plan_dia_servicios';

    protected $fillable = [
        'plan_dia_id',
        'servicio_id',
        'hora_inicio',
        'hora_fin',
        'duracion_minutos',
        'notas',
        'orden',
        'es_opcional',
        'precio_adicional',
    ];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'es_opcional' => 'boolean',
        'precio_adicional' => 'decimal:2',
        'galeria' => 'array',

    ];

    // Relación con el día del plan
    public function planDia(): BelongsTo
    {
        return $this->belongsTo(PlanDia::class);
    }

    // Relación con el servicio
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    // Obtener la duración en minutos
    public function getDuracionCalculadaAttribute(): int
    {
        if ($this->hora_inicio && $this->hora_fin) {
            $inicio = \Carbon\Carbon::parse($this->hora_inicio);
            $fin = \Carbon\Carbon::parse($this->hora_fin);
            return $fin->diffInMinutes($inicio);
        }

        return $this->duracion_minutos ?? 0;
    }

    // Scope para servicios obligatorios
    public function scopeObligatorios($query)
    {
        return $query->where('es_opcional', false);
    }

    // Scope para servicios opcionales
    public function scopeOpcionales($query)
    {
        return $query->where('es_opcional', true);
    }

    // Scope para ordenar por orden
    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden');
    }
}
