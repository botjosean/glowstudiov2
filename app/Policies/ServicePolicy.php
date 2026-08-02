<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /**
     * Any authenticated provider may create a service for themselves — the
     * provider_id always comes from $user->provider, never the request.
     */
    public function create(User $user): bool
    {
        $user->loadMissing('provider');

        return $user->provider !== null;
    }

    /**
     * Covers update, deactivate and activate — all three are "this is my
     * service" checks with no further distinction.
     */
    public function update(User $user, Service $service): bool
    {
        $user->loadMissing('provider');

        return $service->provider_id === $user->provider?->id;
    }
}
