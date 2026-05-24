<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentTicketController extends Controller
{
    public function show(Request $request, Ticket $ticket): View
    {
        $agent = $request->user();

        $this->abortUnlessAgentTicket($agent, $ticket);

        return view('agent.tickets.show', [
            'account' => $agent->account()->firstOrFail(),
            'accountAgents' => $agent->account->agents()->orderBy('name')->get(),
            'agent' => $agent,
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

        $ticket->auditEvents()->create([
            'account_id' => $ticket->account_id,
            'site_id' => $ticket->site_id,
            'actor_type' => User::class,
            'actor_id' => $agent->id,
            'action' => 'ticket.note_added',
            'metadata' => [
                'body' => $validated['body'],
            ],
            'occurred_at' => now(),
        ]);

        return $this->redirectAfterUpdate($ticket, 'Ticket note added.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->abortUnlessAgentTicket($request->user(), $ticket);

        $ticket->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
        ])->save();

        return $this->redirectAfterUpdate($ticket, 'Ticket closed.');
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->abortUnlessAgentTicket($request->user(), $ticket);

        $ticket->forceFill([
            'status' => 'open',
            'closed_at' => null,
        ])->save();

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

        $ticket->forceFill([
            'assignee_id' => $validated['assignee_id'] ?? null,
        ])->save();

        return $this->redirectAfterUpdate($ticket, 'Ticket assignee updated.');
    }

    private function abortUnlessAgentTicket(User $agent, Ticket $ticket): void
    {
        abort_unless($agent->account_id && $ticket->account_id === $agent->account_id, 404);
    }

    private function redirectAfterUpdate(Ticket $ticket, string $status): RedirectResponse
    {
        return redirect()
            ->back(302, [], route('dashboard'))
            ->with('status', $status);
    }
}
