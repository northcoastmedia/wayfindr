<?php

use App\Enums\AccountRole;
use App\Enums\PlatformRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('operator console requires authentication', function (): void {
    $this->get('/operator')
        ->assertRedirect('/login');
});

test('account roles do not grant platform operator access', function (AccountRole $accountRole): void {
    $agent = User::factory()->for(Account::factory())->create([
        'account_role' => $accountRole,
    ]);

    $this->actingAs($agent)
        ->get('/operator')
        ->assertForbidden();
})->with([
    'owner' => AccountRole::Owner,
    'admin' => AccountRole::Admin,
    'agent' => AccountRole::Agent,
]);

test('explicit platform operators can inspect the operator console', function (): void {
    $operator = User::factory()->for(Account::factory(['name' => 'Wayfindr Ops']))->create([
        'account_role' => AccountRole::Agent,
        'platform_role' => PlatformRole::Operator,
    ]);

    $this->actingAs($operator)
        ->get('/operator')
        ->assertOk()
        ->assertSee('Operator console')
        ->assertSee('Instance readiness')
        ->assertSee('Platform operator access does not grant support data access.');
});

test('deactivated platform operators cannot inspect the operator console', function (): void {
    $operator = User::factory()->for(Account::factory())->create([
        'platform_role' => PlatformRole::Operator,
        'deactivated_at' => now(),
    ]);

    $this->actingAs($operator)
        ->get('/operator')
        ->assertRedirect('/login');
});
