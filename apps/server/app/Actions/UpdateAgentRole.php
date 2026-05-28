<?php

namespace App\Actions;

use App\Enums\AccountRole;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateAgentRole
{
    public function handle(User $actor, User $target, AccountRole $role): User
    {
        return DB::transaction(function () use ($actor, $target, $role): User {
            $users = $this->lockUsers($actor, $target);
            $actor = $this->lockedUser($users, $actor);
            $target = $this->lockedUser($users, $target);

            Gate::forUser($actor)->authorize('updateRole', $target);
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

    /**
     * @return Collection<int, User>
     */
    private function lockUsers(User $actor, User $target): Collection
    {
        $userIds = collect([$actor->id, $target->id])
            ->unique()
            ->sort()
            ->values()
            ->all();

        return User::query()
            ->whereKey($userIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function lockedUser(Collection $users, User $user): User
    {
        $lockedUser = $users->get($user->id);

        if (! $lockedUser instanceof User) {
            throw (new ModelNotFoundException)->setModel(User::class, [$user->id]);
        }

        return $lockedUser;
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
