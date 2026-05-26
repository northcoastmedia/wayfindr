<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\TicketExternalLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketExternalLink>
 */
class TicketExternalLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'site_id' => fn (array $attributes) => Site::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'ticket_id' => fn (array $attributes) => Ticket::factory()
                ->create([
                    'account_id' => $attributes['account_id'],
                    'site_id' => $attributes['site_id'],
                ])
                ->id,
            'provider' => 'github',
            'project_key' => 'example/project',
            'external_id' => (string) fake()->numberBetween(1, 9999),
            'external_key' => '#'.fake()->numberBetween(1, 9999),
            'url' => 'https://example.test/issues/'.fake()->numberBetween(1, 9999),
            'sync_status' => 'linked',
            'last_synced_at' => null,
            'metadata' => [],
        ];
    }
}
