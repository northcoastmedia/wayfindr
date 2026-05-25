<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentTicketController extends Controller
{
    public function show(Request $request, Ticket $ticket): View
    {
        $agent = $request->user();

        $this->abortUnlessAgentTicket($agent, $ticket);
        $this->markTicketAssignmentNotificationsRead($agent, $ticket);
        $ticket->loadMissing('site');

        return view('agent.tickets.show', [
            'account' => $agent->account()->firstOrFail(),
            'accountAgents' => $this->supportAgentsForSite($ticket->site),
            'agent' => $agent,
            'ticketActivity' => $ticket->auditEvents()
                ->with('actor')
                ->whereIn('action', $this->visibleActivityActions())
                ->latest('occurred_at')
                ->latest('id')
                ->get(),
            'ticket' => $ticket->load([
                'assignee',
                'conversation',
                'requester',
                'site',
                'auditEvents' => fn ($query) => $query
                    ->where('action', 'ticket.note_added')
                    ->with('actor')
                    ->latest('occurred_at')
                    ->latest('id'),
            ]),
        ]);
    }

    public function storeNote(Request $request, Ticket $ticket): RedirectResponse
    {
        $agent = $request->user();

        $this->abortUnlessAgentTicket($agent, $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $this->recordActivity($ticket, $agent, 'ticket.note_added', [
            'body' => $validated['body'],
        ]);

        return $this->redirectAfterUpdate($ticket, 'Ticket note added.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $agent = $request->user();

        $this->abortUnlessAgentTicket($agent, $ticket);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);

        $changes = $this->ticketFieldChanges($ticket, $validated);

        $ticket->forceFill($validated)->save();

        if ($changes !== []) {
            $this->recordActivity($ticket, $agent, 'ticket.updated', [
                'changes' => $changes,
            ]);
        }

        return $this->redirectAfterUpdate($ticket, 'Ticket updated.');
    }

    public function pending(Request $request, Ticket $ticket): RedirectResponse
    {
        $agent = $request->user();

        $this->abortUnlessAgentTicket($agent, $ticket);

        $ticket->forceFill([
            'status' => 'pending',
            'closed_at' => null,
        ])->save();

        $this->recordActivity($ticket, $agent, 'ticket.pending');

        return $this->redirectAfterUpdate($ticket, 'Ticket marked pending.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $agent = $request->user();

        $this->abortUnlessAgentTicket($agent, $ticket);

        $ticket->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
        ])->save();

        $this->recordActivity($ticket, $agent, 'ticket.closed');

        return $this->redirectAfterUpdate($ticket, 'Ticket closed.');
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        $agent = $request->user();

        $this->abortUnlessAgentTicket($agent, $ticket);

        $ticket->forceFill([
            'status' => 'open',
            'closed_at' => null,
        ])->save();

        $this->recordActivity($ticket, $agent, 'ticket.reopened');

        return $this->redirectAfterUpdate($ticket, 'Ticket reopened.');
    }

    public function updateAssignee(Request $request, Ticket $ticket): RedirectResponse
    {
        $agent = $request->user();

        $this->abortUnlessAgentTicket($agent, $ticket);

        $validated = $request->validate([
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('account_id', $agent->account_id),
            ],
        ]);

        $ticket->loadMissing(['assignee', 'site']);
        $oldAssigneeId = $ticket->assignee_id;
        $oldAssigneeName = $ticket->assignee?->name;
        $newAssigneeId = $validated['assignee_id'] ?? null;
        $newAssignee = $newAssigneeId
            ? $agent->account->agents()->whereKey($newAssigneeId)->first()
            : null;

        if ($newAssignee && ! $ticket->site->supportsAgent($newAssignee)) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Choose an agent assigned to this site.',
            ]);
        }

        $newAssigneeName = $newAssignee?->name;

        $ticket->forceFill([
            'assignee_id' => $newAssigneeId,
        ])->save();

        $this->recordActivity($ticket, $agent, 'ticket.assignee_updated', [
            'old_assignee_name' => $oldAssigneeName,
            'new_assignee_name' => $newAssigneeName,
        ]);

        if ($newAssignee && $newAssignee->isNot($agent) && $newAssignee->id !== $oldAssigneeId) {
            $newAssignee->notify(new TicketAssigned($ticket->fresh(), $agent));
        }

        return $this->redirectAfterUpdate($ticket, 'Ticket assignee updated.');
    }

    private function abortUnlessAgentTicket(User $agent, Ticket $ticket): void
    {
        $ticket->loadMissing('site');

        abort_unless(
            $agent->account_id
                && (int) $ticket->account_id === (int) $agent->account_id
                && $ticket->site?->supportsAgent($agent),
            404
        );
    }

    private function supportAgentsForSite(Site $site): Collection
    {
        $supportAgents = $site->eligibleSupportAgents()
            ->orderBy('name')
            ->get();

        return $supportAgents->isNotEmpty()
            ? $supportAgents
            : $site->account->agents()->orderBy('name')->get();
    }

    private function redirectAfterUpdate(Ticket $ticket, string $status): RedirectResponse
    {
        return redirect()
            ->back(302, [], route('dashboard'))
            ->with('status', $status);
    }

    private function markTicketAssignmentNotificationsRead(User $agent, Ticket $ticket): void
    {
        $agent->unreadNotifications()
            ->where('type', TicketAssigned::class)
            ->get()
            ->filter(fn ($notification): bool => (int) data_get($notification->data, 'ticket_id') === $ticket->id)
            ->each
            ->markAsRead();
    }

    /**
     * @return list<string>
     */
    private function visibleActivityActions(): array
    {
        return [
            'ticket.updated',
            'ticket.pending',
            'ticket.closed',
            'ticket.reopened',
            'ticket.assignee_updated',
            'ticket.note_added',
        ];
    }

    /**
     * @param  array{subject: string, description?: string|null, priority: string}  $validated
     * @return array<string, array{old: string|null, new: string|null}>
     */
    private function ticketFieldChanges(Ticket $ticket, array $validated): array
    {
        $changes = [];

        foreach (['subject', 'description', 'priority'] as $field) {
            $oldValue = $ticket->{$field};
            $newValue = $validated[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    private function recordActivity(Ticket $ticket, User $agent, string $action, array $metadata = []): void
    {
        $ticket->auditEvents()->create([
            'account_id' => $ticket->account_id,
            'site_id' => $ticket->site_id,
            'actor_type' => User::class,
            'actor_id' => $agent->id,
            'action' => $action,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
