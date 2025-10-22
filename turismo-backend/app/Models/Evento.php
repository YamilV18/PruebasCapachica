<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;


class Evento extends Model
{
    protected $table = 'eventos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo_evento',
        'idioma_principal',
        'fecha_inicio',
        'hora_inicio',
        'fecha_fin',
        'hora_fin',
        'duracion_horas',
        'coordenada_x',
        'coordenada_y',
        'id_emprendedor',
        'que_llevar',
    ];
    protected $casts = [
        // ... lo que ya tienes
        'galeria' => 'array',
    ];

    protected $appends = ['imagen_url','galeria_urls'];

    // Relación con emprendedor
    public function emprendedor()
    {
        return $this->belongsTo(Emprendedor::class, 'id_emprendedor');
    }




    public function sliders(): HasMany
    {
    return $this->hasMany(Slider::class, 'entidad_id')
                ->where('tipo_entidad', 'evento')
                ->orderBy('orden');
    }

    public function getImagenUrlAttribute(): ?string {
        if (!isset($this->imagen) || !$this->imagen) return null;
        return filter_var($this->imagen, FILTER_VALIDATE_URL)
            ? $this->imagen
            : Storage::disk('media')->url($this->imagen);
    }

    public function getGaleriaUrlsAttribute(): array {
        if (!isset($this->galeria) || !is_array($this->galeria)) return [];
        return collect($this->galeria)->map(fn($p) =>
        filter_var($p, FILTER_VALIDATE_URL) ? $p : Storage::disk('media')->url($p)
        )->all();
    }


}
