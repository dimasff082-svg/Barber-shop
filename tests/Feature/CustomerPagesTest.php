<?php

namespace Tests\Feature;

use App\Enums\BarberAvailability;
use App\Models\Barber;
use App\Models\BusinessClosure;
use App\Models\BusinessSetting;
use App\Models\GalleryImage;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Services\Queue\CreateQueueTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_public_pages_work_without_operational_data_and_gallery_is_labelled_illustration(): void
    {
        foreach (['home' => 'welcome', 'public.services' => 'public/services', 'public.barbers' => 'public/barbers', 'public.gallery' => 'public/gallery', 'public.contact' => 'public/contact'] as $route => $component) {
            $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component)
                ->has('services', 0)->has('barbers', 0)->where('registration.open', false));
        }
        $this->get(route('public.gallery'))->assertInertia(fn (Assert $page) => $page->where('illustrative', true)->has('images', 3));
    }

    public function test_public_catalog_exposes_active_profiles_and_aggregate_counts_without_customer_data(): void
    {
        ['barber' => $barber, 'service' => $service] = $this->openQueue();
        Service::factory()->create(['name' => 'Inactive private service', 'is_active' => false]);
        Barber::factory()->create(['is_active' => false]);
        $revoked = Barber::factory()->create();
        $revoked->user->forceFill(['is_active' => false])->save();
        $ticket = app(CreateQueueTicket::class)->create($this->queueInput(['name' => 'CUSTOMER_MUST_NOT_LEAK']), str_repeat('o', 64))->ticket;

        $response = $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('services', 1)->where('services.0.id', $service->id)->where('services.0.price', '50000.00')
            ->has('barbers', 1)->where('barbers.0.id', $barber->id)->where('barbers.0.waiting_count', 1)
            ->where('barbers.0.registration.allowed', true)->where('registration.open', true)
            ->missing('barbers.0.user')->missing('barbers.0.user_id')->missing('tickets'));
        foreach ([$ticket->customer_name, $ticket->normalized_phone, $ticket->token_hash, $ticket->owner_hash, $barber->user->email] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
    }

    public function test_form_reflects_backend_closure_and_barber_rules(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $this->get(route('queue.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('queue/index')
            ->where('registration.open', true)->where('qr', null)->where('selected_service', null));
        $barber->update(['availability_status' => BarberAvailability::Break]);
        $this->get(route('queue.index'))->assertInertia(fn (Assert $page) => $page->where('registration.open', false)
            ->where('barbers.0.registration.allowed', false));
        $barber->update(['availability_status' => BarberAvailability::Available]);
        BusinessClosure::query()->create(['date' => '2026-10-26']);
        $this->get(route('queue.index'))->assertInertia(fn (Assert $page) => $page->where('registration.open', false));
    }

    public function test_qr_portal_validates_context_and_returns_designed_invalid_state(): void
    {
        ['qr' => $qr, 'service' => $service, 'barber' => $barber] = $this->openQueue();
        $this->get(route('queue.location', $qr->code).'?service='.$service->id.'&barber='.$barber->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('queue/location')->where('qr.code', $qr->code)
                ->where('selected_service', $service->id)->where('selected_barber', $barber->id));
        $qr->update(['is_active' => false]);
        $this->get(route('queue.location', $qr->code))->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('queue/invalid-qr')->missing('qr'));
    }

    public function test_private_page_is_a_generic_wrapper_even_with_a_valid_session(): void
    {
        $this->openQueue();
        $created = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64));
        $id = $created->ticket->id;
        $this->withSession(['queue' => ['grants' => [$id => $created->ticket->token_hash]]]);
        $response = $this->get(route('queue.show', $id))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page->component('queue/ticket')->where('ticket_id', $id)
                ->missing('ticket')->missing('customer_name')->missing('access_token')->missing('normalized_phone'));
        $this->assertStringNotContainsString($created->accessToken, $response->getContent());
        $this->assertStringNotContainsString($created->ticket->token_hash, $response->getContent());
        $this->get(route('queue.show', '01k00000000000000000000000'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('queue/ticket')->missing('ticket'));
    }

    public function test_inertia_creation_redirects_without_secret_props_and_handoff_requires_access(): void
    {
        $this->openQueue();
        $input = $this->queueInput();
        unset($input['source']);
        $response = $this->withHeaders(['X-Inertia' => 'true', 'Accept' => 'text/html'])->post(route('queue.store'), [...$input, 'consent' => true]);
        $ticket = QueueTicket::query()->firstOrFail();
        $response->assertRedirect(route('queue.show', $ticket->id));
        $this->assertStringNotContainsString('#', $response->headers->get('Location'));
        $handoff = session('queue.handoff.'.$ticket->id);
        $this->assertIsArray($handoff);
        $token = Crypt::decryptString($handoff['ciphertext']);
        $this->assertSame($ticket->token_hash, hash('sha256', $token));
        $this->assertStringNotContainsString($token, json_encode(session()->all()));

        $this->withHeaders(['X-Inertia' => '', 'Accept' => 'application/json']);
        $this->postJson(route('queue.secret', $ticket->id))->assertOk()->assertJsonPath('access_token', $token);
        // A retry before acknowledgement remains safe across dropped responses / StrictMode remounts.
        $this->postJson(route('queue.secret', $ticket->id))->assertOk()->assertJsonPath('access_token', $token);
        $this->postJson(route('queue.secret.ack', $ticket->id))->assertOk();
        $this->postJson(route('queue.secret', $ticket->id))->assertOk()->assertJsonPath('access_token', null);
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->flushSession();
        $this->postJson(route('queue.secret', $ticket->id))->assertNotFound();
    }

    public function test_secret_handoff_expires_and_corrupted_ciphertext_fails_closed(): void
    {
        $this->openQueue();
        $created = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64));
        $id = $created->ticket->id;
        $this->withSession(['queue' => ['grants' => [$id => $created->ticket->token_hash], 'handoff' => [$id => [
            'ciphertext' => Crypt::encryptString($created->accessToken), 'expires' => now()->addMinutes(5)->timestamp,
        ]]]]);
        $this->travel(5)->minutes();
        $this->postJson(route('queue.secret', $id))->assertOk()->assertJsonPath('access_token', null);
        $this->withSession(['queue' => ['grants' => [$id => $created->ticket->token_hash], 'handoff' => [$id => [
            'ciphertext' => 'invalid', 'expires' => now()->addMinutes(5)->timestamp,
        ]]]]);
        $this->postJson(route('queue.secret', $id))->assertOk()->assertJsonPath('access_token', null);
    }

    public function test_gallery_and_contact_use_safe_published_data(): void
    {
        GalleryImage::query()->create(['title' => 'Karya studio', 'alt' => 'Hasil potongan studio', 'image_path' => 'gallery/cut.webp', 'is_active' => true]);
        GalleryImage::query()->create(['title' => 'Draft', 'alt' => 'Draft', 'image_path' => 'gallery/draft.webp']);
        GalleryImage::query()->create(['title' => 'Unsafe image', 'alt' => 'Unsafe', 'image_path' => 'https://tracker.invalid/secret.webp', 'is_active' => true]);
        $this->get(route('public.gallery'))->assertInertia(fn (Assert $page) => $page->where('illustrative', false)->has('images', 1)
            ->where('images.0.src', '/storage/gallery/cut.webp'));
        BusinessSetting::query()->create(['key' => 'address', 'value' => 'Alamat resmi studio']);
        BusinessSetting::query()->create(['key' => 'whatsapp', 'value' => '081234567890']);
        BusinessSetting::query()->create(['key' => 'map_url', 'value' => 'javascript:alert(1)']);
        BusinessSetting::query()->create(['key' => 'private_setting', 'value' => 'SECRET_SETTING_MUST_NOT_LEAK']);
        $response = $this->get(route('public.contact'))->assertInertia(fn (Assert $page) => $page
            ->where('business.address', 'Alamat resmi studio')->where('business.whatsapp_url', 'https://wa.me/6281234567890')
            ->where('business.map_url', null));
        $this->assertStringNotContainsString('SECRET_SETTING_MUST_NOT_LEAK', $response->getContent());
    }
}
