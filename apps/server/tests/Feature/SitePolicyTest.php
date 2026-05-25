<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('site policy allows support agents to view and update privacy for sites they support', function (): void {
    $account = Account::factory()->create();
    $supportAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $otherAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach($supportAgent);

    expect(Gate::forUser($supportAgent)->allows('view', $site))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('updatePrivacy', $site))->toBeTrue()
        ->and(Gate::forUser($otherAgent)->allows('view', $site))->toBeFalse()
        ->and(Gate::forUser($otherAgent)->allows('updatePrivacy', $site))->toBeFalse();
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
