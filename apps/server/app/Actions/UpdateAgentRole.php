<?php

namespace App\Actions;

use App\Enums\AccountRole;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAgentRole
{
    public function handle(User $actor, User $target, AccountRole $role): User
    {
        return DB::transaction(function () use ($actor, $target, $role): User {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $target = User::query()->lockForUpdate()->findOrFail($target->id);

            $this->authorize($actor, $target);
            $this->preventLastOwnerRemoval($target, $role);
            $this->preventSelfChange($actor, $target);

            $oldRole = $target->account_role;

            if ($oldRole === $role) {
                return $target;
            }

            $target->forceFill(['account_role' => $role])->save();

            AuditEvent::query()->create([
                'account_id' => $target->account_id,
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => $actor->id,
                'subject_type' => $target->getMorphClass(),
                'subject_id' => $target->id,
                'action' => 'agent.role_changed',
                'metadata' => [
                    'old_role' => $oldRole->value,
                    'new_role' => $role->value,
                ],
                'occurred_at' => now(),
            ]);

            return $target->refresh();
        });
    }

    private function authorize(User $actor, User $target): void
    {
        if (! $actor->isOwner() || $actor->account_id === null) {
            throw new AuthorizationException('Only account owners can change agent roles.');
        }

        if ($target->account_id === null || $target->account_id !== $actor->account_id) {
            throw new AuthorizationException('Agent roles can only be changed inside the same account.');
        }
    }

    private function preventSelfChange(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new AuthorizationException('Owners cannot change their own role.');
        }
    }

    private function preventLastOwnerRemoval(User $target, AccountRole $role): void
    {
        if ($target->account_role !== AccountRole::Owner || $role === AccountRole::Owner) {
            return;
        }

        $ownerCount = User::query()
            ->where('account_id', $target->account_id)
            ->where('account_role', AccountRole::Owner->value)
            ->count();

        if ($ownerCount <= 1) {
            throw ValidationException::withMessages([
                'account_role' => 'Keep at least one account owner.',
            ]);
        }
    }
}
