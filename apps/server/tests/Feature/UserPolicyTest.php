<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('user policy scopes account agent creation to active owners and admins', function (): void {
    $account = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $deactivatedAdmin = User::factory()->for($account)->create([
        'account_role' => AccountRole::Admin,
        'deactivated_at' => now(),
    ]);
    $orphanOwner = User::factory()->create([
        'account_id' => null,
        'account_role' => AccountRole::Owner,
    ]);

    expect(Gate::forUser($owner)->allows('createAccountAgent', User::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('createAccountAgent', User::class))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('createAccountAgent', User::class))->toBeFalse()
        ->and(Gate::forUser($deactivatedAdmin)->allows('createAccountAgent', User::class))->toBeFalse()
        ->and(Gate::forUser($orphanOwner)->allows('createAccountAgent', User::class))->toBeFalse();
});

test('user policy limits role updates to owners managing same account users', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $deactivatedOwner = User::factory()->for($account)->create([
        'account_role' => AccountRole::Owner,
        'deactivated_at' => now(),
    ]);
    $outsideAgent = User::factory()->for($otherAccount)->create(['account_role' => AccountRole::Agent]);

    expect(Gate::forUser($owner)->allows('updateRole', $agent))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('updateRole', $agent))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('updateRole', $admin))->toBeFalse()
        ->and(Gate::forUser($deactivatedOwner)->allows('updateRole', $agent))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('updateRole', $outsideAgent))->toBeFalse();
});

test('user policy scopes account access changes by role account and target authority', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $deactivatedAdmin = User::factory()->for($account)->create([
        'account_role' => AccountRole::Admin,
        'deactivated_at' => now(),
    ]);
    $outsideAgent = User::factory()->for($otherAccount)->create(['account_role' => AccountRole::Agent]);

    expect(Gate::forUser($owner)->allows('deactivate', $admin))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('reactivate', $admin))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('deactivate', $agent))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('reactivate', $agent))->toBeTrue()
        ->and(Gate::forUser($deactivatedAdmin)->allows('deactivate', $agent))->toBeFalse()
        ->and(Gate::forUser($deactivatedAdmin)->allows('reactivate', $agent))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('deactivate', $owner))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('deactivate', $admin))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('deactivate', $owner))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('deactivate', $outsideAgent))->toBeFalse();
});
