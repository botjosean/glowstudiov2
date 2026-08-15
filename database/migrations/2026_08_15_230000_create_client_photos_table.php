<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Work photos pinned to a client card — the "after" shot taken when she
     * leaves, Booksy-style. They ride the same R2 pipeline as provider
     * gallery photos; deleting the card deletes its rows (the objects stay
     * in the bucket, same accepted trade-off as the admin panel wipe).
     */
    public function up(): void
    {
        Schema::create('client_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_photos');
    }
};
