@extends('layouts.admin')

@section('title', 'Student Officer Accounts — NU Lipa SDAO')

@section('content')
@php
  $badgeClass = function (string $status): string {
    return match ($status) {
      'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
      'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
      'ACTIVE' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
      'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
      'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
      default => 'bg-slate-100 text-slate-700 border border-slate-200',
    };
  };
@endphp

<header class="mb-6">
  <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Student Officer Accounts</h1>
  <p class="mt-1 text-sm text-slate-500">Review and validate student accounts for organization officer access.</p>
</header>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Student Name</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">School ID</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">School Email</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Organization</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Position</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Account Status</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Validation Status</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date Registered</th>
          <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @forelse ($accounts as $account)
          @php
            $latestOfficer = $account->organizationOfficers->first();
          @endphp
          <tr class="hover:bg-slate-50/80">
            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $account->full_name }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $account->school_id }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $account->email }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $latestOfficer?->organization?->organization_name ?? 'Not linked' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $latestOfficer?->position_title ?? 'N/A' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $account->account_status }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass($account->officer_validation_status) }}">
                {{ str_replace('_', ' ', $account->officer_validation_status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ optional($account->created_at)->format('M d, Y') ?? 'N/A' }}</td>
            <td class="px-4 py-3 text-right">
              <a href="{{ route('admin.officer-accounts.show', $account) }}" class="inline-flex rounded-lg border border-[#003E9F] px-3 py-1.5 text-xs font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white">
                Review
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">No student officer accounts found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="border-t border-slate-100 px-4 py-3">
    {{ $accounts->links() }}
  </div>
</section>
@endsection

