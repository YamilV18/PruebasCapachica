<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServicioHorarioRequest extends FormRequest
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
        return [
            'servicio_id' => 'required|exists:servicios,id',
            'dia_semana' => ['required', Rule::in(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])],
            'hora_inicio' => 'required|date_format:H:i:s',
            'hora_fin' => 'required|date_format:H:i:s|after:hora_inicio',
            'activo' => 'sometimes|boolean',
            'imagen'   => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
            'galeria'  => ['nullable','array','max:12'],
            'galeria.*'=> ['image','mimes:jpg,jpeg,png,webp','max:4096'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'servicio_id.required' => 'El servicio es obligatorio',
            'servicio_id.exists' => 'El servicio seleccionado no existe',
            'dia_semana.required' => 'El día de la semana es obligatorio',
            'dia_semana.in' => 'El día de la semana no es válido',
            'hora_inicio.required' => 'La hora de inicio es obligatoria',
            'hora_inicio.date_format' => 'El formato de hora de inicio no es válido',
            'hora_fin.required' => 'La hora de fin es obligatoria',
            'hora_fin.date_format' => 'El formato de hora de fin no es válido',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
        ];
    }
}
