<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Emprendedor extends Model
{
    use HasFactory;

    protected $table = 'emprendedores';

    protected $fillable = [
        'nombre',
        'tipo_servicio',
        'descripcion',
        'ubicacion',
        'telefono',
        'email',
        'pagina_web',
        'horario_atencion',
        'precio_rango',
        'metodos_pago',
        'capacidad_aforo',
        'numero_personas_atiende',
        'comentarios_resenas',
        'imagenes',
        'categoria',
        'certificaciones',
        'idiomas_hablados',
        'opciones_acceso',
        'facilidades_discapacidad',
        'asociacion_id',
        'estado'
    ];

    protected $casts = [
        'metodos_pago' => 'array',
        'imagenes' => 'array',
        'certificaciones' => 'array',
        'idiomas_hablados' => 'array',
        'opciones_acceso' => 'array',
        'facilidades_discapacidad' => 'boolean',
        'estado' => 'boolean',
        'galeria' => 'array',

    ];

    /**
     * Obtener la asociación a la que pertenece el emprendedor
     */
    public function asociacion(): BelongsTo
    {
        return $this->belongsTo(Asociacion::class);
    }

    /**
     * Obtener los usuarios administradores del emprendimiento
     */
    public function administradores()
    {
        return $this->belongsToMany(User::class, 'user_emprendedor')
                    ->withPivot('es_principal', 'rol')
                    ->withTimestamps();
    }

    /**
     * Obtener el administrador principal del emprendimiento
     */
    public function administradorPrincipal()
    {
        return $this->belongsToMany(User::class, 'user_emprendedor')
                    ->wherePivot('es_principal', true)
                    ->first();
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class);
    }

    public function reservas()
    {
        return $this->hasManyThrough(
            Reserva::class,
            ReservaServicio::class,
            'emprendedor_id', // Foreign key en reserva_servicios
            'id', // Foreign key en reservas
            'id', // Local key en emprendedores
            'reserva_id' // Local key en reserva_servicios
        )->distinct()->where('reservas.estado', '!=', 'en_carrito');
    }

    /**
     * Relación directa con los servicios reservados del emprendedor
     */
    public function reservaServicios()
    {
        return $this->hasMany(ReservaServicio::class, 'emprendedor_id');
    }
    /**
     * Reservas confirmadas del emprendedor
     */
    public function reservasConfirmadas()
    {
        return $this->reservas()->where('reservas.estado', 'confirmada');
    }

    /**
     * Reservas pendientes del emprendedor
     */
    public function reservasPendientes()
    {
        return $this->reservas()->where('reservas.estado', 'pendiente');
    }

    /**
     * Servicios reservados confirmados
     */
    public function serviciosReservadosConfirmados()
    {
        return $this->reservaServicios()
            ->whereIn('estado', ['confirmado', 'completado']);
    }

    /**
     * Servicios reservados pendientes
     */
    public function serviciosReservadosPendientes()
    {
        return $this->reservaServicios()
            ->where('estado', 'pendiente');
    }

    // Nuevas relaciones para sliders
    public function sliders(): HasMany
    {
        return $this->hasMany(Slider::class, 'entidad_id')
                    ->where('tipo_entidad', 'emprendedor')
                    ->orderBy('orden');
    }

    public function slidersPrincipales()
    {
        return $this->hasMany(Slider::class, 'entidad_id')
                    ->where('tipo_entidad', 'emprendedor')
                    ->where('es_principal', true)
                    ->orderBy('orden');
    }

    public function slidersSecundarios()
    {
        return $this->hasMany(Slider::class, 'entidad_id')
                    ->where('tipo_entidad', 'emprendedor')
                    ->where('es_principal', false)
                    ->with('descripcion')
                    ->orderBy('orden');
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class, 'id_emprendedor');
    }

    /**
     * RELACIONES ACTUALIZADAS PARA PLANES MÚLTIPLES
     */

    // Obtener los planes creados/administrados por este emprendedor (DEPRECATED)
    public function planes(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    // NUEVAS RELACIONES - Relación directa con la tabla pivote
    public function planEmprendedores(): HasMany
    {
        return $this->hasMany(PlanEmprendedor::class);
    }

    // Todos los planes donde participa este emprendedor
    public function planesParticipando(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_emprendedores')
                    ->withPivot([
                        'rol',
                        'es_organizador_principal',
                        'descripcion_participacion',
                        'porcentaje_ganancia'
                    ])
                    ->withTimestamps()
                    ->orderByPivot('es_organizador_principal', 'desc')
                    ->orderByPivot('created_at', 'desc');
    }

    // Planes donde es organizador principal
    public function planesOrganizando(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_emprendedores')
                    ->withPivot([
                        'rol',
                        'es_organizador_principal',
                        'descripcion_participacion',
                        'porcentaje_ganancia'
                    ])
                    ->withTimestamps()
                    ->wherePivot('es_organizador_principal', true)
                    ->orderByPivot('created_at', 'desc');
    }

    // Planes donde es organizador (principal o no)
    public function planesComoOrganizador(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_emprendedores')
                    ->withPivot([
                        'rol',
                        'es_organizador_principal',
                        'descripcion_participacion',
                        'porcentaje_ganancia'
                    ])
                    ->withTimestamps()
                    ->wherePivot('rol', 'organizador')
                    ->orderByPivot('es_organizador_principal', 'desc')
                    ->orderByPivot('created_at', 'desc');
    }

    // Planes donde es colaborador
    public function planesColaborando(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_emprendedores')
                    ->withPivot([
                        'rol',
                        'es_organizador_principal',
                        'descripcion_participacion',
                        'porcentaje_ganancia'
                    ])
                    ->withTimestamps()
                    ->wherePivot('rol', 'colaborador')
                    ->orderByPivot('created_at', 'desc');
    }

    // Planes activos donde participa
    public function planesActivosParticipando(): BelongsToMany
    {
        return $this->planesParticipando()
                    ->where('plans.estado', Plan::ESTADO_ACTIVO);
    }

    // Planes públicos donde participa
    public function planesPublicosParticipando(): BelongsToMany
    {
        return $this->planesParticipando()
                    ->where('plans.estado', Plan::ESTADO_ACTIVO)
                    ->where('plans.es_publico', true);
    }

    // Obtener todas las inscripciones a planes donde participa
    public function inscripcionesPlanesParticipando()
    {
        return PlanInscripcion::whereHas('plan.emprendedores', function($query) {
            $query->where('emprendedores.id', $this->id);
        });
    }

    // Obtener estadísticas actualizadas de planes
    public function getEstadisticasPlanesAttribute(): array
    {
        $planesParticipando = $this->planesParticipando;
        $planesOrganizando = $this->planesOrganizando;
        $planesColaborando = $this->planesColaborando;

        return [
            // Estadísticas generales
            'total_planes_participando' => $planesParticipando->count(),
            'planes_organizando' => $planesOrganizando->count(),
            'planes_colaborando' => $planesColaborando->count(),

            // Por estado
            'planes_activos_participando' => $planesParticipando->where('estado', Plan::ESTADO_ACTIVO)->count(),
            'planes_publicos_participando' => $this->planesPublicosParticipando()->count(),

            // Inscripciones
            'total_inscripciones' => $this->inscripcionesPlanesParticipando()->count(),
            'inscripciones_confirmadas' => $this->inscripcionesPlanesParticipando()->where('estado', 'confirmada')->count(),

            // Ingresos estimados
            'ingresos_estimados' => $this->inscripcionesPlanesParticipando()
                                        ->where('estado', 'confirmada')
                                        ->sum('precio_pagado') ?? 0,

            // Estadísticas legacy (mantener por compatibilidad)
            'total_planes' => $this->planes()->count(),
            'planes_activos' => $this->planes()->where('estado', Plan::ESTADO_ACTIVO)->count(),
            'planes_publicos' => $this->planes()->where('estado', Plan::ESTADO_ACTIVO)->where('es_publico', true)->count(),
        ];
    }

    // Verificar si tiene planes disponibles (actualizado)
    public function tienePlanesDisponibles(): bool
    {
        return $this->planesPublicosParticipando()
                   ->where('capacidad', '>', function($query) {
                       $query->selectRaw('COALESCE(SUM(numero_participantes), 0)')
                             ->from('plan_inscripciones')
                             ->whereColumn('plan_id', 'plans.id')
                             ->where('estado', 'confirmada');
                   })->exists();
    }

    // Métodos de utilidad para roles en planes
    public function esOrganizadorDePlan(int $planId): bool
    {
        return $this->planesParticipando()
                   ->where('plans.id', $planId)
                   ->wherePivot('rol', 'organizador')
                   ->exists();
    }

    public function esOrganizadorPrincipalDePlan(int $planId): bool
    {
        return $this->planesParticipando()
                   ->where('plans.id', $planId)
                   ->wherePivot('es_organizador_principal', true)
                   ->exists();
    }

    public function esColaboradorDePlan(int $planId): bool
    {
        return $this->planesParticipando()
                   ->where('plans.id', $planId)
                   ->wherePivot('rol', 'colaborador')
                   ->exists();
    }

    public function participaEnPlan(int $planId): bool
    {
        return $this->planesParticipando()
                   ->where('plans.id', $planId)
                   ->exists();
    }
}
