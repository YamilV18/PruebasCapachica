<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'icono_url',
    ];
    protected $casts = [
        // ... lo que ya tienes
        'galeria' => 'array',
    ];

    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'categoria_servicio')
            ->withTimestamps();
    }

}
