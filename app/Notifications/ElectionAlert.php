<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ElectionAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected $type;

    /**
     * Create a new notification instance.
     * * @param string $type ('started' or 'reminder')
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $cycle = \App\Models\ElectionCycle::where('status', 'active')->first();
        $student = \App\Models\Student::where('user_id', $notifiable->id)->first();

        $subject = $this->type === 'reminder'
            ? 'Reminder: Cast Your Vote in the Ongoing Election!'
            : 'Official Election: Voting is Now Open!';

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($subject)
            ->view('emails.students.election-alert', [
                'type' => $this->type,
                'student' => $student,
                'cycle' => $cycle,
            ]);
    }
}
