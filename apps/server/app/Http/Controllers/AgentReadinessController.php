<?php

namespace App\Http\Controllers;

use App\Support\OperatorReadiness;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AgentReadinessController extends Controller
{
    public function __invoke(Request $request, OperatorReadiness $readiness): View
    {
        $agent = $request->user();

        abort_unless($agent?->account_id, 403);
        abort_unless($agent->isAdmin(), 403);

        return view('agent.readiness.show', [
            'account' => $agent->account()->firstOrFail(),
            'agent' => $agent,
            'readiness' => $readiness->summary(),
        ]);
    }
}
