<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'que_incluye',
        'capacidad',
        'duracion_dias',
        'es_publico',
        'estado',
        'creado_por_usuario_id',
        'emprendedor_id', // MANTENER por compatibilidad, pero deprecado
        'precio_total',
        'dificultad',
        'requerimientos',
        'que_llevar',
        'imagen_principal',
        'imagenes_galeria',
    ];

    protected $casts = [
        'es_publico' => 'boolean',
        'precio_total' => 'decimal:2',
        'imagenes_galeria'=> 'array',
        'galeria' => 'array',
    ];

    protected $appends = [
        'imagen_principal_url',
        'imagenes_galeria_urls',
        'cupos_disponibles',
        'organizador_principal',
        'total_emprendedores'
    ];

    // Estados del plan
    const ESTADO_ACTIVO = 'activo';
    const ESTADO_INACTIVO = 'inactivo';
    const ESTADO_BORRADOR = 'borrador';

    // Niveles de dificultad
    const DIFICULTAD_FACIL = 'facil';
    const DIFICULTAD_MODERADO = 'moderado';
    const DIFICULTAD_DIFICIL = 'dificil';

    // El usuario que creó el plan
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_usuario_id');
    }

    // El emprendedor asociado al plan (DEPRECATED - mantener por compatibilidad)
    public function emprendedor(): BelongsTo
    {
        return $this->belongsTo(Emprendedor::class);
    }

    // NUEVAS RELACIONES PARA MÚLTIPLES EMPRENDEDORES

    // Relación directa con la tabla pivote plan_emprendedores
    public function planEmprendedores(): HasMany
    {
        return $this->hasMany(PlanEmprendedor::class);
    }

    // Todos los emprendedores del plan
    public function emprendedores(): BelongsToMany
    {
        return $this->belongsToMany(Emprendedor::class, 'plan_emprendedores')
                    ->withPivot([
                        'rol',
                        'es_organizador_principal',
                        'descripcion_participacion',
                        'porcentaje_ganancia'
                    ])
                    ->withTimestamps()
                    ->orderByPivot('es_organizador_principal', 'desc')
                    ->orderByPivot('rol', 'asc');
    }

    // Solo los emprendedores organizadores
    public function emprendedoresOrganizadores(): BelongsToMany
    {
        return $this->belongsToMany(Emprendedor::class, 'plan_emprendedores')
                    ->withPivot([
                        'rol',
                        'es_organizador_principal',
                        'descripcion_participacion',
                        'porcentaje_ganancia'
                    ])
                    ->withTimestamps()
                    ->wherePivot('rol', 'organizador')
                    ->orderByPivot('es_organizador_principal', 'desc');
    }

    // Solo los emprendedores colaboradores
    public function emprendedoresColaboradores(): BelongsToMany
    {
        return $this->belongsToMany(Emprendedor::class, 'plan_emprendedores')
                    ->withPivot([
                        'rol',
                        'es_organizador_principal',
                        'descripcion_participacion',
                        'porcentaje_ganancia'
                    ])
                    ->withTimestamps()
                    ->wherePivot('rol', 'colaborador');
    }

    // Obtener el emprendedor organizador principal
    public function getOrganizadorPrincipalAttribute(): ?Emprendedor
    {
        return $this->emprendedores()
                   ->wherePivot('es_organizador_principal', true)
                   ->first();
    }

    // Obtener el total de emprendedores participantes
    public function getTotalEmprendedoresAttribute(): int
    {
        return $this->emprendedores()->count();
    }

    // Los días del plan
    public function dias(): HasMany
    {
        return $this->hasMany(PlanDia::class)->orderBy('numero_dia');
    }

    // Los servicios incluidos en el plan (relación legacy, mantener por compatibilidad)
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'plan_services')
                    ->withPivot(['fecha_inicio', 'fecha_fin', 'hora_inicio', 'hora_fin', 'duracion_minutos', 'notas', 'orden'])
                    ->withTimestamps()
                    ->orderBy('pivot_orden');
    }

    // Las inscripciones de usuarios al plan
    public function inscripciones(): HasMany
    {
        return $this->hasMany(PlanInscripcion::class);
    }

    // Los usuarios inscritos al plan
    public function usuariosInscritos(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'plan_inscripciones')
                    ->withPivot([
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
                        'comentarios_adicionales'
                    ])
                    ->withTimestamps();
    }

    // Obtener todos los servicios únicos del plan a través de los días
    public function todosLosServicios()
    {
        return Servicio::whereHas('planDias.plan', function($query) {
            $query->where('id', $this->id);
        })->distinct();
    }

    // Verificar si el plan tiene cupos disponibles
    public function tieneCuposDisponibles(): bool
    {
        $inscripcionesConfirmadas = $this->inscripciones()
                                         ->where('estado', 'confirmada')
                                         ->sum('numero_participantes');

        return $inscripcionesConfirmadas < $this->capacidad;
    }

    // Obtener el número de cupos disponibles
    public function getCuposDisponiblesAttribute(): int
    {
        $inscripcionesConfirmadas = $this->inscripciones()
                                         ->where('estado', 'confirmada')
                                         ->sum('numero_participantes');

        return max(0, $this->capacidad - $inscripcionesConfirmadas);
    }

    // NUEVOS MÉTODOS PARA GESTIÓN DE EMPRENDEDORES

    // Verificar si un emprendedor está en el plan
    public function tieneEmprendedor(int $emprendedorId): bool
    {
        return $this->emprendedores()->where('emprendedores.id', $emprendedorId)->exists();
    }

    // Verificar si un emprendedor es organizador del plan
    public function esOrganizador(int $emprendedorId): bool
    {
        return $this->emprendedores()
                   ->where('emprendedores.id', $emprendedorId)
                   ->wherePivot('rol', 'organizador')
                   ->exists();
    }

    // Verificar si un emprendedor es el organizador principal
    public function esOrganizadorPrincipal(int $emprendedorId): bool
    {
        return $this->emprendedores()
                   ->where('emprendedores.id', $emprendedorId)
                   ->wherePivot('es_organizador_principal', true)
                   ->exists();
    }

    // Agregar emprendedor al plan
    public function agregarEmprendedor(int $emprendedorId, array $datos = []): void
    {
        $defaults = [
            'rol' => 'colaborador',
            'es_organizador_principal' => false,
            'descripcion_participacion' => null,
            'porcentaje_ganancia' => null,
        ];

        $this->emprendedores()->attach($emprendedorId, array_merge($defaults, $datos));
    }

    // Actualizar datos del emprendedor en el plan
    public function actualizarEmprendedor(int $emprendedorId, array $datos): void
    {
        $this->emprendedores()->updateExistingPivot($emprendedorId, $datos);
    }

    // Remover emprendedor del plan
    public function removerEmprendedor(int $emprendedorId): void
    {
        $this->emprendedores()->detach($emprendedorId);
    }

    // Cambiar organizador principal
    public function cambiarOrganizadorPrincipal(int $nuevoOrganizadorId): bool
    {
        // Verificar que el nuevo organizador esté en el plan
        if (!$this->tieneEmprendedor($nuevoOrganizadorId)) {
            return false;
        }

        // Quitar el flag de principal a todos
        $this->emprendedores()->updateExistingPivot(
            $this->emprendedores()->pluck('emprendedores.id')->toArray(),
            ['es_organizador_principal' => false]
        );

        // Asignar el nuevo principal
        $this->emprendedores()->updateExistingPivot($nuevoOrganizadorId, [
            'es_organizador_principal' => true,
            'rol' => 'organizador'
        ]);

        return true;
    }

    // Obtener URL de imagen principal
    public function getImagenPrincipalUrlAttribute(): ?string {
        if (!$this->imagen_principal) return null;
        if (filter_var($this->imagen_principal, FILTER_VALIDATE_URL)) return $this->imagen_principal;
        return Storage::disk('media')->url($this->imagen_principal);
    }

    public function getImagenesGaleriaUrlsAttribute(): array {
        if (!$this->imagenes_galeria || !is_array($this->imagenes_galeria)) return [];
        return collect($this->imagenes_galeria)->map(
            fn($p) => filter_var($p, FILTER_VALIDATE_URL) ? $p : Storage::disk('media')->url($p)
        )->all();
    }

    // MÉTODO CORREGIDO - Calcular precio total basado en servicios de los días
    public function calcularPrecioTotal(): float
    {
        $precio = 0;

        foreach ($this->dias as $dia) {
            foreach ($dia->servicios as $servicio) {
                // Usar precio_referencial en lugar de precio
                $precio += $servicio->precio_referencial ?? 0;

                // Sumar precio adicional si existe
                $precio += $servicio->pivot->precio_adicional ?? 0;
            }
        }

        return $precio;
    }

    // Obtener resumen del plan
    public function getResumenAttribute(): array
    {
        return [
            'total_dias' => $this->duracion_dias,
            'total_servicios' => $this->todosLosServicios()->count(),
            'precio_calculado' => $this->calcularPrecioTotal(),
            'inscripciones_activas' => $this->inscripciones()->where('estado', 'confirmada')->count(),
            'cupos_disponibles' => $this->cupos_disponibles,
            'total_emprendedores' => $this->total_emprendedores,
            'organizador_principal' => $this->organizador_principal?->nombre,
        ];
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    public function scopePublicos($query)
    {
        return $query->where('es_publico', true);
    }

    // SCOPE ACTUALIZADO - buscar por cualquier emprendedor del plan
    public function scopeDeEmprendedor($query, $emprendedorId)
    {
        return $query->whereHas('emprendedores', function($q) use ($emprendedorId) {
            $q->where('emprendedores.id', $emprendedorId);
        });
    }

    // NUEVO SCOPE - buscar por organizador principal
    public function scopeOrganizadoPor($query, $emprendedorId)
    {
        return $query->whereHas('emprendedores', function($q) use ($emprendedorId) {
            $q->where('emprendedores.id', $emprendedorId)
            ->where('plan_emprendedores.es_organizador_principal', true);
        });
    }

    // NUEVO SCOPE - buscar donde el emprendedor es colaborador
    public function scopeColaborandoEn($query, $emprendedorId)
    {
        return $query->whereHas('emprendedores', function($q) use ($emprendedorId) {
            $q->where('emprendedores.id', $emprendedorId)
            ->where('plan_emprendedores.rol', 'colaborador');
        });
    }

    // SCOPE CORREGIDO - Compatible con PostgreSQL
    public function scopeConCuposDisponibles($query)
    {
        return $query->where('capacidad', '>', function($subquery) {
            $subquery->selectRaw('COALESCE(SUM(numero_participantes), 0)')
                    ->from('plan_inscripciones')
                    ->whereColumn('plan_id', 'plans.id')
                    ->where('estado', 'confirmada');
        });
    }

    public function scopePorDificultad($query, $dificultad)
    {
        return $query->where('dificultad', $dificultad);
    }

    public function scopeBuscar($query, $termino)
    {
        return $query->where(function($q) use ($termino) {
            $q->where('nombre', 'ILIKE', "%{$termino}%") // ILIKE para PostgreSQL (case insensitive)
            ->orWhere('descripcion', 'ILIKE', "%{$termino}%")
            ->orWhere('que_incluye', 'ILIKE', "%{$termino}%");
        });
    }

    /**
     * Obtener el emprendedor que es organizador principal de este plan
     */
    public function organizadorPrincipal()
    {
        return $this->emprendedores()
                    ->wherePivot('es_organizador_principal', true)
                    ->limit(1);
    }

    // También puedes crear un método para obtener directamente el emprendedor
    public function getOrganizadorPrincipalEmprendedor()
    {
        return $this->emprendedores()
                    ->wherePivot('es_organizador_principal', true)
                    ->first();
    }
}
