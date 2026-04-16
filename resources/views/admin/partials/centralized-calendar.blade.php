<x-ui.card padding="p-0" class="overflow-hidden">
  <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-slate-100 px-6 py-4">
    <span class="flex items-center gap-2 text-xs text-slate-600">
      <span class="inline-block h-3 w-3 rounded border-l-[3px] border-amber-400 bg-amber-100"></span>
      Pending
    </span>
    <span class="flex items-center gap-2 text-xs text-slate-600">
      <span class="inline-block h-3 w-3 rounded border-l-[3px] border-blue-400 bg-blue-100"></span>
      Under Review
    </span>
    <span class="flex items-center gap-2 text-xs text-slate-600">
      <span class="inline-block h-3 w-3 rounded border-l-[3px] border-emerald-400 bg-emerald-100"></span>
      Approved
    </span>
    <span class="flex items-center gap-2 text-xs text-slate-600">
      <span class="inline-block h-3 w-3 rounded border-l-[3px] border-rose-400 bg-rose-100"></span>
      Rejected
    </span>
    <span class="flex items-center gap-2 text-xs text-slate-600">
      <span class="inline-block h-3 w-3 rounded border-l-[3px] border-orange-400 bg-orange-100"></span>
      Revision Required
    </span>
  </div>

  <div class="px-5 py-4 sm:px-6">
    <div id="admin-activity-calendar"></div>
  </div>

  <script id="admin-calendar-events-data" type="application/json">@json($calendarEvents)</script>
</x-ui.card>

<div id="admin-calendar-drawer" class="admin-calendar-drawer hidden" aria-hidden="true">
  <div id="admin-calendar-drawer-backdrop" class="admin-calendar-drawer-backdrop"></div>
  <aside class="admin-calendar-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="admin-calendar-drawer-title">
    <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
      <div>
        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#003E9F]">Event Monitoring Details</p>
        <h2 id="admin-calendar-drawer-title" class="mt-1 text-lg font-bold text-slate-900">Submission Event</h2>
      </div>
      <button id="admin-calendar-drawer-close" type="button" class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close details">
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <div class="space-y-4 px-5 py-5">
      <span id="admin-calendar-status" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">Status</span>
      <dl class="grid grid-cols-1 gap-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Organization</dt><dd id="admin-calendar-org" class="mt-1 text-sm text-slate-800"></dd></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Submitted By</dt><dd id="admin-calendar-submitted-by" class="mt-1 text-sm text-slate-800"></dd></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</dt><dd id="admin-calendar-date" class="mt-1 text-sm text-slate-800"></dd></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Time</dt><dd id="admin-calendar-time" class="mt-1 text-sm text-slate-800"></dd></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Venue</dt><dd id="admin-calendar-venue" class="mt-1 text-sm text-slate-800"></dd></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Submission Type</dt><dd id="admin-calendar-submission-type" class="mt-1 text-sm text-slate-800"></dd></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3"><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Submission Date</dt><dd id="admin-calendar-submission-date" class="mt-1 text-sm text-slate-800"></dd></div>
      </dl>
    </div>

    <div class="border-t border-slate-200 px-5 py-4">
      <a id="admin-calendar-view-submission" href="#" class="inline-flex w-full items-center justify-center rounded-xl border border-[#003E9F] px-4 py-2.5 text-sm font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white focus:outline-none focus:ring-4 focus:ring-[#003E9F]/30">
        Open Related Submission
      </a>
    </div>
  </aside>
</div>
