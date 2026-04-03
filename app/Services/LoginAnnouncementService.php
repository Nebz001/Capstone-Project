<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class LoginAnnouncementService
{
    /**
     * Active announcements within schedule that the user has not dismissed.
     *
     * @return Collection<int, Announcement>
     */
    public function pendingForUser(User $user): Collection
    {
        $now = now();

        return Announcement::query()
            ->whereRaw('UPPER(status) = ?', ['ACTIVE'])
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->whereDoesntHave('dismissedByUsers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }
}
