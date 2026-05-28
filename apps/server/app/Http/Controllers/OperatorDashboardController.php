<?php

namespace App\Http\Controllers;

use App\Support\OperatorReadiness;
use App\Support\OperatorSystemIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OperatorDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        OperatorReadiness $readiness,
        OperatorSystemIdentity $systemIdentity,
    ): View {
        return view('operator.dashboard', [
            'operator' => $request->user(),
            'readiness' => $readiness->summary(),
            'systemIdentity' => $systemIdentity->summary(),
        ]);
    }
}
