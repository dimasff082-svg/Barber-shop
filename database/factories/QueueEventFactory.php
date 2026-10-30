<?php

namespace Database\Factories;

use App\Enums\QueueStatus;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QueueEvent> */
class QueueEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['queue_ticket_id' => QueueTicket::factory(), 'actor_user_id' => null, 'event_type' => 'created', 'previous_status' => null, 'new_status' => QueueStatus::WaitingCheckin, 'metadata' => null];
    }
}
