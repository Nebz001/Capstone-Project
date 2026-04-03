<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementDismissController extends Controller
{
    /**
     * Record that the user has dismissed (seen) one or more login announcements.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'announcement_ids' => ['required', 'array', 'min:1'],
            'announcement_ids.*' => ['integer', 'exists:announcements,id'],
        ]);

        $user = $request->user();
        $ids = array_unique($validated['announcement_ids']);
        $user->dismissedAnnouncements()->syncWithoutDetaching($ids);

        return response()->json(['ok' => true]);
    }
}
