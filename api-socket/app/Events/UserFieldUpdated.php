<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserFieldUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public int $byId,
        public string $byName
    ) {
    }

    public static function fromUser(User $user, int $byId, string $byName): self
    {
        return new self($user->id, $user->name, $user->email, $byId, $byName);
    }

    public function broadcastOn(): array
    {
        return [new Channel('user-management')];
    }
}
