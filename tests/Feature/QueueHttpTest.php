<?php

namespace Tests\Feature;

use App\Enums\QueueSource;
use App\Models\Barber;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Queue\CreateQueueTicket;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class QueueHttpTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function form(array $overrides = []): array
    {
        $input = $this->queueInput($overrides);
        unset($input['source'], $input['qr_code']);

        return [...$input, 'consent' => true];
    }

    public function test_creation_grants_private_session_and_only_once_returns_raw_token(): void
    {
        $this->openQueue();
        $input = $this->form();
        $first = $this->postJson(route('queue.store'), $input)->assertCreated();
        $id = $first->json('ticket.id');
        $token = $first->json('access_token');
        $this->assertIsString($token);
        $this->assertSame(hash('sha256', $token), QueueTicket::findOrFail($id)->token_hash);
        $first->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $first->assertJsonMissingPath('ticket.normalized_phone')->assertJsonMissingPath('ticket.token_hash');

        $this->getJson(route('queue.status', $id))->assertOk();
        $this->postJson(route('queue.store'), $input)->assertOk()
            ->assertJsonPath('ticket.id', $id)->assertJsonPath('access_token', null)->assertJsonPath('created', false);
        $this->assertDatabaseCount('queue_tickets', 1);
    }

    public function test_ticket_identifier_phone_and_query_token_do_not_authorize_access(): void
    {
        $this->openQueue();
        $created = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('p', 64));
        $url = route('queue.status', $created->ticket->id);
        $missing = $this->getJson($url)->assertNotFound()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $missing->headers->get('Cache-Control'));
        $this->getJson($url.'?token='.$created->accessToken)->assertNotFound();
        $this->withToken(str_repeat('x', 64))->getJson($url)->assertNotFound();
        $this->withToken($created->accessToken)->getJson($url)->assertOk();
        $another = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('b', 64));
        $this->withToken($created->accessToken)->getJson(route('queue.status', $another->ticket->id))->assertNotFound();
    }

    public function test_bearer_exchange_restores_access_in_new_session_and_checkin_keeps_number(): void
    {
        $scenario = $this->openQueue();
        $created = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64));
        $id = $created->ticket->id;
        $this->withToken($created->accessToken)->postJson(route('queue.access', $id))->assertOk();
        $this->withHeaders(['Authorization' => ''])->getJson(route('queue.status', $id))->assertOk();
        $this->postJson(route('queue.check-in', $id), ['qr_code' => $scenario['qr']->code])
            ->assertOk()->assertJsonPath('ticket.status', 'checked_in')
            ->assertJsonPath('ticket.queue_number', $created->ticket->queue_number);
        $this->postJson(route('queue.cancel', $id))->assertOk()->assertJsonPath('ticket.status', 'cancelled');
        $this->assertDatabaseCount('queue_tickets', 1);
    }

    public function test_private_mutations_reject_unauthorized_guests(): void
    {
        $scenario = $this->openQueue();
        $created = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64));
        $this->postJson(route('queue.check-in', $created->ticket->id), ['qr_code' => $scenario['qr']->code])->assertNotFound();
        $this->postJson(route('queue.cancel', $created->ticket->id))->assertNotFound();
        $this->postJson(route('queue.access', $created->ticket->id))->assertNotFound();
        $this->assertSame('waiting_checkin', $created->ticket->refresh()->status->value);
    }

    public function test_route_controls_source_and_requires_consent(): void
    {
        $scenario = $this->openQueue();
        $input = $this->form();
        $this->postJson(route('queue.store'), [...$input, 'source' => QueueSource::Onsite->value])->assertUnprocessable();
        $this->postJson(route('queue.store'), [...$input, 'status' => 'completed'])->assertUnprocessable();
        $this->postJson(route('queue.store'), [...$input, 'consent' => false])->assertUnprocessable();
        $this->postJson(route('queue.onsite.store', 'inactive'))->assertUnprocessable();
        Cache::flush();
        $this->postJson(route('queue.onsite.store', $scenario['qr']->code), $input)
            ->assertCreated()->assertJsonPath('ticket.source', 'onsite')->assertJsonPath('ticket.status', 'checked_in');
    }

    public function test_retry_key_does_not_disclose_ticket_to_different_owner(): void
    {
        $this->openQueue();
        $input = $this->queueInput();
        app(CreateQueueTicket::class)->create($input, str_repeat('a', 64));
        unset($input['source'], $input['qr_code']);
        $this->postJson(route('queue.store'), [...$input, 'consent' => true])->assertForbidden();
        $this->assertDatabaseCount('queue_tickets', 1);
    }

    public function test_mutations_and_access_are_rate_limited(): void
    {
        $this->openQueue();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('queue.store'), [])->assertUnprocessable();
        }
        $limited = $this->postJson(route('queue.store'), [])->assertTooManyRequests()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $limited->headers->get('Cache-Control'));

        $id = '01K00000000000000000000000';
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('queue.access', $id))->assertNotFound();
        }
        $this->postJson(route('queue.access', $id))->assertTooManyRequests();
    }

    public function test_web_mutation_rejects_missing_csrf_and_accepts_matching_csrf(): void
    {
        $this->openQueue();
        // Enable the real CSRF check normally bypassed by Laravel's test runtime.
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $input = $this->form();
        $this->postJson(route('queue.store'), $input)->assertStatus(419);
        $this->withSession(['_token' => 'csrf-fixture'])->withHeader('X-CSRF-TOKEN', 'csrf-fixture')
            ->postJson(route('queue.store'), $input)->assertCreated();
    }

    public function test_queue_routes_lock_the_session_to_preserve_owner_and_grants(): void
    {
        foreach (['queue.store', 'queue.onsite.store', 'queue.access', 'queue.check-in', 'queue.cancel', 'queue.status'] as $name) {
            $this->assertSame(30, app('router')->getRoutes()->getByName($name)->locksFor());
        }
    }

    public function test_staff_routes_require_login_and_enforce_barber_ownership(): void
    {
        $scenario = $this->openQueue();
        $other = Barber::factory()->create();
        $created = app(CreateQueueTicket::class)->create($this->queueInput(['source' => QueueSource::Onsite, 'qr_code' => $scenario['qr']->code]), str_repeat('o', 64));
        $this->postJson(route('queue.staff.call', $scenario['barber']->id))->assertUnauthorized();
        $this->actingAs($other->user)->postJson(route('queue.staff.call', $scenario['barber']->id))->assertForbidden();
        $this->actingAs($scenario['barber']->user)->postJson(route('queue.staff.call', $scenario['barber']->id))
            ->assertOk()->assertJsonPath('ticket.id', $created->ticket->id);
        $this->postJson(route('queue.staff.cancel', $created->ticket->id), ['reason' => 'Alasan koreksi'])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('queue.staff.cancel', $created->ticket->id), ['reason' => 'Koreksi oleh admin'])
            ->assertOk()->assertJsonPath('ticket.status', 'cancelled');
    }
}
