<?php

use App\Broadcasting\ConversationChannel;
use App\Events\ConversationMessageCreated;
use App\Models\Account;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Site;
use App\Models\User;
use App\Models\Visitor;
use App\Support\VisitorSessionToken;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('conversation message broadcasts use a private conversation channel and safe payload', function (): void {
    $account = Account::factory()->create();
    $site = Site::factory()->for($account)->create(['name' => 'Docs Site']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-BROADCAST',
        'status' => 'open',
    ]);

    $message = ConversationMessage::factory()->for($conversation)->create([
        'sender_type' => Visitor::class,
        'sender_id' => $visitor->id,
        'type' => 'text',
        'body' => 'Can someone see this?',
    ]);

    $event = new ConversationMessageCreated($message->load(['conversation', 'sender']));
    $channels = $event->broadcastOn();

    expect($event)
        ->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and($event->broadcastAs())->toBe('conversation.message.created')
        ->and($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-conversations.WF-BROADCAST')
        ->and($event->broadcastWith())->toMatchArray([
            'conversation' => [
                'support_code' => 'WF-BROADCAST',
                'status' => 'open',
            ],
            'message' => [
                'id' => $message->id,
                'sender' => [
                    'kind' => 'visitor',
                    'name' => 'Visitor',
                ],
                'type' => 'text',
                'body' => 'Can someone see this?',
                'created_at' => $message->created_at?->toJSON(),
            ],
        ]);
});

test('agent replies dispatch conversation message broadcasts', function (): void {
    Event::fake([ConversationMessageCreated::class]);

    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create(['name' => 'Ada Agent']);
    $site = Site::factory()->for($account)->create();
    $visitor = Visitor::factory()->for($site)->create();
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-AGENT1',
    ]);

    $this->actingAs($agent)
        ->post('/dashboard/conversations/WF-AGENT1/messages', [
            'body' => 'I can help with that.',
        ])
        ->assertRedirect('/dashboard/conversations/WF-AGENT1');

    Event::assertDispatched(
        ConversationMessageCreated::class,
        fn (ConversationMessageCreated $event): bool => $event->message->conversation_id === $conversation->id
            && $event->message->sender_type === User::class
            && $event->message->sender_id === $agent->id
            && $event->message->body === 'I can help with that.',
    );
});

test('visitor messages dispatch conversation message broadcasts', function (): void {
    Event::fake([ConversationMessageCreated::class]);

    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-VISITOR',
    ]);
    $token = app(VisitorSessionToken::class)->issue($site, $visitor);

    $this->postJson('/api/conversations/WF-VISITOR/messages', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'body' => 'Hello from the widget.',
    ])
        ->assertCreated();

    Event::assertDispatched(
        ConversationMessageCreated::class,
        fn (ConversationMessageCreated $event): bool => $event->message->conversation_id === $conversation->id
            && $event->message->sender_type === Visitor::class
            && $event->message->sender_id === $visitor->id
            && $event->message->body === 'Hello from the widget.',
    );
});

test('visitor broadcast auth signs their private conversation channel', function (): void {
    config()->set('broadcasting.connections.reverb.key', 'reverb-key');
    config()->set('broadcasting.connections.reverb.secret', 'reverb-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'reverb-app');
    Broadcast::purge('reverb');

    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-LIVE',
    ]);
    $token = app(VisitorSessionToken::class)->issue($site, $visitor);

    $response = $this->postJson('/api/widget/broadcasting/auth', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-docs',
        'visitor_token' => $token,
        'socket_id' => '1234.5678',
        'channel_name' => 'private-conversations.WF-LIVE',
    ]);

    $signature = hash_hmac('sha256', '1234.5678:private-conversations.WF-LIVE', 'reverb-secret');

    $response
        ->assertOk()
        ->assertJson([
            'auth' => 'reverb-key:'.$signature,
        ]);
});

test('visitor broadcast auth rejects another visitors conversation channel', function (): void {
    config()->set('broadcasting.connections.reverb.key', 'reverb-key');
    config()->set('broadcasting.connections.reverb.secret', 'reverb-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'reverb-app');
    Broadcast::purge('reverb');

    $site = Site::factory()->create(['public_key' => 'site_public_docs']);
    $visitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-docs']);
    $otherVisitor = Visitor::factory()->for($site)->create(['anonymous_id' => 'anon-other']);
    Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-LIVE',
    ]);
    $token = app(VisitorSessionToken::class)->issue($site, $otherVisitor);

    $this->postJson('/api/widget/broadcasting/auth', [
        'site_public_key' => 'site_public_docs',
        'anonymous_id' => 'anon-other',
        'visitor_token' => $token,
        'socket_id' => '1234.5678',
        'channel_name' => 'private-conversations.WF-LIVE',
    ])->assertForbidden();
});

test('conversation channel authorizes account agents and matching visitors', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create();
    $otherAgent = User::factory()->for($otherAccount)->create();
    $site = Site::factory()->for($account)->create();
    $visitor = Visitor::factory()->for($site)->create();
    $otherVisitor = Visitor::factory()->for($site)->create();

    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'support_code' => 'WF-CHANNEL',
    ]);

    $channel = app(ConversationChannel::class);

    expect($channel->join($agent, $conversation->support_code))->toBeTrue()
        ->and($channel->join($otherAgent, $conversation->support_code))->toBeFalse()
        ->and($channel->join($visitor, $conversation->support_code))->toBeTrue()
        ->and($channel->join($otherVisitor, $conversation->support_code))->toBeFalse()
        ->and($channel->join($agent, 'WF-MISSING'))->toBeFalse();
});
