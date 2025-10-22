<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioHorario extends Model
{
    use HasFactory;

    protected $fillable = [
        'servicio_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'galeria' => 'array',
    ];

    // Definición de los valores de día de la semana
    const DIAS_SEMANA = [
        'lunes',
        'martes',
        'miercoles',
        'jueves',
        'viernes',
        'sabado',
        'domingo'
    ];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * Calcula la duración del horario en minutos
     */
    public function getDuracionMinutosAttribute(): int
    {
        $inicio = strtotime($this->hora_inicio);
        $fin = strtotime($this->hora_fin);

        return ($fin - $inicio) / 60;
    }
}
