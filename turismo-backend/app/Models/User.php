<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'active',
        'foto_perfil',
        'google_id',
        'avatar',
        'country',
        'birth_date',
        'address',
        'gender',
        'preferred_language',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'birth_date' => 'date',
        'last_login' => 'datetime',
        'galeria' => 'array',
    ];

    protected $appends = [
        'foto_perfil_url',
    ];

    /**
     * Obtener los emprendimientos administrados por el usuario
     */
    public function emprendimientos()
    {
        return $this->belongsToMany(Emprendedor::class, 'user_emprendedor')
                    ->withPivot('es_principal', 'rol')
                    ->withTimestamps();
    }

    public function emprendedores()
    {
        return $this->emprendimientos();
    }

    /**
     * Verificar si el usuario administra algún emprendimiento
     */
    public function administraEmprendimientos()
    {
        return $this->emprendimientos()->exists();
    }

    /**
     * NUEVAS RELACIONES PARA PLANES
     */

    // Planes creados por el usuario
    public function planesCreados()
    {
        return $this->hasMany(Plan::class, 'creado_por_usuario_id');
    }

    // Inscripciones del usuario a planes
    public function inscripciones()
    {
        return $this->hasMany(PlanInscripcion::class, 'user_id');
    }

    // Planes a los que está inscrito el usuario
    public function planesInscritos()
    {
        return $this->belongsToMany(Plan::class, 'plan_inscripciones', 'user_id', 'plan_id')
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

    // Inscripciones activas (confirmadas o en progreso)
    public function inscripcionesActivas()
    {
        return $this->inscripciones()
                    ->whereIn('estado', ['confirmada', 'en_progreso']);
    }

    // Inscripciones pendientes de confirmación
    public function inscripcionesPendientes()
    {
        return $this->inscripciones()
                    ->where('estado', 'pendiente');
    }

    // Próximas inscripciones (que van a empezar pronto)
    public function proximasInscripciones($dias = 30)
    {
        return $this->inscripciones()
                    ->with(['plan:id,nombre,duracion_dias'])
                    ->where('estado', 'confirmada')
                    ->where('fecha_inicio_plan', '>=', now())
                    ->where('fecha_inicio_plan', '<=', now()->addDays($dias))
                    ->orderBy('fecha_inicio_plan');
    }

    // Inscripciones en progreso (están sucediendo ahora)
    public function inscripcionesEnProgreso()
    {
        return $this->inscripciones()
                    ->with(['plan:id,nombre,duracion_dias'])
                    ->where('estado', 'confirmada')
                    ->where('fecha_inicio_plan', '<=', now())
                    ->where('fecha_fin_plan', '>=', now());
    }

    // Obtener todos los planes relacionados al usuario (creados + administrados por emprendimientos)
    public function todosLosPlanesRelacionados()
    {
        $planesCreados = $this->planesCreados()->pluck('id');

        $planesEmprendimientos = Plan::whereHas('emprendedor.administradores', function($query) {
            $query->where('users.id', $this->id);
        })->pluck('id');

        $todosIds = $planesCreados->merge($planesEmprendimientos)->unique();

        return Plan::whereIn('id', $todosIds);
    }

    // Verificar si el usuario puede gestionar un plan específico
    public function puedeGestionarPlan($planId): bool
    {
        $plan = Plan::find($planId);

        if (!$plan) {
            return false;
        }

        // Es el creador del plan
        if ($plan->creado_por_usuario_id === $this->id) {
            return true;
        }

        // Es administrador del emprendimiento asociado
        if ($plan->emprendedor_id &&
            $plan->emprendedor->administradores()->where('users.id', $this->id)->exists()) {
            return true;
        }

        // Es admin del sistema
        if ($this->hasRole('admin')) {
            return true;
        }

        return false;
    }

    // Obtener estadísticas de planes del usuario
    public function getEstadisticasPlanesUsuarioAttribute(): array
    {
        return [
            'planes_creados' => $this->planesCreados()->count(),
            'planes_activos_creados' => $this->planesCreados()->activos()->count(),
            'total_inscripciones' => $this->inscripciones()->count(),
            'inscripciones_confirmadas' => $this->inscripcionesActivas()->count(),
            'inscripciones_pendientes' => $this->inscripcionesPendientes()->count(),
            'proximas_inscripciones' => $this->proximasInscripciones(7)->count(),
            'inscripciones_en_progreso' => $this->inscripcionesEnProgreso()->count(),
            'total_gastado' => $this->inscripcionesActivas()->sum('precio_pagado') ?? 0,
        ];
    }

    // Verificar si tiene inscripciones próximas
    public function tieneInscripcionesProximas($dias = 7): bool
    {
        return $this->proximasInscripciones($dias)->exists();
    }

    // Verificar si tiene inscripciones en progreso
    public function tieneInscripcionesEnProgreso(): bool
    {
        return $this->inscripcionesEnProgreso()->exists();
    }

    /**
     * Obtener la URL completa de la foto de perfil
     */
    public function getFotoPerfilUrlAttribute() {
        if (!$this->foto_perfil && !$this->avatar) return null;
        if ($this->foto_perfil) {
            return filter_var($this->foto_perfil, FILTER_VALIDATE_URL)
                ? $this->foto_perfil
                : url(Storage::disk('media')->url($this->foto_perfil));
        }
        return $this->avatar;
    }

    /**
     * Verificar si el usuario se registró mediante Google
     */
    public function registeredWithGoogle()
    {
        return $this->google_id !== null;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }
}
