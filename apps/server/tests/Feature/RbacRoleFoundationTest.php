<?php

use App\Actions\UpdateAgentRole;
use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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

test('owners can change another account agent role and record an audit event', function (): void {
    $account = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);

    app(UpdateAgentRole::class)->handle($owner, $agent, AccountRole::Admin);

    $auditEvent = AuditEvent::query()
        ->where('action', 'agent.role_changed')
        ->firstOrFail();

    expect($agent->fresh()->account_role)->toBe(AccountRole::Admin)
        ->and($auditEvent->account_id)->toBe($account->id)
        ->and($auditEvent->actor->is($owner))->toBeTrue()
        ->and($auditEvent->subject->is($agent))->toBeTrue()
        ->and($auditEvent->metadata)->toMatchArray([
            'old_role' => AccountRole::Agent->value,
            'new_role' => AccountRole::Admin->value,
        ]);
});

test('role changes lock actor and target rows in a deterministic query', function (): void {
    $account = Account::factory()->create();
    $target = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $actor = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);

    DB::enableQueryLog();

    app(UpdateAgentRole::class)->handle($actor, $target, AccountRole::Admin);

    $lockQuery = collect(DB::getQueryLog())
        ->pluck('query')
        ->first(fn (string $query): bool => str_contains($query, 'from "users"')
            && str_contains($query, '"id" in')
            && str_contains($query, 'order by "id" asc'));

    expect($target->fresh()->account_role)->toBe(AccountRole::Admin)
        ->and($lockQuery)->not->toBeNull();
});

test('admins and agents cannot change account roles', function (AccountRole $actorRole): void {
    $account = Account::factory()->create();
    $actor = User::factory()->for($account)->create(['account_role' => $actorRole]);
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);

    expect(fn () => app(UpdateAgentRole::class)->handle($actor, $agent, AccountRole::Admin))
        ->toThrow(AuthorizationException::class);

    expect($agent->fresh()->account_role)->toBe(AccountRole::Agent)
        ->and(AuditEvent::query()->where('action', 'agent.role_changed')->exists())->toBeFalse();
})->with([
    'admin' => [AccountRole::Admin],
    'agent' => [AccountRole::Agent],
]);

test('owners cannot change roles for another account', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $outsideAgent = User::factory()->for($otherAccount)->create(['account_role' => AccountRole::Agent]);

    expect(fn () => app(UpdateAgentRole::class)->handle($owner, $outsideAgent, AccountRole::Admin))
        ->toThrow(AuthorizationException::class);

    expect($outsideAgent->fresh()->account_role)->toBe(AccountRole::Agent)
        ->and(AuditEvent::query()->where('action', 'agent.role_changed')->exists())->toBeFalse();
});

test('owners cannot change their own role', function (): void {
    $account = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);

    expect(fn () => app(UpdateAgentRole::class)->handle($owner, $owner, AccountRole::Admin))
        ->toThrow(AuthorizationException::class);

    expect($owner->fresh()->account_role)->toBe(AccountRole::Owner)
        ->and(AuditEvent::query()->where('action', 'agent.role_changed')->exists())->toBeFalse();
});

test('owners cannot demote the last account owner', function (): void {
    $account = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);

    expect(fn () => app(UpdateAgentRole::class)->handle($owner, $owner, AccountRole::Admin))
        ->toThrow(ValidationException::class);

    expect($owner->fresh()->account_role)->toBe(AccountRole::Owner);
});
