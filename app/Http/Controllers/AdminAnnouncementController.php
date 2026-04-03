<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $announcements = Announcement::query()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.announcements.form', [
            'announcement' => new Announcement,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $this->validatedData($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('announcements', 'public');
        }

        Announcement::query()->create($data);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement created.');
    }

    public function edit(Request $request, Announcement $announcement): View
    {
        $this->authorizeAdmin($request);

        return view('admin.announcements.form', [
            'announcement' => $announcement,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $this->validatedData($request);

        if ($request->hasFile('image')) {
            if ($announcement->image_path) {
                Storage::disk('public')->delete($announcement->image_path);
            }
            $data['image_path'] = $request->file('image')->store('announcements', 'public');
        }

        $announcement->update($data);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement updated.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($announcement->image_path) {
            Storage::disk('public')->delete($announcement->image_path);
        }

        $announcement->delete();

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'link_label' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:ACTIVE,INACTIVE'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];

        $rules['image'] = ['nullable', 'image', 'max:4096', 'mimes:jpeg,jpg,png,webp,gif'];

        $validated = $request->validate($rules);

        return [
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'link_url' => $validated['link_url'] ?: null,
            'link_label' => $validated['link_label'] ?: null,
            'status' => $validated['status'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isSdaoAdmin()) {
            abort(403, 'Only authorized SDAO admins can access this section.');
        }
    }
}
