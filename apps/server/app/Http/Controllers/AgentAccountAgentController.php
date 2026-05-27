<?php

namespace App\Http\Controllers;

use App\Enums\AccountRole;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgentAccountAgentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor?->account_id && $actor->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
        ]);

        $password = Str::password(24);

        $agent = User::query()->create([
            'account_id' => $actor->account_id,
            'account_role' => AccountRole::Agent,
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($password),
        ]);

        AuditEvent::query()->create([
            'account_id' => $actor->account_id,
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->id,
            'subject_type' => $agent->getMorphClass(),
            'subject_id' => $agent->id,
            'action' => 'agent.created',
            'metadata' => [
                'role' => AccountRole::Agent->value,
            ],
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('dashboard.account.show')
            ->with('status', 'Agent created. Share the temporary password securely.')
            ->with('created_agent_email', $agent->email)
            ->with('created_agent_password', $password);
    }
}
