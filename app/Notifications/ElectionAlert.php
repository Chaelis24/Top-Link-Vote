<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to active students when an election opens
 * (`started`) or when a reminder is triggered (`reminder`).
 * Delivers the alert via email using the `election-alert` Blade view.
 */
class ElectionAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var string 'started' | 'reminder' */
    protected $type;

    /**
     * @param  string  $type  'started' when voting opens, 'reminder' for follow-ups.
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
