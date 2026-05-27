<?php

namespace App\Http\Controllers;

use App\Enums\AccountRole;
use App\Models\AuditEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AgentProfileController extends Controller
{
    public function show(Request $request): View
    {
        $agent = $request->user();

        return view('agent.profile.show', [
            'agent' => $agent,
            'account' => $agent->account,
            'roleLabels' => [
                AccountRole::Owner->value => 'Owner',
                AccountRole::Admin->value => 'Admin',
                AccountRole::Agent->value => 'Agent',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'name' => trim($validated['name']),
        ]);

        return redirect()
            ->route('dashboard.profile.show')
            ->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $agent = $request->user();

        $agent->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditEvent::query()->create([
            'account_id' => $agent->account_id,
            'actor_type' => $agent->getMorphClass(),
            'actor_id' => $agent->id,
            'subject_type' => $agent->getMorphClass(),
            'subject_id' => $agent->id,
            'action' => 'agent.password_updated',
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('dashboard.profile.show')
            ->with('status', 'Password updated.');
    }
}
