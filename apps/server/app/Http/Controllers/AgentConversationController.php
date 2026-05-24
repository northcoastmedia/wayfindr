<?php

namespace App\Http\Controllers;

use App\Events\CobrowseStateUpdated;
use App\Events\ConversationMessageCreated;
use App\Models\CobrowseSession;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ConversationNeedsReply;
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
            ->load(['assignedAgent', 'latestMessage', 'site', 'visitor']);

        $this->markConversationNotificationsRead($agent, $conversation);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $tickets = $conversation->tickets()
            ->with('assignee')
            ->latest()
            ->get();

        return view('agent.conversations.show', [
            'account' => $agent->account()->firstOrFail(),
            'accountAgents' => $agent->account->agents()->orderBy('name')->get(),
            'agent' => $agent,
            'cobrowseConsent' => $cobrowseConsentState->forConversation($conversation),
            'conversation' => $conversation,
            'messages' => $messages,
            'realtime' => $this->realtimeConfig($conversation),
            'tickets' => $tickets,
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
            'assigned_agent_id' => $conversation->assigned_agent_id ?: $agent->id,
            'status' => 'open',
            'closed_at' => null,
            'last_message_at' => $message->created_at,
        ])->save();

        $this->markConversationNotificationsRead($agent, $conversation);

        event(new ConversationMessageCreated($message));

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Reply sent.');
    }

    public function close(Request $request, string $supportCode): RedirectResponse
    {
        $agent = $request->user();
        $conversation = $this->conversationForAgent($agent, $supportCode);

        $conversation->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
        ])->save();

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Conversation closed.');
    }

    public function reopen(Request $request, string $supportCode): RedirectResponse
    {
        $agent = $request->user();
        $conversation = $this->conversationForAgent($agent, $supportCode);

        $conversation->forceFill([
            'status' => 'open',
            'closed_at' => null,
        ])->save();

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Conversation reopened.');
    }

    public function claim(Request $request, string $supportCode): RedirectResponse
    {
        $agent = $request->user();
        $conversation = $this->conversationForAgent($agent, $supportCode);

        abort_if($conversation->assigned_agent_id && $conversation->assigned_agent_id !== $agent->id, 403);

        $conversation->forceFill([
            'assigned_agent_id' => $agent->id,
        ])->save();

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Conversation claimed.');
    }

    public function release(Request $request, string $supportCode): RedirectResponse
    {
        $agent = $request->user();
        $conversation = $this->conversationForAgent($agent, $supportCode);

        abort_unless($conversation->assigned_agent_id === $agent->id, 403);

        $conversation->forceFill([
            'assigned_agent_id' => null,
        ])->save();

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Conversation released.');
    }

    public function storeTicket(Request $request, string $supportCode): RedirectResponse
    {
        $agent = $request->user();
        $conversation = $this->conversationForAgent($agent, $supportCode)
            ->load(['site', 'visitor']);

        $validated = $request->validate([
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ]);

        if ($conversation->tickets()->exists()) {
            return redirect()
                ->route('dashboard.conversations.show', $conversation->support_code)
                ->with('status', 'Ticket already exists.');
        }

        $conversation->tickets()->create([
            'account_id' => $conversation->site->account_id,
            'site_id' => $conversation->site_id,
            'requester_id' => $conversation->visitor_id,
            'assignee_id' => $agent->id,
            'status' => 'open',
            'priority' => $validated['priority'] ?? 'normal',
            'subject' => $conversation->subject ?: 'Conversation '.$conversation->support_code,
            'description' => $this->ticketDescription($conversation),
            'metadata' => [
                'source' => 'conversation',
                'support_code' => $conversation->support_code,
            ],
        ]);

        if (! $conversation->assigned_agent_id) {
            $conversation->forceFill([
                'assigned_agent_id' => $agent->id,
            ])->save();
        }

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Ticket created.');
    }

    public function requestCobrowse(Request $request, string $supportCode): RedirectResponse
    {
        $agent = $request->user();
        $conversation = $this->conversationForAgent($agent, $supportCode)
            ->load(['site', 'visitor']);

        if ($this->activeCobrowseSession($conversation)) {
            return redirect()
                ->route('dashboard.conversations.show', $conversation->support_code)
                ->with('status', 'Cobrowse request already active.');
        }

        $cobrowseSession = $conversation->cobrowseSessions()->create([
            'site_id' => $conversation->site_id,
            'visitor_id' => $conversation->visitor_id,
            'requested_by_id' => $agent->id,
            'status' => 'requested',
            'metadata' => [],
            'consented_at' => null,
            'ended_at' => null,
        ]);

        event(new CobrowseStateUpdated($cobrowseSession, 'consent_requested'));

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Cobrowse requested.');
    }

    public function endCobrowse(Request $request, string $supportCode): RedirectResponse
    {
        $agent = $request->user();
        $conversation = $this->conversationForAgent($agent, $supportCode);
        $cobrowseSession = $this->activeCobrowseSession($conversation);

        if (! $cobrowseSession) {
            return redirect()
                ->route('dashboard.conversations.show', $conversation->support_code)
                ->with('status', 'No active cobrowse session.');
        }

        $metadata = $cobrowseSession->metadata ?? [];
        $metadata['ended_by_id'] = $agent->id;
        $metadata['ended_by_type'] = 'agent';

        $cobrowseSession->forceFill([
            'status' => 'ended',
            'metadata' => $metadata,
            'ended_at' => now(),
        ])->save();

        event(new CobrowseStateUpdated($cobrowseSession, 'ended'));

        return redirect()
            ->route('dashboard.conversations.show', $conversation->support_code)
            ->with('status', 'Cobrowse session ended.');
    }

    private function conversationForAgent(User $agent, string $supportCode): Conversation
    {
        abort_unless($agent->account_id, 403);

        return Conversation::query()
            ->where('support_code', $supportCode)
            ->whereHas('site', fn ($query) => $query->where('account_id', $agent->account_id))
            ->firstOrFail();
    }

    private function activeCobrowseSession(Conversation $conversation): ?CobrowseSession
    {
        return $conversation->cobrowseSessions()
            ->whereNull('ended_at')
            ->whereIn('status', ['requested', 'granted'])
            ->latest('id')
            ->first();
    }

    private function markConversationNotificationsRead(User $agent, Conversation $conversation): void
    {
        $agent->unreadNotifications()
            ->where('type', ConversationNeedsReply::class)
            ->get()
            ->filter(fn ($notification): bool => (int) data_get($notification->data, 'conversation_id') === $conversation->id)
            ->each
            ->markAsRead();
    }

    private function ticketDescription(Conversation $conversation): string
    {
        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(function ($message): ?string {
                $body = trim((string) $message->body);

                if ($body === '') {
                    return null;
                }

                $senderName = $message->sender_type === User::class
                    ? ($message->sender?->name ?? 'Agent')
                    : 'Visitor';

                return $senderName.': '.$body;
            })
            ->filter()
            ->implode(PHP_EOL.PHP_EOL);

        if ($messages === '') {
            return 'Created from conversation '.$conversation->support_code.'.';
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function realtimeConfig(Conversation $conversation): ?array
    {
        if ((string) config('broadcasting.default') !== 'reverb') {
            return null;
        }

        $key = config('broadcasting.connections.reverb.key');
        $host = config('broadcasting.connections.reverb.options.host');
        $port = config('broadcasting.connections.reverb.options.port');
        $scheme = config('broadcasting.connections.reverb.options.scheme');

        if (! $this->hasConfigValue($key) || ! $this->hasConfigValue($host) || ! $this->hasConfigValue($port) || ! $this->hasConfigValue($scheme)) {
            return null;
        }

        return [
            'appKey' => (string) $key,
            'authEndpoint' => url('/broadcasting/auth'),
            'channelName' => 'private-conversations.'.$conversation->support_code,
            'eventName' => 'conversation.cobrowse.updated',
            'host' => (string) $host,
            'port' => (int) $port,
            'scheme' => (string) $scheme,
        ];
    }

    private function hasConfigValue(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : $value !== null;
    }
}
