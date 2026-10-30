<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

abstract class QueueInvalidated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    protected const TYPE = 'summary_changed';

    public int $tries = 3;

    public int $timeout = 15;

    /** @var list<int> */
    public array $backoff = [5, 30, 60];

    public string $broadcastQueue = 'realtime';

    public function __construct(
        protected readonly ?int $barberId,
        protected readonly string $queueDate,
    ) {}

    public function broadcastAs(): string
    {
        return class_basename($this);
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        $channels = [new Channel('queue.summary'), new PrivateChannel('staff.admin')];
        if ($this->barberId !== null) {
            $channels[] = new PrivateChannel('staff.barber.'.$this->barberId);
        }

        return $channels;
    }

    /** @return array{barber_id: int|null, queue_date: string, type: string} */
    public function broadcastWith(): array
    {
        // A refresh signal only: even private channels never carry ticket/customer data.
        return ['barber_id' => $this->barberId, 'queue_date' => $this->queueDate, 'type' => static::TYPE];
    }

    public function broadcastWhen(): bool
    {
        return in_array(config('broadcasting.default'), ['reverb', 'pusher', 'ably', 'mercure', 'redis', 'log'], true);
    }
}
