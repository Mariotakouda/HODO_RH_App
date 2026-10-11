<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation d'un nouvel employé : AUCUN mot de passe n'est envoyé.
 * L'employé crée lui-même son mot de passe via un lien à usage unique et limité dans le temps.
 */
class EmployeeInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $activationUrl,
        public int $validHours,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activez votre compte ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.invitation',
        );
    }
}
