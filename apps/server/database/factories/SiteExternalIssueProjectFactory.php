<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ExternalIssueProviderConnection;
use App\Models\Site;
use App\Models\SiteExternalIssueProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteExternalIssueProject>
 */
class SiteExternalIssueProjectFactory extends Factory
{
    protected $model = SiteExternalIssueProject::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'site_id' => fn (array $attributes): int => Site::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'external_issue_provider_connection_id' => fn (array $attributes): int => ExternalIssueProviderConnection::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'project_key' => 'adamgreenwell/wayfindr',
            'project_name' => 'Wayfindr',
            'web_url' => 'https://github.com/adamgreenwell/wayfindr',
            'settings' => [],
        ];
    }
}
