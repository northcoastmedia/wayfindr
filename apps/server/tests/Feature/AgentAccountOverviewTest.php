<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Conversation;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('agent can inspect their account role and same-account roster', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $owner = User::factory()->for($account)->create([
        'account_role' => AccountRole::Owner,
        'name' => 'Olive Owner',
        'email' => 'olive@example.test',
    ]);
    $admin = User::factory()->for($account)->create([
        'account_role' => AccountRole::Admin,
        'name' => 'Ada Admin',
        'email' => 'ada@example.test',
    ]);
    $agent = User::factory()->for($account)->create([
        'account_role' => AccountRole::Agent,
        'name' => 'Bea Builder',
        'email' => 'bea@example.test',
    ]);
    $otherAccount = Account::factory()->create(['name' => 'Other Support']);
    User::factory()->for($otherAccount)->create([
        'name' => 'Mallory Elsewhere',
        'email' => 'mallory@example.test',
    ]);

    $visibleSite = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $visibleSite->supportAgents()->attach([$owner->id, $admin->id, $agent->id]);
    $visibleVisitor = Visitor::factory()->for($visibleSite)->create();
    Conversation::factory()->for($visibleSite)->for($visibleVisitor)->create([
        'assigned_agent_id' => $agent->id,
        'status' => 'open',
    ]);
    Ticket::factory()->for($account)->for($visibleSite)->create([
        'assignee_id' => $agent->id,
        'status' => 'open',
    ]);

    $restrictedSite = Site::factory()->for($account)->create(['name' => 'Restricted Store']);
    $restrictedSite->supportAgents()->attach($admin);
    $restrictedVisitor = Visitor::factory()->for($restrictedSite)->create();
    Conversation::factory()->for($restrictedSite)->for($restrictedVisitor)->create([
        'assigned_agent_id' => $admin->id,
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->get('/dashboard/account')
        ->assertOk()
        ->assertSee('Account')
        ->assertSee('Acme Support')
        ->assertSee('Your role')
        ->assertSee('Agent')
        ->assertSee('Role changes are limited to account owners')
        ->assertSee('Olive Owner')
        ->assertSee('olive@example.test')
        ->assertSee('Owner')
        ->assertSee('Ada Admin')
        ->assertSee('Admin')
        ->assertSee('Bea Builder')
        ->assertSee('1 open conversation')
        ->assertSee('1 open ticket')
        ->assertSee('3 support assignments')
        ->assertSee('2 sites')
        ->assertDontSee('Mallory Elsewhere')
        ->assertDontSee('Other Support')
        ->assertDontSee('Restricted Store');
});

test('agent can inspect visible site access from the account overview', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $owner = User::factory()->for($account)->create([
        'account_role' => AccountRole::Owner,
        'name' => 'Olive Owner',
        'email' => 'olive@example.test',
    ]);
    $admin = User::factory()->for($account)->create([
        'account_role' => AccountRole::Admin,
        'name' => 'Ada Admin',
        'email' => 'ada@example.test',
    ]);
    $agent = User::factory()->for($account)->create([
        'account_role' => AccountRole::Agent,
        'name' => 'Bea Builder',
        'email' => 'bea@example.test',
    ]);
    $deactivatedAgent = User::factory()->for($account)->create([
        'name' => 'Doug Dormant',
        'email' => 'doug@example.test',
        'deactivated_at' => now(),
    ]);

    $fallbackSite = Site::factory()->for($account)->create([
        'name' => 'Public Docs',
        'domain' => 'docs.example.test',
    ]);
    $explicitSite = Site::factory()->for($account)->create([
        'name' => 'VIP Portal',
        'domain' => 'vip.example.test',
    ]);
    $explicitSite->supportAgents()->attach([$owner->id, $agent->id, $deactivatedAgent->id]);

    $restrictedSite = Site::factory()->for($account)->create([
        'name' => 'Restricted Store',
        'domain' => 'store.example.test',
    ]);
    $restrictedSite->supportAgents()->attach($admin);

    $this->actingAs($agent)
        ->get('/dashboard/account')
        ->assertOk()
        ->assertSee('Site access matrix')
        ->assertSee('Public Docs')
        ->assertSee('docs.example.test')
        ->assertSee('Account-wide fallback')
        ->assertSee('All active account agents')
        ->assertSee('VIP Portal')
        ->assertSee('vip.example.test')
        ->assertSee('Explicit access')
        ->assertSee('2 assigned active agents')
        ->assertSee('Olive Owner')
        ->assertSee('Bea Builder')
        ->assertSee(route('dashboard.sites.show', $fallbackSite), false)
        ->assertSee(route('dashboard.sites.show', $explicitSite), false)
        ->assertDontSee('Restricted Store');
});
