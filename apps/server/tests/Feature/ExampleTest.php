<?php

test('homepage identifies the Wayfindr server', function (): void {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('Wayfindr Server');
});

test('health endpoint reports ok status', function (): void {
    $this->get('/up')
        ->assertOk()
        ->assertSee('Application up');
});

test('documented legacy health endpoint is not exposed', function (): void {
    $this->get('/health')
        ->assertNotFound();
});
