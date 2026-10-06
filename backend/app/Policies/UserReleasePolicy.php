<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserRelease;

class UserReleasePolicy
{
    public function view(User $user, UserRelease $userRelease): bool
    {
        return $userRelease->user_id === $user->id;
    }

    public function delete(User $user, UserRelease $userRelease): bool
    {
        return $this->view($user, $userRelease);
    }
}
