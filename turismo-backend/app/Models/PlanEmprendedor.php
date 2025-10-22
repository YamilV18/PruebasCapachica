<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEmprendedor extends Model
{
    use HasFactory;

    protected $table = 'plan_emprendedores';

    protected $fillable = [
        'plan_id',
        'emprendedor_id',
        'rol',
        'es_organizador_principal',
        'descripcion_participacion',
        'porcentaje_ganancia',
    ];

    protected $casts = [
        'es_organizador_principal' => 'boolean',
        'porcentaje_ganancia' => 'decimal:2',
        'galeria' => 'array',
    ];

    // Roles disponibles
    const ROL_ORGANIZADOR = 'organizador';
    const ROL_COLABORADOR = 'colaborador';

    // Relación con el plan
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Relación con el emprendedor
    public function emprendedor(): BelongsTo
    {
        return $this->belongsTo(Emprendedor::class);
    }

    // Verificar si es el organizador principal
    public function esOrganizadorPrincipal(): bool
    {
        return $this->es_organizador_principal;
    }

    // Verificar si es organizador (principal o no)
    public function esOrganizador(): bool
    {
        return $this->rol === self::ROL_ORGANIZADOR;
    }

    // Verificar si es colaborador
    public function esColaborador(): bool
    {
        return $this->rol === self::ROL_COLABORADOR;
    }

    // Scopes
    public function scopeOrganizadores($query)
    {
        return $query->where('rol', self::ROL_ORGANIZADOR);
    }

    public function scopeColaboradores($query)
    {
        return $query->where('rol', self::ROL_COLABORADOR);
    }

    public function scopeOrganizadorPrincipal($query)
    {
        return $query->where('es_organizador_principal', true);
    }
}
