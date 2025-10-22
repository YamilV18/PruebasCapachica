<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PlanEmprendedorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        $planId = $this->route('plan');

        if (!$planId) {
            return false;
        }

        $plan = \App\Models\Plan::find($planId);

        if (!$plan) {
            return false;
        }

        // Admins siempre pueden gestionar
        if ($user->hasRole('admin')) {
            return true;
        }

        // Es el creador del plan
        if ($plan->creado_por_usuario_id === $user->id) {
            return true;
        }

        // Es organizador principal de algún emprendimiento en el plan
        $emprendimientosUsuario = $user->emprendimientos()->pluck('emprendedores.id');

        return $plan->emprendedores()
                   ->whereIn('emprendedores.id', $emprendimientosUsuario)
                   ->wherePivot('es_organizador_principal', true)
                   ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'emprendedor_id' => 'required|integer|exists:emprendedores,id',
            'rol' => 'required|in:organizador,colaborador',
            'es_organizador_principal' => 'nullable|boolean',
            'descripcion_participacion' => 'nullable|string|max:500',
            'porcentaje_ganancia' => 'nullable|numeric|min:0|max:100',
            'imagen_principal'     => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'imagenes_galeria'     => ['nullable','array','max:12'],
            'imagenes_galeria.*'   => ['image','mimes:jpg,jpeg,png,webp','max:5120'],
        ];

        // Para actualizaciones, hacer emprendedor_id opcional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['emprendedor_id'] = 'sometimes|integer|exists:emprendedores,id';
            $rules['rol'] = 'sometimes|in:organizador,colaborador';
        }

        return $rules;
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'emprendedor_id.required' => 'El emprendedor es obligatorio',
            'emprendedor_id.exists' => 'El emprendedor seleccionado no existe',
            'rol.required' => 'El rol es obligatorio',
            'rol.in' => 'El rol debe ser: organizador o colaborador',
            'es_organizador_principal.boolean' => 'El campo organizador principal debe ser verdadero o falso',
            'descripcion_participacion.max' => 'La descripción de participación no puede exceder 500 caracteres',
            'porcentaje_ganancia.numeric' => 'El porcentaje de ganancia debe ser un número',
            'porcentaje_ganancia.min' => 'El porcentaje de ganancia no puede ser negativo',
            'porcentaje_ganancia.max' => 'El porcentaje de ganancia no puede exceder 100%',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $planId = $this->route('plan');
            $emprendedorId = $this->get('emprendedor_id');
            $esOrganizadorPrincipal = $this->get('es_organizador_principal', false);
            $user = Auth::user();

            if (!$planId || !$emprendedorId) {
                return;
            }

            $plan = \App\Models\Plan::find($planId);

            if (!$plan) {
                $validator->errors()->add('plan', 'El plan no existe');
                return;
            }

            // Para creación, verificar que el emprendedor no esté ya en el plan
            if ($this->isMethod('POST')) {
                if ($plan->tieneEmprendedor($emprendedorId)) {
                    $validator->errors()->add('emprendedor_id',
                        'Este emprendedor ya participa en el plan');
                    return;
                }
            }

            // Para actualización, verificar que el emprendedor esté en el plan
            if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                $emprendedorActualId = $this->route('emprendedor');

                if ($emprendedorActualId && !$plan->tieneEmprendedor($emprendedorActualId)) {
                    $validator->errors()->add('emprendedor_id',
                        'El emprendedor no participa en este plan');
                    return;
                }
            }

            // Verificar permisos del usuario sobre el emprendedor
            if (!$user->hasRole('admin')) {
                $emprendimientosUsuario = $user->emprendimientos()->pluck('emprendedores.id')->toArray();

                if (!in_array($emprendedorId, $emprendimientosUsuario)) {
                    $validator->errors()->add('emprendedor_id',
                        'No tienes permisos para gestionar este emprendimiento');
                    return;
                }
            }

            // Si se está asignando como organizador principal, verificar restricciones
            if ($esOrganizadorPrincipal) {
                // Solo puede haber un organizador principal
                $organizadorPrincipalActual = $plan->emprendedores()
                                                  ->wherePivot('es_organizador_principal', true)
                                                  ->first();

                // Para actualización, excluir el emprendedor actual de la verificación
                if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                    $emprendedorActualId = $this->route('emprendedor');

                    if ($organizadorPrincipalActual &&
                        $organizadorPrincipalActual->id != $emprendedorActualId) {
                        $validator->errors()->add('es_organizador_principal',
                            'Ya existe un organizador principal para este plan. Primero debe cambiarlo.');
                    }
                } else {
                    // Para creación
                    if ($organizadorPrincipalActual) {
                        $validator->errors()->add('es_organizador_principal',
                            'Ya existe un organizador principal para este plan');
                    }
                }

                // Si es organizador principal, debe ser organizador
                if ($this->get('rol') !== 'organizador') {
                    $validator->errors()->add('rol',
                        'El organizador principal debe tener rol de organizador');
                }
            }

            // Validar que la suma de porcentajes no exceda 100%
            if ($this->has('porcentaje_ganancia')) {
                $porcentajeNuevo = $this->get('porcentaje_ganancia', 0);

                // Obtener la suma actual de porcentajes
                $porcentajeActual = $plan->emprendedores()->sum('porcentaje_ganancia') ?? 0;

                // Para actualización, restar el porcentaje actual del emprendedor
                if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                    $emprendedorActualId = $this->route('emprendedor');
                    $emprendedorActual = $plan->emprendedores()->find($emprendedorActualId);

                    if ($emprendedorActual) {
                        $porcentajeActual -= $emprendedorActual->pivot->porcentaje_ganancia ?? 0;
                    }
                }

                if (($porcentajeActual + $porcentajeNuevo) > 100) {
                    $disponible = 100 - $porcentajeActual;
                    $validator->errors()->add('porcentaje_ganancia',
                        "El porcentaje máximo disponible es {$disponible}%");
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'No tienes permisos para gestionar emprendedores en este plan'
            ], Response::HTTP_FORBIDDEN)
        );
    }
}
