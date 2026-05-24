<?php

namespace App\Http\Controllers;

use App\Notifications\ConversationNeedsReply;
use App\Notifications\TicketAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class AgentAlertController extends Controller
{
    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $agent = $request->user();

        abort_unless($notification->notifiable_type === $agent->getMorphClass(), 404);
        abort_unless((string) $notification->notifiable_id === (string) $agent->getKey(), 404);
        abort_unless(in_array($notification->type, $this->supportAlertTypes(), true), 404);

        $notification->markAsRead();

        return redirect()->route('dashboard');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->whereIn('type', $this->supportAlertTypes())
            ->get()
            ->each
            ->markAsRead();

        return redirect()->route('dashboard');
    }

    /**
     * @return list<class-string>
     */
    private function supportAlertTypes(): array
    {
        return [
            ConversationNeedsReply::class,
            TicketAssigned::class,
        ];
    }
}
