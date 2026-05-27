<?php

use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard shows site widget connection status from latest visitor check in', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create();
    $connectedSite = Site::factory()->for($account)->create([
        'name' => 'Wayfindr Public Site',
        'domain' => 'wayfindr.cc',
    ]);
    $quietSite = Site::factory()->for($account)->create([
        'name' => 'Unused Smoke Site',
        'domain' => 'smoke.example.test',
    ]);
    $otherAccount = Account::factory()->create();
    $otherSite = Site::factory()->for($otherAccount)->create(['name' => 'Other Site']);

    Visitor::factory()->for($connectedSite)->create([
        'anonymous_id' => 'anon-old',
        'last_seen_at' => now()->subHour(),
        'metadata' => [
            'last_page_url' => 'https://wayfindr.cc/old',
        ],
    ]);

    Visitor::factory()->for($connectedSite)->create([
        'anonymous_id' => 'anon-current',
        'last_seen_at' => now()->subMinutes(5),
        'metadata' => [
            'last_page_url' => 'https://wayfindr.cc/pricing',
        ],
    ]);

    Visitor::factory()->for($otherSite)->create([
        'anonymous_id' => 'anon-other',
        'last_seen_at' => now()->subMinute(),
        'metadata' => [
            'last_page_url' => 'https://other.example.test',
        ],
    ]);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Last check-in')
        ->assertSee('Wayfindr Public Site')
        ->assertSee('Seen 5 minutes ago')
        ->assertSee('https://wayfindr.cc/pricing')
        ->assertSee('Unused Smoke Site')
        ->assertSee('Not seen yet')
        ->assertDontSee('https://wayfindr.cc/old')
        ->assertDontSee('Other Site')
        ->assertDontSee('https://other.example.test');
});

test('site settings show the latest widget check in details', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create([
        'name' => 'Wayfindr Public Site',
        'domain' => 'wayfindr.cc',
    ]);

    Visitor::factory()->for($site)->create([
        'anonymous_id' => 'anon-current',
        'last_seen_at' => now()->subMinutes(3),
        'metadata' => [
            'last_page_url' => 'https://wayfindr.cc/docs',
        ],
    ]);

    $this->actingAs($agent)
        ->get("/dashboard/sites/{$site->id}")
        ->assertOk()
        ->assertSee('Latest check-in')
        ->assertSee('Seen 3 minutes ago')
        ->assertSee('https://wayfindr.cc/docs')
        ->assertSee('Install verification')
        ->assertSee('The widget has checked in recently.')
        ->assertSee('Last verified page')
        ->assertSee('Verify again')
        ->assertSee("/dashboard/sites/{$site->id}?verify=", false)
        ->assertDontSee("href=\"http://localhost/dashboard/sites/{$site->id}#install-verification\"", false);
});

test('site settings guide agents when the widget has not checked in yet', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create([
        'name' => 'Fresh Install',
        'domain' => 'fresh.example.test',
    ]);

    $this->actingAs($agent)
        ->get("/dashboard/sites/{$site->id}")
        ->assertOk()
        ->assertSee('Install verification')
        ->assertSee('Not seen yet')
        ->assertSee('Wayfindr has not seen this widget check in yet.')
        ->assertSee('Copy the snippet, load the site, then refresh this page.')
        ->assertSee('Verify again');
});

test('site settings call out stale widget check ins', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create([
        'name' => 'Quiet Site',
        'domain' => 'quiet.example.test',
    ]);

    Visitor::factory()->for($site)->create([
        'anonymous_id' => 'anon-stale',
        'last_seen_at' => now()->subDays(2),
        'metadata' => [
            'last_page_url' => 'https://quiet.example.test/help',
        ],
    ]);

    $this->actingAs($agent)
        ->get("/dashboard/sites/{$site->id}")
        ->assertOk()
        ->assertSee('Install verification')
        ->assertSee('Last seen 2 days ago')
        ->assertSee('Wayfindr has seen this widget before, but not recently.')
        ->assertSee('Visit the site and refresh this page if it should still be active.')
        ->assertSee('Last verified page')
        ->assertSee('https://quiet.example.test/help');
});
