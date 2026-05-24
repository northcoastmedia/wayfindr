<?php

namespace App\Notifications;

use App\Models\ConversationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ConversationNeedsReply extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly ConversationMessage $message)
    {
        $this->message->loadMissing(['conversation.site', 'conversation.visitor']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $conversation = $this->message->conversation;

        return [
            'kind' => 'conversation_needs_reply',
            'conversation_id' => $conversation->id,
            'message_id' => $this->message->id,
            'support_code' => $conversation->support_code,
            'subject' => $conversation->subject ?? 'Untitled conversation',
            'site_name' => $conversation->site->name,
            'visitor_anonymous_id' => $conversation->visitor?->anonymous_id,
            'message_preview' => Str::limit((string) $this->message->body, 160),
            'url' => route('dashboard.conversations.show', $conversation->support_code, false),
        ];
    }
}
