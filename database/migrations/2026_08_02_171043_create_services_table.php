<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 80);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price');
            $table->string('category', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['provider_id', 'is_active', 'position']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE services ADD CONSTRAINT services_duration_chk '
                .'CHECK (duration_minutes BETWEEN 5 AND 180 AND duration_minutes % 5 = 0)'
            );
            DB::statement(
                'ALTER TABLE services ADD CONSTRAINT services_category_chk '
                ."CHECK (category IN ('fade','classic','beard','kids','color'))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
