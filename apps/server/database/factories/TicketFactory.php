<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Site;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'site_id' => fn (array $attributes) => Site::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'conversation_id' => null,
            'requester_id' => null,
            'assignee_id' => null,
            'status' => 'open',
            'priority' => 'normal',
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'metadata' => [],
            'closed_at' => null,
        ];
    }
}
