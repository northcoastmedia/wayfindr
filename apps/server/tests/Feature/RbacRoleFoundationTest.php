<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('users have an account role column cast to the account role enum', function (): void {
    expect(Schema::hasColumn('users', 'account_role'))->toBeTrue();

    $user = User::factory()->create([
        'account_role' => AccountRole::Admin,
    ]);

    expect($user->fresh()->account_role)->toBe(AccountRole::Admin);
});

test('new agents default to the agent account role', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();

    expect($agent->account_role)->toBe(AccountRole::Agent)
        ->and($agent->isAgent())->toBeTrue()
        ->and($agent->isAdmin())->toBeFalse()
        ->and($agent->isOwner())->toBeFalse();
});

test('role helpers expose owner admin and agent authority checks', function (): void {
    $account = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);

    expect($owner->hasAccountRole(AccountRole::Owner))->toBeTrue()
        ->and($owner->isOwner())->toBeTrue()
        ->and($owner->isAdmin())->toBeTrue()
        ->and($owner->isAgent())->toBeTrue()
        ->and($admin->isOwner())->toBeFalse()
        ->and($admin->isAdmin())->toBeTrue()
        ->and($admin->isAgent())->toBeTrue()
        ->and($agent->isOwner())->toBeFalse()
        ->and($agent->isAdmin())->toBeFalse()
        ->and($agent->isAgent())->toBeTrue();
});

test('bootstrap creates the first agent as the account owner', function (): void {
    $this->artisan('wayfindr:bootstrap', [
        '--account' => 'Acme Support',
        '--name' => 'Ada Agent',
        '--email' => 'ada@example.com',
        '--password' => 'correct-horse-battery-staple',
        '--site' => 'Acme Docs',
        '--site-public-key' => 'site_acme_public_key',
    ])->assertExitCode(0);

    $agent = User::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($agent->account_role)->toBe(AccountRole::Owner)
        ->and($agent->isOwner())->toBeTrue();
});

test('additional agents created by command default to the agent role', function (): void {
    $account = Account::factory()->create([
        'name' => 'Acme Support',
        'slug' => 'acme-support',
    ]);

    $this->artisan('wayfindr:agent', [
        '--account' => 'acme-support',
        '--name' => 'Bea Builder',
        '--email' => 'bea@example.com',
        '--password' => 'correct-horse-battery-staple',
    ])->assertExitCode(0);

    $agent = User::query()->where('email', 'bea@example.com')->firstOrFail();

    expect($agent->account_id)->toBe($account->id)
        ->and($agent->account_role)->toBe(AccountRole::Agent)
        ->and($agent->isAgent())->toBeTrue();
});

test('agent command preserves an existing account role when updating an agent', function (): void {
    $account = Account::factory()->create([
        'name' => 'Acme Support',
        'slug' => 'acme-support',
    ]);
    $owner = User::factory()->for($account)->create([
        'account_role' => AccountRole::Owner,
        'email' => 'ada@example.com',
        'name' => 'Ada Agent',
    ]);

    $this->artisan('wayfindr:agent', [
        '--account' => 'acme-support',
        '--name' => 'Ada Updated',
        '--email' => 'ada@example.com',
        '--password' => 'correct-horse-battery-staple',
    ])->assertExitCode(0);

    expect($owner->fresh()->account_role)->toBe(AccountRole::Owner)
        ->and($owner->fresh()->name)->toBe('Ada Updated');
});
