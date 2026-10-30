<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('photo')->nullable();
            $table->text('bio')->nullable();
            $table->string('specialization')->nullable();
            $table->string('queue_prefix', 3)->unique();
            $table->enum('availability_status', ['available', 'busy', 'break', 'off_duty'])->default('off_duty');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE barbers ADD CONSTRAINT barbers_queue_prefix_format CHECK (queue_prefix ~ '^[A-Z]{1,3}$')");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite cannot add CHECK constraints after CREATE TABLE. Keep test invariants equivalent.
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $trigger = 'barbers_queue_prefix_'.strtolower($operation);
                DB::statement("CREATE TRIGGER {$trigger} BEFORE {$operation} ON barbers
                    WHEN length(NEW.queue_prefix) NOT BETWEEN 1 AND 3 OR NEW.queue_prefix GLOB '*[^A-Z]*'
                    BEGIN SELECT RAISE(ABORT, 'Queue prefix must contain one to three uppercase letters'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('barbers');
    }
};
