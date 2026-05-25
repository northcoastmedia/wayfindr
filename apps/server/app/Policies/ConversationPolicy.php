<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->site?->supportsAgent($user) === true;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function updateStatus(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function claim(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && (! $conversation->assigned_agent_id || (int) $conversation->assigned_agent_id === (int) $user->id);
    }

    public function release(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && (int) $conversation->assigned_agent_id === (int) $user->id;
    }

    public function createTicket(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function requestCobrowse(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function endCobrowse(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
