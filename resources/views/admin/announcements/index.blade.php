@extends('layouts.admin')

@section('title', 'Login announcements — NU Lipa SDAO')

@section('content')
<header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Login announcements</h1>
    <p class="mt-1 text-sm text-slate-500">
      Manage announcements shown to students and admins after sign-in. Active items within the schedule appear in the login modal.
    </p>
  </div>
  <a
    href="{{ route('admin.announcements.create') }}"
    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00327F]"
  >
    New announcement
  </a>
</header>

@if (session('success'))
  <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
    {{ session('success') }}
  </div>
@endif

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Schedule</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Order</th>
          <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @forelse ($announcements as $announcement)
          <tr class="hover:bg-slate-50/80">
            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $announcement->title }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $announcement->status === 'ACTIVE' ? 'border border-emerald-200 bg-emerald-100 text-emerald-800' : 'border border-slate-200 bg-slate-100 text-slate-700' }}"
              >
                {{ $announcement->status }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-600">
              <div>{{ $announcement->starts_at ? $announcement->starts_at->format('M j, Y g:i A') : '—' }}</div>
              <div class="text-slate-400">to {{ $announcement->ends_at ? $announcement->ends_at->format('M j, Y g:i A') : 'open' }}</div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $announcement->sort_order }}</td>
            <td class="px-4 py-3 text-right">
              <a
                href="{{ route('admin.announcements.edit', $announcement) }}"
                class="inline-flex rounded-lg border border-[#003E9F] px-3 py-1.5 text-xs font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white"
              >
                Edit
              </a>
              <form
                method="POST"
                action="{{ route('admin.announcements.destroy', $announcement) }}"
                class="inline-block"
                onsubmit="return confirm('Delete this announcement?');"
              >
                @csrf
                @method('DELETE')
                <button
                  type="submit"
                  class="ml-2 inline-flex rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                >
                  Delete
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
              No announcements yet. Create one to show on the next login.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="border-t border-slate-100 px-4 py-3">
    {{ $announcements->links() }}
  </div>
</section>
@endsection
