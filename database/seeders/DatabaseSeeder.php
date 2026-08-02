<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Kept intentionally without a provider row: fixture for the
        // "authenticated but not a provider -> 403" admin access path.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            ServiceTypeSeeder::class,
            ProviderSeeder::class,
            AppointmentSeeder::class,
        ]);
    }
}
