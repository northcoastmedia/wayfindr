<?php

use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('it creates the first account agent and site', function (): void {
    $this->artisan('wayfindr:bootstrap', [
        '--account' => 'Acme Support',
        '--name' => 'Ada Agent',
        '--email' => 'ada@example.com',
        '--password' => 'correct-horse-battery-staple',
        '--site' => 'Acme Docs',
        '--domain' => 'docs.example.test',
        '--site-public-key' => 'site_acme_public_key',
    ])
        ->expectsOutputToContain('Wayfindr is ready.')
        ->expectsOutputToContain('Agent email: ada@example.com')
        ->expectsOutputToContain('Site public key: site_acme_public_key')
        ->assertExitCode(0);

    $account = Account::query()->firstOrFail();
    $agent = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $site = Site::query()->where('public_key', 'site_acme_public_key')->firstOrFail();

    expect($account->name)->toBe('Acme Support')
        ->and($account->slug)->toBe('acme-support')
        ->and($agent->account_id)->toBe($account->id)
        ->and($agent->name)->toBe('Ada Agent')
        ->and(Hash::check('correct-horse-battery-staple', $agent->password))->toBeTrue()
        ->and($site->account_id)->toBe($account->id)
        ->and($site->name)->toBe('Acme Docs')
        ->and($site->domain)->toBe('docs.example.test');
});

test('it requires an email address', function (): void {
    $this->artisan('wayfindr:bootstrap')
        ->expectsOutputToContain('Pass --email to create the first agent.')
        ->assertExitCode(1);
});

test('it refuses to run again without force', function (): void {
    Account::factory()->create();

    $this->artisan('wayfindr:bootstrap', [
        '--email' => 'ada@example.com',
    ])
        ->expectsOutputToContain('Wayfindr already has bootstrap data.')
        ->assertExitCode(1);
});

test('force allows bootstrap to create missing records', function (): void {
    Account::factory()->create(['name' => 'Existing Account']);

    $this->artisan('wayfindr:bootstrap', [
        '--force' => true,
        '--account' => 'Acme Support',
        '--name' => 'Ada Agent',
        '--email' => 'ada@example.com',
        '--password' => 'correct-horse-battery-staple',
        '--site' => 'Acme Docs',
        '--site-public-key' => 'site_acme_public_key',
    ])
        ->expectsOutputToContain('Wayfindr is ready.')
        ->assertExitCode(0);

    $this->assertDatabaseHas('accounts', ['slug' => 'acme-support']);
    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    $this->assertDatabaseHas('sites', ['public_key' => 'site_acme_public_key']);
});
