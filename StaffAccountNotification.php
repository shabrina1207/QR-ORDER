<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffAccountNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $loginUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Informasi akun Coffee Shop');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-account-notification');
    }
}
