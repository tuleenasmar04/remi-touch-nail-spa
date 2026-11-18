<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    public function view(User $user): bool
    {
        return $user->role === 'owner' && (int) $user->owner_slot === 1;
    }

    public function manage(User $user, string $resource): bool
    {
        return $this->view($user);
    }
}
