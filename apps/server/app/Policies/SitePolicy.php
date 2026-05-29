<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function view(User $user, Site $site): bool
    {
        return ! $user->isDeactivated()
            && $site->supportsAgent($user);
    }

    public function updatePrivacy(User $user, Site $site): bool
    {
        return $user->isAdmin() && $this->view($user, $site);
    }

    public function manageAccess(User $user, Site $site): bool
    {
        return $user->isAdmin() && $this->view($user, $site);
    }

    public function manageIntegrations(User $user, Site $site): bool
    {
        return $user->isAdmin() && $this->view($user, $site);
    }
}
