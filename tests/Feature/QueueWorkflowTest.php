<?php

namespace Tests\Feature;

use App\Enums\BarberAvailability;
use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use App\Models\Barber;
use App\Models\QrCode;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Queue\BarberAvailabilityService;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\QueueEstimate;
use App\Services\Queue\QueueWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class QueueWorkflowTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    public function test_online_check_in_preserves_the_number_and_is_idempotent(): void
    {
        ['qr' => $qr] = $this->openQueue();
        $ticket = $this->ticket();
        $workflow = app(QueueWorkflow::class);

        $checkedIn = $workflow->checkIn($ticket->id, $qr->code);
        $workflow->checkIn($ticket->id, $qr->code);

        $this->assertSame(QueueStatus::CheckedIn, $checkedIn->status);
        $this->assertSame($ticket->queue_number, $checkedIn->queue_number);
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertSame(1, $checkedIn->events()->where('event_type', 'checked_in')->count());
        $this->assertNotNull($checkedIn->checked_in_at);
    }

    public function test_inactive_qr_and_onsite_check_in_are_rejected_without_state_change(): void
    {
        ['qr' => $qr] = $this->openQueue();
        $online = $this->ticket();
        $onsite = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $inactive = QrCode::query()->create(['code' => str_repeat('x', 64), 'is_active' => false]);

        $this->assertInvalid(fn () => app(QueueWorkflow::class)->checkIn($online->id, $inactive->code));
        $this->assertInvalid(fn () => app(QueueWorkflow::class)->checkIn($onsite->id, $qr->code));
        $this->assertSame(QueueStatus::WaitingCheckin, $online->refresh()->status);
        $this->assertSame(QueueStatus::CheckedIn, $onsite->refresh()->status);
        $this->assertDatabaseCount('queue_events', 2);
    }

    public function test_call_next_selects_smallest_checked_in_sequence_and_records_the_full_lifecycle(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $first = $this->ticket();
        $second = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $third = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);

        $called = $workflow->callNext($barber->user, $barber->id);
        $this->assertSame($second->id, $called?->id);
        $this->assertNotNull($first->refresh()->grace_started_at);
        $this->assertSame(300, (int) $first->grace_started_at->diffInSeconds($first->grace_expires_at));
        $this->assertInvalid(fn () => $workflow->callNext($barber->user, $barber->id));
        $workflow->start($barber->user, $second->id);
        $this->assertSame(BarberAvailability::Busy, $barber->refresh()->availability_status);
        $workflow->complete($barber->user, $second->id);
        $this->assertSame(BarberAvailability::Available, $barber->refresh()->availability_status);
        $this->assertSame($third->id, $workflow->callNext($barber->user, $barber->id)?->id);
        $this->assertSame(['created', 'called', 'started', 'completed'], $second->events()->orderBy('id')->pluck('event_type')->all());
        $this->assertNotNull($second->refresh()->started_at);
        $this->assertNotNull($second->completed_at);
    }

    public function test_no_eligible_customer_and_estimates_do_not_start_grace(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $ticket = $this->ticket();
        app(QueueEstimate::class)->forTicket($ticket);

        $this->assertNull(app(QueueWorkflow::class)->callNext($barber->user, $barber->id));
        $this->assertNull($ticket->refresh()->grace_started_at);
        $this->travel(30)->minutes();
        $this->assertSame(0, app(QueueWorkflow::class)->expireDue());
        $this->assertSame(QueueStatus::WaitingCheckin, $ticket->refresh()->status);
    }

    public function test_late_arrival_during_grace_waits_for_current_service_then_regains_fifo_priority(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $late = $this->ticket();
        $current = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $workflow->callNext($barber->user, $barber->id);
        $workflow->start($barber->user, $current->id);
        $this->travel(2)->minutes();
        $workflow->checkIn($late->id, $qr->code);

        $this->assertSame(QueueStatus::InService, $current->refresh()->status);
        $this->assertInvalid(fn () => $workflow->callNext($barber->user, $barber->id));
        $workflow->complete($barber->user, $current->id);
        $this->assertSame($late->id, $workflow->callNext($barber->user, $barber->id)?->id);
    }

    public function test_grace_is_not_reset_when_another_customer_is_called(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $late = $this->ticket();
        $second = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $workflow->callNext($barber->user, $barber->id);
        $expiry = $late->refresh()->grace_expires_at;
        $workflow->start($barber->user, $second->id);
        $this->travel(3)->minutes();
        $workflow->complete($barber->user, $second->id);
        $workflow->callNext($barber->user, $barber->id);

        $this->assertTrue($expiry->equalTo($late->refresh()->grace_expires_at));
        $this->assertSame(1, $late->events()->where('event_type', 'grace_started')->count());
    }

    public function test_late_check_in_at_exact_deadline_commits_missed_before_rejecting(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $late = $this->ticket();
        $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $workflow->callNext($barber->user, $barber->id);
        $this->travelTo($late->refresh()->grace_expires_at);

        $this->assertInvalid(fn () => $workflow->checkIn($late->id, $qr->code));
        $this->assertSame(QueueStatus::Missed, $late->refresh()->status);
        $this->assertSame(1, $late->events()->where('event_type', 'missed')->count());
        $this->assertSame(0, $workflow->expireDue());
    }

    public function test_scheduler_expires_no_show_once_and_the_command_is_idempotent(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $late = $this->ticket();
        $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        app(QueueWorkflow::class)->callNext($barber->user, $barber->id);
        $this->travel(6)->minutes();

        $this->artisan('barberflow:expire-queues')->expectsOutput('1 antrean ditandai terlewat.')->assertSuccessful();
        $this->artisan('barberflow:expire-queues')->expectsOutput('0 antrean ditandai terlewat.')->assertSuccessful();
        $this->assertSame(QueueStatus::Missed, $late->refresh()->status);
        $this->assertNotNull($late->missed_at);
        $this->assertSame(1, $late->events()->where('event_type', 'missed')->count());
    }

    public function test_business_day_expiry_preserves_service_that_crosses_midnight(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $waiting = $this->ticket();
        $current = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $queued = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $workflow->callNext($barber->user, $barber->id);
        $workflow->start($barber->user, $current->id);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 00:01:00', 'Asia/Makassar'));

        $this->assertSame(2, $workflow->expireDue());
        $this->assertSame(QueueStatus::Missed, $waiting->refresh()->status);
        $this->assertSame(QueueStatus::Missed, $queued->refresh()->status);
        $this->assertSame(QueueStatus::InService, $current->refresh()->status);
        $this->assertInvalid(fn () => $workflow->callNext($barber->user, $barber->id));
        $this->assertSame(QueueStatus::Completed, $workflow->complete($barber->user, $current->id)->status);
    }

    public function test_called_ticket_expires_on_business_date_change(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $ticket = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $workflow->callNext($barber->user, $barber->id);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 00:00:00', 'Asia/Makassar'));

        $this->assertInvalid(fn () => $workflow->start($barber->user, $ticket->id));
        $this->assertSame(1, $workflow->expireDue());
        $this->assertSame(QueueStatus::Missed, $ticket->refresh()->status);
    }

    public function test_guest_cancellation_is_idempotent_and_cannot_interrupt_called_ticket(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $waiting = $this->ticket();
        $called = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);

        $workflow->cancelGuest($waiting->id);
        $workflow->cancelGuest($waiting->id);
        $workflow->callNext($barber->user, $barber->id);
        $this->assertInvalid(fn () => $workflow->cancelGuest($called->id));
        $this->assertSame(1, $waiting->events()->where('event_type', 'cancelled')->count());
        $this->assertSame(QueueStatus::Called, $called->refresh()->status);
    }

    public function test_admin_can_cancel_service_with_recorded_reason_and_release_barber(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $admin = User::factory()->admin()->create();
        $ticket = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $workflow->callNext($barber->user, $barber->id);
        $workflow->start($barber->user, $ticket->id);
        $this->assertInvalid(fn () => $workflow->cancelByAdmin($admin, $ticket->id, '  '));

        $cancelled = $workflow->cancelByAdmin($admin, $ticket->id, '  Koreksi operasional  ');
        $this->assertSame(QueueStatus::Cancelled, $cancelled->status);
        $this->assertSame('Koreksi operasional', $cancelled->cancellation_reason);
        $this->assertSame(BarberAvailability::Available, $barber->refresh()->availability_status);
        $event = $cancelled->events()->where('event_type', 'cancelled')->firstOrFail();
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame('Koreksi operasional', $event->reason);
    }

    public function test_terminal_and_out_of_order_transitions_cannot_mutate_tickets_or_events(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $ticket = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $this->assertInvalid(fn () => $workflow->start($barber->user, $ticket->id));
        $this->assertInvalid(fn () => $workflow->complete($barber->user, $ticket->id));
        $workflow->callNext($barber->user, $barber->id);
        $this->assertInvalid(fn () => $workflow->complete($barber->user, $ticket->id));
        $workflow->start($barber->user, $ticket->id);
        $workflow->complete($barber->user, $ticket->id);
        $events = QueueEvent::query()->count();
        $this->assertInvalid(fn () => $workflow->start($barber->user, $ticket->id));
        $this->assertInvalid(fn () => $workflow->complete($barber->user, $ticket->id));
        $this->assertInvalid(fn () => $workflow->cancelGuest($ticket->id));
        $this->assertSame(QueueStatus::Completed, $ticket->refresh()->status);
        $this->assertSame($events, QueueEvent::query()->count());
    }

    public function test_barber_cannot_access_another_barbers_queue_or_admin_cancellation(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $other = Barber::factory()->create(['queue_prefix' => 'B']);
        $ticket = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);

        foreach ([
            fn () => $workflow->callNext($other->user, $barber->id),
            fn () => $workflow->start($other->user, $ticket->id),
            fn () => $workflow->complete($other->user, $ticket->id),
            fn () => $workflow->cancelByAdmin($barber->user, $ticket->id, 'Bukan hak barber'),
        ] as $action) {
            $this->assertForbidden($action);
        }
        $this->assertSame(QueueStatus::CheckedIn, $ticket->refresh()->status);
        $this->assertDatabaseCount('queue_events', 1);
    }

    public function test_revoked_staff_account_is_rechecked_at_service_boundary(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $staleActor = $barber->user;
        User::query()->whereKey($staleActor->id)->update(['is_active' => false]);

        $this->assertForbidden(fn () => app(QueueWorkflow::class)->callNext($staleActor, $barber->id));
        $this->assertForbidden(fn () => app(BarberAvailabilityService::class)->change($staleActor, $barber->id, BarberAvailability::Break));
    }

    public function test_availability_changes_cannot_interrupt_processing_or_invent_busy_status(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $availability = app(BarberAvailabilityService::class);
        $workflow = app(QueueWorkflow::class);
        $ticket = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $this->assertInvalid(fn () => $availability->change($barber->user, $barber->id, BarberAvailability::Busy));
        $workflow->callNext($barber->user, $barber->id);
        foreach (BarberAvailability::cases() as $status) {
            $this->assertInvalid(fn () => $availability->change($barber->user, $barber->id, $status));
        }
        $workflow->start($barber->user, $ticket->id);
        $this->assertSame(BarberAvailability::Busy, $availability->change($barber->user, $barber->id, BarberAvailability::Busy)->availability_status);
        $this->assertInvalid(fn () => $availability->change($barber->user, $barber->id, BarberAvailability::Break));
        $workflow->complete($barber->user, $ticket->id);
        $this->assertSame(BarberAvailability::Break, $availability->change($barber->user, $barber->id, BarberAvailability::Break)->availability_status);
        $this->assertInvalid(fn () => $workflow->callNext($barber->user, $barber->id));
    }

    private function ticket(array $overrides = []): QueueTicket
    {
        return app(CreateQueueTicket::class)->create($this->queueInput($overrides), bin2hex(random_bytes(32)))->ticket;
    }

    private function assertInvalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected invalid transition to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function assertForbidden(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected unauthorized actor to be rejected.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
