<?php

namespace App\Policies;

use App\Models\BillOccurrence;
use App\Models\User;

class BillOccurrencePolicy
{
    public function view(User $user, BillOccurrence $occurrence): bool
    {
        return $occurrence->user_id === $user->id;
    }

    public function update(User $user, BillOccurrence $occurrence): bool
    {
        return $occurrence->user_id === $user->id;
    }
}
