<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Conversation;
use App\Models\Site;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('conversation policy allows agents to view and reply only for conversations on sites they support', function (): void {
    $account = Account::factory()->create();
    $supportAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $otherAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $otherAccountAgent = User::factory()->for(Account::factory())->create(['account_role' => AccountRole::Agent]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach($supportAgent);
    $visitor = Visitor::factory()->for($site)->create();
    $conversation = Conversation::factory()->for($site)->for($visitor)->create();

    expect(Gate::forUser($supportAgent)->allows('view', $conversation))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('reply', $conversation))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('updateStatus', $conversation))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('createTicket', $conversation))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('requestCobrowse', $conversation))->toBeTrue()
        ->and(Gate::forUser($otherAgent)->allows('view', $conversation))->toBeFalse()
        ->and(Gate::forUser($otherAgent)->allows('reply', $conversation))->toBeFalse()
        ->and(Gate::forUser($otherAccountAgent)->allows('view', $conversation))->toBeFalse();
});

test('conversation policy preserves account-wide site fallback until explicit support agents exist', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $otherAccountAgent = User::factory()->for(Account::factory())->create(['account_role' => AccountRole::Agent]);
    $site = Site::factory()->for($account)->create();
    $visitor = Visitor::factory()->for($site)->create();
    $conversation = Conversation::factory()->for($site)->for($visitor)->create();

    expect(Gate::forUser($agent)->allows('view', $conversation))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('reply', $conversation))->toBeTrue()
        ->and(Gate::forUser($otherAccountAgent)->allows('view', $conversation))->toBeFalse();
});

test('conversation policy protects claim and release assignment transitions', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $assignedAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $unsupportedAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach([$agent->id, $assignedAgent->id]);
    $visitor = Visitor::factory()->for($site)->create();
    $unassignedConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => null,
    ]);
    $assignedConversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $assignedAgent->id,
    ]);

    expect(Gate::forUser($agent)->allows('claim', $unassignedConversation))->toBeTrue()
        ->and(Gate::forUser($assignedAgent)->allows('claim', $assignedConversation))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('claim', $assignedConversation))->toBeFalse()
        ->and(Gate::forUser($unsupportedAgent)->allows('claim', $unassignedConversation))->toBeFalse()
        ->and(Gate::forUser($assignedAgent)->allows('release', $assignedConversation))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('release', $assignedConversation))->toBeFalse()
        ->and(Gate::forUser($unsupportedAgent)->allows('release', $assignedConversation))->toBeFalse();
});

test('conversation policy denies deactivated agents even when stale site assignments remain', function (): void {
    $account = Account::factory()->create();
    $deactivatedAgent = User::factory()->for($account)->create([
        'account_role' => AccountRole::Agent,
        'deactivated_at' => now(),
    ]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach($deactivatedAgent);
    $visitor = Visitor::factory()->for($site)->create();
    $conversation = Conversation::factory()->for($site)->for($visitor)->create([
        'assigned_agent_id' => $deactivatedAgent->id,
    ]);

    expect(Gate::forUser($deactivatedAgent)->allows('view', $conversation))->toBeFalse()
        ->and(Gate::forUser($deactivatedAgent)->allows('reply', $conversation))->toBeFalse()
        ->and(Gate::forUser($deactivatedAgent)->allows('claim', $conversation))->toBeFalse()
        ->and(Gate::forUser($deactivatedAgent)->allows('release', $conversation))->toBeFalse()
        ->and(Gate::forUser($deactivatedAgent)->allows('requestCobrowse', $conversation))->toBeFalse();
});
