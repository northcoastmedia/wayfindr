<?php

namespace App\Http\Controllers;

use App\Events\ConversationMessageCreated;
use App\Models\Conversation;
use App\Models\User;
use App\Support\CobrowseConsentState;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgentConversationController extends Controller
{
    public function show(Request $request, string $supportCode, CobrowseConsentState $cobrowseConsentState): View
    {
        $agent = $request->user();

        $conversation = $this->conversationForAgent($agent, $supportCode)
            ->load(['site', 'visitor']);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('agent.conversations.show', [
            'account' => $agent->account()->firstOrFail(),
            'agent' => $agent,
            'cobrowseConsent' => $cobrowseConsentState->forConversation($conversation),
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function storeMessage(Request $request, string $supportCode): RedirectResponse
    {
        $agent = $request->user();
        $conversation = $this->conversationForAgent($agent, $supportCode);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $body = trim($validated['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Please enter a reply.',
            ]);
        }

        $message = $conversation->messages()->create([
            'sender_type' => User::class,
            'sender_id' => $agent->id,
            'type' => 'text',
            'body' => $body,
            'metadata' => [],
        ]);

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
        ])->save();

        event(new ConversationMessageCreated($message));

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Reply sent.');
    }

    private function conversationForAgent(User $agent, string $supportCode): Conversation
    {
        abort_unless($agent->account_id, 403);

        return Conversation::query()
            ->where('support_code', $supportCode)
            ->whereHas('site', fn ($query) => $query->where('account_id', $agent->account_id))
            ->firstOrFail();
    }
}
