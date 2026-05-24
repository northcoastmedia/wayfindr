<?php

use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('agent can view their account site privacy settings', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create([
        'name' => 'Acme Docs',
        'domain' => 'docs.example.test',
        'public_key' => 'site_public_docs',
        'settings' => [
            'mask_selectors' => ['[data-secret]', 'input[name="token"]'],
            'internal_note' => 'do not show this',
        ],
    ]);

    $this->actingAs($agent)
        ->get("/dashboard/sites/{$site->id}")
        ->assertOk()
        ->assertSee('Acme Docs')
        ->assertSee('docs.example.test')
        ->assertSee('site_public_docs')
        ->assertSee('Mask selectors')
        ->assertSee('[data-secret]')
        ->assertSee('input[name=&quot;token&quot;]', false)
        ->assertSee('data-wayfindr-mask')
        ->assertSee('data-wayfindr-private')
        ->assertSee('data-wayfindr-allow')
        ->assertSee('Retaining visitor-supplied data may create privacy, security, and legal obligations.')
        ->assertDontSee('internal_note')
        ->assertDontSee('do not show this');
});

test('agent can update mask selectors for their account site', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create([
        'settings' => [
            'mask_selectors' => ['[data-old]'],
            'internal_note' => 'keep me',
        ],
    ]);

    $this->actingAs($agent)
        ->from("/dashboard/sites/{$site->id}")
        ->put("/dashboard/sites/{$site->id}", [
            'mask_selectors' => " [data-secret] \n\ninput[name=\"token\"]\n[data-secret]\n",
        ])
        ->assertRedirect("/dashboard/sites/{$site->id}")
        ->assertSessionHas('status', 'Site privacy settings saved.');

    expect($site->fresh()->settings)->toBe([
        'mask_selectors' => ['[data-secret]', 'input[name="token"]'],
        'internal_note' => 'keep me',
    ]);
});

test('agent cannot view another account site settings', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherSite = Site::factory()->for($otherAccount)->create();

    $this->actingAs($agent)
        ->get("/dashboard/sites/{$otherSite->id}")
        ->assertNotFound();
});

test('agent cannot update another account site settings', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherSite = Site::factory()->for($otherAccount)->create([
        'settings' => [
            'mask_selectors' => ['[data-original]'],
        ],
    ]);

    $this->actingAs($agent)
        ->put("/dashboard/sites/{$otherSite->id}", [
            'mask_selectors' => '[data-secret]',
        ])
        ->assertNotFound();

    expect($otherSite->fresh()->settings['mask_selectors'])->toBe(['[data-original]']);
});
