<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\User;
use App\Support\OperatorReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('account owner can inspect operator readiness diagnostics', function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.app_id' => 'wayfindr-production',
        'broadcasting.connections.reverb.key' => 'wayfindr-key',
        'broadcasting.connections.reverb.secret' => 'wayfindr-secret',
        'broadcasting.connections.reverb.options.host' => 'wayfindr.example.test',
        'broadcasting.connections.reverb.options.port' => 443,
        'broadcasting.connections.reverb.options.scheme' => 'https',
        'queue.default' => 'database',
    ]);

    $agent = User::factory()->for(Account::factory(['name' => 'Acme Support']))->create([
        'account_role' => AccountRole::Owner,
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/readiness')
        ->assertOk()
        ->assertSee('Operator readiness')
        ->assertSee('Application key')
        ->assertSee('Database connection')
        ->assertSee('Queue worker')
        ->assertSee('Realtime broadcasting')
        ->assertSee('Storage paths')
        ->assertSee('Scheduler')
        ->assertSee('Ready')
        ->assertSee('php artisan schedule:run')
        ->assertSee('php artisan reverb:restart');
});

test('plain agents cannot inspect operator readiness diagnostics', function (): void {
    $agent = User::factory()->for(Account::factory())->create([
        'account_role' => AccountRole::Agent,
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/readiness')
        ->assertForbidden();
});

test('readiness diagnostics flag missing app key and incomplete realtime setup', function (): void {
    config([
        'app.key' => null,
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.app_id' => null,
        'broadcasting.connections.reverb.key' => null,
        'broadcasting.connections.reverb.secret' => null,
        'broadcasting.connections.reverb.options.host' => null,
        'broadcasting.connections.reverb.options.port' => null,
        'broadcasting.connections.reverb.options.scheme' => null,
    ]);

    $readiness = app(OperatorReadiness::class)->summary();
    $applicationKey = collect($readiness['checks'])->firstWhere('key', 'application_key');
    $realtime = collect($readiness['checks'])->firstWhere('key', 'realtime_broadcasting');

    expect($applicationKey)->toMatchArray([
        'label' => 'Application key',
        'status' => 'attention',
        'status_label' => 'Needs attention',
        'action' => 'Run php artisan key:generate and save the generated APP_KEY in the environment.',
    ])->and($realtime)->toMatchArray([
        'label' => 'Realtime broadcasting',
        'status' => 'attention',
        'detail' => 'Add Reverb app credentials and public host settings before enabling live updates.',
    ]);
});

test('readiness diagnostics require an authenticated agent', function (): void {
    $this->get('/dashboard/readiness')
        ->assertRedirect('/login');
});
