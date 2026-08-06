<?php

namespace App\Actions\Fortify;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates the empty, unpublished provider profile every new account gets —
 * shared by manual registration (CreateNewUser) and Google sign-in
 * (FindOrCreateGoogleUser), so the two paths can't drift on how a slug is
 * chosen or a profile is shaped.
 */
class CreatesProviderProfile
{
    public function create(User $user, string $publicName, string $slugSeed, string $timezone = 'America/New_York'): Provider
    {
        return $user->provider()->create([
            'slug' => $this->uniqueSlug($slugSeed),
            'public_name' => $publicName,
            'timezone' => $timezone,
        ]);
    }

    private function uniqueSlug(string $seed): string
    {
        $base = Str::slug($seed) ?: 'provider';
        $slug = $base;
        $suffix = 1;

        while (Provider::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
