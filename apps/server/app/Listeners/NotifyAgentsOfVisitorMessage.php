<?php

namespace App\Listeners;

use App\Events\ConversationMessageCreated;
use App\Models\Visitor;
use App\Notifications\ConversationNeedsReply;

class NotifyAgentsOfVisitorMessage
{
    /**
     * Handle the event.
     */
    public function handle(ConversationMessageCreated $event): void
    {
        $message = $event->message;

        if ($message->sender_type !== Visitor::class) {
            return;
        }

        $message->loadMissing(['conversation.site.account']);

        $conversation = $message->conversation;
        $agentQuery = $conversation->site->account->agents();

        if ($conversation->assigned_agent_id) {
            $agentQuery->whereKey($conversation->assigned_agent_id);
        }

        $agentQuery
            ->get()
            ->each
            ->notify(new ConversationNeedsReply($message));
    }
}
