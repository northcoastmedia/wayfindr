<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard gives agents a clear place to manage their workspace', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create([
        'account_role' => AccountRole::Agent,
        'name' => 'Ada Agent',
    ]);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Manage')
        ->assertSee('Profile and alerts')
        ->assertSee('/dashboard/profile', false)
        ->assertSee('Sites and widget installs')
        ->assertSee('/dashboard/sites', false)
        ->assertSee('Account and team')
        ->assertSee('/dashboard/account', false)
        ->assertDontSee('Operator readiness')
        ->assertDontSee('/dashboard/readiness', false);
});

test('dashboard gives account admins an operator readiness shortcut', function (): void {
    $admin = User::factory()->for(Account::factory())->create([
        'account_role' => AccountRole::Admin,
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Operator readiness')
        ->assertSee('/dashboard/readiness', false);
});
