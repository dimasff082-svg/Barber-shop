<?php

namespace Tests\Feature;

use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use App\Models\Barber;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\QueueWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\ResponseFactory;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class StaffOperationsTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    public function test_dashboard_counts_come_from_the_actual_business_day_tickets(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $this->ticket();
        $ready = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $workflow->callNext($barber->user, $barber->id);
        $workflow->start($barber->user, $ready->id);
        $workflow->complete($barber->user, $ready->id);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard')->where('date', '2026-10-26')
                ->where('stats.total', 2)->where('stats.completed', 1)->where('stats.waiting_checkin', 1)
                ->has('barbers', 1)->has('processing', 0));
    }

    public function test_barber_without_a_profile_gets_an_explicit_empty_dashboard(): void
    {
        $this->actingAs(User::factory()->barber()->create())->get(route('barber.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('profile_missing', true)->where('barber', null)
                ->where('stats.total', 0)->where('can_call', false)->has('processing', 0));
    }

    public function test_active_barber_kpi_excludes_inactive_accounts_and_profiles(): void
    {
        Barber::factory()->create();
        Barber::factory()->create(['is_active' => false]);
        $revoked = Barber::factory()->create();
        $revoked->user->forceFill(['is_active' => false])->save();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('active_barbers', 1));
    }

    public function test_barber_lists_and_detail_never_expose_another_barbers_customers(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $own = $this->ticket(['name' => 'Own Customer']);
        $other = Barber::factory()->create(['queue_prefix' => 'B']);
        $foreign = QueueTicket::factory()->create(['barber_id' => $other->id, 'customer_name' => 'PRIVATE_FOREIGN_CUSTOMER', 'queue_date' => '2026-10-26']);
        $response = $this->actingAs($barber->user)->get(route('barber.queues.index', ['barber_id' => $other->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $own->id)
                ->missing('tickets.data.0.token_hash')->missing('tickets.data.0.idempotency_key')->missing('tickets.data.0.normalized_phone'));
        $this->assertStringNotContainsString($foreign->customer_name, $response->getContent());
        $this->get(route('barber.queues.show', $foreign->id))->assertNotFound();
        $this->get(route('barber.queues.show', $own->id))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('ticket.id', $own->id)->has('events', 1)->missing('events.0.metadata'));
    }

    public function test_admin_queue_filters_and_pagination_preserve_validated_queries(): void
    {
        $this->openQueue();
        for ($i = 0; $i < 22; $i++) {
            $this->ticket();
        }
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.queues.index', ['date' => '2026-10-26', 'status' => 'waiting_checkin', 'source' => 'online']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('tickets.total', 22)->has('tickets.data', 20)
            ->where('filters.status', 'waiting_checkin')->where('filters.source', 'online')->where('tickets.last_page', 2));
        $this->get(route('admin.queues.index', ['source' => 'forged']))->assertSessionHasErrors('source');
        $this->get(route('admin.queues.index', ['date' => '2026-02-30']))->assertSessionHasErrors('date');
    }

    public function test_barber_history_contains_only_own_terminal_records(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $cancelled = $this->ticket();
        $this->ticket();
        app(QueueWorkflow::class)->cancelGuest($cancelled->id);
        $this->actingAs($barber->user)->get(route('barber.history'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('history', true)->has('tickets.data', 1)->where('tickets.data.0.status', 'cancelled'));
    }

    public function test_operational_routes_require_staff_role_and_reports_require_admin(): void
    {
        $this->get(route('admin.queues.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->barber()->create())->get(route('admin.reports.index'))->assertForbidden();
        $this->get(route('admin.queues.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('barber.queues.index'))->assertForbidden();
    }

    public function test_inertia_mutations_redirect_and_preserve_queue_event_actor(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $ticket = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $this->actingAs($barber->user)->from(route('barber.dashboard'))->withHeader('X-Inertia', 'true')
            ->post(route('queue.staff.call', $barber->id))->assertRedirect(route('barber.dashboard'));
        $this->assertSame(QueueStatus::Called, $ticket->refresh()->status);
        $this->assertSame($barber->user_id, $ticket->events()->where('event_type', 'called')->firstOrFail()->actor_user_id);
    }

    public function test_private_inertia_navigation_keeps_mutations_and_errors_on_the_work_page_without_a_referrer(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $ticket = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $this->actingAs($barber->user)->get(route('home'))->assertOk();
        $this->withHeaders([
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => app(ResponseFactory::class)->getVersion(),
        ])->get(route('barber.dashboard'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');

        $this->post(route('queue.staff.call', $barber->id))->assertRedirect(route('barber.dashboard'));
        $this->assertSame(QueueStatus::Called, $ticket->refresh()->status);
        $this->post(route('queue.staff.complete', $ticket->id))->assertRedirect(route('barber.dashboard'))->assertSessionHasErrors('queue');

        $this->withHeaders([
            'X-Inertia-Partial-Component' => 'barber/dashboard', 'X-Inertia-Partial-Data' => 'stats',
        ])->get(route('barber.dashboard', ['refresh' => 1]))->assertOk();
        $this->withoutHeader('X-Inertia-Partial-Component')->withoutHeader('X-Inertia-Partial-Data');
        $this->post(route('queue.staff.start', $ticket->id))->assertRedirect(route('barber.dashboard'));
        $this->post(route('queue.staff.complete', $ticket->id))->assertRedirect(route('barber.dashboard'));
        $this->assertSame(QueueStatus::Completed, $ticket->refresh()->status);
    }

    public function test_manual_missed_action_requires_elapsed_grace_and_the_correct_owner(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $late = $this->ticket();
        $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $this->actingAs($barber->user)->postJson(route('queue.staff.missed', $late->id))->assertUnprocessable();
        app(QueueWorkflow::class)->callNext($barber->user, $barber->id);
        $this->travel(5)->minutes();
        $other = Barber::factory()->create(['queue_prefix' => 'B']);
        $this->actingAs($other->user)->postJson(route('queue.staff.missed', $late->id))->assertForbidden();
        $this->actingAs($barber->user)->postJson(route('queue.staff.missed', $late->id))->assertOk()->assertJsonPath('ticket.status', 'missed');
        $this->postJson(route('queue.staff.missed', $late->id))->assertUnprocessable();
        $this->assertSame(1, $late->events()->where('event_type', 'missed')->count());
        $this->assertSame($barber->user_id, $late->events()->where('event_type', 'missed')->firstOrFail()->actor_user_id);
    }

    public function test_reports_count_sources_outcomes_and_actual_checkin_wait_without_payment_claims(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $ready = $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        $workflow = app(QueueWorkflow::class);
        $this->travel(10)->minutes();
        $workflow->callNext($barber->user, $barber->id);
        $workflow->start($barber->user, $ready->id);
        $workflow->complete($barber->user, $ready->id);
        $cancelled = $this->ticket();
        $workflow->cancelGuest($cancelled->id);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.index', ['from' => '2026-10-26', 'to' => '2026-10-26']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/reports')->where('totals.total', 2)
                ->where('totals.completed', 1)->where('totals.cancelled', 1)->where('totals.online', 1)->where('totals.onsite', 1)
                ->where('totals.avg_wait_minutes', 10)->where('totals.wait_samples', 1)->where('barbers.0.completed', 1)
                ->where('services.0.completed', 1)->missing('revenue')->missing('totals.revenue')->missing('tickets'));
    }

    public function test_report_date_range_is_bounded_and_profile_is_read_only(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.index', ['from' => '2024-01-01', 'to' => '2026-10-26']))->assertSessionHasErrors('to');
        $this->get(route('admin.reports.index', ['from' => '2026-10-27', 'to' => '2026-10-26']))->assertSessionHasErrors('to');
        $this->actingAs($barber->user)->get(route('barber.profile'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('barber/profile')->where('barber.name', $barber->name)->missing('barber.user')->missing('barber.password'));
    }

    public function test_report_days_are_chronological_even_when_ticket_ids_have_another_date_order(): void
    {
        ['barber' => $barber, 'service' => $service] = $this->openQueue();
        QueueTicket::factory()->create(['barber_id' => $barber->id, 'service_id' => $service->id,
            'queue_date' => '2026-10-27', 'queue_number' => 'A-101', 'sequence_number' => 101, 'status' => QueueStatus::Completed]);
        QueueTicket::factory()->create(['barber_id' => $barber->id, 'service_id' => $service->id,
            'queue_date' => '2026-10-26', 'queue_number' => 'A-102', 'sequence_number' => 102, 'status' => QueueStatus::Completed]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.index', ['from' => '2026-10-26', 'to' => '2026-10-27']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('totals.total', 2)->where('totals.completed', 2)
            ->where('days.0.date', '2026-10-26')->where('days.1.date', '2026-10-27')->where('totals.avg_wait_minutes', null));
    }

    private function ticket(array $overrides = []): QueueTicket
    {
        return app(CreateQueueTicket::class)->create($this->queueInput($overrides), str_repeat('o', 64))->ticket;
    }
}
