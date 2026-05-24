<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ConversationNeedsReply;
use App\Notifications\TicketAssigned;
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
        $agents = $account->agents()
            ->withCount([
                'assignedConversations as open_assigned_conversations_count' => fn ($query) => $query
                    ->where('status', 'open')
                    ->whereHas('site', fn ($query) => $query->where('account_id', $account->id)),
                'assignedTickets as open_assigned_tickets_count' => fn ($query) => $query
                    ->where('account_id', $account->id)
                    ->where('status', 'open'),
            ])
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
        $ticketFilters = [
            'all' => 'Any assignee',
            'assigned_to_me' => 'Assigned to me',
            'unassigned' => 'Unassigned',
        ];
        $ticketFilter = $request->query('ticket_filter', 'all');
        $ticketFilter = is_string($ticketFilter) && array_key_exists($ticketFilter, $ticketFilters)
            ? $ticketFilter
            : 'all';
        $ticketStatusFilters = [
            'open' => 'All open',
            'pending' => 'Pending',
            'closed' => 'Closed',
            'all' => 'All tickets',
        ];
        $ticketStatus = $request->query('ticket_status', 'open');
        $ticketStatus = is_string($ticketStatus) && array_key_exists($ticketStatus, $ticketStatusFilters)
            ? $ticketStatus
            : 'open';
        $ticketStatusSummary = match ($ticketStatus) {
            'all' => 'total',
            default => $ticketStatus,
        };
        $ticketEmptyMessage = match ($ticketStatus) {
            'all' => 'No tickets yet.',
            'pending' => 'No pending tickets yet.',
            'closed' => 'No closed tickets yet.',
            default => 'No open tickets yet.',
        };

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
            ->when($ticketStatus !== 'all', fn ($query) => $query->where('status', $ticketStatus))
            ->when($ticketFilter === 'assigned_to_me', fn ($query) => $query->where('assignee_id', $agent->id))
            ->when($ticketFilter === 'unassigned', fn ($query) => $query->whereNull('assignee_id'))
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();
        $unreadNotificationsQuery = $agent->unreadNotifications()
            ->whereIn('type', [
                ConversationNeedsReply::class,
                TicketAssigned::class,
            ]);
        $unreadNotificationCount = (clone $unreadNotificationsQuery)->count();
        $unreadNotifications = $unreadNotificationsQuery
            ->limit(5)
            ->get();

        return view('agent.dashboard', [
            'account' => $account,
            'agent' => $agent,
            'agents' => $agents,
            'conversationFilter' => $conversationFilter,
            'conversationFilters' => $conversationFilters,
            'conversations' => $conversations,
            'dataResponsibility' => config('wayfindr.data_responsibility'),
            'realtimeHealth' => $realtimeHealth->summary(),
            'sites' => $sites,
            'ticketEmptyMessage' => $ticketEmptyMessage,
            'ticketFilter' => $ticketFilter,
            'ticketFilters' => $ticketFilters,
            'ticketStatus' => $ticketStatus,
            'ticketStatusFilters' => $ticketStatusFilters,
            'ticketStatusSummary' => $ticketStatusSummary,
            'tickets' => $tickets,
            'unreadNotificationCount' => $unreadNotificationCount,
            'unreadNotifications' => $unreadNotifications,
        ]);
    }
}
