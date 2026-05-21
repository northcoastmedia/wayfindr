<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_homepage_identifies_wayfindr_server(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Wayfindr Server');
    }

    public function test_health_endpoint_reports_ok_status(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'service' => 'Wayfindr',
        ]);
    }
}
