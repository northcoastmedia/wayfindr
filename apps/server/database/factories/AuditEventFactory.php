<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'site_id' => null,
            'actor_type' => null,
            'actor_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'action' => fake()->randomElement([
                'conversation.created',
                'ticket.created',
                'cobrowse.requested',
            ]),
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
