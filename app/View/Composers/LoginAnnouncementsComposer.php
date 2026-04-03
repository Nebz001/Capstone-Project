<?php

namespace App\View\Composers;

use App\Services\LoginAnnouncementService;
use Illuminate\View\View;

class LoginAnnouncementsComposer
{
    public function __construct(
        private LoginAnnouncementService $loginAnnouncementService
    ) {}

    public function compose(View $view): void
    {
        $session = request()->session();

        if (! $session->get('show_login_announcements')) {
            return;
        }

        $user = request()->user();
        if (! $user) {
            return;
        }

        $session->forget('show_login_announcements');

        $items = $this->loginAnnouncementService->pendingForUser($user);
        if ($items->isEmpty()) {
            return;
        }

        $view->with('loginAnnouncements', $items);
    }
}
