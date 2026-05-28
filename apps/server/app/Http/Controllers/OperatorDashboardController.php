<?php

namespace App\Http\Controllers;

use App\Support\OperatorReadiness;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OperatorDashboardController extends Controller
{
    public function __invoke(Request $request, OperatorReadiness $readiness): View
    {
        return view('operator.dashboard', [
            'operator' => $request->user(),
            'readiness' => $readiness->summary(),
        ]);
    }
}
