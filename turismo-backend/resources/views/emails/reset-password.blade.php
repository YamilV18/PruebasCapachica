@component('mail::message')
# Recuperación de Contraseña

Hola,

Has recibido este correo porque se solicitó un restablecimiento de contraseña para tu cuenta.

@component('mail::button', ['url' => $resetUrl])
Restablecer Contraseña
@endcomponent

Este enlace de restablecimiento de contraseña caducará en 60 minutos.

Si no solicitaste un restablecimiento de contraseña, no es necesario realizar ninguna acción.

Saludos,<br>
{{ config('app.name') }}
@endcomponent