<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservaRequest extends FormRequest
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
            'usuario_id' => 'sometimes|exists:users,id',
            'codigo_reserva' => 'sometimes|string|max:20|unique:reservas,codigo_reserva' .
                (($this->isMethod('PUT') || $this->isMethod('PATCH')) ? (',' . $this->route('id')) : ''),
            'estado' => ['sometimes', Rule::in(['pendiente', 'confirmada', 'cancelada', 'completada'])],
            'notas' => 'nullable|string',

            // Servicios (array de servicios reservados)
            'servicios' => 'required|array', // Hacemos los servicios obligatorios
            'servicios.*.id' => 'nullable|integer|exists:reserva_servicios,id',
            'servicios.*.servicio_id' => 'required|integer|exists:servicios,id',
            'servicios.*.emprendedor_id' => 'required|integer|exists:emprendedores,id',
            'servicios.*.fecha_inicio' => 'required|date_format:Y-m-d',
            'servicios.*.fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:servicios.*.fecha_inicio',
            'servicios.*.hora_inicio' => 'required|date_format:H:i:s',
            'servicios.*.hora_fin' => 'required|date_format:H:i:s|after:servicios.*.hora_inicio',
            'servicios.*.duracion_minutos' => 'required|integer|min:1',
            'servicios.*.cantidad' => 'sometimes|integer|min:1',
            'servicios.*.precio' => 'nullable|numeric|min:0',
            'servicios.*.estado' => ['sometimes', Rule::in(['pendiente', 'confirmado', 'cancelado', 'completado'])],
            'servicios.*.notas_cliente' => 'nullable|string',
            'servicios.*.notas_emprendedor' => 'nullable|string',
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
            'usuario_id.exists' => 'El usuario seleccionado no existe',
            'codigo_reserva.unique' => 'El código de reserva ya existe',
            'servicios.*.servicio_id.required' => 'El servicio es obligatorio',
            'servicios.*.servicio_id.exists' => 'El servicio seleccionado no existe',
            'servicios.*.emprendedor_id.required' => 'El emprendedor es obligatorio',
            'servicios.*.emprendedor_id.exists' => 'El emprendedor seleccionado no existe',
            'servicios.*.fecha_inicio.required' => 'La fecha de inicio es obligatoria',
            'servicios.*.fecha_inicio.date_format' => 'El formato de fecha de inicio no es válido',
            'servicios.*.fecha_fin.date_format' => 'El formato de fecha de fin no es válido',
            'servicios.*.fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
            'servicios.*.hora_inicio.required' => 'La hora de inicio es obligatoria',
            'servicios.*.hora_inicio.date_format' => 'El formato de hora de inicio no es válido',
            'servicios.*.hora_fin.required' => 'La hora de fin es obligatoria',
            'servicios.*.hora_fin.date_format' => 'El formato de hora de fin no es válido',
            'servicios.*.hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
            'servicios.*.duracion_minutos.required' => 'La duración es obligatoria',
            'servicios.*.duracion_minutos.min' => 'La duración debe ser mayor a 0',
            'servicios.*.cantidad.min' => 'La cantidad debe ser mayor a 0',
            'servicios.*.precio.min' => 'El precio no puede ser negativo',
        ];
    }
}
