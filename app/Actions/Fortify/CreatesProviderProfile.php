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
    /**
     * @param  string|null  $businessCategory  obligatorio en el registro
     *        manual; null solo en el alta por Google, donde no hay formulario
     *        que preguntarlo y se pide después en la guía.
     */
    public function create(
        User $user,
        string $publicName,
        string $slugSeed,
        string $timezone = 'America/New_York',
        ?string $businessCategory = null,
    ): Provider {
        return $user->provider()->create([
            'slug' => $this->uniqueSlug($slugSeed),
            'public_name' => $publicName,
            'timezone' => $timezone,
            'business_category' => $businessCategory,
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
