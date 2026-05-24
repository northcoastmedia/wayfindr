<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ConversationNeedsReply;
use App\Support\RealtimeHealth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AgentDashboardController extends Controller
{
    public function __invoke(Request $request, RealtimeHealth $realtimeHealth): View
    {
        $agent = $request->user();

        abort_unless($agent->account_id, 403);

        $account = $agent->account()->firstOrFail();
        $sites = $account->sites()
            ->with('latestVisitor')
            ->orderBy('name')
            ->get();

        $conversationFilters = [
            'all' => 'All open',
            'needs_reply' => 'Needs reply',
            'assigned_to_me' => 'Assigned to me',
            'unassigned' => 'Unassigned',
        ];
        $conversationFilter = $request->query('conversation_filter', 'all');
        $conversationFilter = is_string($conversationFilter) && array_key_exists($conversationFilter, $conversationFilters)
            ? $conversationFilter
            : 'all';

        $conversations = Conversation::query()
            ->with(['assignedAgent', 'latestMessage', 'site', 'visitor'])
            ->where('status', 'open')
            ->whereHas('site', fn ($query) => $query->where('account_id', $account->id))
            ->when($conversationFilter === 'needs_reply', function ($query): void {
                $query->where(function ($query): void {
                    $query->whereDoesntHave('messages')
                        ->orWhereHas('latestMessage', fn ($query) => $query->where('sender_type', '!=', User::class));
                });
            })
            ->when($conversationFilter === 'assigned_to_me', fn ($query) => $query->where('assigned_agent_id', $agent->id))
            ->when($conversationFilter === 'unassigned', fn ($query) => $query->whereNull('assigned_agent_id'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
        $tickets = Ticket::query()
            ->with(['assignee', 'conversation', 'site'])
            ->where('account_id', $account->id)
            ->where('status', 'open')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();
        $unreadNotificationsQuery = $agent->unreadNotifications()
            ->where('type', ConversationNeedsReply::class);
        $unreadNotificationCount = (clone $unreadNotificationsQuery)->count();
        $unreadNotifications = $unreadNotificationsQuery
            ->limit(5)
            ->get();

        return view('agent.dashboard', [
            'account' => $account,
            'agent' => $agent,
            'conversationFilter' => $conversationFilter,
            'conversationFilters' => $conversationFilters,
            'conversations' => $conversations,
            'dataResponsibility' => config('wayfindr.data_responsibility'),
            'realtimeHealth' => $realtimeHealth->summary(),
            'sites' => $sites,
            'tickets' => $tickets,
            'unreadNotificationCount' => $unreadNotificationCount,
            'unreadNotifications' => $unreadNotifications,
        ]);
    }
}
