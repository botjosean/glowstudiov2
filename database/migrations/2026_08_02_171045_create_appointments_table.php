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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            $table->string('client_name', 120);
            $table->string('client_phone', 20);

            // Snapshots — the linked service can change price/name/duration or be
            // deleted after the appointment is booked; these preserve what the
            // client actually booked and priced.
            $table->string('service_name', 80);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price');

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 16)->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'starts_at']);
            $table->index(['provider_id', 'status', 'starts_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_ends_after_starts_chk CHECK (ends_at > starts_at)');
            DB::statement(
                'ALTER TABLE appointments ADD CONSTRAINT appointments_status_chk '
                ."CHECK (status IN ('pending','confirmed','cancelled','closed'))"
            );

            // Defense in depth against double-booking: the primary guarantee is
            // the row lock + revalidation in CreateAppointment, this constraint
            // catches anything that slips past it (e.g. a direct DB write).
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement(
                'ALTER TABLE appointments ADD CONSTRAINT appointments_no_overlap '
                .'EXCLUDE USING gist (provider_id WITH =, tsrange(starts_at, ends_at, \'[)\') WITH &&) '
                ."WHERE (status IN ('pending','confirmed'))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
