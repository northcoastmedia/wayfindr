<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => fake()->company().' Support Site',
            'domain' => fake()->unique()->domainName(),
            'public_key' => 'site_'.Str::lower(Str::random(32)),
            'settings' => [
                'mask_selectors' => ['input[type="password"]', '[data-wayfindr-mask]'],
            ],
        ];
    }
}
