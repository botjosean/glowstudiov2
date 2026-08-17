<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Somebody wrote on WhatsApp and nobody has dealt with her yet.
     *
     * This is the piece a canned WhatsApp Business away-message cannot have:
     * what the client answers stops dying in the chat and becomes a row the
     * panel can show, count, and turn into an appointment. Before this, every
     * conversation had to be re-read and re-typed by hand into the agenda.
     *
     * **The row is also the conversation's state**, which is why
     * `bot_messages_sent` lives here rather than in a table of its own. The
     * receptionist may send two messages and no more, and that count has to
     * survive past the handful of turns Kapso's history endpoint returns —
     * counting our own messages in a transcript that scrolls would quietly
     * re-arm the greeting for a client who sent ten messages. One row per
     * provider and phone, so a client who writes back next month lands on her
     * own card instead of a second one.
     *
     * No foreign key to clients: a lead exists before anybody knows her name,
     * and the link is the same phone match the assistant and the client book
     * already use.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            // Ten digits, the same normal form as appointments.client_phone
            // and clients.phone.
            $table->string('phone', 10);
            $table->string('name', 120)->nullable();

            // What she actually wrote, so the professional can judge in one
            // glance whether this is a client, a supplier or spam. Trimmed on
            // write; a wall of text helps nobody in a list.
            $table->text('message')->nullable();

            // nuevo      = waiting for a person
            // atendido   = dealt with (converted, or marked by hand)
            // descartado = not a client (spam, wrong number)
            $table->string('status', 20)->default('nuevo');

            // 0, 1 or 2. Two is silence: see App\Support\Assistant\Receptionist.
            $table->unsignedSmallInteger('bot_messages_sent')->default(0);

            $table->timestamp('first_contact_at');
            $table->timestamp('last_contact_at');
            // When a person took it over. Drives the "waiting too long" alert.
            $table->timestamp('answered_at')->nullable();
            // Stamped once the professional has been told this one is waiting,
            // so an hourly check nags about each request exactly once instead
            // of mailing the same list every hour until she opens the app.
            $table->timestamp('alerted_at')->nullable();

            $table->timestamps();

            $table->unique(['provider_id', 'phone']);
            // The panel's own query: this provider's open leads, newest first.
            $table->index(['provider_id', 'status', 'last_contact_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE leads ADD CONSTRAINT leads_phone_chk CHECK (phone ~ '^[0-9]{10}$')"
            );
            DB::statement(
                "ALTER TABLE leads ADD CONSTRAINT leads_status_chk CHECK (status IN ('nuevo', 'atendido', 'descartado'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
