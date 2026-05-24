<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
