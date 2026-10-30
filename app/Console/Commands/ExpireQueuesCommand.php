<?php

namespace App\Console\Commands;

use App\Services\Queue\QueueWorkflow;
use Illuminate\Console\Command;

class ExpireQueuesCommand extends Command
{
    protected $signature = 'barberflow:expire-queues';

    protected $description = 'Tandai antrean lewat toleransi atau tanggal bisnis secara idempoten';

    public function handle(QueueWorkflow $workflow): int
    {
        $this->info($workflow->expireDue().' antrean ditandai terlewat.');

        return self::SUCCESS;
    }
}
