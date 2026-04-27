<?php

namespace App\View\Composers;

use App\Models\Notification;
use Illuminate\View\View;

class OrganizationNavbarComposer
{
    public function compose(View $view): void
    {
        $user = request()->user();
        if (! $user) {
            $view->with('navbarNotifications', collect());
            $view->with('navbarUnreadNotificationCount', 0);

            return;
        }

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
        $unreadCount = Notification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->count();

        $view->with('navbarNotifications', $notifications);
        $view->with('navbarUnreadNotificationCount', $unreadCount);
    }
}
