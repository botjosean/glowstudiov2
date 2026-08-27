<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Opens the category picker up to businesses beyond hair/nails/barbershop
     * before any provider of that kind exists — waxing, makeup, massage,
     * braids, extensions, tattoo, piercing, aesthetics. Only ever widens, so
     * every row already stored stays valid.
     *
     * No new ServiceIcon values: each new category maps onto an icon the
     * enum already carries (see ServiceCategory::icon()), so
     * service_types_icon_chk is untouched.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_category_chk');
        DB::statement(
            'ALTER TABLE services ADD CONSTRAINT services_category_chk '
            ."CHECK (category IN ('fade','classic','beard','kids','color',"
            ."'nails','hands','feet','lashes','facial','hair','other',"
            ."'waxing','makeup','massage','braids','extensions','tattoo','piercing','aesthetics'))"
        );
    }

    /**
     * Restores the narrower, pre-multitenant list. Fails loudly if a service
     * has since been saved with one of the new categories — rolling back is
     * not a reason to quietly delete or rewrite a provider's real service.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_category_chk');
        DB::statement(
            'ALTER TABLE services ADD CONSTRAINT services_category_chk '
            ."CHECK (category IN ('fade','classic','beard','kids','color',"
            ."'nails','hands','feet','lashes','facial','hair','other'))"
        );
    }
};
