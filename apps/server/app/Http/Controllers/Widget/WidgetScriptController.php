<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class WidgetScriptController extends Controller
{
    public function __invoke(): Response
    {
        $scriptPath = base_path('../../packages/widget-js/src/wayfindr-widget.js');

        abort_unless(is_file($scriptPath), 404);

        return response(file_get_contents($scriptPath), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
