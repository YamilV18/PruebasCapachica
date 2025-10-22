<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
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
        $userId = Auth::id();
        
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $userId,
            'phone' => 'nullable|string',
            'foto_perfil' => 'nullable|image|max:5120', // 5MB máximo
            'password' => 'nullable|string|min:8|confirmed',
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
            'name.string' => 'El nombre debe ser texto',
            'email.email' => 'El formato del correo electrónico no es válido',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'La confirmación de la contraseña no coincide',
            'foto_perfil.image' => 'El archivo debe ser una imagen',
            'foto_perfil.max' => 'La imagen no debe superar los 5MB',
            'birth_date.date' => 'La fecha de nacimiento debe ser una fecha válida',
            'gender.in' => 'El género debe ser uno de los valores permitidos',
        ];
    }
}