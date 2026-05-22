<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $account = Account::query()->updateOrCreate(
            ['slug' => 'demo-support-co'],
            ['name' => 'Demo Support Co'],
        );

        User::query()->updateOrCreate(
            ['email' => 'agent@example.com'],
            [
                'account_id' => $account->id,
                'name' => 'Demo Agent',
                'password' => Hash::make('password'),
            ],
        );

        Site::query()->updateOrCreate(
            ['public_key' => 'site_demo_public_key'],
            [
                'account_id' => $account->id,
                'name' => 'Demo Site',
                'domain' => 'demo.test',
                'settings' => [
                    'mask_selectors' => ['input[type="password"]', '[data-wayfindr-mask]'],
                ],
            ],
        );
    }
}
