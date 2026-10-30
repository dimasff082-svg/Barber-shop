<?php

namespace App\Data;

use App\Models\QueueTicket;

final readonly class CreatedQueueTicket
{
    public function __construct(
        public QueueTicket $ticket,
        public ?string $accessToken,
        public bool $created,
    ) {}
}
