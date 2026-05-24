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
        ->assertSee('https://wayfindr.cc/docs');
});
