<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $token;
    public $email;
    public $resetUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
        
        // URL del frontend para resetear contraseña
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $this->resetUrl = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Recuperación de Contraseña')
                    ->markdown('emails.reset-password');
    }
}