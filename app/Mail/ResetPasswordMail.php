<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable sent to students when a password-reset link is requested.
 * Renders the `emails.students.password-reset` view with the reset URL
 * and the recipient's student details.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $url      Signed password-reset URL.
     * @param  mixed   $student  The Student model instance.
     */
    public function __construct(
        public $url,
        public $student
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
            view: 'emails.students.password-reset',
        );
    }
}
