<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Opens the schema up to beauty work: nail, lash, facial and generic hair
     * categories alongside the original barbershop five, a 6-hour ceiling
     * instead of 3, and the icons those categories map to.
     *
     * Both constraints only ever widen, so every row already stored stays
     * valid and the validation pass Postgres runs on ADD CONSTRAINT finds
     * nothing to reject. The value lists are spelled out literally rather
     * than derived from ServiceCategory on purpose: a migration that reads a
     * live enum silently changes what it did the next time somebody adds a
     * case, so a fresh database would stop matching production.
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
            ."'nails','hands','feet','lashes','facial','hair','other'))"
        );

        // 3 h was a barbershop ceiling. A balayage already sat exactly on it,
        // and a keratin treatment or a full set of lash extensions runs past.
        DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_duration_chk');
        DB::statement(
            'ALTER TABLE services ADD CONSTRAINT services_duration_chk '
            .'CHECK (duration_minutes BETWEEN 5 AND 360 AND duration_minutes % 5 = 0)'
        );

        // service_types.icon is constrained to the ServiceIcon enum as well,
        // and the beauty categories introduce six new icons. No catalog row
        // uses them yet — this only stops the constraint from being narrower
        // than the enum PHP is free to emit.
        DB::statement('ALTER TABLE service_types DROP CONSTRAINT IF EXISTS service_types_icon_chk');
        DB::statement(
            'ALTER TABLE service_types ADD CONSTRAINT service_types_icon_chk '
            ."CHECK (icon IN ('scissors','sparkles','smile',"
            ."'gem','hand','footprints','eye','flower','star'))"
        );
    }

    /**
     * Restores the original, narrower constraints. This deliberately fails
     * loudly if a service has since been saved with a beauty category or a
     * duration over 3 h — rolling back is not a reason to quietly delete or
     * rewrite a provider's real service.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_category_chk');
        DB::statement(
            'ALTER TABLE services ADD CONSTRAINT services_category_chk '
            ."CHECK (category IN ('fade','classic','beard','kids','color'))"
        );

        DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_duration_chk');
        DB::statement(
            'ALTER TABLE services ADD CONSTRAINT services_duration_chk '
            .'CHECK (duration_minutes BETWEEN 5 AND 180 AND duration_minutes % 5 = 0)'
        );

        DB::statement('ALTER TABLE service_types DROP CONSTRAINT IF EXISTS service_types_icon_chk');
        DB::statement(
            'ALTER TABLE service_types ADD CONSTRAINT service_types_icon_chk '
            ."CHECK (icon IN ('scissors','sparkles','smile'))"
        );
    }
};
