<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipalidad extends Model
{
    use HasFactory;

    protected $table = 'municipalidad';

    protected $fillable = [
        'nombre',
        'descripcion',
        'red_facebook',
        'red_instagram',
        'red_youtube',
        'coordenadas_x',
        'coordenadas_y',
        'frase',
        'comunidades',
        'historiafamilias',
        'historiacapachica',
        'comite',
        'mision',
        'vision',
        'valores',
        'ordenanzamunicipal',
        'alianzas',
        'correo',
        'horariodeatencion',
    ];
    protected $casts = [
        // ... lo que ya tienes
        'galeria' => 'array',
    ];

    /**
     * Obtener las asociaciones de la municipalidad
     */
    public function asociaciones(): HasMany
    {
        return $this->hasMany(Asociacion::class);
    }

    public function sliders(): HasMany
    {
        return $this->hasMany(Slider::class, 'entidad_id')
                    ->where('tipo_entidad', 'municipalidad')
                    ->orderBy('orden');
    }

    // Relación para sliders principales
    public function slidersPrincipales()
    {
        return $this->hasMany(Slider::class, 'entidad_id')
                    ->where('tipo_entidad', 'municipalidad')
                    ->where('es_principal', true)
                    ->orderBy('orden');
    }

    // Relación para sliders secundarios con descripción
    public function slidersSecundarios()
    {
        return $this->hasMany(Slider::class, 'entidad_id')
                    ->where('tipo_entidad', 'municipalidad')
                    ->where('es_principal', false)
                    ->with('descripcion')
                    ->orderBy('orden');
    }
}
