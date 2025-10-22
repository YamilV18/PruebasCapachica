<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class VerifyEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $verificationUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
        
        // Crear URL de verificación personalizada para la API
        $this->verificationUrl = $this->verificationUrl($user);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Verificación de correo electrónico')
                    ->markdown('emails.verify-email');
    }
    
    /**
     * Get the verification URL for the given user.
     *
     * @param  mixed  $user
     * @return string
     */
    protected function verificationUrl($user)
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
        
        // Extraer el token y la firma
        $queryParams = parse_url($verificationUrl, PHP_URL_QUERY);
        
        // Extraer id y hash de los parámetros de la ruta
        $id = $user->getKey();
        $hash = sha1($user->getEmailForVerification());
        
        // Construye la URL del frontend incluyendo todos los parámetros necesarios
        return $frontendUrl . '/verify-email?' . $queryParams . '&id=' . $id . '&hash=' . $hash;
    }
}