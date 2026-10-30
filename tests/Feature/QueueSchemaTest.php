<?php

namespace Tests\Feature;

use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessHour;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\QrCode;
use App\Models\QueueCounter;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueueSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_domain_casts_relationships_and_secret_serialization(): void
    {
        $ticket = QueueTicket::factory()->create(['price_snapshot' => '45000.50', 'duration_snapshot' => 30]);
        $event = $ticket->events()->create(['event_type' => 'created', 'new_status' => QueueStatus::WaitingCheckin, 'metadata' => ['source' => 'online']]);

        $this->assertTrue($ticket->is($ticket->customer->tickets()->first()));
        $this->assertTrue($ticket->is($ticket->barber->tickets()->first()));
        $this->assertSame(QueueSource::Online, $ticket->source);
        $this->assertSame(QueueStatus::WaitingCheckin, $ticket->status);
        $this->assertInstanceOf(CarbonImmutable::class, $ticket->queue_date);
        $this->assertSame('45000.50', $ticket->price_snapshot);
        $this->assertSame(30, $ticket->duration_snapshot);
        $this->assertTrue($event->ticket->is($ticket));
        $this->assertSame(['source' => 'online'], $event->metadata);
        $this->assertNull($event->previous_status);
        foreach (['token_hash', 'owner_hash', 'request_fingerprint', 'idempotency_key', 'normalized_phone'] as $secret) {
            $this->assertArrayNotHasKey($secret, $ticket->toArray());
        }
        $this->assertArrayNotHasKey('normalized_phone', $ticket->customer->toArray());
    }

    public function test_business_configuration_accepts_json_scalars_and_defaults_closed(): void
    {
        BusinessSetting::query()->create(['key' => 'queue_registration_open', 'value' => false]);
        BusinessSetting::query()->create(['key' => 'grace_minutes', 'value' => 5]);
        $hour = BusinessHour::query()->create(['weekday' => 1])->refresh();
        $qr = QrCode::query()->create(['code' => bin2hex(random_bytes(32))])->refresh();
        $barber = Barber::factory()->create()->refresh();

        $this->assertFalse(BusinessSetting::query()->findOrFail('queue_registration_open')->value);
        $this->assertSame(5, BusinessSetting::query()->findOrFail('grace_minutes')->value);
        $this->assertTrue($hour->is_closed);
        $this->assertFalse($qr->is_active);
        $this->assertFalse($barber->accepts_queue);
        $this->assertNull($barber->daily_capacity);
    }

    public function test_counter_is_unique_per_barber_and_date(): void
    {
        $counter = QueueCounter::factory()->create();
        $this->expectException(QueryException::class);
        QueueCounter::factory()->create(['barber_id' => $counter->barber_id, 'queue_date' => $counter->queue_date]);
    }

    public function test_sequence_is_unique_per_barber_and_date(): void
    {
        $ticket = QueueTicket::factory()->create();
        $this->expectException(QueryException::class);
        QueueTicket::factory()->create(['barber_id' => $ticket->barber_id, 'queue_date' => $ticket->queue_date, 'sequence_number' => $ticket->sequence_number]);
    }

    public function test_idempotency_keys_cannot_be_reused_even_on_another_barber(): void
    {
        $ticket = QueueTicket::factory()->create();
        $this->expectException(QueryException::class);
        QueueTicket::factory()->create(['idempotency_key' => $ticket->idempotency_key]);
    }

    public function test_active_phone_uniqueness_spans_all_barbers_for_the_day(): void
    {
        $ticket = QueueTicket::factory()->create();
        $this->expectException(QueryException::class);
        QueueTicket::factory()->create(['customer_id' => $ticket->customer_id, 'queue_date' => $ticket->queue_date]);
    }

    public function test_terminal_ticket_does_not_block_a_new_ticket_for_that_phone(): void
    {
        $ticket = QueueTicket::factory()->create(['status' => QueueStatus::Completed]);
        $new = QueueTicket::factory()->create(['customer_id' => $ticket->customer_id, 'queue_date' => $ticket->queue_date]);
        $this->assertNotSame($ticket->id, $new->id);
    }

    public function test_only_one_called_or_in_service_ticket_can_exist_per_barber_across_days(): void
    {
        $ticket = QueueTicket::factory()->create(['status' => QueueStatus::InService]);
        $this->expectException(QueryException::class);
        QueueTicket::factory()->create(['barber_id' => $ticket->barber_id, 'queue_date' => $ticket->queue_date->addDay(), 'status' => QueueStatus::Called]);
    }

    #[DataProvider('invalidTicketFields')]
    public function test_database_rejects_invalid_ticket_fields(array $attributes): void
    {
        $ticket = QueueTicket::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('queue_tickets')->where('id', $ticket->id)->update($attributes);
    }

    public static function invalidTicketFields(): array
    {
        return [
            'zero sequence' => [['sequence_number' => 0]],
            'negative price' => [['price_snapshot' => -1]],
            'zero duration' => [['duration_snapshot' => 0]],
            'invalid status' => [['status' => 'invalid']],
            'invalid source' => [['source' => 'invalid']],
            'onsite waiting for checkin' => [['source' => 'onsite', 'status' => 'waiting_checkin']],
            'unstarted grace' => [['grace_expires_at' => '2026-10-28 10:05:00']],
            'reversed grace' => [['grace_started_at' => '2026-10-28 10:05:00', 'grace_expires_at' => '2026-10-28 10:00:00']],
            'noncanonical phone' => [['normalized_phone' => '081234567890']],
            'phone with non digits' => [['normalized_phone' => '+62812345678a']],
            'short hash' => [['token_hash' => 'abc']],
            'non hex hash' => [['owner_hash' => str_repeat('z', 64)]],
        ];
    }

    #[DataProvider('invalidBusinessConfiguration')]
    public function test_database_rejects_invalid_business_configuration(string $table, array $attributes): void
    {
        $this->expectException(QueryException::class);
        DB::table($table)->insert($attributes);
    }

    public static function invalidBusinessConfiguration(): array
    {
        return [
            ['business_hours', ['weekday' => 7]],
            ['business_hours', ['weekday' => 1, 'is_closed' => false, 'starts_at' => null, 'ends_at' => null]],
            ['business_hours', ['weekday' => 1, 'is_closed' => false, 'starts_at' => '20:00:00', 'ends_at' => '09:00:00']],
            ['customers', ['name' => 'Test customer', 'normalized_phone' => '+62123456789']],
        ];
    }

    public function test_database_rejects_reversed_barber_schedule(): void
    {
        $barber = Barber::factory()->create();
        $this->expectException(QueryException::class);
        BarberSchedule::factory()->create(['barber_id' => $barber->id, 'starts_at' => '20:00:00', 'ends_at' => '09:00:00']);
    }

    #[DataProvider('auditMutations')]
    public function test_audit_history_cannot_be_changed_even_through_raw_queries(string $operation): void
    {
        $event = QueueEvent::factory()->create();
        $query = DB::table('queue_events')->where('id', $event->id);
        $this->expectException(QueryException::class);
        if ($operation === 'update') {
            $query->update(['reason' => 'Changed history']);
        } else {
            $query->delete();
        }
    }

    public static function auditMutations(): array
    {
        return [['update'], ['delete']];
    }

    public function test_model_prevents_audit_event_updates(): void
    {
        $event = QueueEvent::factory()->create();
        $this->expectException(LogicException::class);
        $event->update(['reason' => 'Changed history']);
    }

    public function test_model_prevents_historical_ticket_deletion(): void
    {
        $ticket = QueueTicket::factory()->create();
        $this->expectException(LogicException::class);
        $ticket->delete();
    }

    public function test_database_prevents_historical_ticket_deletion(): void
    {
        $ticket = QueueTicket::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('queue_tickets')->where('id', $ticket->id)->delete();
    }

    public function test_customer_referenced_by_a_ticket_cannot_be_deleted(): void
    {
        $ticket = QueueTicket::factory()->create();
        $this->expectException(QueryException::class);
        Customer::query()->where('id', $ticket->customer_id)->delete();
    }
}
