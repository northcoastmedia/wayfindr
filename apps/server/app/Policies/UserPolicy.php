<?php

namespace App\Policies;

use App\Enums\AccountRole;
use App\Models\User;

class UserPolicy
{
    public function createAccountAgent(User $user): bool
    {
        return ! $user->isDeactivated()
            && $user->account_id !== null
            && $user->isAdmin();
    }

    public function updateRole(User $user, User $target): bool
    {
        return ! $user->isDeactivated()
            && $user->isOwner()
            && $this->sameAccount($user, $target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $this->manageAccess($user, $target);
    }

    public function reactivate(User $user, User $target): bool
    {
        return $this->manageAccess($user, $target);
    }

    private function manageAccess(User $user, User $target): bool
    {
        if ($user->isDeactivated() || ! $user->isAdmin() || ! $this->sameAccount($user, $target)) {
            return false;
        }

        if ($user->is($target)) {
            return false;
        }

        return $user->isOwner() || $target->account_role === AccountRole::Agent;
    }

    private function sameAccount(User $user, User $target): bool
    {
        return $user->account_id !== null
            && $target->account_id !== null
            && (int) $user->account_id === (int) $target->account_id;
    }
}
