<?php

namespace Tests\Feature;

use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use App\Models\QrCode;
use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\QueueWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class ScannedTicketCheckInTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_scanned_location_qr_checks_in_the_session_ticket_id_without_reissuing_a_number(): void
    {
        ['qr' => $qr] = $this->openQueue();
        $ticket = $this->ticket();
        $number = $ticket->queue_number;
        $counter = QueueCounter::query()->firstOrFail()->last_number;
        $this->grant($ticket);

        $response = $this->postJson(route('queue.check-in', $ticket->id), ['qr_code' => $qr->code]);
        $response->assertOk()->assertJsonPath('ticket.id', $ticket->id)
            ->assertJsonPath('ticket.queue_number', $number)->assertJsonPath('ticket.status', 'checked_in')
            ->assertJsonPath('ticket.source', 'online')->assertJsonMissingPath('ticket.token_hash')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $checkedInAt = $ticket->refresh()->checked_in_at;
        $this->travel(1)->minute();

        $this->postJson(route('queue.check-in', $ticket->id), ['qr_code' => $qr->code])
            ->assertOk()->assertJsonPath('ticket.id', $ticket->id)->assertJsonPath('ticket.queue_number', $number);
        $this->assertTrue($checkedInAt->equalTo($ticket->refresh()->checked_in_at));
        $this->assertSame($counter, QueueCounter::query()->firstOrFail()->last_number);
        $this->assertSame(1, $ticket->events()->where('event_type', 'checked_in')->count());
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertDatabaseCount('queue_events', 2);
    }

    public function test_a_ticket_id_and_location_qr_cannot_check_in_a_different_sessions_ticket(): void
    {
        ['qr' => $qr] = $this->openQueue();
        $own = $this->ticket();
        $foreign = $this->ticket();
        $this->grant($own);

        $response = $this->postJson(route('queue.check-in', $foreign->id), [
            'qr_code' => $qr->code, 'ticket_id' => $own->id,
        ])->assertNotFound()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(QueueStatus::WaitingCheckin, $own->refresh()->status);
        $this->assertSame(QueueStatus::WaitingCheckin, $foreign->refresh()->status);
        $this->assertDatabaseCount('queue_events', 2);

        // The URL selects the ticket. A body ID cannot redirect the granted action.
        $this->postJson(route('queue.check-in', $own->id), [
            'qr_code' => $qr->code, 'ticket_id' => $foreign->id,
        ])->assertOk()->assertJsonPath('ticket.id', $own->id);
        $this->assertSame(QueueStatus::WaitingCheckin, $foreign->refresh()->status);
        $this->assertNull($foreign->checked_in_at);
    }

    public function test_an_inactive_or_unknown_scanned_qr_cannot_change_the_granted_ticket(): void
    {
        $this->openQueue();
        $ticket = $this->ticket();
        $this->grant($ticket);
        $inactive = QrCode::query()->create(['code' => str_repeat('x', 64), 'is_active' => false]);

        foreach ([$inactive->code, str_repeat('z', 64), 'https://outside.invalid/antrean/lokasi/not-a-studio-qr'] as $value) {
            $this->postJson(route('queue.check-in', $ticket->id), ['qr_code' => $value])->assertUnprocessable();
        }
        $this->assertSame(QueueStatus::WaitingCheckin, $ticket->refresh()->status);
        $this->assertNull($ticket->checked_in_at);
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertDatabaseCount('queue_events', 1);
    }

    public function test_scanning_at_the_grace_deadline_commits_expiry_once_instead_of_checking_in(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        $late = $this->ticket();
        $this->ticket(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]);
        app(QueueWorkflow::class)->callNext($barber->user, $barber->id);
        $this->grant($late);
        $number = $late->queue_number;
        $this->travelTo($late->refresh()->grace_expires_at);

        $this->postJson(route('queue.check-in', $late->id), ['qr_code' => $qr->code])->assertUnprocessable();
        $this->postJson(route('queue.check-in', $late->id), ['qr_code' => $qr->code])->assertUnprocessable();
        $this->assertSame(QueueStatus::Missed, $late->refresh()->status);
        $this->assertSame($number, $late->queue_number);
        $this->assertNull($late->checked_in_at);
        $this->assertSame(1, $late->events()->where('event_type', 'missed')->count());
        $this->assertSame(0, $late->events()->where('event_type', 'checked_in')->count());
        $this->assertDatabaseCount('queue_tickets', 2);
    }

    private function ticket(array $overrides = []): QueueTicket
    {
        return app(CreateQueueTicket::class)->create($this->queueInput($overrides), str_repeat('o', 64))->ticket;
    }

    private function grant(QueueTicket $ticket): void
    {
        $this->withSession(['queue' => ['grants' => [$ticket->id => $ticket->token_hash]]]);
    }
}
