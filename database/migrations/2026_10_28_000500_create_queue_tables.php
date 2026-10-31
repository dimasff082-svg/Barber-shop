<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->date('queue_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['barber_id', 'queue_date']);
        });

        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('customer_name');
            $table->string('normalized_phone', 16);
            $table->date('queue_date');
            $table->unsignedInteger('sequence_number');
            $table->string('queue_number', 24);
            $table->enum('source', ['online', 'onsite']);
            $table->enum('status', ['waiting_checkin', 'checked_in', 'called', 'in_service', 'completed', 'missed', 'cancelled']);
            $table->string('token_hash', 64);
            $table->string('owner_hash', 64);
            $table->string('request_fingerprint', 64);
            $table->uuid('idempotency_key')->unique();
            $table->decimal('price_snapshot', 12, 2);
            $table->unsignedSmallInteger('duration_snapshot');
            $table->timestamp('grace_started_at')->nullable();
            $table->timestamp('grace_expires_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('missed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['barber_id', 'queue_date', 'sequence_number']);
            $table->unique(['queue_date', 'queue_number']);
            $table->index(['barber_id', 'queue_date', 'status', 'sequence_number']);
            $table->index(['status', 'grace_expires_at']);
        });

        if (DB::getDriverName() === 'pgsql' || DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX queue_tickets_active_phone_date_unique ON queue_tickets (normalized_phone, queue_date)
                WHERE status IN ('waiting_checkin', 'checked_in', 'called', 'in_service')");
            DB::statement("CREATE UNIQUE INDEX queue_tickets_one_service_per_barber_unique ON queue_tickets (barber_id)
                WHERE status IN ('called', 'in_service')");
        }
        $this->addCheck('queue_counters', 'queue_counters_number_valid', 'last_number >= 0 AND last_number <= 2147483647');
        $this->addCheck('queue_tickets', 'queue_tickets_sequence_valid', 'sequence_number > 0 AND sequence_number <= 2147483647');
        $this->addCheck('queue_tickets', 'queue_tickets_price_valid', 'price_snapshot >= 0 AND price_snapshot <= 9999999999.99');
        $this->addCheck('queue_tickets', 'queue_tickets_duration_valid', 'duration_snapshot > 0 AND duration_snapshot <= 32767');
        $this->addCheck('queue_tickets', 'queue_tickets_source_status_valid', "source <> 'onsite' OR status <> 'waiting_checkin'");
        $this->addCheck('queue_tickets', 'queue_tickets_grace_valid', 'grace_expires_at IS NULL OR (grace_started_at IS NOT NULL AND grace_expires_at >= grace_started_at)');
        $phoneCondition = match (DB::getDriverName()) {
            'pgsql' => "normalized_phone ~ '^\\+628[0-9]{7,11}$'",
            'mysql' => "normalized_phone REGEXP '^\\\\+628[0-9]{7,11}$'",
            default => "length(normalized_phone) BETWEEN 11 AND 15 AND substr(normalized_phone, 1, 4) = '+628' AND substr(normalized_phone, 2) NOT GLOB '*[^0-9]*'",
        };
        $this->addCheck('queue_tickets', 'queue_tickets_phone_valid', $phoneCondition);
        foreach (['token_hash', 'owner_hash', 'request_fingerprint'] as $column) {
            $condition = match (DB::getDriverName()) {
                'pgsql' => "{$column} ~ '^[0-9a-f]{64}$'",
                'mysql' => "{$column} REGEXP '^[0-9a-f]{64}$'",
                default => "length({$column}) = 64 AND {$column} NOT GLOB '*[^0-9a-f]*'",
            };
            $this->addCheck('queue_tickets', 'queue_tickets_'.$column.'_valid', $condition);
        }

        Schema::create('queue_events', function (Blueprint $table) {
            $table->id();
            if (DB::getDriverName() === 'mysql') {
                $table->ulid('queue_ticket_id')->index();
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            } else {
                $table->foreignUlid('queue_ticket_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            }
            $table->string('event_type', 64);
            $table->enum('previous_status', ['waiting_checkin', 'checked_in', 'called', 'in_service', 'completed', 'missed', 'cancelled'])->nullable();
            $table->enum('new_status', ['waiting_checkin', 'checked_in', 'called', 'in_service', 'completed', 'missed', 'cancelled']);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['queue_ticket_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION reject_queue_event_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Queue events are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER queue_events_append_only BEFORE UPDATE OR DELETE ON queue_events
                    FOR EACH ROW EXECUTE FUNCTION reject_queue_event_mutation();
                CREATE FUNCTION reject_queue_ticket_deletion() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Queue tickets cannot be deleted';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER queue_tickets_no_delete BEFORE DELETE ON queue_tickets
                    FOR EACH ROW EXECUTE FUNCTION reject_queue_ticket_deletion();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $trigger = 'queue_events_append_only_'.strtolower($operation);
                DB::statement("CREATE TRIGGER {$trigger} BEFORE {$operation} ON queue_events
                    BEGIN SELECT RAISE(ABORT, 'Queue events are append-only'); END");
            }
            DB::statement("CREATE TRIGGER queue_tickets_no_delete BEFORE DELETE ON queue_tickets
                BEGIN SELECT RAISE(ABORT, 'Queue tickets cannot be deleted'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_events');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS reject_queue_event_mutation()');
        }
        Schema::dropIfExists('queue_tickets');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS reject_queue_ticket_deletion()');
        }
        Schema::dropIfExists('queue_counters');
    }

    private function addCheck(string $table, string $name, string $condition): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $trigger = $name.'_'.strtolower($operation);
                $sqliteCondition = preg_replace('/\b(last_number|sequence_number|price_snapshot|duration_snapshot|source|status|grace_expires_at|grace_started_at|normalized_phone|token_hash|owner_hash|request_fingerprint)\b/', 'NEW.$1', $condition);
                DB::statement("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$table}
                    WHEN NOT ({$sqliteCondition})
                    BEGIN SELECT RAISE(ABORT, '{$name}'); END");
            }
        }
    }
};
