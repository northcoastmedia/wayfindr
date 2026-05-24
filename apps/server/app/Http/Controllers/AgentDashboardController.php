<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Ticket;
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
        $conversations = Conversation::query()
            ->with(['assignedAgent', 'site', 'visitor'])
            ->where('status', 'open')
            ->whereHas('site', fn ($query) => $query->where('account_id', $account->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
        $tickets = Ticket::query()
            ->with(['conversation', 'site'])
            ->where('account_id', $account->id)
            ->where('status', 'open')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        return view('agent.dashboard', [
            'account' => $account,
            'agent' => $agent,
            'conversations' => $conversations,
            'dataResponsibility' => config('wayfindr.data_responsibility'),
            'realtimeHealth' => $realtimeHealth->summary(),
            'sites' => $sites,
            'tickets' => $tickets,
        ]);
    }
}
