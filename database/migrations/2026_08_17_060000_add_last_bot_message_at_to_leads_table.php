<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the receptionist last spoke in this conversation.
     *
     * The second message used to wait for the client to write again, which
     * meant a client who said one thing and then went quiet got a greeting and
     * nothing else. The owner asked on 2026-08-17 for it to arrive on its own
     * ten minutes later, so there has to be a clock to measure those ten
     * minutes from — and `updated_at` is not it: anything that touches the row
     * would move it and either delay the follow-up forever or fire it early.
     *
     * Backfilled from `last_contact_at` for rows that already had a message
     * sent, so no conversation open right now gets a follow-up it should have
     * received minutes ago the instant this deploys.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('last_bot_message_at')->nullable();

            // Kapso's id for this thread. Needed by the follow-up, which runs
            // on a timer with no inbound message to take it from — and without
            // it the follow-up could not check whether the professional has
            // already answered, so it would talk over her.
            $table->string('conversation_id', 64)->nullable();
        });

        DB::table('leads')
            ->where('bot_messages_sent', '>', 0)
            ->update(['last_bot_message_at' => DB::raw('last_contact_at')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['last_bot_message_at', 'conversation_id']);
        });
    }
};
