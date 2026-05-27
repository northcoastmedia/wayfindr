<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('account owners can change another same-account agent role from the account page', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $owner = User::factory()->for($account)->create([
        'account_role' => AccountRole::Owner,
        'name' => 'Olive Owner',
    ]);
    $agent = User::factory()->for($account)->create([
        'account_role' => AccountRole::Agent,
        'name' => 'Bea Builder',
        'email' => 'bea@example.test',
    ]);

    $this->actingAs($owner)
        ->get('/dashboard/account')
        ->assertOk()
        ->assertSee('Manage role')
        ->assertSee("/dashboard/account/agents/{$agent->id}/role", false)
        ->assertDontSee("/dashboard/account/agents/{$owner->id}/role", false);

    $this->actingAs($owner)
        ->from('/dashboard/account')
        ->put("/dashboard/account/agents/{$agent->id}/role", [
            'account_role' => AccountRole::Admin->value,
        ])
        ->assertRedirect('/dashboard/account')
        ->assertSessionHas('status', 'Agent role updated.');

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

test('admins and agents cannot use the account role management endpoint', function (AccountRole $actorRole): void {
    $account = Account::factory()->create();
    $actor = User::factory()->for($account)->create(['account_role' => $actorRole]);
    $target = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);

    $this->actingAs($actor)
        ->get('/dashboard/account')
        ->assertOk()
        ->assertDontSee('Manage role')
        ->assertDontSee("/dashboard/account/agents/{$target->id}/role", false);

    $this->actingAs($actor)
        ->from('/dashboard/account')
        ->put("/dashboard/account/agents/{$target->id}/role", [
            'account_role' => AccountRole::Admin->value,
        ])
        ->assertForbidden();

    expect($target->fresh()->account_role)->toBe(AccountRole::Agent)
        ->and(AuditEvent::query()->where('action', 'agent.role_changed')->exists())->toBeFalse();
})->with([
    'admin' => [AccountRole::Admin],
    'agent' => [AccountRole::Agent],
]);

test('owners cannot change roles for agents outside their account from the dashboard', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $owner = User::factory()->for($account)->create(['account_role' => AccountRole::Owner]);
    $outsideAgent = User::factory()->for($otherAccount)->create(['account_role' => AccountRole::Agent]);

    $this->actingAs($owner)
        ->from('/dashboard/account')
        ->put("/dashboard/account/agents/{$outsideAgent->id}/role", [
            'account_role' => AccountRole::Admin->value,
        ])
        ->assertForbidden();

    expect($outsideAgent->fresh()->account_role)->toBe(AccountRole::Agent)
        ->and(AuditEvent::query()->where('action', 'agent.role_changed')->exists())->toBeFalse();
});
