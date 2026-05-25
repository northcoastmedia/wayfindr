<?php

namespace App\Broadcasting;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Visitor;

class ConversationChannel
{
    public function join(User|Visitor|null $participant, string $supportCode): bool
    {
        if (! $participant) {
            return false;
        }

        $conversation = Conversation::query()
            ->with('site')
            ->where('support_code', $supportCode)
            ->first();

        if (! $conversation) {
            return false;
        }

        if ($participant instanceof User) {
            return $conversation->site?->supportsAgent($participant) === true;
        }

        return $conversation->visitor_id === $participant->id;
    }
}
