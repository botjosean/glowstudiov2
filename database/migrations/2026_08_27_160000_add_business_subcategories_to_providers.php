<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which specialties within her business_category — "Corte, Balayage,
     * Keratina" instead of just "Cabello". Nullable/empty by default, same
     * reasoning as business_category itself: not required to use the app,
     * validated against BusinessCategory::subcategories() at the request
     * layer rather than a Postgres CHECK (a JSON array of arbitrary length
     * doesn't fit a simple IN (...) constraint).
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->jsonb('business_subcategories')->nullable()->after('business_category');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('business_subcategories');
        });
    }
};
