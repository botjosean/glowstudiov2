<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gives a provider a schedule per weekday plus dated time off.
     *
     * Until now a single work window on `providers` applied to all seven days,
     * so a salon that closes on Sunday had no way to say so and clients were
     * offered Sunday slots the professional would never honour.
     *
     * The backfill copies each provider's existing window into all seven days,
     * open. That is what makes this safe to run against live data: the day
     * after the migration every provider offers exactly the same slots as the
     * day before, and closing a day becomes an explicit act in the panel.
     */
    public function up(): void
    {
        Schema::create('provider_business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            // Carbon's dayOfWeek: 0 = Sunday through 6 = Saturday. Stored rather
            // than derived so a closed day survives independently of any
            // locale's idea of where the week starts.
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_open')->default(true);
            $table->unsignedSmallInteger('work_start_minute');
            $table->unsignedSmallInteger('work_end_minute');

            $table->timestamps();

            $table->unique(['provider_id', 'weekday']);
        });

        Schema::create('provider_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            // Inclusive on both ends: a single day off is starts_on = ends_on,
            // which is what someone means when they block "el 15".
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 80)->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'starts_on', 'ends_on']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE provider_business_hours ADD CONSTRAINT provider_business_hours_window_chk '
                .'CHECK (work_start_minute < work_end_minute AND work_end_minute <= 1440 '
                .'AND work_start_minute % 15 = 0 AND work_end_minute % 15 = 0)'
            );
            DB::statement(
                'ALTER TABLE provider_business_hours ADD CONSTRAINT provider_business_hours_weekday_chk '
                .'CHECK (weekday BETWEEN 0 AND 6)'
            );
            DB::statement(
                'ALTER TABLE provider_time_off ADD CONSTRAINT provider_time_off_range_chk '
                .'CHECK (ends_on >= starts_on)'
            );
        }

        $now = now();

        foreach (DB::table('providers')->select('id', 'work_start_minute', 'work_end_minute')->get() as $provider) {
            DB::table('provider_business_hours')->insert(
                array_map(fn (int $weekday) => [
                    'provider_id' => $provider->id,
                    'weekday' => $weekday,
                    'is_open' => true,
                    'work_start_minute' => $provider->work_start_minute,
                    'work_end_minute' => $provider->work_end_minute,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], range(0, 6))
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_time_off');
        Schema::dropIfExists('provider_business_hours');
    }
};
