<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_type' => null,
            'sender_id' => null,
            'type' => 'text',
            'body' => fake()->sentence(),
            'metadata' => [],
            'seen_at' => null,
        ];
    }
}
