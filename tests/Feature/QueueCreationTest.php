<?php

namespace Tests\Feature;

use App\Data\CreatedQueueTicket;
use App\Enums\BarberAvailability;
use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Models\BusinessSetting;
use App\Models\QueueCounter;
use App\Models\QueueEvent;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\PhoneNormalizer;
use App\Services\Queue\QueueEstimate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class QueueCreationTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    private const OWNER = 'queue-owner-secret-used-only-by-tests-123';

    public function test_online_and_onsite_share_one_counter_and_store_only_secret_hashes(): void
    {
        ['qr' => $qr] = $this->openQueue();
        $online = $this->createTicket($this->queueInput(['phone' => '0812 3456-7890']));
        $onsite = $this->createTicket($this->queueInput(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]));

        $this->assertTrue($online->created);
        $this->assertSame('A-001', $online->ticket->queue_number);
        $this->assertSame('A-002', $onsite->ticket->queue_number);
        $this->assertSame(QueueStatus::WaitingCheckin, $online->ticket->status);
        $this->assertNull($online->ticket->checked_in_at);
        $this->assertSame(QueueStatus::CheckedIn, $onsite->ticket->status);
        $this->assertNotNull($onsite->ticket->checked_in_at);
        $this->assertSame('+6281234567890', $online->ticket->normalized_phone);
        $this->assertSame(hash('sha256', $online->accessToken), $online->ticket->token_hash);
        $this->assertSame(64, strlen($online->accessToken));
        $this->assertArrayNotHasKey('token_hash', $online->ticket->toArray());
        $this->assertSame(2, QueueCounter::query()->sole()->last_number);
        $this->assertDatabaseCount('queue_events', 2);
        $this->assertStringNotContainsString($online->accessToken, QueueEvent::query()->get()->toJson());
    }

    public function test_same_canonical_request_is_idempotent_without_reissuing_the_token_even_after_closure(): void
    {
        $this->openQueue();
        $input = $this->queueInput(['phone' => '081234567890']);
        $first = $this->createTicket($input);
        BusinessSetting::query()->whereKey('queue_registration_open')->update(['value' => false]);
        $second = $this->createTicket(array_replace($input, ['phone' => '+6281234567890']));

        $this->assertSame($first->ticket->id, $second->ticket->id);
        $this->assertFalse($second->created);
        $this->assertNull($second->accessToken);
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertSame(1, QueueCounter::query()->sole()->last_number);
        $this->assertDatabaseCount('queue_events', 1);
    }

    public function test_an_idempotency_key_cannot_be_reused_with_different_payload(): void
    {
        $this->openQueue();
        $input = $this->queueInput();
        $this->createTicket($input);
        $this->expectException(ConflictHttpException::class);
        $this->createTicket(array_replace($input, ['name' => 'Nama berbeda']));
    }

    public function test_an_idempotency_key_does_not_grant_a_different_session_access(): void
    {
        $this->openQueue();
        $input = $this->queueInput();
        $this->createTicket($input);
        $this->expectException(AccessDeniedHttpException::class);
        app(CreateQueueTicket::class)->create($input, 'another-owner-secret-used-only-by-tests');
    }

    public function test_normalized_phone_cannot_have_two_active_tickets_even_for_another_barber(): void
    {
        $this->openQueue();
        $first = $this->queueInput(['phone' => '081234567890']);
        $this->createTicket($first);
        $barber = Barber::factory()->create(['queue_prefix' => 'B', 'accepts_queue' => true, 'availability_status' => BarberAvailability::Available]);
        BarberSchedule::query()->create(['barber_id' => $barber->id, 'weekday' => 1, 'starts_at' => '09:00:00', 'ends_at' => '20:00:00', 'is_active' => true]);

        $this->assertRejected($this->queueInput(['phone' => '+6281234567890', 'barber_id' => $barber->id]), 'phone');
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertDatabaseCount('queue_counters', 1);
    }

    public function test_cancelled_numbers_are_not_reused_and_same_phone_can_register_again(): void
    {
        $this->openQueue();
        $ticket = $this->createTicket($this->queueInput(['phone' => '081234567890']))->ticket;
        $ticket->update(['status' => QueueStatus::Cancelled, 'cancelled_at' => now()->utc()]);
        $next = $this->createTicket($this->queueInput(['phone' => '081234567890']));

        $this->assertSame(2, $next->ticket->sequence_number);
        $this->assertDatabaseCount('queue_tickets', 2);
    }

    public function test_barber_capacity_counts_all_issued_tickets_including_cancelled(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $barber->update(['daily_capacity' => 1]);
        $ticket = $this->createTicket($this->queueInput())->ticket;
        $ticket->update(['status' => QueueStatus::Cancelled, 'cancelled_at' => now()->utc()]);

        $this->assertRejected($this->queueInput(), 'barber_id');
        $this->assertSame(1, QueueCounter::query()->sole()->last_number);
    }

    public function test_business_capacity_is_used_when_barber_has_no_override(): void
    {
        $this->openQueue();
        BusinessSetting::query()->create(['key' => 'daily_queue_capacity', 'value' => 1]);
        $this->createTicket($this->queueInput());
        $this->assertRejected($this->queueInput(), 'barber_id');
    }

    public function test_prices_and_durations_are_snapshots_of_the_active_service(): void
    {
        ['service' => $service] = $this->openQueue();
        $ticket = $this->createTicket($this->queueInput())->ticket;
        $service->update(['price' => '90000.00', 'estimated_duration_minutes' => 60]);

        $this->assertSame('50000.00', $ticket->refresh()->price_snapshot);
        $this->assertSame(30, $ticket->duration_snapshot);
    }

    public function test_business_date_uses_makassar_and_counter_restarts_on_the_next_business_day(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $first = $this->createTicket($this->queueInput());
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00:00', 'Asia/Makassar'));
        BusinessHour::query()->create(['weekday' => 2, 'starts_at' => '08:00:00', 'ends_at' => '21:00:00', 'is_closed' => false]);
        BarberSchedule::query()->create(['barber_id' => $barber->id, 'weekday' => 2, 'starts_at' => '09:00:00', 'ends_at' => '20:00:00', 'is_active' => true]);
        $second = $this->createTicket($this->queueInput());

        $this->assertSame('2026-10-26', $first->ticket->queue_date->toDateString());
        $this->assertSame('2026-10-27', $second->ticket->queue_date->toDateString());
        $this->assertSame('A-001', $second->ticket->queue_number);
    }

    public function test_business_date_can_differ_from_the_utc_timestamp_date(): void
    {
        ['barber' => $barber] = $this->openQueue();
        BusinessHour::query()->where('weekday', 1)->update(['starts_at' => '00:00:00', 'ends_at' => '23:59:00']);
        BarberSchedule::query()->where('barber_id', $barber->id)->update(['starts_at' => '00:00:00', 'ends_at' => '23:59:00']);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 00:30:00', 'Asia/Makassar'));

        $ticket = $this->createTicket($this->queueInput())->ticket->refresh();

        $this->assertSame('2026-10-26', $ticket->queue_date->toDateString());
        $this->assertSame('2026-10-25 16:30:00', $ticket->created_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_missing_or_invalid_registration_configuration_fails_closed(): void
    {
        $this->openQueue();
        BusinessSetting::query()->whereKey('queue_registration_open')->delete();
        $this->assertRejected($this->queueInput(), 'queue');
        BusinessSetting::query()->create(['key' => 'queue_registration_open', 'value' => 'true']);
        $this->assertRejected($this->queueInput(), 'queue');
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_business_holiday_rejects_new_tickets(): void
    {
        $this->openQueue();
        BusinessClosure::query()->create(['date' => '2026-10-26', 'reason' => 'Libur']);
        $this->assertRejected($this->queueInput(), 'queue');
    }

    public function test_missing_or_closed_business_hours_reject_tickets(): void
    {
        $this->openQueue();
        BusinessHour::query()->where('weekday', 1)->update(['is_closed' => true]);
        $this->assertRejected($this->queueInput(), 'queue');
        BusinessHour::query()->delete();
        $this->assertRejected($this->queueInput(), 'queue');
    }

    public function test_registration_is_rejected_at_the_exact_end_of_the_barber_schedule(): void
    {
        $this->openQueue();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 20:00:00', 'Asia/Makassar'));
        $this->assertRejected($this->queueInput(), 'barber_id');
    }

    public function test_registration_is_rejected_at_the_exact_business_closing_time(): void
    {
        $this->openQueue();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 21:00:00', 'Asia/Makassar'));
        $this->assertRejected($this->queueInput(), 'queue');
    }

    public function test_inactive_service_or_staff_and_barber_reject_registration(): void
    {
        ['barber' => $barber, 'service' => $service] = $this->openQueue();
        $service->update(['is_active' => false]);
        $this->assertRejected($this->queueInput(), 'service_id');
        $service->update(['is_active' => true]);
        $barber->user->forceFill(['is_active' => false])->save();
        $this->assertRejected($this->queueInput(), 'barber_id');
        $barber->user->forceFill(['is_active' => true])->save();
        $barber->update(['is_active' => false]);
        $this->assertRejected($this->queueInput(), 'barber_id');
    }

    public function test_break_off_duty_and_queue_toggle_reject_registration_but_busy_is_allowed(): void
    {
        ['barber' => $barber] = $this->openQueue();
        foreach ([BarberAvailability::Break, BarberAvailability::OffDuty] as $state) {
            $barber->update(['availability_status' => $state]);
            $this->assertRejected($this->queueInput(), 'barber_id');
        }
        $barber->update(['availability_status' => BarberAvailability::Available, 'accepts_queue' => false]);
        $this->assertRejected($this->queueInput(), 'barber_id');
        $barber->update(['availability_status' => BarberAvailability::Busy, 'accepts_queue' => true]);
        $this->assertTrue($this->createTicket($this->queueInput())->created);
    }

    public function test_onsite_requires_an_active_qr_and_cannot_trust_source_alone(): void
    {
        ['qr' => $qr] = $this->openQueue();
        $this->assertRejected($this->queueInput(['source' => QueueSource::Onsite]), 'qr_code');
        $qr->update(['is_active' => false]);
        $this->assertRejected($this->queueInput(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]), 'qr_code');
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_estimates_use_ready_and_running_tickets_without_starting_grace_or_mutating_state(): void
    {
        ['qr' => $qr] = $this->openQueue();
        $waiting = $this->createTicket($this->queueInput())->ticket;
        $this->createTicket($this->queueInput(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]));
        $last = $this->createTicket($this->queueInput())->ticket;
        $estimate = app(QueueEstimate::class)->forTicket($last);

        $this->assertSame(1, $estimate['ahead']);
        $this->assertSame(24, $estimate['minutes_min']);
        $this->assertSame(72, $estimate['minutes_max']);
        $this->assertNull($waiting->refresh()->grace_started_at);
        $this->assertDatabaseCount('queue_events', 3);
    }

    #[DataProvider('validPhones')]
    public function test_phone_formats_normalize_consistently(string $input, string $expected): void
    {
        $this->assertSame($expected, app(PhoneNormalizer::class)->normalize($input));
    }

    /** @return array<string, array{string, string}> */
    public static function validPhones(): array
    {
        return [
            'domestic' => ['0812 3456-7890', '+6281234567890'],
            'international' => ['+62 (812) 34567890', '+6281234567890'],
            'country code' => ['6281234567890', '+6281234567890'],
        ];
    }

    #[DataProvider('invalidPhones')]
    public function test_invalid_and_non_mobile_phone_numbers_are_rejected(string $input): void
    {
        $this->expectException(ValidationException::class);
        app(PhoneNormalizer::class)->normalize($input);
    }

    /** @return array<string, array{string}> */
    public static function invalidPhones(): array
    {
        return ['short' => ['0812'], 'long' => ['081234567890123456'], 'landline' => ['0211234567'], 'foreign' => ['+14155552671'], 'letters' => ['0812abc3456789']];
    }

    /** @param array<string, mixed> $input */
    private function createTicket(array $input): CreatedQueueTicket
    {
        return app(CreateQueueTicket::class)->create($input, self::OWNER);
    }

    /** @param array<string, mixed> $input */
    private function assertRejected(array $input, string $field): void
    {
        try {
            $this->createTicket($input);
            $this->fail('The registration should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
