<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable sent to admin users when a password-reset link is requested.
 * Renders the `emails.admin.password-reset` view with the reset URL
 * and the recipient's user details.
 */
class AdminResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $url   Signed password-reset URL.
     * @param  mixed   $user  The admin User model instance.
     */
    public function __construct(
        public $url,
        public $user
    ) {}

    /**
     * Define the mail envelope (sender, subject, etc.).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password',
        );
    }

    /**
     * Build the message content from the Blade view.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.password-reset',
        );
    }
}
