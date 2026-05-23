<?php

namespace Tests\Feature;

use Tests\TestCase;

class WidgetScriptAssetTest extends TestCase
{
    public function test_public_widget_script_is_served_from_the_laravel_app(): void
    {
        $response = $this->get('/widget.js');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertSee('root.Wayfindr = api', false)
            ->assertSee('createClient', false);
    }
}
