<?php

namespace App\Mail;

use App\Models\Student;
use App\Models\ElectionCycle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Mailable sent to a student confirming that their vote has been
 * successfully cast and recorded. Includes the election-cycle name
 * and the unique reference number for the student's records.
 */
class VoteConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** The student who cast the vote. */
    public $student;

    /** The election cycle in which the vote was cast. */
    public $cycle;

    /** The unique reference number assigned to this vote. */
    public $reference;

    /**
     * @param  Student        $student
     * @param  ElectionCycle  $cycle
     * @param  string         $reference  Unique vote reference number.
     */
    public function __construct(Student $student, ElectionCycle $cycle, $reference)
    {
        $this->student = $student;
        $this->cycle = $cycle;
        $this->reference = $reference;
    }

    /**
     * Build the message with a dynamic subject line that includes
     * the election-cycle name and render the confirmation view.
     */
    public function build()
    {
        return $this->subject('Vote Confirmed - ' . $this->cycle->name)
            ->view('emails.students.vote-confirmed');
    }
}
