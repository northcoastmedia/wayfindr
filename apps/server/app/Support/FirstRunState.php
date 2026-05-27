<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Site;
use App\Models\User;

class FirstRunState
{
    public function needsSetup(): bool
    {
        return ! Account::query()->exists()
            && ! User::query()->whereNotNull('account_id')->exists()
            && ! Site::query()->exists();
    }
}
