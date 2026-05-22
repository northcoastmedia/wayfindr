<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WayfindrBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_account_agent_and_site(): void
    {
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

        $this->assertSame('Acme Support', $account->name);
        $this->assertSame('acme-support', $account->slug);
        $this->assertSame($account->id, $agent->account_id);
        $this->assertSame('Ada Agent', $agent->name);
        $this->assertTrue(Hash::check('correct-horse-battery-staple', $agent->password));
        $this->assertSame($account->id, $site->account_id);
        $this->assertSame('Acme Docs', $site->name);
        $this->assertSame('docs.example.test', $site->domain);
    }

    public function test_it_requires_an_email_address(): void
    {
        $this->artisan('wayfindr:bootstrap')
            ->expectsOutputToContain('Pass --email to create the first agent.')
            ->assertExitCode(1);
    }

    public function test_it_refuses_to_run_again_without_force(): void
    {
        Account::factory()->create();

        $this->artisan('wayfindr:bootstrap', [
            '--email' => 'ada@example.com',
        ])
            ->expectsOutputToContain('Wayfindr already has bootstrap data.')
            ->assertExitCode(1);
    }

    public function test_force_allows_bootstrap_to_create_missing_records(): void
    {
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
    }
}
