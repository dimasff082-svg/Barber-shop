<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbers', function (Blueprint $table) {
            $table->boolean('accepts_queue')->default(false);
            $table->unsignedSmallInteger('daily_capacity')->nullable();
        });
        $this->addCheck('barbers', 'barbers_daily_capacity_valid', 'daily_capacity IS NULL OR (daily_capacity > 0 AND daily_capacity <= 32767)');

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_phone', 16)->unique();
            $table->timestamps();
        });
        $phoneCondition = match (DB::getDriverName()) {
            'pgsql' => "normalized_phone ~ '^\\+628[0-9]{7,11}$'",
            'mysql' => "normalized_phone REGEXP '^\\\\+628[0-9]{7,11}$'",
            default => "length(normalized_phone) BETWEEN 11 AND 15 AND substr(normalized_phone, 1, 4) = '+628' AND substr(normalized_phone, 2) NOT GLOB '*[^0-9]*'",
        };
        $this->addCheck('customers', 'customers_phone_valid', $phoneCondition);

        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('is_closed')->default(true);
            $table->timestamps();
        });
        $this->addCheck('business_hours', 'business_hours_weekday_valid', 'weekday >= 0 AND weekday <= 6');
        $this->addCheck('business_hours', 'business_hours_interval_valid', 'is_closed OR (starts_at IS NOT NULL AND ends_at IS NOT NULL AND starts_at < ends_at)');

        Schema::create('business_closures', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('barber_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['barber_id', 'weekday']);
        });
        $this->addCheck('barber_schedules', 'barber_schedules_weekday_valid', 'weekday >= 0 AND weekday <= 6');
        $this->addCheck('barber_schedules', 'barber_schedules_interval_valid', 'starts_at < ends_at');

        Schema::create('business_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('business_settings');
        Schema::dropIfExists('barber_schedules');
        Schema::dropIfExists('business_closures');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('customers');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE barbers DROP CONSTRAINT IF EXISTS barbers_daily_capacity_valid');
        } elseif (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE barbers DROP CONSTRAINT IF EXISTS barbers_daily_capacity_valid');
            } catch (\Throwable) {
            }
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS barbers_daily_capacity_valid_insert');
            DB::statement('DROP TRIGGER IF EXISTS barbers_daily_capacity_valid_update');
        }

        Schema::table('barbers', function (Blueprint $table) {
            $table->dropColumn(['accepts_queue', 'daily_capacity']);
        });
    }

    private function addCheck(string $table, string $name, string $condition): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $trigger = $name.'_'.strtolower($operation);
                $sqliteCondition = str_replace('daily_capacity', 'NEW.daily_capacity', $condition);
                foreach (['weekday', 'is_closed', 'starts_at', 'ends_at', 'normalized_phone'] as $column) {
                    $sqliteCondition = str_replace($column, 'NEW.'.$column, $sqliteCondition);
                }
                DB::statement("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$table}
                    WHEN NOT ({$sqliteCondition})
                    BEGIN SELECT RAISE(ABORT, '{$name}'); END");
            }
        }
    }
};
