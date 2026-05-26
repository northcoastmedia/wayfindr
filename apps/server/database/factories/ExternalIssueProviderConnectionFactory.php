<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ExternalIssueProviderConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalIssueProviderConnection>
 */
class ExternalIssueProviderConnectionFactory extends Factory
{
    protected $model = ExternalIssueProviderConnection::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'provider' => 'github',
            'name' => 'Engineering GitHub',
            'base_url' => 'https://api.github.com',
            'credentials' => ['token' => fake()->password(24, 32)],
            'capabilities' => [
                'create_issue' => true,
                'add_comment' => true,
                'sync_status' => false,
            ],
            'settings' => [],
            'is_enabled' => true,
            'last_checked_at' => null,
        ];
    }
}
