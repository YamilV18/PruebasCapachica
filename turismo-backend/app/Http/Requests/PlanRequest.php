<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        
        // Para crear un nuevo plan
        if ($this->isMethod('POST')) {
            return $user && (
                $user->hasPermissionTo('plan_create') || 
                $user->hasRole('admin') ||
                $user->emprendimientos()->exists()
            );
        }
        
        // Para actualizar un plan existente
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            // Si tiene permiso general de actualización, está autorizado
            if ($user->hasPermissionTo('plan_update') || $user->hasRole('admin')) {
                return true;
            }
            
            // Verificar si es el creador del plan o administrador del emprendimiento asociado
            $planId = $this->route('id') ?? $this->route('plan');
            
            if ($planId) {
                $plan = \App\Models\Plan::find($planId);
                
                if ($plan) {
                    // Es el creador del plan
                    if ($plan->creado_por_usuario_id === $user->id) {
                        return true;
                    }
                    
                    // ACTUALIZADO: Verificar si es organizador principal de cualquier emprendimiento en el plan
                    $emprendimientosUsuario = $user->emprendimientos()->pluck('emprendedores.id');
                    if ($plan->emprendedores()
                            ->whereIn('emprendedores.id', $emprendimientosUsuario)
                            ->wherePivot('es_organizador_principal', true)
                            ->exists()) {
                        return true;
                    }
                    
                    // Legacy: Es administrador del emprendimiento asociado
                    if ($plan->emprendedor_id && 
                        $plan->emprendedor->administradores()->where('users.id', $user->id)->exists()) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'que_incluye' => 'nullable|string',
            'capacidad' => 'required|integer|min:1|max:1000',
            'duracion_dias' => 'required|integer|min:1|max:365',
            'es_publico' => 'nullable|boolean',
            'estado' => 'nullable|in:activo,inactivo,borrador',
            'emprendedor_id' => 'nullable|exists:emprendedores,id', // Legacy - mantener por compatibilidad
            'precio_total' => 'nullable|numeric|min:0|max:999999.99',
            'dificultad' => 'nullable|in:facil,moderado,dificil',
            'requerimientos' => 'nullable|string',
            'que_llevar' => 'nullable|string',
            
            // NUEVAS VALIDACIONES PARA MÚLTIPLES EMPRENDEDORES
            'emprendedores' => 'nullable|array|min:1|max:10',
            'emprendedores.*.emprendedor_id' => 'required|exists:emprendedores,id',
            'emprendedores.*.rol' => 'required|in:organizador,colaborador',
            'emprendedores.*.es_organizador_principal' => 'nullable|boolean',
            'emprendedores.*.descripcion_participacion' => 'nullable|string|max:500',
            'emprendedores.*.porcentaje_ganancia' => 'nullable|numeric|min:0|max:100',
            
            // Validaciones para imágenes
            'imagen_principal' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:12288',
            'imagenes_galeria' => 'nullable|array|max:10',
            'imagenes_galeria.*' => 'file|mimes:jpg,jpeg,png,gif,webp|max:12288',
            
            // Validaciones para días
            'dias' => 'nullable|array|min:1',
            'dias.*.numero_dia' => 'required|integer|min:1',
            'dias.*.titulo' => 'required|string|max:255',
            'dias.*.descripcion' => 'required|string',
            'dias.*.hora_inicio' => 'nullable|date_format:H:i',
            'dias.*.hora_fin' => 'nullable|date_format:H:i|after:dias.*.hora_inicio',
            'dias.*.duracion_estimada_minutos' => 'nullable|integer|min:0|max:1440',
            'dias.*.notas_adicionales' => 'nullable|string',
            'dias.*.orden' => 'nullable|integer|min:1',
            
            // Validaciones para servicios de cada día
            'dias.*.servicios' => 'nullable|array',
            'dias.*.servicios.*.servicio_id' => 'required|exists:servicios,id',
            'dias.*.servicios.*.hora_inicio' => 'nullable|date_format:H:i',
            'dias.*.servicios.*.hora_fin' => 'nullable|date_format:H:i|after:dias.*.servicios.*.hora_inicio',
            'dias.*.servicios.*.duracion_minutos' => 'nullable|integer|min:0|max:1440',
            'dias.*.servicios.*.notas' => 'nullable|string',
            'dias.*.servicios.*.orden' => 'nullable|integer|min:1',
            'dias.*.servicios.*.es_opcional' => 'nullable|boolean',
            'dias.*.servicios.*.precio_adicional' => 'nullable|numeric|min:0|max:99999.99',
        ];
        
        // Para actualizaciones, hacer campos opcionales
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['nombre'] = 'sometimes|' . $rules['nombre'];
            $rules['descripcion'] = 'sometimes|' . $rules['descripcion'];
            $rules['capacidad'] = 'sometimes|' . $rules['capacidad'];
            $rules['duracion_dias'] = 'sometimes|' . $rules['duracion_dias'];
        }
        
        return $rules;
    }
    
    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del plan es obligatorio',
            'descripcion.required' => 'La descripción del plan es obligatoria',
            'capacidad.required' => 'La capacidad del plan es obligatoria',
            'capacidad.min' => 'La capacidad debe ser al menos 1 persona',
            'capacidad.max' => 'La capacidad no puede exceder 1000 personas',
            'duracion_dias.required' => 'La duración en días es obligatoria',
            'duracion_dias.min' => 'La duración debe ser al menos 1 día',
            'duracion_dias.max' => 'La duración no puede exceder 365 días',
            'precio_total.numeric' => 'El precio debe ser un valor numérico',
            'precio_total.min' => 'El precio no puede ser negativo',
            'emprendedor_id.exists' => 'El emprendedor seleccionado no existe',
            'dificultad.in' => 'La dificultad debe ser: fácil, moderado o difícil',
            'estado.in' => 'El estado debe ser: activo, inactivo o borrador',
            
            // NUEVOS MENSAJES PARA EMPRENDEDORES
            'emprendedores.array' => 'Los emprendedores deben ser un arreglo',
            'emprendedores.min' => 'Debe agregar al menos un emprendedor al plan',
            'emprendedores.max' => 'No se pueden agregar más de 10 emprendedores al plan',
            'emprendedores.*.emprendedor_id.required' => 'El emprendedor es obligatorio',
            'emprendedores.*.emprendedor_id.exists' => 'El emprendedor seleccionado no existe',
            'emprendedores.*.rol.required' => 'El rol del emprendedor es obligatorio',
            'emprendedores.*.rol.in' => 'El rol debe ser: organizador o colaborador',
            'emprendedores.*.descripcion_participacion.max' => 'La descripción de participación no puede exceder 500 caracteres',
            'emprendedores.*.porcentaje_ganancia.numeric' => 'El porcentaje de ganancia debe ser un número',
            'emprendedores.*.porcentaje_ganancia.min' => 'El porcentaje de ganancia no puede ser negativo',
            'emprendedores.*.porcentaje_ganancia.max' => 'El porcentaje de ganancia no puede exceder 100%',
            
            // Mensajes para imágenes
            'imagen_principal.file' => 'La imagen principal debe ser un archivo válido',
            'imagen_principal.mimes' => 'La imagen principal debe ser jpg, jpeg, png, gif o webp',
            'imagen_principal.max' => 'La imagen principal no puede exceder 5MB',
            'imagenes_galeria.array' => 'Las imágenes de galería deben ser un arreglo',
            'imagenes_galeria.max' => 'No puedes subir más de 10 imágenes en la galería',
            'imagenes_galeria.*.file' => 'Cada imagen de galería debe ser un archivo válido',
            'imagenes_galeria.*.mimes' => 'Las imágenes de galería deben ser jpg, jpeg, png, gif o webp',
            'imagenes_galeria.*.max' => 'Cada imagen de galería no puede exceder 5MB',
            
            // Mensajes para días
            'dias.array' => 'Los días deben ser un arreglo',
            'dias.min' => 'Debe agregar al menos un día al plan',
            'dias.*.numero_dia.required' => 'El número de día es obligatorio',
            'dias.*.numero_dia.integer' => 'El número de día debe ser un entero',
            'dias.*.numero_dia.min' => 'El número de día debe ser al menos 1',
            'dias.*.titulo.required' => 'El título del día es obligatorio',
            'dias.*.titulo.max' => 'El título del día no puede exceder 255 caracteres',
            'dias.*.descripcion.required' => 'La descripción del día es obligatoria',
            'dias.*.hora_inicio.date_format' => 'La hora de inicio debe tener formato HH:MM',
            'dias.*.hora_fin.date_format' => 'La hora de fin debe tener formato HH:MM',
            'dias.*.hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
            'dias.*.duracion_estimada_minutos.integer' => 'La duración estimada debe ser un número entero',
            'dias.*.duracion_estimada_minutos.min' => 'La duración estimada no puede ser negativa',
            'dias.*.duracion_estimada_minutos.max' => 'La duración estimada no puede exceder 1440 minutos (24 horas)',
            
            // Mensajes para servicios
            'dias.*.servicios.*.servicio_id.required' => 'El servicio es obligatorio',
            'dias.*.servicios.*.servicio_id.exists' => 'El servicio seleccionado no existe',
            'dias.*.servicios.*.hora_inicio.date_format' => 'La hora de inicio del servicio debe tener formato HH:MM',
            'dias.*.servicios.*.hora_fin.date_format' => 'La hora de fin del servicio debe tener formato HH:MM',
            'dias.*.servicios.*.hora_fin.after' => 'La hora de fin del servicio debe ser posterior a la hora de inicio',
            'dias.*.servicios.*.duracion_minutos.integer' => 'La duración del servicio debe ser un número entero',
            'dias.*.servicios.*.duracion_minutos.min' => 'La duración del servicio no puede ser negativa',
            'dias.*.servicios.*.duracion_minutos.max' => 'La duración del servicio no puede exceder 1440 minutos',
            'dias.*.servicios.*.precio_adicional.numeric' => 'El precio adicional debe ser un valor numérico',
            'dias.*.servicios.*.precio_adicional.min' => 'El precio adicional no puede ser negativo',
        ];
    }
    
    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que el número de días coincida con la duración
            if ($this->has('dias') && $this->has('duracion_dias')) {
                $diasData = $this->get('dias', []);
                $duracionDias = $this->get('duracion_dias');
                
                if (count($diasData) != $duracionDias) {
                    $validator->errors()->add('dias', 
                        'El número de días debe coincidir con la duración del plan (' . $duracionDias . ' días)');
                }
                
                // Validar que los números de día sean consecutivos
                $numerosDia = array_column($diasData, 'numero_dia');
                sort($numerosDia);
                
                for ($i = 1; $i <= $duracionDias; $i++) {
                    if (!in_array($i, $numerosDia)) {
                        $validator->errors()->add('dias', 
                            'Falta el día ' . $i . ' en la planificación');
                        break;
                    }
                }
            }
            
            // NUEVAS VALIDACIONES PARA EMPRENDEDORES
            if ($this->has('emprendedores')) {
                $emprendedoresData = $this->get('emprendedores', []);
                $user = Auth::user();
                
                // Validar que haya exactamente un organizador principal
                $organizadoresPrincipales = array_filter($emprendedoresData, function($emp) {
                    return isset($emp['es_organizador_principal']) && $emp['es_organizador_principal'];
                });
                
                if (count($organizadoresPrincipales) === 0) {
                    $validator->errors()->add('emprendedores', 
                        'Debe haber al menos un organizador principal');
                } elseif (count($organizadoresPrincipales) > 1) {
                    $validator->errors()->add('emprendedores', 
                        'Solo puede haber un organizador principal');
                }
                
                // Validar que no haya emprendedores duplicados
                $emprendedorIds = array_column($emprendedoresData, 'emprendedor_id');
                if (count($emprendedorIds) !== count(array_unique($emprendedorIds))) {
                    $validator->errors()->add('emprendedores', 
                        'No se pueden agregar emprendedores duplicados');
                }
                
                // Validar que el usuario tenga acceso a los emprendedores
                if (!$user->hasRole('admin')) {
                    $emprendimientosUsuario = $user->emprendimientos()->pluck('emprendedores.id')->toArray();
                    
                    foreach ($emprendedoresData as $index => $emprendedorData) {
                        $emprendedorId = $emprendedorData['emprendedor_id'] ?? null;
                        
                        if ($emprendedorId && !in_array($emprendedorId, $emprendimientosUsuario)) {
                            $validator->errors()->add("emprendedores.{$index}.emprendedor_id", 
                                'No tienes permisos para agregar este emprendimiento al plan');
                        }
                    }
                }
                
                // Validar que la suma de porcentajes no exceda 100%
                $totalPorcentaje = array_sum(array_column($emprendedoresData, 'porcentaje_ganancia'));
                if ($totalPorcentaje > 100) {
                    $validator->errors()->add('emprendedores', 
                        'La suma de porcentajes de ganancia no puede exceder 100%');
                }
            }
            
            // Validar compatibilidad con emprendedor_id legacy
            if ($this->has('emprendedor_id') && $this->has('emprendedores')) {
                $emprendedorId = $this->get('emprendedor_id');
                $emprendedoresData = $this->get('emprendedores', []);
                
                // Verificar que el emprendedor_id esté incluido en la lista de emprendedores
                $emprendedorIds = array_column($emprendedoresData, 'emprendedor_id');
                if (!in_array($emprendedorId, $emprendedorIds)) {
                    $validator->errors()->add('emprendedor_id', 
                        'El emprendedor principal debe estar incluido en la lista de emprendedores');
                }
            }
            
            // Legacy: Validar emprendedor_id único si no se especifican múltiples emprendedores
            if ($this->has('emprendedor_id') && !$this->has('emprendedores')) {
                $emprendedorId = $this->emprendedor_id;
                $user = Auth::user();
                
                if ($emprendedorId && !$user->hasRole('admin')) {
                    $emprendedor = \App\Models\Emprendedor::find($emprendedorId);
                    
                    if ($emprendedor && 
                        !$emprendedor->administradores()->where('users.id', $user->id)->exists()) {
                        $validator->errors()->add('emprendedor_id', 
                            'No tienes permisos para asignar planes a este emprendimiento');
                    }
                }
            }
            
            // Validar que los servicios pertenezcan a los emprendedores del plan
            if ($this->has('dias') && ($this->has('emprendedores') || $this->has('emprendedor_id'))) {
                $emprendedorIds = [];
                
                if ($this->has('emprendedores')) {
                    $emprendedorIds = array_column($this->get('emprendedores', []), 'emprendedor_id');
                } elseif ($this->has('emprendedor_id')) {
                    $emprendedorIds = [$this->emprendedor_id];
                }
                
                $diasData = $this->get('dias', []);
                
                foreach ($diasData as $diaIndex => $dia) {
                    if (isset($dia['servicios'])) {
                        foreach ($dia['servicios'] as $servicioIndex => $servicio) {
                            if (isset($servicio['servicio_id'])) {
                                $servicioModel = \App\Models\Servicio::find($servicio['servicio_id']);
                                
                                if ($servicioModel && !in_array($servicioModel->emprendedor_id, $emprendedorIds)) {
                                    $validator->errors()->add(
                                        "dias.{$diaIndex}.servicios.{$servicioIndex}.servicio_id",
                                        'El servicio no pertenece a ninguno de los emprendimientos del plan'
                                    );
                                }
                            }
                        }
                    }
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
                'message' => 'No estás autorizado para realizar esta acción'
            ], Response::HTTP_FORBIDDEN)
        );
    }
}