<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cash register: what the professional actually charged, tip and
     * payment method included. A ledger, not a payment processor — recording
     * a sale moves no money, exactly like Booksy's manual checkout.
     *
     * client_id is a soft link to the client book with the name snapshotted:
     * deleting a card must never rewrite what was charged, so the sale keeps
     * its own name and merely loses the link.
     *
     * The accepted payment methods live on the provider (jsonb) because each
     * professional decides what she takes — Zelle, Cash App, Venmo, cash —
     * and the register only offers those.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name', 120)->nullable();
            $table->decimal('amount', 8, 2);
            $table->decimal('tip', 8, 2)->default(0);
            $table->string('payment_method', 20);
            $table->timestamps();

            $table->index(['provider_id', 'created_at']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->jsonb('payment_methods')->default(json_encode(['cash', 'card', 'zelle', 'cashapp', 'venmo']));
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE sales ADD CONSTRAINT sales_amounts_chk CHECK (amount > 0 AND tip >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('payment_methods');
        });
        Schema::dropIfExists('sales');
    }
};
