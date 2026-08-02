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
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('slug', 50)->unique();
            $table->string('public_name', 80);
            $table->text('bio')->nullable();
            $table->string('banner_photo_url', 2048)->nullable();
            $table->string('avatar_photo_url', 2048)->nullable();

            $table->boolean('is_mobile')->default(false);
            $table->boolean('is_available_now')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->string('service_area', 120)->nullable();
            $table->string('address_line', 160)->nullable();

            $table->string('whatsapp_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('tiktok_url', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();

            $table->string('timezone', 64)->default('America/New_York');
            $table->unsignedSmallInteger('work_start_minute')->default(540);
            $table->unsignedSmallInteger('work_end_minute')->default(1200);
            $table->unsignedSmallInteger('lunch_start_minute')->default(780);
            $table->unsignedSmallInteger('lunch_end_minute')->default(840);
            $table->unsignedSmallInteger('buffer_minutes')->default(15);

            $table->timestamps();

            $table->index('published_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE providers ADD CONSTRAINT providers_work_window_chk '
                .'CHECK (work_start_minute < work_end_minute AND work_end_minute <= 1440)'
            );
            DB::statement(
                'ALTER TABLE providers ADD CONSTRAINT providers_lunch_window_chk '
                .'CHECK (lunch_start_minute <= lunch_end_minute AND lunch_end_minute <= 1440)'
            );
            DB::statement(
                'ALTER TABLE providers ADD CONSTRAINT providers_buffer_chk '
                .'CHECK (buffer_minutes BETWEEN 0 AND 300)'
            );
            DB::statement(
                'ALTER TABLE providers ADD CONSTRAINT providers_quarter_hour_chk '
                .'CHECK (work_start_minute % 15 = 0 AND work_end_minute % 15 = 0 '
                .'AND lunch_start_minute % 15 = 0 AND lunch_end_minute % 15 = 0 '
                .'AND buffer_minutes % 15 = 0)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
