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
        $this->get('/up')
            ->assertOk()
            ->assertSee('Application up');
    }

    public function test_documented_legacy_health_endpoint_is_not_exposed(): void
    {
        $this->get('/health')
            ->assertNotFound();
    }
}
