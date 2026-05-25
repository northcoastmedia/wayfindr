<?php

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('ticket policy allows agents to work tickets only for sites they support', function (): void {
    $account = Account::factory()->create();
    $supportAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $otherAgent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $otherAccountAgent = User::factory()->for(Account::factory())->create(['account_role' => AccountRole::Agent]);
    $site = Site::factory()->for($account)->create();
    $site->supportAgents()->attach($supportAgent);
    $ticket = Ticket::factory()->for($account)->for($site)->create();

    expect(Gate::forUser($supportAgent)->allows('view', $ticket))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('addNote', $ticket))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('update', $ticket))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('updateStatus', $ticket))->toBeTrue()
        ->and(Gate::forUser($supportAgent)->allows('assign', $ticket))->toBeTrue()
        ->and(Gate::forUser($otherAgent)->allows('view', $ticket))->toBeFalse()
        ->and(Gate::forUser($otherAgent)->allows('assign', $ticket))->toBeFalse()
        ->and(Gate::forUser($otherAccountAgent)->allows('view', $ticket))->toBeFalse();
});

test('ticket policy preserves account-wide site fallback until explicit support agents exist', function (): void {
    $account = Account::factory()->create();
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $otherAccountAgent = User::factory()->for(Account::factory())->create(['account_role' => AccountRole::Agent]);
    $site = Site::factory()->for($account)->create();
    $ticket = Ticket::factory()->for($account)->for($site)->create();

    expect(Gate::forUser($agent)->allows('view', $ticket))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('update', $ticket))->toBeTrue()
        ->and(Gate::forUser($otherAccountAgent)->allows('view', $ticket))->toBeFalse();
});

test('ticket policy denies tickets whose account does not match the supported site', function (): void {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $agent = User::factory()->for($account)->create(['account_role' => AccountRole::Agent]);
    $site = Site::factory()->for($account)->create();
    $ticket = Ticket::factory()->for($otherAccount)->for($site)->create();

    expect(Gate::forUser($agent)->allows('view', $ticket))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('update', $ticket))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('assign', $ticket))->toBeFalse();
});
