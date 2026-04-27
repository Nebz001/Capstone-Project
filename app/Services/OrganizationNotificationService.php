<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OrganizationNotificationService
{
    public function createForUser(
        User $user,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?string $linkUrl = null,
        ?Model $related = null
    ): Notification {
        return Notification::query()->create([
            'user_id' => (int) $user->id,
            'notifiable_type' => $related?->getMorphClass() ?? User::class,
            'notifiable_id' => $related?->getKey() ?? (int) $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $message,
            'link_url' => $linkUrl,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  iterable<int, User>  $users
     */
    public function createForUsers(
        iterable $users,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?string $linkUrl = null,
        ?Model $related = null
    ): void {
        $rows = [];
        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }
            $rows[] = [
                'user_id' => (int) $user->id,
                'notifiable_type' => $related?->getMorphClass() ?? User::class,
                'notifiable_id' => $related?->getKey() ?? (int) $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $message,
                'link_url' => $linkUrl,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows !== []) {
            Notification::query()->insert($rows);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function organizationOfficerUsers(Organization $organization): Collection
    {
        return User::query()
            ->whereHas('organizationOfficers', function ($query) use ($organization): void {
                $query->where('organization_id', $organization->id)
                    ->where('status', 'active');
            })
            ->get();
    }

    public function createForOrganization(
        Organization $organization,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?string $linkUrl = null,
        ?Model $related = null
    ): void {
        $this->createForUsers(
            $this->organizationOfficerUsers($organization),
            $title,
            $message,
            $type,
            $linkUrl,
            $related
        );
    }
}
