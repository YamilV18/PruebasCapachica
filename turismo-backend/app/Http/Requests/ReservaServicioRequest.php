<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservaServicioRequest extends FormRequest
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
            'reserva_id' => 'required|exists:reservas,id',
            'servicio_id' => 'required|exists:servicios,id',
            'emprendedor_id' => 'required|exists:emprendedores,id',
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
            'hora_inicio' => 'required|date_format:H:i:s',
            'hora_fin' => 'required|date_format:H:i:s|after:hora_inicio',
            'duracion_minutos' => 'required|integer|min:1',
            'cantidad' => 'sometimes|integer|min:1',
            'precio' => 'nullable|numeric|min:0',
            'estado' => ['sometimes', Rule::in(['pendiente', 'confirmado', 'cancelado', 'completado'])],
            'notas_cliente' => 'nullable|string',
            'notas_emprendedor' => 'nullable|string',
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
            'reserva_id.required' => 'La reserva es obligatoria',
            'reserva_id.exists' => 'La reserva seleccionada no existe',
            'servicio_id.required' => 'El servicio es obligatorio',
            'servicio_id.exists' => 'El servicio seleccionado no existe',
            'emprendedor_id.required' => 'El emprendedor es obligatorio',
            'emprendedor_id.exists' => 'El emprendedor seleccionado no existe',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria',
            'fecha_inicio.date_format' => 'El formato de fecha de inicio no es válido',
            'fecha_fin.date_format' => 'El formato de fecha de fin no es válido',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
            'hora_inicio.required' => 'La hora de inicio es obligatoria',
            'hora_inicio.date_format' => 'El formato de hora de inicio no es válido',
            'hora_fin.required' => 'La hora de fin es obligatoria',
            'hora_fin.date_format' => 'El formato de hora de fin no es válido',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
            'duracion_minutos.required' => 'La duración es obligatoria',
            'duracion_minutos.min' => 'La duración debe ser mayor a 0',
            'cantidad.min' => 'La cantidad debe ser mayor a 0',
            'precio.min' => 'El precio no puede ser negativo',
        ];
    }
}
