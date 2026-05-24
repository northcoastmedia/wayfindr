<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentTicketController extends Controller
{
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
        $ticket->loadMissing('conversation');

        if ($ticket->conversation) {
            return redirect()
                ->route('dashboard.conversations.show', $ticket->conversation->support_code)
                ->with('status', $status);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', $status);
    }
}
