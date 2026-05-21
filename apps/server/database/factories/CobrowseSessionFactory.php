<?php

namespace Database\Factories;

use App\Models\CobrowseSession;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CobrowseSession>
 */
class CobrowseSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'site_id' => fn (array $attributes) => Conversation::query()
                ->findOrFail($attributes['conversation_id'])
                ->site_id,
            'visitor_id' => fn (array $attributes) => Conversation::query()
                ->findOrFail($attributes['conversation_id'])
                ->visitor_id,
            'requested_by_id' => null,
            'status' => 'requested',
            'metadata' => [],
            'consented_at' => null,
            'ended_at' => null,
        ];
    }
}
