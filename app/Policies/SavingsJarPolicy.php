<?php

namespace App\Policies;

use App\Models\SavingsJar;
use App\Models\User;

class SavingsJarPolicy
{
    public function view(User $user, SavingsJar $jar): bool
    {
        return $jar->user_id === $user->id;
    }

    public function update(User $user, SavingsJar $jar): bool
    {
        return $jar->user_id === $user->id;
    }

    public function delete(User $user, SavingsJar $jar): bool
    {
        return $jar->user_id === $user->id;
    }
}
