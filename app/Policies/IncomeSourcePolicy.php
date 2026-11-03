<?php

namespace App\Policies;

use App\Models\IncomeSource;
use App\Models\User;

class IncomeSourcePolicy
{
    public function view(User $user, IncomeSource $source): bool
    {
        return $source->user_id === $user->id;
    }

    public function update(User $user, IncomeSource $source): bool
    {
        return $source->user_id === $user->id;
    }

    public function delete(User $user, IncomeSource $source): bool
    {
        return $source->user_id === $user->id;
    }
}
