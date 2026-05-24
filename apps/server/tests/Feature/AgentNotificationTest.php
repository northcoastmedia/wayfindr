<?php

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

uses(RefreshDatabase::class);

test('visitor messages notify the assigned agent', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $assignedAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $otherAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create([
        'name' => 'Acme Docs',
        'public_key' => 'site_public_docs',
    ]);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $assignedAgent->id,
        'support_code' => 'WF-ALERT1',
        'subject' => 'Checkout trouble',
    ]);
    $token = notificationVisitorToken($this, 'site_public_docs', 'anon-docs');

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'The checkout button is still stuck.',
    ])->assertCreated();

    expect($assignedAgent->unreadNotifications)->toHaveCount(1)
        ->and($otherAgent->unreadNotifications)->toHaveCount(0);

    $notification = $assignedAgent->unreadNotifications()->firstOrFail();

    expect($notification->type)->toBe(ConversationNeedsReply::class)
        ->and($notification->data)->toMatchArray([
            'kind' => 'conversation_needs_reply',
            'conversation_id' => $conversation->id,
            'support_code' => 'WF-ALERT1',
            'subject' => 'Checkout trouble',
            'site_name' => 'Acme Docs',
            'visitor_anonymous_id' => 'anon-docs',
            'message_preview' => 'The checkout button is still stuck.',
        ]);
});

test('assigned conversation alerts batch repeated visitor messages', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create([
        'name' => 'Acme Docs',
        'public_key' => 'site_public_docs',
    ]);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'support_code' => 'WF-BATCH1',
        'subject' => 'Checkout trouble',
    ]);
    $token = notificationVisitorToken($this, 'site_public_docs', 'anon-docs');

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'The checkout button is stuck.',
    ])->assertCreated();

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'Actually, now the whole cart is empty.',
    ])->assertCreated();

    expect($agent->fresh()->unreadNotifications)->toHaveCount(1);

    $notification = $agent->fresh()->unreadNotifications()->firstOrFail();
    $latestMessage = $conversation->messages()->latest('id')->firstOrFail();

    expect($notification->data)->toMatchArray([
        'conversation_id' => $conversation->id,
        'latest_message_id' => $latestMessage->id,
        'message_count' => 2,
        'message_preview' => 'Actually, now the whole cart is empty.',
    ]);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('1 unread')
        ->assertSee('2 new messages')
        ->assertSee('Actually, now the whole cart is empty.');
});

test('visitor messages notify all account agents when a conversation is unassigned', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $firstAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $secondAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $otherAccountAgent = User::factory()->for(Account::factory())->create(['name' => 'Casey Elsewhere']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
        'support_code' => 'WF-UNASSIGNED',
    ]);
    $token = notificationVisitorToken($this, 'site_public_docs', 'anon-docs');

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'Can someone help?',
    ])->assertCreated();

    expect($firstAgent->unreadNotifications)->toHaveCount(1)
        ->and($secondAgent->unreadNotifications)->toHaveCount(1)
        ->and($otherAccountAgent->unreadNotifications)->toHaveCount(0);
});

test('unassigned conversation alerts batch for each account agent', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $firstAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $secondAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
        'support_code' => 'WF-BATCH2',
    ]);
    $token = notificationVisitorToken($this, 'site_public_docs', 'anon-docs');

    foreach (['Can someone help?', 'I am still blocked.'] as $body) {
        $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
            'site_public_key' => 'site_public_docs',
            'anonymous_id' => 'anon-docs',
            'visitor_token' => $token,
            'body' => $body,
        ])->assertCreated();
    }

    expect($firstAgent->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($secondAgent->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($firstAgent->fresh()->unreadNotifications()->firstOrFail()->data)->toMatchArray([
            'conversation_id' => $conversation->id,
            'message_count' => 2,
            'message_preview' => 'I am still blocked.',
        ])
        ->and($secondAgent->fresh()->unreadNotifications()->firstOrFail()->data)->toMatchArray([
            'conversation_id' => $conversation->id,
            'message_count' => 2,
            'message_preview' => 'I am still blocked.',
        ]);
});

test('agent replies do not create needs reply notifications', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create();
    $visitor = Visitor::factory()->for($site)->create();
    Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'support_code' => 'WF-QUIET1',
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-QUIET1/messages', [
            'body' => 'I can help with that.',
        ])
        ->assertRedirect('/dashboard/conversations/WF-QUIET1');

    expect($agent->notifications)->toHaveCount(0);
});

test('assigning a ticket notifies the new assignee', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $assigningAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $assignedAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->create([
            'assignee_id' => null,
            'subject' => 'Escalated checkout issue',
            'priority' => 'high',
            'status' => 'open',
        ]);

    $this->actingAs($assigningAgent)
        ->from("/dashboard/tickets/{$ticket->id}")
        ->put("/dashboard/tickets/{$ticket->id}/assignee", [
            'assignee_id' => $assignedAgent->id,
        ])
        ->assertRedirect("/dashboard/tickets/{$ticket->id}");

    expect($assignedAgent->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($assigningAgent->fresh()->unreadNotifications)->toHaveCount(0);

    $notification = $assignedAgent->fresh()->unreadNotifications()->firstOrFail();

    expect($notification->data)->toMatchArray([
        'kind' => 'ticket_assigned',
        'ticket_id' => $ticket->id,
        'subject' => 'Escalated checkout issue',
        'priority' => 'high',
        'site_name' => 'Acme Docs',
        'assigned_by_name' => 'Ada Agent',
        'url' => "/dashboard/tickets/{$ticket->id}",
    ]);
});

test('dashboard shows unread conversation alerts', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create([
        'name' => 'Acme Docs',
        'public_key' => 'site_public_docs',
    ]);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'support_code' => 'WF-DASH1',
        'subject' => 'Install help',
    ]);
    $token = notificationVisitorToken($this, 'site_public_docs', 'anon-docs');

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'I am stuck on the install step.',
    ])->assertCreated();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Alerts')
        ->assertSee('1 unread')
        ->assertSee('Install help')
        ->assertSee('WF-DASH1')
        ->assertSee('I am stuck on the install step.');
});

test('dashboard shows unread ticket assignment alerts', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $assigningAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $assignedAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($assignedAgent, 'assignee')
        ->create([
            'subject' => 'Escalated checkout issue',
            'priority' => 'high',
            'status' => 'open',
        ]);

    $assignedAgent->notify(new TicketAssigned($ticket, $assigningAgent));

    $this->actingAs($assignedAgent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Alerts')
        ->assertSee('1 unread')
        ->assertSee('Escalated checkout issue')
        ->assertSee('Ticket assigned')
        ->assertSee('Ada Agent assigned this ticket to you.')
        ->assertSee('High priority')
        ->assertSee("/dashboard/tickets/{$ticket->id}", false);
});

test('dashboard exposes calm alert controls and empty state', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create([
        'name' => 'Acme Docs',
        'public_key' => 'site_public_docs',
    ]);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'support_code' => 'WF-CONTROL1',
        'subject' => 'Install help',
    ]);
    $message = ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'I am stuck on the install step.',
    ]);
    $agent->notify(new ConversationNeedsReply($message));
    $notification = $agent->unreadNotifications()->firstOrFail();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Mark all read')
        ->assertSee('Mark read')
        ->assertSee("/dashboard/alerts/{$notification->id}/read", false)
        ->assertSee('/dashboard/alerts/read', false)
        ->assertDontSee('You’re caught up.');

    $notification->markAsRead();

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('You’re caught up.');
});

test('agent can mark one alert as read', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);

    $firstConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-ONE1',
        'subject' => 'First issue',
    ]);
    $secondConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-ONE2',
        'subject' => 'Second issue',
    ]);

    $firstMessage = ConversationMessage::factory()->for($firstConversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'First thing is stuck.',
    ]);
    $secondMessage = ConversationMessage::factory()->for($secondConversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'Second thing is stuck.',
    ]);

    $agent->notify(new ConversationNeedsReply($firstMessage));
    $agent->notify(new ConversationNeedsReply($secondMessage));
    $firstNotification = $agent->unreadNotifications()
        ->get()
        ->firstOrFail(fn ($notification): bool => data_get($notification->data, 'support_code') === 'WF-ONE1');
    $secondNotification = $agent->unreadNotifications()
        ->get()
        ->firstOrFail(fn ($notification): bool => data_get($notification->data, 'support_code') === 'WF-ONE2');

    $this->actingAs($agent)
        ->post("/dashboard/alerts/{$firstNotification->id}/read")
        ->assertRedirect('/dashboard');

    expect($firstNotification->fresh()->read())->toBeTrue()
        ->and($secondNotification->fresh()->unread())->toBeTrue();
});

test('agent can mark all conversation alerts as read', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $otherAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-ALL1',
    ]);
    $message = ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'Could someone help?',
    ]);

    $agent->notify(new ConversationNeedsReply($message));
    $agent->notify(new ConversationNeedsReply($message));
    $otherAgent->notify(new ConversationNeedsReply($message));

    $this->actingAs($agent)
        ->post('/dashboard/alerts/read')
        ->assertRedirect('/dashboard');

    expect($agent->fresh()->unreadNotifications)->toHaveCount(0)
        ->and($agent->fresh()->readNotifications)->toHaveCount(2)
        ->and($otherAgent->fresh()->unreadNotifications)->toHaveCount(1);
});

test('agent cannot mark another agent alert as read', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $otherAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-BOUNDARY1',
    ]);
    $message = ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'This alert belongs to Bea.',
    ]);
    $otherAgent->notify(new ConversationNeedsReply($message));
    $notification = $otherAgent->unreadNotifications()->firstOrFail();

    $this->actingAs($agent)
        ->post("/dashboard/alerts/{$notification->id}/read")
        ->assertNotFound();

    expect($notification->fresh()->unread())->toBeTrue();
});

test('opening a conversation marks its unread alerts as read', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'support_code' => 'WF-READ1',
    ]);
    $token = notificationVisitorToken($this, 'site_public_docs', 'anon-docs');

    $this->postJson("/api/conversations/{$conversation->support_code}/messages", [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'Could you look at this?',
    ])->assertCreated();

    expect($agent->unreadNotifications)->toHaveCount(1);

    $this->actingAs($agent)
        ->get('/dashboard/conversations/WF-READ1')
        ->assertOk();

    expect($agent->fresh()->unreadNotifications)->toHaveCount(0)
        ->and($agent->fresh()->readNotifications)->toHaveCount(1);
});

test('opening an assigned ticket marks its unread assignment alerts as read', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $assigningAgent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $assignedAgent = User::factory()->for($account)->create(['name' => 'Bea Builder']);
    $site = Site::factory()->for($account)->create(['name' => 'Acme Docs']);
    $ticket = Ticket::factory()
        ->for($account)
        ->for($site)
        ->for($assignedAgent, 'assignee')
        ->create([
            'subject' => 'Escalated checkout issue',
            'status' => 'open',
        ]);

    $assignedAgent->notify(new TicketAssigned($ticket, $assigningAgent));

    expect($assignedAgent->unreadNotifications)->toHaveCount(1);

    $this->actingAs($assignedAgent)
        ->get("/dashboard/tickets/{$ticket->id}")
        ->assertOk();

    expect($assignedAgent->fresh()->unreadNotifications)->toHaveCount(0)
        ->and($assignedAgent->fresh()->readNotifications)->toHaveCount(1);
});

test('replying to a conversation marks its unread alerts as read', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Support']);
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $agent->id,
        'support_code' => 'WF-READ2',
    ]);
    $message = ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'body' => 'Can I get an update?',
    ]);
    $agent->notify(new ConversationNeedsReply($message));

    expect($agent->unreadNotifications)->toHaveCount(1);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-READ2/messages', [
            'body' => 'Yes, I am on it.',
        ])
        ->assertRedirect('/dashboard/conversations/WF-READ2');

    expect($agent->fresh()->unreadNotifications)->toHaveCount(0)
        ->and($agent->fresh()->readNotifications)->toHaveCount(1);
});

function notificationVisitorToken($test, string $sitePublicKey, string $anonymousId): string
{
    return $test->postJson('/api/widget/bootstrap', [
        'site_public_key' => $sitePublicKey,
        'anonymous_id' => $anonymousId,
        'page_url' => 'https://docs.example.test/install',
    ])
        ->assertSuccessful()
        ->json('data.visitor.token');
}
