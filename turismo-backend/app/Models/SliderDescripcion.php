<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SliderDescripcion extends Model
{
    use HasFactory;

    protected $table = 'slider_descripciones';

    protected $fillable = [
        'slider_id',
        'titulo',
        'descripcion'
    ];
    protected $casts = [
        // ... lo que ya tienes
        'galeria' => 'array',
    ];

    public function slider(): BelongsTo
    {
        return $this->belongsTo(Slider::class);
    }
}
