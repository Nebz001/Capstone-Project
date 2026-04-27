<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = (string) $request->string('filter', 'all');
        $type = (string) $request->string('type', '');

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($filter === 'unread') {
            $query->unread();
        } elseif ($filter === 'read') {
            $query->read();
        }
        if ($type !== '') {
            $query->where('type', $type);
        }

        $notifications = $query->paginate(15)->withQueryString();
        $typeOptions = Notification::query()
            ->where('user_id', $user->id)
            ->select('type')
            ->distinct()
            ->pluck('type')
            ->filter(fn (string $item): bool => $item !== '')
            ->values();

        return view('organizations.notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'type' => $type,
            'typeOptions' => $typeOptions,
        ]);
    }

    public function open(Request $request, Notification $notification): RedirectResponse
    {
        $this->authorizeOwnedNotification($request, $notification);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        if (filled($notification->link_url)) {
            return redirect()->to((string) $notification->link_url);
        }

        return redirect()->route('organizations.notifications.index');
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        $this->authorizeOwnedNotification($request, $notification);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    private function authorizeOwnedNotification(Request $request, Notification $notification): void
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);
    }
}
