<?php

namespace Tests\Feature;

use App\Enums\QueueSource;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\GalleryImage;
use App\Models\Service;
use App\Models\User;
use App\Services\Admin\CatalogImages;
use App\Services\Queue\CreateQueueTicket;
use App\Services\Queue\QueueWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\QueueScenario;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use QueueScenario, RefreshDatabase;

    /** @return array<string, mixed> */
    private function barberInput(array $overrides = []): array
    {
        return [...[
            'name' => 'Barber Pengujian', 'email' => 'barber@example.test',
            'password' => 'Safe-Test-Password42!', 'password_confirmation' => 'Safe-Test-Password42!',
            'queue_prefix' => 'TST', 'specialization' => 'Classic', 'bio' => 'Profil uji',
            'is_active' => true, 'accepts_queue' => true, 'daily_capacity' => 30,
        ], ...$overrides];
    }

    /** @return array<string, mixed> */
    private function serviceInput(array $overrides = []): array
    {
        return [...['name' => 'Haircut Pengujian', 'description' => 'Layanan uji', 'price' => '45000.00', 'estimated_duration_minutes' => 30, 'is_active' => true], ...$overrides];
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('upload.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/F1EAAAAASUVORK5CYII='));
    }

    public function test_catalog_routes_require_active_admin_and_reject_barber_mutations(): void
    {
        foreach (['admin.barbers.index', 'admin.services.index', 'admin.gallery.index'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
        $this->postJson(route('admin.barbers.store'), $this->barberInput())->assertUnauthorized();
        $this->actingAs(User::factory()->barber()->create());
        foreach (['admin.barbers.index', 'admin.services.index', 'admin.gallery.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->postJson(route('admin.barbers.store'), $this->barberInput())->assertForbidden();
        $this->postJson(route('admin.services.store'), $this->serviceInput())->assertForbidden();
        $this->postJson(route('admin.gallery.store'), [])->assertForbidden();
    }

    public function test_admin_creates_barber_staff_with_hashed_password_and_private_serialization(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.barbers.store'), $this->barberInput(['email' => '  BARBER@Example.Test  ']))
            ->assertRedirect(route('admin.barbers.index'));
        $barber = Barber::query()->firstOrFail();
        $this->assertSame(UserRole::Barber, $barber->user->role);
        $this->assertSame('barber@example.test', $barber->user->email);
        $this->assertTrue(Hash::check('Safe-Test-Password42!', $barber->user->password));
        $this->assertTrue($barber->is_active);
        $this->assertSame('off_duty', $barber->availability_status->value);
        $response = $this->get(route('admin.barbers.index'))->assertInertia(fn (Assert $page) => $page->component('admin/barbers')
            ->has('barbers.data', 1)->missing('barbers.data.0.password')->missing('barbers.data.0.user')
            ->where('barbers.data.0.email', 'barber@example.test'));
        $this->assertStringNotContainsString($barber->user->password, $response->getContent());
        $this->assertStringNotContainsString('Safe-Test-Password42!', json_encode(DB::table('staff_audits')->get()));
        $this->assertSame(1, DB::table('staff_audits')->where('action', 'barber.created')->where('actor_user_id', $admin->id)->count());
    }

    public function test_barber_payload_cannot_promote_accounts_reassign_owner_or_set_availability(): void
    {
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.barbers.store'), $this->barberInput([
            'role' => 'admin', 'user_id' => 1, 'availability_status' => 'busy', 'photo' => 'https://tracker.invalid/pixel.png',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['role', 'user_id', 'availability_status', 'photo']);
        $this->assertDatabaseCount('barbers', 0);
    }

    public function test_invalid_and_duplicate_email_password_prefix_and_capacity_are_rejected(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'EXISTING@example.test']);
        $this->actingAs($admin)->postJson(route('admin.barbers.store'), $this->barberInput([
            'email' => 'existing@example.test', 'password' => 'password', 'password_confirmation' => 'password',
            'queue_prefix' => '1234', 'daily_capacity' => 0,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'queue_prefix', 'daily_capacity']);
        $this->assertDatabaseCount('barbers', 0);
    }

    public function test_barber_prefix_is_immutable_once_a_counter_or_ticket_exists(): void
    {
        ['barber' => $barber] = $this->openQueue();
        app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64));
        $this->actingAs(User::factory()->admin()->create())->putJson(route('admin.barbers.update', $barber), $this->barberInput([
            'email' => $barber->user->email, 'queue_prefix' => 'NEW',
        ]))->assertUnprocessable()->assertJsonValidationErrors('queue_prefix');
        $this->assertSame('A', $barber->refresh()->queue_prefix);
        $this->assertDatabaseCount('queue_tickets', 1);
    }

    public function test_processing_barber_cannot_be_archived_or_deactivated(): void
    {
        ['barber' => $barber, 'qr' => $qr] = $this->openQueue();
        app(CreateQueueTicket::class)->create($this->queueInput(['source' => QueueSource::Onsite, 'qr_code' => $qr->code]), str_repeat('o', 64));
        app(QueueWorkflow::class)->callNext($barber->user, $barber->id);
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson(route('admin.barbers.archive', $barber))->assertUnprocessable();
        $this->putJson(route('admin.barbers.update', $barber), $this->barberInput([
            'email' => $barber->user->email, 'queue_prefix' => 'A', 'is_active' => false,
        ]))->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->assertTrue($barber->refresh()->is_active);
        $this->assertTrue($barber->user->refresh()->is_active);
    }

    public function test_barber_account_can_be_updated_and_archived_without_deleting_history(): void
    {
        ['barber' => $barber] = $this->openQueue();
        $ticket = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64))->ticket;
        $oldPassword = $barber->user->password;
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.barbers.update', $barber), $this->barberInput([
            'name' => 'Nama baru', 'email' => $barber->user->email, 'queue_prefix' => 'A',
        ]))->assertRedirect();
        $this->assertSame('Nama baru', $barber->refresh()->name);
        $this->assertNotSame($oldPassword, $barber->user->refresh()->password);
        $this->post(route('admin.barbers.archive', $barber))->assertRedirect();
        $this->assertFalse($barber->refresh()->is_active);
        $this->assertFalse($barber->user->refresh()->is_active);
        $this->assertSame('waiting_checkin', $ticket->refresh()->status->value);
        $this->assertDatabaseCount('queue_events', 1);
        $this->assertDatabaseHas('barbers', ['id' => $barber->id]);
    }

    public function test_service_validation_and_path_tampering_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.services.store'), $this->serviceInput([
            'price' => '-1', 'estimated_duration_minutes' => 0, 'image_path' => '../../secret.png',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['price', 'estimated_duration_minutes', 'image_path']);
        $this->postJson(route('admin.services.store'), $this->serviceInput(['price' => '123.456']))->assertUnprocessable();
        $this->assertDatabaseCount('services', 0);
    }

    public function test_service_edits_and_archiving_preserve_existing_ticket_snapshots(): void
    {
        ['service' => $service] = $this->openQueue();
        $ticket = app(CreateQueueTicket::class)->create($this->queueInput(), str_repeat('o', 64))->ticket;
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.services.update', $service), $this->serviceInput([
            'price' => '99000.00', 'estimated_duration_minutes' => 60,
        ]))->assertRedirect();
        $this->assertSame('50000.00', $ticket->refresh()->price_snapshot);
        $this->assertSame(30, $ticket->duration_snapshot);
        $this->post(route('admin.services.archive', $service))->assertRedirect();
        $this->assertFalse($service->refresh()->is_active);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
        $this->assertDatabaseCount('queue_tickets', 1);
    }

    public function test_images_use_generated_local_names_and_reject_executable_svg_or_oversized_uploads(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $validPhoto = $this->photo();
        $oversizedContents = file_get_contents($validPhoto->getPathname()).str_repeat('x', 2049 * 1024);
        foreach ([UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('executable.jpg', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('oversized.png', $oversizedContents)] as $file) {
            // Use the real MIME probe: Laravel's fake File intentionally guesses MIME from its extension.
            $upload = new UploadedFile($file->getPathname(), $file->getClientOriginalName(), null, null, true);
            $this->postJson(route('admin.services.store'), $this->serviceInput(['image' => $upload]))->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->post(route('admin.services.store'), $this->serviceInput(['image' => $this->photo()]))->assertRedirect();
        $service = Service::query()->firstOrFail();
        $this->assertStringStartsWith('services/', $service->image_path);
        $this->assertStringNotContainsString('upload.png', $service->image_path);
        Storage::disk('public')->assertExists($service->image_path);
        $oldPath = $service->image_path;
        $this->put(route('admin.services.update', $service), $this->serviceInput(['image' => $this->photo()]))->assertRedirect();
        $this->assertNotSame($oldPath, $service->refresh()->image_path);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_gallery_upload_publication_and_archival_preserve_assets(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.gallery.store'), [
            'title' => 'Karya studio', 'alt' => 'Potongan rapi dari samping', 'category' => 'Classic', 'sort_order' => 1, 'is_active' => false, 'image' => $this->photo(),
        ])->assertRedirect();
        $image = GalleryImage::query()->firstOrFail();
        $this->get(route('public.gallery'))->assertInertia(fn (Assert $page) => $page->where('illustrative', true));
        $this->put(route('admin.gallery.update', $image), ['title' => $image->title, 'alt' => $image->alt, 'sort_order' => 2, 'is_active' => true])->assertRedirect();
        $this->get(route('public.gallery'))->assertInertia(fn (Assert $page) => $page->where('illustrative', false)->has('images', 1));
        $this->post(route('admin.gallery.archive', $image))->assertRedirect();
        $this->assertFalse($image->refresh()->is_active);
        Storage::disk('public')->assertExists($image->image_path);
        $this->assertDatabaseCount('gallery_images', 1);
    }

    public function test_concurrent_unique_conflicts_rollback_new_upload_and_return_safe_validation(): void
    {
        Storage::fake('public');
        $existing = Service::factory()->create();
        $request = Request::create('/admin/layanan', 'POST', files: ['image' => $this->photo()]);
        try {
            app(CatalogImages::class)->save($request, 'services', fn () => Service::query()->create([
                'name' => 'Conflicting service', 'slug' => $existing->slug, 'price' => 1000, 'estimated_duration_minutes' => 10,
            ]));
            $this->fail('Expected the unique conflict to be handled safely.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('queue', $exception->errors());
            $this->assertStringNotContainsString('SQL', $exception->getMessage());
        }
        $this->assertDatabaseCount('services', 1);
        $this->assertCount(0, Storage::disk('public')->allFiles('services'));
    }

    public function test_lists_are_paginated_and_search_is_preserved(): void
    {
        Service::factory()->count(17)->create(['name' => 'Matching service']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.services.index').'?search=Matching')
            ->assertInertia(fn (Assert $page) => $page->component('admin/services')->has('services.data', 15)
                ->where('services.total', 17)->where('filters.search', 'Matching'));
        $this->get(route('admin.services.index').'?search=Unknown')->assertInertia(fn (Assert $page) => $page->has('services.data', 0));
    }
}
