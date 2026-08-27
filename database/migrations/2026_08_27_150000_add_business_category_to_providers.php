<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "¿Qué tipo de negocio tenés?" — nullable: nobody is forced to answer
     * it before using the app (see App\Http\Responses\VerifyEmailResponse
     * and the Admin/Inicio.vue checklist it feeds — a step there, not a
     * gate). Null just means she hasn't picked one yet.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->string('business_category', 20)->nullable()->after('slug');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE providers ADD CONSTRAINT providers_business_category_chk '
                ."CHECK (business_category IS NULL OR business_category IN ("
                ."'nails','hair','barbershop','lashes_brows','braids','waxing',"
                ."'makeup','spa_massage','aesthetics','tattoo_piercing','other'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE providers DROP CONSTRAINT IF EXISTS providers_business_category_chk');
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('business_category');
        });
    }
};
