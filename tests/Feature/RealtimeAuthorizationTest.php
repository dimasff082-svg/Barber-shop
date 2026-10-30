<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class RealtimeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-reverb-key',
            'broadcasting.connections.reverb.secret' => 'test-reverb-secret',
            'broadcasting.connections.reverb.app_id' => 'test-reverb-app',
            'broadcasting.connections.reverb.options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
    }

    public function test_admin_and_active_owning_barber_can_authorize_only_their_private_channels(): void
    {
        $barber = Barber::factory()->create();
        $other = Barber::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))
            ->assertOk()->assertJsonStructure(['auth'])->assertHeader('Referrer-Policy', 'no-referrer');
        $this->postJson(route('broadcasting.auth'), $this->payload('staff.barber.'.$barber->id))->assertForbidden();
        $this->actingAs($barber->user)->postJson(route('broadcasting.auth'), $this->payload('staff.barber.'.$barber->id))
            ->assertOk()->assertJsonStructure(['auth']);
        $this->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))->assertForbidden();
        $response = $this->postJson(route('broadcasting.auth'), $this->payload('staff.barber.'.$other->id))->assertForbidden();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_guests_revoked_accounts_inactive_profiles_and_missing_profiles_are_rejected(): void
    {
        $this->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))->assertUnauthorized()->assertHeader('Referrer-Policy', 'no-referrer');
        $barber = Barber::factory()->create(['is_active' => false]);
        $this->actingAs($barber->user)->postJson(route('broadcasting.auth'), $this->payload('staff.barber.'.$barber->id))->assertForbidden();
        $this->actingAs(User::factory()->barber()->create())->postJson(route('broadcasting.auth'), $this->payload('staff.barber.999'))->assertForbidden();
        $revoked = User::factory()->admin()->create(['is_active' => false]);
        $this->actingAs($revoked)->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))->assertUnauthorized();
    }

    public function test_auth_is_post_only_validates_channel_and_socket_and_is_rate_limited(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson(route('broadcasting.auth'))->assertMethodNotAllowed();
        $this->postJson(route('broadcasting.auth'), ['socket_id' => 'forged', 'channel_name' => 'private-staff.admin'])
            ->assertUnprocessable()->assertJsonValidationErrors('socket_id');
        $this->postJson(route('broadcasting.auth'), $this->payload('customer.secret'))->assertUnprocessable()->assertJsonValidationErrors('channel_name');
        for ($i = 0; $i < 58; $i++) {
            $this->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))->assertOk();
        }
        $this->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))->assertTooManyRequests()->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_websocket_authorization_does_not_bypass_web_csrf_protection(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->actingAs(User::factory()->admin()->create())->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))
            ->assertStatus(419)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->withSession(['_token' => 'csrf-fixture'])->withHeader('X-CSRF-TOKEN', 'csrf-fixture')
            ->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))->assertOk();
    }

    public function test_disabled_realtime_does_not_issue_a_successful_channel_authorization(): void
    {
        config(['broadcasting.default' => 'null']);
        $this->actingAs(User::factory()->admin()->create())->postJson(route('broadcasting.auth'), $this->payload('staff.admin'))
            ->assertServiceUnavailable()->assertHeader('Referrer-Policy', 'no-referrer');
    }

    /** @return array{socket_id: string, channel_name: string} */
    private function payload(string $channel): array
    {
        return ['socket_id' => '1234.5678', 'channel_name' => 'private-'.$channel];
    }
}
