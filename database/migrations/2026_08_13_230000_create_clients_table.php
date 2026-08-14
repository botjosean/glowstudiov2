<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The provider's client book: who she attends, not who booked once.
     *
     * Appointments keep carrying their own client_name/client_phone snapshots
     * on purpose — a row here is the durable card (notes, tags) while the
     * appointment history is linked at read time by phone, exactly how the
     * WhatsApp assistant already recognizes returning clients. No foreign key
     * from appointments to clients: deleting a card must never touch bookings.
     *
     * The backfill turns every distinct phone the provider has ever booked
     * into a card, keeping the name from the most recent appointment (people
     * fix typos in their own name over time; the newest spelling wins).
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            $table->string('name', 120);
            // Ten digits, same normal form as appointments.client_phone. One
            // card per phone and provider: the phone is also how the bot and
            // the agenda recognize her, so two cards sharing it would make the
            // history ambiguous. A relative booking under the same phone goes
            // on the same card or on a phoneless one.
            $table->string('phone', 10)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('tags')->default('[]');

            $table->timestamps();

            $table->unique(['provider_id', 'phone']);
            $table->index(['provider_id', 'name']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE clients ADD CONSTRAINT clients_phone_chk CHECK (phone IS NULL OR phone ~ '^[0-9]{10}$')"
            );
        }

        // NOW() server-side rather than bound parameters: Postgres cannot
        // infer a type for placeholders in a SELECT list and rejects them.
        DB::statement(<<<'SQL'
            INSERT INTO clients (provider_id, name, phone, created_at, updated_at)
            SELECT DISTINCT ON (provider_id, client_phone)
                provider_id, client_name, client_phone, NOW(), NOW()
            FROM appointments
            WHERE client_phone IS NOT NULL AND client_phone ~ '^[0-9]{10}$'
            ORDER BY provider_id, client_phone, starts_at DESC
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
