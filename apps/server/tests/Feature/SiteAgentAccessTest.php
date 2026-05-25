<?php

use App\Broadcasting\ConversationChannel;
use App\Models\Account;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Visitor;
use App\Notifications\ConversationNeedsReply;
use App\Notifications\TicketAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('dashboard scopes support queues to sites assigned to the agent', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $teammate = User::factory()->for($account)->create(['name' => 'Bea Builder']);

    $assignedSite = Site::factory()->for($account)->create(['name' => 'Assigned Docs']);
    $assignedSite->supportAgents()->attach($agent);
    $assignedVisitor = Visitor::factory()->for($assignedSite)->create(['anonymous_id' => 'anon-assigned']);
    Conversation::factory()->for($assignedSite)->for($assignedVisitor)->create([
        'support_code' => 'WF-ASSIGNED',
        'subject' => 'Assigned site conversation',
        'status' => 'open',
    ]);
    Ticket::factory()->for($account)->for($assignedSite)->create([
        'subject' => 'Assigned site ticket',
        'status' => 'open',
    ]);

    $restrictedSite = Site::factory()->for($account)->create(['name' => 'Restricted Store']);
    $restrictedSite->supportAgents()->attach($teammate);
    $restrictedVisitor = Visitor::factory()->for($restrictedSite)->create(['anonymous_id' => 'anon-restricted']);
    Conversation::factory()->for($restrictedSite)->for($restrictedVisitor)->create([
        'support_code' => 'WF-RESTRICT',
        'subject' => 'Restricted site conversation',
        'status' => 'open',
    ]);
    Ticket::factory()->for($account)->for($restrictedSite)->create([
        'subject' => 'Restricted site ticket',
        'status' => 'open',
    ]);

    $openSite = Site::factory()->for($account)->create(['name' => 'Unassigned Site']);
    $openVisitor = Visitor::factory()->for($openSite)->create(['anonymous_id' => 'anon-open']);
    Conversation::factory()->for($openSite)->for($openVisitor)->create([
        'support_code' => 'WF-OPEN',
        'subject' => 'Open site conversation',
        'status' => 'open',
    ]);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Assigned Docs')
        ->assertSee('Assigned site conversation')
        ->assertSee('Assigned site ticket')
        ->assertSee('Unassigned Site')
        ->assertSee('Open site conversation')
        ->assertDontSee('Restricted Store')
        ->assertDontSee('Restricted site conversation')
        ->assertDontSee('Restricted site ticket');
});

test('agent cannot view conversations or tickets for a site they do not support', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $teammate = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach($teammate);
    $visitor = Visitor::factory()->for($site)->create();
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-NOTMINE',
    ]);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($conversation)
        ->create();

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-NOTMINE')
        ->assertNotFound();

    $this->actingAs($agent)
        ->get("/dashboard/tickets/{$ticket->id}")
        ->assertNotFound();
});

test('agent cannot view or update site settings for a site they do not support', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $teammate = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create([
        'name' => 'Restricted Docs',
        'settings' => [
            'mask_selectors' => ['[data-original]'],
        ],
    ]);
    $site->supportAgents()->attach($teammate);

    $this->actingAs($agent)
        ->get("/dashboard/sites/{$site->id}")
        ->assertNotFound();

    $this->actingAs($agent)
        ->put("/dashboard/sites/{$site->id}", [
            'mask_selectors' => '[data-secret]',
        ])
        ->assertNotFound();

    expect($site->fresh()->settings['mask_selectors'])->toBe(['[data-original]']);
});

test('conversation broadcast channels honor site support access', function (): void {
    $account = Account::factory()->create();
    $siteAgent = User::factory()->for($account)->create();
    $otherAccountAgent = User::factory()->for($account)->create();
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach($siteAgent);
    $visitor = Visitor::factory()->for($site)->create();
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-CHANNEL',
    ]);

    $channel = app(ConversationChannel::class);

    expect($channel->join($siteAgent, $conversation->support_code))->toBeTrue()
        ->and($channel->join($otherAccountAgent, $conversation->support_code))->toBeFalse();
});

test('dashboard hides unread alerts for sites the agent can no longer support', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $teammate = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Restricted Docs']);
    $site->supportAgents()->attach($teammate);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-restricted']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-STALEALERT',
        'subject' => 'Restricted conversation',
    ]);
    $message = ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'This should not leak.',
    ]);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->create([
            'subject' => 'Restricted ticket',
            'status' => 'open',
        ]);

    $agent->notify(new ConversationNeedsReply($message));
    $agent->notify(new TicketAssigned($ticket, $teammate));

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('0 unread')
        ->assertSee('You’re caught up.')
        ->assertDontSee('Restricted conversation')
        ->assertDontSee('Restricted ticket')
        ->assertDontSee('Restricted Docs')
        ->assertDontSee('This should not leak.');
});

test('ticket assignment is limited to agents assigned to the ticket site', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $eligibleAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $ineligibleAgent = User::factory()->for($account)->create(['name' => 'Casey Catalog']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $otherSite = Site::factory()->for($account)->create(['name' => 'Acme Store']);
    $site->supportAgents()->attach([$agent->id, $eligibleAgent->id]);
    $otherSite->supportAgents()->attach($ineligibleAgent);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($agent, 'assignee')
        ->create(['status' => 'open']);

    $this->actingAs($agent)
        ->get("/dashboard/tickets/{$ticket->id}")
        ->assertOk()
        ->assertSee('Ada Agent')
        ->assertSee('Bea Builder')
        ->assertDontSee('Casey Catalog');

    $this->actingAs($agent)
        ->from("/dashboard/tickets/{$ticket->id}")
        ->put("/dashboard/tickets/{$ticket->id}/assignee", [
            'assignee_id' => $ineligibleAgent->id,
        ])
        ->assertRedirect("/dashboard/tickets/{$ticket->id}")
        ->assertSessionHasErrors('assignee_id');

    expect($ticket->fresh()->assignee_id)->toBe($agent->id);
});

test('invalid cross-account site agent links are ignored for support alerts', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $otherAccount = Account::factory()->create(['name' => 'Other Support']);
    $accountAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $crossAccountAgent = User::factory()->for($otherAccount)->create(['name' => 'Otto Outside']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $site->supportAgents()->attach($crossAccountAgent);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
        'support_code' => 'WF-CROSSPIVOT',
    ]);

    $token = $this->postJson('/api/widget/bootstrap', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'page_url' => 'https://docs.example.test/install',
    ])
        ->assertSuccessful()
        ->json('data.visitor.token');

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'Can someone in the account help?',
    ])->assertCreated();

    expect($accountAgent->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($crossAccountAgent->fresh()->unreadNotifications)->toHaveCount(0);
});

test('unassigned visitor messages notify only agents assigned to that site', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $siteAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $otherAccountAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $site->supportAgents()->attach($siteAgent);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
        'support_code' => 'WF-SITEALERT',
    ]);

    $token = $this->postJson('/api/widget/bootstrap', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'page_url' => 'https://docs.example.test/install',
    ])
        ->assertSuccessful()
        ->json('data.visitor.token');

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'Can someone help?',
    ])->assertCreated();

    expect($siteAgent->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($otherAccountAgent->fresh()->unreadNotifications)->toHaveCount(0);
});

test('visitor messages fall back to site agents when the assigned agent no longer supports the site', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $siteAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $formerSiteAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $site->supportAgents()->attach($siteAgent);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $formerSiteAgent->id,
        'support_code' => 'WF-STALEASSIGN',
    ]);

    $token = $this->postJson('/api/widget/bootstrap', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'page_url' => 'https://docs.example.test/install',
    ])
        ->assertSuccessful()
        ->json('data.visitor.token');

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'Is anyone still here?',
    ])->assertCreated();

    expect($siteAgent->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($formerSiteAgent->fresh()->unreadNotifications)->toHaveCount(0);
});

test('site creation attaches the creating agent as a support agent', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create();

    $this->actingAs($agent)
        ->post('/dashboard/sites', [
            'name' => 'Wayfindr Public Site',
            'domain' => 'wayfindr.cc',
        ])
        ->assertRedirect();

    $createdSite = Site::query()
        ->where('name', 'Wayfindr Public Site')
        ->firstOrFail();

    expect($createdSite->supportAgents()->whereKey($agent->id)->exists())->toBeTrue();
});

test('bootstrap attaches the first agent to the first site', function (): void {
    $this->artisan('wayfindr:bootstrap', [
        '--account' => 'Demo Support',
        '--name' => 'Demo Agent',
        '--email' => 'demo@example.com',
        '--password' => 'correct-horse-battery-staple',
        '--site' => 'Demo Site',
        '--site-public-key' => 'site_demo_public_key',
    ])->assertExitCode(0);

    $bootstrapAgent = User::query()->where('email', 'demo@example.com')->firstOrFail();
    $bootstrapSite = Site::query()->where('public_key', 'site_demo_public_key')->firstOrFail();

    expect(Hash::check('correct-horse-battery-staple', $bootstrapAgent->password))->toBeTrue()
        ->and($bootstrapSite->supportAgents()->whereKey($bootstrapAgent->id)->exists())->toBeTrue();
});

test('account and role roadmap documents the boundary between site access and account authority', function (): void {
    $path = base_path('../../docs/product/accounts-and-roles.md');

    expect($path)->toBeFile()
        ->and(file_get_contents($path))->toContain('Site access')
        ->and(file_get_contents($path))->toContain('Account roles')
        ->and(file_get_contents($path))->toContain('owner')
        ->and(file_get_contents($path))->toContain('admin')
        ->and(file_get_contents($path))->toContain('agent')
        ->and(file_get_contents($path))->toContain('not implemented yet');
});
