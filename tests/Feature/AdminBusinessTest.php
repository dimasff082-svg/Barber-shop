<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Models\BusinessSetting;
use App\Models\QrCode;
use App\Models\StaffAuditEntry;
use App\Models\User;
use App\Services\Queue\CreateQueueTicket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class AdminBusinessTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    public function test_optional_contacts_can_be_cleared_without_writing_sql_null_into_json_values(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), [
            'business_name' => 'Studio Uji', 'address' => '', 'whatsapp' => '', 'map_url' => '',
            'queue_registration_open' => false, 'daily_queue_capacity' => 50, 'grace_minutes' => 5,
        ])->assertSessionHasNoErrors()->assertRedirect();
        foreach (['address', 'whatsapp', 'map_url'] as $key) {
            $this->assertSame('', BusinessSetting::query()->findOrFail($key)->value);
        }
        $this->get(route('public.contact'))->assertInertia(fn (Assert $page) => $page
            ->where('business.address', null)->where('business.whatsapp_url', null)->where('business.map_url', null));
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_replace(['business_name' => 'Studio Uji', 'address' => 'Alamat Studio Uji', 'whatsapp' => '081234567890', 'map_url' => 'https://maps.google.com/?q=barber', 'queue_registration_open' => false, 'daily_queue_capacity' => 50, 'grace_minutes' => 5], $overrides);
    }

    /** @return list<array<string, mixed>> */
    private function hours(): array
    {
        return array_map(fn (int $day) => ['weekday' => $day, 'starts_at' => '09:00', 'ends_at' => '18:00', 'is_closed' => $day === 0], range(0, 6));
    }

    public function test_business_pages_require_an_active_admin(): void
    {
        foreach (['admin.settings.edit', 'admin.schedules.index', 'admin.qr.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
            $this->actingAs(User::factory()->barber()->create())->get(route($route))->assertForbidden();
            auth()->logout();
        }
        $this->actingAs(User::factory()->barber()->create())->putJson(route('admin.settings.update'), $this->settings())->assertForbidden();
        $this->postJson(route('admin.qr.store'), ['label' => 'Tanpa izin', 'is_active' => true])->assertForbidden();
        $this->assertDatabaseCount('business_settings', 0);
        $this->assertDatabaseCount('qr_codes', 0);
    }

    public function test_settings_are_whitelisted_audited_and_reflected_on_the_public_site(): void
    {
        $admin = User::factory()->admin()->create();
        BusinessSetting::query()->create(['key' => 'private_setting', 'value' => 'PRIVATE_VALUE']);
        $this->actingAs($admin)->put(route('admin.settings.update'), $this->settings(['private_setting' => 'tampered']))->assertRedirect();
        $this->assertSame('+6281234567890', BusinessSetting::query()->findOrFail('whatsapp')->value);
        $this->assertFalse(BusinessSetting::query()->findOrFail('queue_registration_open')->value);
        $this->assertSame(50, BusinessSetting::query()->findOrFail('daily_queue_capacity')->value);
        $this->assertSame('PRIVATE_VALUE', BusinessSetting::query()->findOrFail('private_setting')->value);
        $this->assertDatabaseHas('staff_audits', ['actor_user_id' => $admin->id, 'action' => 'business_settings.updated']);
        $this->get(route('admin.settings.edit'))->assertInertia(fn (Assert $page) => $page->component('admin/settings')->where('settings.business_name', 'Studio Uji')->missing('settings.private_setting'));
        $this->get(route('public.contact'))->assertInertia(fn (Assert $page) => $page->where('business.address', 'Alamat Studio Uji')->where('business.whatsapp_url', 'https://wa.me/6281234567890'));
    }

    public function test_invalid_settings_are_rejected_without_partial_writes(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->putJson(route('admin.settings.update'), $this->settings(['daily_queue_capacity' => 0, 'grace_minutes' => 121, 'whatsapp' => 'not-a-phone', 'map_url' => 'javascript:alert(1)', 'timezone' => 'UTC']))
            ->assertUnprocessable()->assertJsonValidationErrors(['daily_queue_capacity', 'grace_minutes', 'whatsapp', 'map_url', 'timezone']);
        $this->assertDatabaseCount('business_settings', 0);
        $this->assertDatabaseCount('staff_audits', 0);
        $this->putJson(route('admin.settings.update'), $this->settings(['whatsapp' => ['unexpected'], 'timezone' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['whatsapp', 'timezone']);
        $this->assertDatabaseCount('business_settings', 0);
    }

    public function test_closing_registration_does_not_cancel_existing_tickets(): void
    {
        $this->openQueue();
        $created = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64));
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), $this->settings(['queue_registration_open' => false]))->assertRedirect();
        $this->assertSame('waiting_checkin', $created->ticket->refresh()->status->value);
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertDatabaseCount('queue_events', 1);
    }

    public function test_weekly_hours_require_distinct_days_and_valid_same_day_intervals(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $hours = $this->hours();
        $this->put(route('admin.schedules.hours'), ['hours' => $hours])->assertRedirect();
        $this->assertDatabaseCount('business_hours', 7);
        $this->assertNull(BusinessHour::query()->where('weekday', 0)->firstOrFail()->starts_at);
        $hours[1]['ends_at'] = '08:00';
        $this->putJson(route('admin.schedules.hours'), ['hours' => $hours])->assertUnprocessable()->assertJsonValidationErrors('hours.1.ends_at');
        $this->assertSame('18:00', substr(BusinessHour::query()->where('weekday', 1)->firstOrFail()->ends_at, 0, 5));
        $hours = $this->hours();
        $hours[2]['weekday'] = 1;
        $this->putJson(route('admin.schedules.hours'), ['hours' => $hours])->assertUnprocessable();
        $this->assertSame(1, StaffAuditEntry::query()->where('action', 'business_hours.updated')->count());
    }

    public function test_barber_schedules_are_saved_for_the_selected_active_barber_only(): void
    {
        $barber = Barber::factory()->create();
        $other = Barber::factory()->create();
        $rows = array_map(fn (int $day) => ['weekday' => $day, 'starts_at' => '10:00', 'ends_at' => '17:00', 'is_active' => $day > 0], range(0, 6));
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.schedules.barber', $barber->id), ['schedules' => $rows])->assertRedirect();
        $this->assertSame(7, BarberSchedule::query()->where('barber_id', $barber->id)->count());
        $this->assertSame(0, BarberSchedule::query()->where('barber_id', $other->id)->count());
        $barber->update(['is_active' => false]);
        $this->putJson(route('admin.schedules.barber', $barber->id), ['schedules' => $rows])->assertUnprocessable();
        $barber->update(['is_active' => true]);
        $barber->user->forceFill(['is_active' => false])->save();
        $this->putJson(route('admin.schedules.barber', $barber->id), ['schedules' => $rows])->assertUnprocessable();
        $this->actingAs($other->user)->putJson(route('admin.schedules.barber', $barber->id), ['schedules' => $rows])->assertForbidden();
    }

    public function test_closure_creation_and_removal_preserve_an_audit_trail(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.schedules.closures.store'), ['date' => '2026-12-25', 'reason' => 'Libur studio'])->assertRedirect();
        $closure = BusinessClosure::query()->firstOrFail();
        $this->postJson(route('admin.schedules.closures.store'), ['date' => '2026-12-25', 'reason' => 'Duplikat'])->assertUnprocessable();
        $this->delete(route('admin.schedules.closures.destroy', $closure->id))->assertRedirect();
        $this->assertDatabaseCount('business_closures', 0);
        $this->assertSame(['business_closure.created', 'business_closure.removed'], StaffAuditEntry::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_qr_creation_uses_server_generated_code_and_updates_preserve_it(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson(route('admin.qr.store'), ['label' => 'Meja studio', 'is_active' => true, 'code' => 'attacker-code'])->assertUnprocessable();
        $this->post(route('admin.qr.store'), ['label' => 'Meja studio', 'is_active' => false])->assertRedirect();
        $qr = QrCode::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $qr->code);
        $original = $qr->code;
        $this->putJson(route('admin.qr.update', $qr->id), ['label' => 'Tampered', 'is_active' => true, 'code' => null, 'target_path' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['code', 'target_path']);
        $this->assertSame($original, $qr->refresh()->code);
        $this->put(route('admin.qr.update', $qr->id), ['label' => 'Pintu masuk', 'is_active' => true])->assertRedirect();
        $this->assertSame($original, $qr->refresh()->code);
        $this->get(route('queue.location', $qr->code))->assertOk();
        $this->put(route('admin.qr.update', $qr->id), ['label' => 'Pintu masuk', 'is_active' => false])->assertRedirect();
        $this->get(route('queue.location', $qr->code))->assertNotFound();
        $this->assertDatabaseCount('qr_codes', 1);
        $this->assertDatabaseCount('staff_audits', 3);
    }

    public function test_qr_svg_encodes_the_exact_portal_url_and_print_escapes_labels(): void
    {
        $qr = QrCode::factory()->create(['label' => '<script>alert("unsafe")</script>', 'is_active' => false]);
        $this->get(route('admin.qr.image', $qr->id))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->barber()->create())->get(route('admin.qr.print', $qr->id))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        $expected = (new Writer(new ImageRenderer(new RendererStyle(400, 32), new SvgImageBackEnd)))->writeString(route('queue.location', $qr->code));
        $this->get(route('admin.qr.image', $qr->id))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertContent($expected);
        $this->get(route('admin.qr.download', $qr->id))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="barberflow-qr-'.$qr->id.'.svg"');
        $this->get(route('admin.qr.print', $qr->id))->assertOk()->assertSee('QR NONAKTIF')->assertSee('belum menjadi bukti kehadiran fisik')->assertDontSee('<script>alert', false);
    }

    public function test_business_lists_are_paginated_and_do_not_expose_user_credentials(): void
    {
        $barber = Barber::factory()->create();
        QrCode::factory()->count(12)->create();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.qr.index'))->assertInertia(fn (Assert $page) => $page->component('admin/qr/index')->has('codes.data', 10));
        $this->get(route('admin.schedules.index'))->assertInertia(fn (Assert $page) => $page->component('admin/schedules')->has('hours', 7)->where('barbers.0.id', $barber->id)->missing('barbers.0.user')->missing('barbers.0.password'));
    }
}
