<?php

namespace App\Policies;

use App\Models\ProviderPhoto;
use App\Models\User;

class ProviderPhotoPolicy
{
    /**
     * The only route in the photo-upload surface that carries an {id} —
     * avatar/banner/gallery-store all target $user->provider by
     * construction, so this is the sole cross-tenant vector to guard.
     */
    public function delete(User $user, ProviderPhoto $photo): bool
    {
        $user->loadMissing('provider');

        return $photo->provider_id === $user->provider?->id;
    }
}
