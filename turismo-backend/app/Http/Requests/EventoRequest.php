<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventoRequest extends FormRequest
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
        'tipo_evento' => 'required|string|max:100',
        'idioma_principal' => 'required|string|max:50',
        'fecha_inicio' => 'required|date',
        'hora_inicio' => 'required|date_format:H:i:s',
        'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        'hora_fin' => 'required|date_format:H:i:s',
        'duracion_horas' => 'nullable|integer|min:0',
        'coordenada_x' => 'nullable|numeric|between:-90,90',
        'coordenada_y' => 'nullable|numeric|between:-180,180',
        'id_emprendedor' => 'required|exists:emprendedores,id',
        'que_llevar' => 'nullable|string',
        'imagen'   => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
        'galeria'  => ['nullable','array','max:12'],
        'galeria.*'=> ['image','mimes:jpg,jpeg,png,webp','max:4096'],

        // Si deseas manejar sliders, categorías u horarios como en tu ejemplo anterior:


        'sliders' => 'sometimes|array',
        'sliders.*.id' => 'nullable|integer|exists:sliders,id',
        'sliders.*.imagen' => 'nullable|file|image|max:2048',
        'sliders.*.orden' => 'nullable|integer|min:0',
        'sliders.*.titulo' => 'nullable|string|max:255',
        'sliders.*.descripcion' => 'nullable|string',
        'sliders.*.nombre' => 'nullable|string|max:255',
        'sliders.*.es_principal' => 'nullable',

        'deleted_sliders' => 'sometimes|array',
        'deleted_sliders.*' => 'integer|exists:sliders,id',

        'horarios' => 'sometimes|array',
        'horarios.*.id' => 'nullable|integer|exists:servicio_horarios,id',
        'horarios.*.dia_semana' => ['required', Rule::in(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])],
        'horarios.*.hora_inicio' => 'required|date_format:H:i:s',
        'horarios.*.hora_fin' => 'required|date_format:H:i:s|after:horarios.*.hora_inicio',
        'horarios.*.activo' => 'sometimes',
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
            'nombre.required' => 'El nombre del evento es obligatorio',
            'tipo_evento.required' => 'Debe especificar el tipo de evento',
            'idioma_principal.required' => 'Debe indicar el idioma principal del evento',
            'fecha_inicio.required' => 'Debe indicar la fecha de inicio',
            'fecha_fin.required' => 'Debe indicar la fecha de fin',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
            'hora_inicio.required' => 'La hora de inicio es obligatoria',
            'hora_fin.required' => 'La hora de fin es obligatoria',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
            'duracion_horas.integer' => 'La duración debe ser un número entero',
            'coordenada_x.between' => 'La latitud debe estar entre -90 y 90',
            'coordenada_y.between' => 'La longitud debe estar entre -180 y 180',
            'id_emprendedor.required' => 'Debe seleccionar un emprendedor',
            'id_emprendedor.exists' => 'El emprendedor seleccionado no existe',

            'horarios.*.dia_semana.in' => 'El día de la semana no es válido',
            'horarios.*.hora_inicio.required' => 'La hora de inicio es obligatoria',
            'horarios.*.hora_fin.required' => 'La hora de fin es obligatoria',
            'horarios.*.hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',


            'sliders.*.imagen.image' => 'La imagen del slider debe ser un archivo de imagen válido',
            'sliders.*.orden.integer' => 'El orden debe ser un número entero',
            'deleted_sliders.*.exists' => 'Uno de los sliders a eliminar no existe',
        ];
    }
}
