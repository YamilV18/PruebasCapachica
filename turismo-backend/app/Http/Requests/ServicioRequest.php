<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServicioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio_referencial' => 'nullable|numeric|min:0',
            'emprendedor_id' => 'required|exists:emprendedores,id',
            'estado' => 'sometimes',  // Quitamos la validación boolean para manejarla manualmente
            'capacidad' => 'required|integer|min:1',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'ubicacion_referencia' => 'nullable|string|max:255',
            'categorias' => 'sometimes|array',
            'categorias.*' => 'exists:categorias,id',
            'sliders' => 'sometimes|array',
            'sliders.*.id' => 'nullable|integer|exists:sliders,id',
            'sliders.*.imagen' => 'nullable',  // Cambiamos de string a nullable para aceptar archivos
            'sliders.*.orden' => 'nullable|integer|min:0',
            'sliders.*.titulo' => 'nullable|string|max:255',
            'sliders.*.descripcion' => 'nullable|string',
            'sliders.*.nombre' => 'nullable|string|max:255',  // Añadido según tus datos
            'sliders.*.es_principal' => 'nullable',  // También procesaremos esto manualmente
            'deleted_sliders' => 'sometimes|array',
            'deleted_sliders.*' => 'integer|exists:sliders,id',
            'horarios' => 'sometimes|array',
            'horarios.*.id' => 'nullable|integer|exists:servicio_horarios,id',
            'horarios.*.dia_semana' => ['required', Rule::in(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])],
            'horarios.*.hora_inicio' => 'required|date_format:H:i:s',
            'horarios.*.hora_fin' => 'required|date_format:H:i:s|after:horarios.*.hora_inicio',
            'horarios.*.activo' => 'sometimes',  // Quitamos boolean para manejarla manualmente
            'imagen'   => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
            'galeria'  => ['nullable','array','max:12'],
            'galeria.*'=> ['image','mimes:jpg,jpeg,png,webp','max:4096'],
        ];

        // Si es una solicitud de creación, modificamos las reglas para los archivos
        if ($this->isMethod('post')) {
            $rules['sliders.*.imagen'] = 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del servicio es obligatorio',
            'emprendedor_id.required' => 'Debe seleccionar un emprendedor',
            'emprendedor_id.exists' => 'El emprendedor seleccionado no existe',
            'latitud.between' => 'La latitud debe estar entre -90 y 90',
            'longitud.between' => 'La longitud debe estar entre -180 y 180',
            'horarios.*.dia_semana.in' => 'El día de la semana no es válido',
            'horarios.*.hora_inicio.required' => 'La hora de inicio es obligatoria',
            'horarios.*.hora_fin.required' => 'La hora de fin es obligatoria',
            'horarios.*.hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
        ];
    }
}
