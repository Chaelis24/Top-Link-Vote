<?php

namespace App\Mail;

use App\Models\Student;
use App\Models\ElectionCycle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class VoteConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $student;
    public $cycle;
    public $reference;

    public function __construct(Student $student, ElectionCycle $cycle, $reference)
    {
        $this->student = $student;
        $this->cycle = $cycle;
        $this->reference = $reference;
    }

    public function build()
    {
        return $this->subject('Vote Confirmed - ' . $this->cycle->name)
            ->view('emails.students.vote-confirmed');
    }
}
