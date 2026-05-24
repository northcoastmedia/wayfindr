<?php

namespace App\Listeners;

use App\Events\ConversationMessageCreated;
use App\Models\User;
use App\Models\Visitor;
use App\Notifications\ConversationNeedsReply;
use Illuminate\Notifications\DatabaseNotification;

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
            ->each(fn (User $agent) => $this->notifyAgent($agent, new ConversationNeedsReply($message), $conversation->id));
    }

    private function notifyAgent(User $agent, ConversationNeedsReply $notification, int $conversationId): void
    {
        $existingNotification = $this->existingUnreadConversationNotification($agent, $conversationId);

        if (! $existingNotification) {
            $agent->notify($notification);

            return;
        }

        $existingData = $existingNotification->data;
        $messageCount = max(1, (int) data_get($existingData, 'message_count', 1)) + 1;

        $existingNotification->forceFill([
            'data' => [
                ...$notification->toArray($agent),
                'message_count' => $messageCount,
            ],
        ])->save();
    }

    private function existingUnreadConversationNotification(User $agent, int $conversationId): ?DatabaseNotification
    {
        return $agent->unreadNotifications()
            ->where('type', ConversationNeedsReply::class)
            ->get()
            ->first(fn (DatabaseNotification $notification): bool => (int) data_get($notification->data, 'conversation_id') === $conversationId);
    }
}
