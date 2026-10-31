<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_audits', function (Blueprint $table) {
            $table->id();
            if (DB::getDriverName() === 'mysql') {
                $table->unsignedBigInteger('actor_user_id')->index();
            } else {
                $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            }
            $table->string('action', 100);
            $table->string('target_type', 100);
            $table->string('target_id')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION reject_staff_audit_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Staff audit is append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER staff_audits_append_only BEFORE UPDATE OR DELETE ON staff_audits
                    FOR EACH ROW EXECUTE FUNCTION reject_staff_audit_mutation();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::statement('CREATE TRIGGER staff_audits_no_'.strtolower($operation)." BEFORE {$operation} ON staff_audits BEGIN SELECT RAISE(ABORT, 'Staff audit is append-only'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_audits');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS reject_staff_audit_mutation()');
        }
    }
};
