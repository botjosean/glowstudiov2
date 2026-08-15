<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Any authenticated provider may register a sale for themselves — the
     * provider_id always comes from $user->provider, never the request.
     */
    public function create(User $user): bool
    {
        $user->loadMissing('provider');

        return $user->provider !== null;
    }

    public function delete(User $user, Sale $sale): bool
    {
        $user->loadMissing('provider');

        return $sale->provider_id === $user->provider?->id;
    }
}
