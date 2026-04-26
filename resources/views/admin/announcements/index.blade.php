@extends('layouts.admin')

@section('title', 'Login announcements — NU Lipa SDAO')

@section('content')
<div class="mb-6 flex justify-end">
  <a
    href="{{ route('admin.announcements.create') }}"
    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40"
  >
    New announcement
  </a>
</div>

<x-ui.card padding="p-0" class="overflow-hidden">
  <div class="px-3 py-3 sm:px-5 sm:py-4">
    <div class="overflow-x-auto rounded-xl border border-slate-200">
    <table class="min-w-208 w-full divide-y divide-slate-200 text-left text-sm">
      <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <tr>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Title</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Status</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Schedule</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Order</th>
          <th class="whitespace-nowrap px-4 py-3 text-right sm:px-5">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 bg-white">
        @forelse ($announcements as $announcement)
          <tr class="align-top hover:bg-slate-50/80">
            <td class="px-4 py-3.5 font-semibold text-slate-900 sm:px-5">{{ $announcement->title }}</td>
            <td class="px-4 py-3.5 sm:px-5">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $announcement->status === 'ACTIVE' ? 'border border-emerald-200 bg-emerald-100 text-emerald-800' : 'border border-slate-200 bg-slate-100 text-slate-700' }}"
              >
                {{ $announcement->status }}
              </span>
            </td>
            <td class="px-4 py-3.5 text-xs text-slate-600 sm:px-5">
              <div class="font-medium text-slate-700">{{ $announcement->starts_at ? $announcement->starts_at->format('M j, Y g:i A') : '—' }}</div>
              <div class="text-slate-400">to {{ $announcement->ends_at ? $announcement->ends_at->format('M j, Y g:i A') : 'open' }}</div>
            </td>
            <td class="px-4 py-3.5 font-medium text-slate-700 sm:px-5">{{ $announcement->sort_order }}</td>
            <td class="px-4 py-3.5 text-right sm:px-5">
              <div class="flex items-center justify-end gap-2">
                <a
                  href="{{ route('admin.announcements.edit', $announcement) }}"
                  class="inline-flex rounded-xl border border-[#003E9F] px-3.5 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30"
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
                    class="inline-flex rounded-xl border border-rose-200 px-3.5 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/30"
                  >
                    Delete
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-5 py-12 text-center sm:px-6">
              <div class="flex flex-col items-center gap-2">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
                  <svg class="h-7 w-7 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                  </svg>
                </div>
                <p class="text-sm font-medium text-slate-700">No announcements yet</p>
                <p class="text-sm text-slate-500">Create one to show on the next login.</p>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>

  <div class="border-t border-slate-100 px-5 py-3 sm:px-6">
    {{ $announcements->links() }}
  </div>
</x-ui.card>
@endsection
