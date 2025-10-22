@component('mail::message')
# Verificación de Correo Electrónico

Hola {{ $user->name }},

Gracias por registrarte en nuestra plataforma. Por favor, haz clic en el botón de abajo para verificar tu dirección de correo electrónico.

@component('mail::button', ['url' => $verificationUrl])
Verificar Correo Electrónico
@endcomponent

Si no creaste una cuenta, no es necesario realizar ninguna acción.

Saludos,<br>
{{ config('app.name') }}
@endcomponent