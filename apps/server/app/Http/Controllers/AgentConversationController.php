<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AgentConversationController extends Controller
{
    public function show(Request $request, string $supportCode): View
    {
        $agent = $request->user();

        abort_unless($agent->account_id, 403);

        $conversation = Conversation::query()
            ->with(['site', 'visitor'])
            ->where('support_code', $supportCode)
            ->whereHas('site', fn ($query) => $query->where('account_id', $agent->account_id))
            ->firstOrFail();

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('agent.conversations.show', [
            'account' => $agent->account()->firstOrFail(),
            'agent' => $agent,
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}
