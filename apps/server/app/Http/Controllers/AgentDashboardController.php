<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AgentDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $agent = $request->user();

        abort_unless($agent->account_id, 403);

        $account = $agent->account()->firstOrFail();
        $sites = $account->sites()->orderBy('name')->get();
        $conversations = Conversation::query()
            ->with(['site', 'visitor'])
            ->where('status', 'open')
            ->whereHas('site', fn ($query) => $query->where('account_id', $account->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();

        return view('agent.dashboard', [
            'account' => $account,
            'agent' => $agent,
            'conversations' => $conversations,
            'sites' => $sites,
        ]);
    }
}
