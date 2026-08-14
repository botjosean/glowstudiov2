<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * Any authenticated provider may add a client card for themselves — the
     * provider_id always comes from $user->provider, never the request.
     */
    public function create(User $user): bool
    {
        $user->loadMissing('provider');

        return $user->provider !== null;
    }

    /**
     * View, update and delete are all the same "this is my client" check.
     */
    public function view(User $user, Client $client): bool
    {
        $user->loadMissing('provider');

        return $client->provider_id === $user->provider?->id;
    }

    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }
}
