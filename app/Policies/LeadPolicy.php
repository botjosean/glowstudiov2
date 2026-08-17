<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * A lead carries somebody's phone number and what she wrote, so the only
     * question is the same one every other card asks: is this mine?
     */
    public function update(User $user, Lead $lead): bool
    {
        $user->loadMissing('provider');

        return $lead->provider_id === $user->provider?->id;
    }
}
