<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasted when a user's session is invalidated due to a new login
 * from another device. The affected user receives a `duplicate-login`
 * event on their private channel so the front end can force a logout.
 */
class UserLoggedInElsewhere implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public int $userId)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }
    /**
     * The event alias the front end listens for.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'account.duplicate-login';
    }
}
