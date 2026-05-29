<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('site policy allows support agents to view but not update privacy for sites they support', function (): void {
    $account = Account::factory()->create();
    $supportAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $otherAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach($supportAgent);

    expect(Gate::forUser($supportAgent)->allows('view', $site))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('updatePrivacy', $site))->toBeFalse()
        ->and(Gate::forUser($otherAgent)->allows('view', $site))->toBeFalse()
        ->and(Gate::forUser($otherAgent)->allows('updatePrivacy', $site))->toBeFalse();
});

test('site policy allows account owners and admins to update privacy only for sites they support', function (): void {
    $account = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $unsupportedAdmin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach([$owner->id, $admin->id]);

    expect(Gate::forUser($owner)->allows('updatePrivacy', $site))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('updatePrivacy', $site))->toBeTrue()
        ->and(Gate::forUser($unsupportedAdmin)->allows('updatePrivacy', $site))->toBeFalse();
});

test('site policy allows account owners and admins to manage access only for sites they support', function (): void {
    $account = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $unsupportedAdmin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach([$owner->id, $admin->id, $agent->id]);

    expect(Gate::forUser($owner)->allows('manageAccess', $site))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('manageAccess', $site))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('manageAccess', $site))->toBeFalse()
        ->and(Gate::forUser($unsupportedAdmin)->allows('manageAccess', $site))->toBeFalse();
});

test('site policy denies deactivated agents even when stale assignments remain', function (): void {
    $account = Account::factory()->create();
    $deactivatedAgent = User::factory()->for($account)->create([
        'account_role' => AccountRole::Agent,
        'deactivated_at' => now(),
    ]);
    $deactivatedAdmin = User::factory()->for($account)->create([
        'account_role' => AccountRole::Admin,
        'deactivated_at' => now(),
    ]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach([$deactivatedAgent->id, $deactivatedAdmin->id]);

    expect(Gate::forUser($deactivatedAgent)->allows('view', $site))->toBeFalse()
        ->and(Gate::forUser($deactivatedAgent)->allows('updatePrivacy', $site))->toBeFalse()
        ->and(Gate::forUser($deactivatedAdmin)->allows('view', $site))->toBeFalse()
        ->and(Gate::forUser($deactivatedAdmin)->allows('manageAccess', $site))->toBeFalse()
        ->and(Gate::forUser($deactivatedAdmin)->allows('manageIntegrations', $site))->toBeFalse();
});
