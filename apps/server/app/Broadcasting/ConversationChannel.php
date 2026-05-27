<?php

namespace App\Broadcasting;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\Gate;

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
            if ($participant->isDeactivated()) {
                return false;
            }

            return Gate::forUser($participant)->allows('view', $conversation);
        }

        return $conversation->visitor_id === $participant->id;
    }
}
