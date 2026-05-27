<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('first run setup page is available before bootstrap data exists', function (): void {
    $this->get('/setup')
        ->assertOk()
        ->assertSee('Set up Wayfindr')
        ->assertSee('Create the first account, owner, and install site.')
        ->assertSee('account_name', false)
        ->assertSee('agent_email', false)
        ->assertSee('site_name', false);
});

test('login redirects empty installs to first run setup', function (): void {
    $this->get('/login')
        ->assertRedirect('/setup');
});

test('first run setup creates the owner account site and session', function (): void {
    $this->post('/setup', [
        'account_name' => 'Acme Support',
        'agent_name' => 'Ada Agent',
        'agent_email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'site_name' => 'Acme Docs',
        'site_domain' => 'docs.example.test',
    ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Wayfindr is ready.');

    $account = Account::query()->sole();
    $agent = User::query()->sole();
    $site = Site::query()->sole();

    expect($account->name)->toBe('Acme Support')
        ->and($account->slug)->toBe('acme-support')
        ->and($agent->account_id)->toBe($account->id)
        ->and($agent->account_role)->toBe(AccountRole::Owner)
        ->and($agent->name)->toBe('Ada Agent')
        ->and($agent->email)->toBe('ada@example.com')
        ->and(Hash::check('correct-horse-battery-staple', $agent->password))->toBeTrue()
        ->and($site->account_id)->toBe($account->id)
        ->and($site->name)->toBe('Acme Docs')
        ->and($site->domain)->toBe('docs.example.test')
        ->and($site->public_key)->toStartWith('site_')
        ->and($site->settings)->toMatchArray([
            'mask_selectors' => ['input[type="password"]', '[data-wayfindr-mask]'],
        ]);

    expect($site->supportAgents()->whereKey($agent->id)->exists())->toBeTrue();

    $this->assertAuthenticatedAs($agent);
});

test('first run setup is locked after bootstrap data exists', function (): void {
    Account::factory()->create();

    $this->get('/setup')
        ->assertRedirect('/login');

    $this->post('/setup', [
        'account_name' => 'Acme Support',
        'agent_name' => 'Ada Agent',
        'agent_email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'site_name' => 'Acme Docs',
    ])
        ->assertRedirect('/login');

    expect(User::query()->count())->toBe(0)
        ->and(Site::query()->count())->toBe(0);
});
