<?php

test('public widget script is served from the Laravel app', function (): void {
    $response = $this->get('/widget.js');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
        ->assertSee('root.Wayfindr = api', false)
        ->assertSee('createClient', false);
});
