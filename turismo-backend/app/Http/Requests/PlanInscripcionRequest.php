<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PlanInscripcionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo usuarios autenticados pueden inscribirse
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plans,id',
            'numero_participantes' => 'required|integer|min:1|max:50',
            'notas_usuario' => 'nullable|string|max:1000',
            'requerimientos_especiales' => 'nullable|string|max:1000',
            'metodo_pago' => 'nullable|in:efectivo,transferencia,tarjeta,yape,plin',
            'comentarios_adicionales' => 'nullable|string|max:1000',
            'imagen_principal'     => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'imagenes_galeria'     => ['nullable','array','max:12'],
            'imagenes_galeria.*'   => ['image','mimes:jpg,jpeg,png,webp','max:5120'],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'El plan es obligatorio',
            'plan_id.exists' => 'El plan seleccionado no existe',
            'numero_participantes.required' => 'El número de participantes es obligatorio',
            'numero_participantes.integer' => 'El número de participantes debe ser un número entero',
            'numero_participantes.min' => 'Debe haber al menos 1 participante',
            'numero_participantes.max' => 'No se pueden inscribir más de 50 participantes a la vez',
            'notas_usuario.max' => 'Las notas no pueden exceder 1000 caracteres',
            'requerimientos_especiales.max' => 'Los requerimientos especiales no pueden exceder 1000 caracteres',
            'metodo_pago.in' => 'El método de pago debe ser: efectivo, transferencia, tarjeta, yape o plin',
            'comentarios_adicionales.max' => 'Los comentarios adicionales no pueden exceder 1000 caracteres',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $planId = $this->get('plan_id');
            $numeroParticipantes = $this->get('numero_participantes', 1);

            if ($planId) {
                // Verificar que el plan existe y está disponible
                $plan = \App\Models\Plan::find($planId);

                if (!$plan) {
                    $validator->errors()->add('plan_id', 'El plan seleccionado no existe');
                    return;
                }

                // Verificar que el plan está activo y es público
                if ($plan->estado !== \App\Models\Plan::ESTADO_ACTIVO) {
                    $validator->errors()->add('plan_id', 'El plan seleccionado no está disponible');
                }

                if (!$plan->es_publico) {
                    $validator->errors()->add('plan_id', 'El plan seleccionado no está disponible para inscripciones públicas');
                }

                // Verificar cupos disponibles
                $inscripcionesConfirmadas = $plan->inscripciones()
                                                ->where('estado', 'confirmada')
                                                ->sum('numero_participantes');

                $cuposDisponibles = $plan->capacidad - $inscripcionesConfirmadas;

                if ($numeroParticipantes > $cuposDisponibles) {
                    $validator->errors()->add('numero_participantes',
                        "Solo hay {$cuposDisponibles} cupos disponibles para este plan");
                }

                // Verificar que el usuario no esté ya inscrito
                $user = Auth::user();
                $inscripcionExistente = \App\Models\PlanInscripcion::where('plan_id', $planId)
                                                                  ->where('user_id', $user->id)
                                                                  ->whereIn('estado', ['pendiente', 'confirmada'])
                                                                  ->exists();

                if ($inscripcionExistente) {
                    $validator->errors()->add('plan_id', 'Ya tienes una inscripción activa para este plan');
                }

                // Verificar que el número de participantes no exceda límites del plan
                if ($plan->capacidad_maxima_por_inscripcion &&
                    $numeroParticipantes > $plan->capacidad_maxima_por_inscripcion) {
                    $validator->errors()->add('numero_participantes',
                        "Este plan permite máximo {$plan->capacidad_maxima_por_inscripcion} participantes por inscripción");
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
                'message' => 'Debes estar autenticado para inscribirte a un plan'
            ], Response::HTTP_UNAUTHORIZED)
        );
    }
}
