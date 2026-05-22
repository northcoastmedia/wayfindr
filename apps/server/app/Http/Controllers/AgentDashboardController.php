<?php

namespace App\Http\Controllers;

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

        return view('agent.dashboard', [
            'account' => $account,
            'agent' => $agent,
            'sites' => $sites,
        ]);
    }
}
