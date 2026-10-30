<?php

namespace Tests\Feature;

use App\Enums\BarberAvailability;
use App\Events\BarberAvailabilityChanged;
use App\Events\QueueCalled;
use App\Events\QueueCancelled;
use App\Events\QueueCheckedIn;
use App\Events\QueueCompleted;
use App\Events\QueueCreated;
use App\Events\QueueInvalidated;
use App\Events\QueueMissed;
use App\Events\QueueStarted;
use App\Events\QueueSummaryChanged;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Queue\BarberAvailabilityService;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\QueueWorkflow;
use App\Services\StaffAudit;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    private const EVENTS = [
        QueueCreated::class, QueueCheckedIn::class, QueueCalled::class, QueueStarted::class,
        QueueCompleted::class, QueueCancelled::class, QueueMissed::class,
        BarberAvailabilityChanged::class, QueueSummaryChanged::class,
    ];

    public function test_a_rolled_back_transaction_broadcasts_nothing_and_a_retry_publishes_once_after_commit(): void
    {
        $this->openQueue();
        Event::fake(self::EVENTS);
        $input = $this->queueInput();
        DB::beginTransaction();
        app(CreateQueueTicket::class)->create($input, str_repeat('owner', 10));
        Event::assertNotDispatched(QueueCreated::class);
        DB::rollBack();
        $this->assertDatabaseCount('queue_tickets', 0);

        DB::beginTransaction();
        $ticket = app(CreateQueueTicket::class)->create($input, str_repeat('owner', 10))->ticket;
        Event::assertNotDispatched(QueueCreated::class);
        DB::commit();
        Event::assertDispatchedTimes(QueueCreated::class, 1);
        app(CreateQueueTicket::class)->create($input, str_repeat('owner', 10));
        Event::assertDispatchedTimes(QueueCreated::class, 1);
        $this->assertDatabaseHas('queue_tickets', ['id' => $ticket->id]);
    }

    public function test_real_workflow_changes_publish_all_required_events_with_only_refresh_metadata(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        Event::fake(self::EVENTS);
        $creator = app(CreateQueueTicket::class);
        $workflow = app(QueueWorkflow::class);
        $ticket = $creator->create($this->queueInput(['name' => 'PRIVATE_CUSTOMER_NAME']), str_repeat('o', 64))->ticket;
        $workflow->checkIn($ticket->id, $qr->code);
        $workflow->checkIn($ticket->id, $qr->code); // An idempotent check-in is not another change.
        $workflow->callNext($barber->user, $barber->id);
        $workflow->start($barber->user, $ticket->id);
        $workflow->complete($barber->user, $ticket->id);
        $cancelled = $creator->create($this->queueInput(), str_repeat('o', 64))->ticket;
        $workflow->cancelGuest($cancelled->id);
        $late = $creator->create($this->queueInput(), str_repeat('o', 64))->ticket;
        $this->travel(1)->days();
        $workflow->expireDue();
        app(BarberAvailabilityService::class)->change($barber->user, $barber->id, BarberAvailability::Break);
        app(BarberAvailabilityService::class)->change($barber->user, $barber->id, BarberAvailability::Break);

        foreach (array_slice(self::EVENTS, 0, 8) as $eventClass) {
            Event::assertDispatched($eventClass, function (QueueInvalidated $event): bool {
                $payload = $event->broadcastWith();
                $this->assertSame(['barber_id', 'queue_date', 'type'], array_keys($payload));
                $this->assertStringNotContainsString('PRIVATE_CUSTOMER_NAME', serialize($event));
                $this->assertSame(['queue.summary', 'private-staff.admin', 'private-staff.barber.'.$payload['barber_id']],
                    array_map(fn ($channel) => $channel->name, $event->broadcastOn()));

                return true;
            });
        }
        Event::assertDispatchedTimes(QueueCheckedIn::class, 1);
        Event::assertDispatchedTimes(BarberAvailabilityChanged::class, 3);
        $this->assertSame('missed', $late->refresh()->status->value);
    }

    public function test_grace_and_admin_configuration_changes_invalidate_without_leaking_audit_metadata(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $creator = app(CreateQueueTicket::class);
        $creator->create($this->queueInput(), str_repeat('o', 64));
        $creator->create($this->queueInput(['source' => 'onsite', 'qr_code' => $qr->code]), str_repeat('o', 64));
        Event::fake(self::EVENTS);
        app(QueueWorkflow::class)->callNext($barber->user, $barber->id);
        Event::assertDispatched(QueueSummaryChanged::class, fn ($event) => $event->broadcastWith()['barber_id'] === $barber->id);

        app(StaffAudit::class)->record(User::factory()->admin()->create(), 'settings.updated', 'business_settings', null, [
            'phone' => 'PRIVATE_PHONE', 'reason' => 'PRIVATE_REASON', 'password' => 'PRIVATE_PASSWORD',
        ]);
        Event::assertDispatched(QueueSummaryChanged::class, function (QueueSummaryChanged $event): bool {
            if ($event->broadcastWith()['barber_id'] !== null) {
                return false;
            }
            $this->assertStringNotContainsString('PRIVATE_', serialize($event));
            $this->assertSame(['queue.summary', 'private-staff.admin'], array_map(fn ($channel) => $channel->name, $event->broadcastOn()));

            return true;
        });
    }

    public function test_broadcast_transport_or_queue_failure_does_not_revert_or_fail_a_valid_ticket(): void
    {
        $this->openQueue();
        Log::spy();
        config(['broadcasting.default' => 'reverb']);
        Broadcast::shouldReceive('queue')->once()->andThrow(new RuntimeException('PRIVATE_TRANSPORT_CREDENTIAL'));
        $ticket = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64))->ticket;
        $this->assertDatabaseHas('queue_tickets', ['id' => $ticket->id, 'status' => 'waiting_checkin']);
        $this->assertSame(1, QueueTicket::query()->count());
        Log::shouldHaveReceived('warning')->once()->with(
            'Realtime delivery unavailable; clients will refetch using polling.',
            ['event' => 'QueueCreated', 'exception_type' => RuntimeException::class],
        );
    }

    public function test_broadcast_job_has_bounded_retries_and_immutable_scalar_payload(): void
    {
        $event = new QueueCreated(5, '2026-10-26');
        $job = new BroadcastEvent($event);
        $this->assertSame(3, $job->tries);
        $this->assertSame([5, 30, 60], $job->backoff);
        $this->assertSame(15, $job->timeout);
        $this->assertSame('realtime', $event->broadcastQueue);
        $this->assertSame(['barber_id' => 5, 'queue_date' => '2026-10-26', 'type' => 'created'], unserialize(serialize($event))->broadcastWith());
    }

    public function test_disabled_broadcasters_do_not_enqueue_transport_jobs_for_valid_tickets(): void
    {
        $this->openQueue();
        Queue::fake();
        foreach ([null, 'null', ''] as $connection) {
            config(['broadcasting.default' => $connection]);
            app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64));
            Queue::assertNothingPushed();
        }
        $this->assertDatabaseCount('queue_tickets', 3);
    }
}
