<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalRealtimeConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalRealtimeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_setup_is_private_idempotent_and_never_overwrites_an_existing_file(): void
    {
        $originalStorage = storage_path();
        $sandbox = $originalStorage.'/framework/testing/local-realtime-'.bin2hex(random_bytes(8));
        mkdir($sandbox, 0700, true);
        $this->app->useStoragePath($sandbox);
        $this->app->detectEnvironment(fn () => 'local');
        $path = $sandbox.'/app/private/realtime-local.json';
        try {
            $this->artisan('barberflow:realtime-local', ['--port' => '19085'])->assertSuccessful();
            $first = file_get_contents($path);
            $configuration = LocalRealtimeConfiguration::read($path);
            $this->assertNotNull($configuration);
            $this->assertSame(19085, $configuration['port']);
            $this->assertSame('127.0.0.1', $configuration['host']);
            $this->assertSame(['localhost', '127.0.0.1'], $configuration['origins']);
            $this->artisan('barberflow:realtime-local')->assertSuccessful();
            $this->assertSame($first, file_get_contents($path));
            file_put_contents($path, '{invalid');
            $this->artisan('barberflow:realtime-local')->assertFailed();
            $this->assertSame('{invalid', file_get_contents($path));
            $this->assertNull(LocalRealtimeConfiguration::read($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            foreach ([$sandbox.'/app/private', $sandbox.'/app', $sandbox] as $directory) {
                if (is_dir($directory)) {
                    rmdir($directory);
                }
            }
            $this->app->useStoragePath($originalStorage);
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_setup_cannot_be_used_outside_local_or_with_invalid_ports(): void
    {
        $this->artisan('barberflow:realtime-local')->assertFailed();
        $this->app->detectEnvironment(fn () => 'local');
        $this->artisan('barberflow:realtime-local', ['--port' => '80'])->assertFailed();
        $this->artisan('barberflow:realtime-local', ['--port' => 'invalid'])->assertFailed();
    }

    public function test_realtime_shared_props_only_expose_the_public_key_and_server_selected_channel(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'public-qa-key', 'secret' => 'NEVER_SHARE_THIS_SECRET', 'app_id' => 'private-app-id',
            'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http'],
        ]]);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('realtime.enabled', true)
                ->where('realtime.key', 'public-qa-key')->where('realtime.channel', 'staff.admin')->missing('realtime.secret'));
        $this->assertStringNotContainsString('NEVER_SHARE_THIS_SECRET', $response->getContent());
        $this->assertStringNotContainsString('private-app-id', $response->getContent());
        $this->actingAs(User::factory()->barber()->create())->get(route('barber.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('realtime.channel', null));
    }
}
