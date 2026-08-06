<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A user who signs up via Google proves their identity through OAuth, not a
 * password, and may never set one. `password IS NULL` is that state's own
 * signal — no extra column needed. Schema::table()->change() would need
 * doctrine/dbal, which this app doesn't have, so this drops the constraint
 * with a raw statement instead (Postgres-only, same as the rest of this app).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN password SET NOT NULL');
    }
};
