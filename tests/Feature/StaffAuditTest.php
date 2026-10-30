<?php

namespace Tests\Feature;

use App\Models\StaffAuditEntry;
use App\Models\User;
use App\Services\StaffAudit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_audit_whitelists_metadata_and_redacts_nested_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        app(StaffAudit::class)->record($admin, 'settings.updated', 'business_settings', null, [
            'fields' => ['queue_registration_open', 'password'], 'queue_registration_open' => true,
            'password' => 'NEVER_PERSIST_CREDENTIAL', 'nested' => ['token_hash' => 'NEVER_PERSIST_HASH', 'valid' => 5],
        ]);
        $entry = StaffAuditEntry::query()->firstOrFail();
        $this->assertSame(['valid' => 5], $entry->metadata['nested']);
        $this->assertArrayNotHasKey('password', $entry->metadata);
        $this->assertSame($admin->id, $entry->actor_user_id);
        $response = $this->actingAs($admin)->get(route('admin.audit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit')->where('entries.total', 1)->where('entries.data.0.action', 'settings.updated'));
        $this->assertStringNotContainsString('NEVER_PERSIST', $response->getContent());
        $this->assertStringNotContainsString($admin->password, $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_barbers_and_guests_cannot_read_administrative_audit(): void
    {
        $this->get(route('admin.audit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->barber()->create())->get(route('admin.audit'))->assertForbidden();
    }

    public function test_audit_records_cannot_be_updated_through_the_model(): void
    {
        $admin = User::factory()->admin()->create();
        app(StaffAudit::class)->record($admin, 'test.created', 'test', 1);
        $entry = StaffAuditEntry::query()->firstOrFail();
        $this->expectException(LogicException::class);
        $entry->update(['action' => 'tampered']);
    }

    #[DataProvider('mutationOperations')]
    public function test_audit_is_append_only_at_the_database_layer(string $operation): void
    {
        $admin = User::factory()->admin()->create();
        app(StaffAudit::class)->record($admin, 'test.created', 'test', 1);
        $this->expectException(QueryException::class);
        $operation === 'update' ? DB::table('staff_audits')->update(['action' => 'tampered']) : DB::table('staff_audits')->delete();
    }

    public static function mutationOperations(): array
    {
        return [['update'], ['delete']];
    }
}
