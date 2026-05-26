<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\ExternalIssueProviderConnection;
use App\Models\Site;
use App\Models\SiteExternalIssueProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('account admins can create encrypted external issue provider connections', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $site->supportAgents()->attach($admin);

    $this->actingAs($admin)
        ->get("/dashboard/sites/{$site->id}")
        ->assertOk()
        ->assertSee('External issue routing')
        ->assertSee('Add provider connection');

    $this->actingAs($admin)
        ->from("/dashboard/sites/{$site->id}")
        ->post('/dashboard/external-issue-provider-connections', [
            'site_id' => $site->id,
            'provider' => 'github',
            'name' => 'Engineering GitHub',
            'base_url' => 'https://api.github.com',
            'credential_token' => 'ghp_test_secret',
            'capabilities' => ['create_issue', 'add_comment'],
        ])
        ->assertRedirect("/dashboard/sites/{$site->id}")
        ->assertSessionHas('status', 'Provider connection saved.');

    $connection = ExternalIssueProviderConnection::query()->firstOrFail();

    expect($connection->account_id)->toBe($account->id)
        ->and($connection->provider)->toBe('github')
        ->and($connection->providerLabel())->toBe('GitHub')
        ->and($connection->hasCapability('create_issue'))->toBeTrue()
        ->and($connection->hasCapability('sync_status'))->toBeFalse()
        ->and($connection->credentials)->toBe(['token' => 'ghp_test_secret']);

    $storedCredentials = DB::table('external_issue_provider_connections')
        ->where('id', $connection->id)
        ->value('credentials');

    expect($storedCredentials)->toBeString()
        ->and($storedCredentials)->not->toContain('ghp_test_secret');
});

test('account admins can map a site to an external provider project', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $site->supportAgents()->attach($admin);
    $connection = ExternalIssueProviderConnection::factory()
        ->for($account)
        ->create([
            'provider' => 'github',
            'name' => 'Engineering GitHub',
            'capabilities' => [
                'create_issue' => true,
                'add_comment' => true,
                'sync_status' => false,
            ],
        ]);

    $this->actingAs($admin)
        ->from("/dashboard/sites/{$site->id}")
        ->post("/dashboard/sites/{$site->id}/external-issue-projects", [
            'external_issue_provider_connection_id' => $connection->id,
            'project_key' => 'adamgreenwell/wayfindr',
            'project_name' => 'Wayfindr',
            'web_url' => 'https://github.com/adamgreenwell/wayfindr',
        ])
        ->assertRedirect("/dashboard/sites/{$site->id}")
        ->assertSessionHas('status', 'External issue project mapped.');

    $mapping = SiteExternalIssueProject::query()->firstOrFail();

    expect($mapping->account_id)->toBe($account->id)
        ->and($mapping->site_id)->toBe($site->id)
        ->and($mapping->providerConnection->is($connection))->toBeTrue()
        ->and($mapping->providerLabel())->toBe('GitHub')
        ->and($mapping->hasCapability('create_issue'))->toBeTrue()
        ->and($mapping->hasCapability('sync_status'))->toBeFalse();

    $this->actingAs($admin)
        ->get("/dashboard/sites/{$site->id}")
        ->assertOk()
        ->assertSee('External issue routing')
        ->assertSee('Engineering GitHub')
        ->assertSee('adamgreenwell/wayfindr')
        ->assertSee('Create issues')
        ->assertDontSee('Sync status');
});

test('site project mappings reject provider connections from another account', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $otherAccount = Account::factory()->create(['name' => 'Other Support']);
    $admin = User::factory()->for($account)->create(['account_role' => AccountRole::Admin]);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $site->supportAgents()->attach($admin);
    $otherConnection = ExternalIssueProviderConnection::factory()
        ->for($otherAccount)
        ->create(['name' => 'Other GitHub']);

    $this->actingAs($admin)
        ->from("/dashboard/sites/{$site->id}")
        ->post("/dashboard/sites/{$site->id}/external-issue-projects", [
            'external_issue_provider_connection_id' => $otherConnection->id,
            'project_key' => 'other/private',
        ])
        ->assertRedirect("/dashboard/sites/{$site->id}")
        ->assertSessionHasErrors('external_issue_provider_connection_id');

    expect(SiteExternalIssueProject::query()->exists())->toBeFalse();
});

test('plain agents can view mapped external projects but cannot manage routing', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create([
        'account_role' => AccountRole::Agent,
        'name' => 'Ada Agent',
    ]);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $site->supportAgents()->attach($agent);
    $connection = ExternalIssueProviderConnection::factory()
        ->for($account)
        ->create(['name' => 'Engineering GitHub']);
    SiteExternalIssueProject::factory()
        ->for($account)
        ->for($site)
        ->for($connection, 'providerConnection')
        ->create(['project_key' => 'adamgreenwell/wayfindr']);

    $this->actingAs($agent)
        ->get("/dashboard/sites/{$site->id}")
        ->assertOk()
        ->assertSee('External issue routing')
        ->assertSee('Engineering GitHub')
        ->assertSee('adamgreenwell/wayfindr')
        ->assertSee('Account owners and admins manage external issue routing.')
        ->assertDontSee('Add provider connection')
        ->assertDontSee('Map project');

    $this->actingAs($agent)
        ->post('/dashboard/external-issue-provider-connections', [
            'site_id' => $site->id,
            'provider' => 'github',
            'name' => 'Nope',
        ])
        ->assertForbidden();

    $this->actingAs($agent)
        ->post("/dashboard/sites/{$site->id}/external-issue-projects", [
            'external_issue_provider_connection_id' => $connection->id,
            'project_key' => 'blocked/project',
        ])
        ->assertForbidden();
});
