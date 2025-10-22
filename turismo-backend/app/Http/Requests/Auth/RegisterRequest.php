<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'foto_perfil' => 'nullable|image|max:5120', // 5MB max
            'country' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'preferred_language' => 'nullable|string|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'El nombre de usuario es obligatorio',
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'El formato del correo electrónico no es válido',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'La confirmación de la contraseña no coincide',
            'foto_perfil.image' => 'El archivo debe ser una imagen',
            'foto_perfil.max' => 'La imagen no debe superar los 5MB',
            'birth_date.date' => 'La fecha de nacimiento debe ser una fecha válida',
            'gender.in' => 'El género debe ser uno de los valores permitidos',
        ];
    }
}