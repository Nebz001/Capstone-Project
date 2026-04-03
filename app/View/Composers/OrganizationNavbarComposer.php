<?php

namespace App\View\Composers;

use App\Services\LoginAnnouncementService;
use Illuminate\View\View;

class OrganizationNavbarComposer
{
    public function __construct(
        private LoginAnnouncementService $loginAnnouncementService
    ) {}

    public function compose(View $view): void
    {
        $user = request()->user();
        if (! $user) {
            $view->with('navbarAnnouncements', collect());

            return;
        }

        $items = $this->loginAnnouncementService->pendingForUser($user)->take(10);
        $view->with('navbarAnnouncements', $items);
    }
}
