<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedSmallInteger('estimated_duration_minutes');
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_valid_range CHECK (price >= 0 AND price <= 9999999999.99)');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_valid_range CHECK (estimated_duration_minutes > 0 AND estimated_duration_minutes <= 32767)');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $trigger = 'services_valid_values_'.strtolower($operation);
                DB::statement("CREATE TRIGGER {$trigger} BEFORE {$operation} ON services
                    WHEN NEW.price < 0 OR NEW.price > 9999999999.99
                        OR NEW.estimated_duration_minutes <= 0 OR NEW.estimated_duration_minutes > 32767
                    BEGIN SELECT RAISE(ABORT, 'Service price or duration is outside its supported range'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
