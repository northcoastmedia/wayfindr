<?php

namespace App\Actions;

use App\Enums\AccountRole;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAgentAccess
{
    public function deactivate(User $actor, User $target): User
    {
        return DB::transaction(function () use ($actor, $target): User {
            [$actor, $target] = $this->lockedUsers($actor, $target);

            $this->authorize($actor, $target);

            if ($target->isDeactivated()) {
                return $target;
            }

            $this->preventLastActiveOwnerDeactivation($target);

            $target->forceFill(['deactivated_at' => now()])->save();

            $this->recordAuditEvent($actor, $target, 'agent.deactivated');

            return $target->refresh();
        });
    }

    public function reactivate(User $actor, User $target): User
    {
        return DB::transaction(function () use ($actor, $target): User {
            [$actor, $target] = $this->lockedUsers($actor, $target);

            $this->authorize($actor, $target);

            if (! $target->isDeactivated()) {
                return $target;
            }

            $target->forceFill(['deactivated_at' => null])->save();

            $this->recordAuditEvent($actor, $target, 'agent.reactivated');

            return $target->refresh();
        });
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function lockedUsers(User $actor, User $target): array
    {
        $userIds = collect([$actor->id, $target->id])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $users = User::query()
            ->whereKey($userIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        return [
            $this->lockedUser($users, $actor),
            $this->lockedUser($users, $target),
        ];
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

    private function authorize(User $actor, User $target): void
    {
        if ($actor->isDeactivated()) {
            throw new AuthorizationException('Deactivated agents cannot manage account access.');
        }

        if (! $actor->isAdmin() || $actor->account_id === null) {
            throw new AuthorizationException('Only account owners and admins can manage agent access.');
        }

        if ($target->account_id === null || $target->account_id !== $actor->account_id) {
            throw new AuthorizationException('Agent access can only be changed inside the same account.');
        }

        if ($actor->is($target)) {
            throw new AuthorizationException('Agents cannot deactivate themselves.');
        }

        if (! $actor->isOwner() && $target->account_role !== AccountRole::Agent) {
            throw new AuthorizationException('Only account owners can manage owner or admin access.');
        }
    }

    private function preventLastActiveOwnerDeactivation(User $target): void
    {
        if ($target->account_role !== AccountRole::Owner) {
            return;
        }

        $activeOwnerCount = User::query()
            ->where('account_id', $target->account_id)
            ->where('account_role', AccountRole::Owner->value)
            ->whereNull('deactivated_at')
            ->count();

        if ($activeOwnerCount <= 1) {
            throw ValidationException::withMessages([
                'agent' => 'Keep at least one active account owner.',
            ]);
        }
    }

    private function recordAuditEvent(User $actor, User $target, string $action): void
    {
        AuditEvent::query()->create([
            'account_id' => $target->account_id,
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->id,
            'subject_type' => $target->getMorphClass(),
            'subject_id' => $target->id,
            'action' => $action,
            'metadata' => [
                'role' => $target->account_role?->value,
            ],
            'occurred_at' => now(),
        ]);
    }
}
