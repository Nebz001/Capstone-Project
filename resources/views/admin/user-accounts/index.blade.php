@extends('layouts.admin')

@section('title', 'Account Management — NU Lipa SDAO')

@section('content')
@php
  $validationBadgeClass = function (?string $status): string {
    return match ($status) {
      'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
      'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
      'ACTIVE' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
      'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
      'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
      default => 'bg-slate-100 text-slate-700 border border-slate-200',
    };
  };
  $roleBadgeClass = function (?string $roleType): string {
    $roleType = $roleType ?: 'UNASSIGNED';
    return match ($roleType) {
      'ORG_OFFICER' => 'bg-sky-100 text-sky-900 border border-sky-200',
      'APPROVER' => 'bg-violet-100 text-violet-900 border border-violet-200',
      'ADMIN' => 'bg-slate-200 text-slate-800 border border-slate-300',
      default => 'bg-slate-100 text-slate-700 border border-slate-200',
    };
  };
  $accountStatusBadgeClass = function (?string $status): string {
    $status = strtoupper((string) ($status ?? 'UNKNOWN'));
    return match ($status) {
      'ACTIVE' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
      'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
      'INACTIVE' => 'bg-slate-100 text-slate-700 border border-slate-200',
      'SUSPENDED' => 'bg-rose-100 text-rose-700 border border-rose-200',
      default => 'bg-slate-100 text-slate-700 border border-slate-200',
    };
  };
@endphp

<x-ui.card padding="p-0" class="overflow-hidden max-w-full">
  <div class="px-3 py-3 sm:px-5 sm:py-4">
    <div class="max-w-full overflow-x-auto rounded-xl border border-slate-200">
    <table class="min-w-304 w-full divide-y divide-slate-200 text-left text-sm">
      <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <tr>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Name</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Role</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">School ID</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Email</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Account status</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Organization / context</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Officer validation</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Registered</th>
          <th class="whitespace-nowrap px-4 py-3 text-right sm:px-5">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 bg-white">
        @forelse ($accounts as $account)
          @php
            $latestOfficer = $account->organizationOfficers->first();
            $isOfficer = $account->role_type === 'ORG_OFFICER';
            $roleLabel = match ($account->role_type) {
              'ORG_OFFICER' => 'Organization Officer',
              'APPROVER' => 'Approver',
              'ADMIN' => 'Admin',
              default => 'Unassigned',
            };
            $context = $isOfficer
              ? ($latestOfficer?->organization?->organization_name ?? 'Not linked')
                . ($latestOfficer?->position_title ? ' · '.$latestOfficer->position_title : '')
              : match ($account->role_type) {
                'APPROVER' => 'Signatory / approval workflow',
                'ADMIN' => '—',
                default => '—',
              };
          @endphp
          <tr class="align-top hover:bg-slate-50/80">
            <td class="px-4 py-3.5 sm:px-5">
              <p class="font-semibold text-slate-900">{{ $account->full_name }}</p>
            </td>
            <td class="px-4 py-3.5 sm:px-5">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $roleBadgeClass($account->role_type) }}">
                {{ $roleLabel }}
              </span>
            </td>
            <td class="whitespace-nowrap px-4 py-3.5 font-medium text-slate-700 sm:px-5">{{ $account->school_id }}</td>
            <td class="px-4 py-3.5 text-slate-700 sm:px-5">
              <span class="block max-w-xs wrap-break-word font-medium">{{ $account->email }}</span>
            </td>
            <td class="px-4 py-3.5 sm:px-5">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $accountStatusBadgeClass($account->account_status) }}">
                {{ $account->account_status }}
              </span>
            </td>
            <td class="px-4 py-3.5 sm:px-5">
              <span class="block max-w-xs wrap-break-word font-medium text-slate-700">{{ $context }}</span>
            </td>
            <td class="px-4 py-3.5 sm:px-5">
              @if ($isOfficer)
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $validationBadgeClass($account->officer_validation_status) }}">
                  {{ str_replace('_', ' ', $account->officer_validation_status) }}
                </span>
              @else
                <span class="text-sm font-medium text-slate-400">—</span>
              @endif
            </td>
            <td class="whitespace-nowrap px-4 py-3.5 font-medium text-slate-700 sm:px-5">{{ optional($account->created_at)->format('M d, Y') ?? 'N/A' }}</td>
            <td class="px-4 py-3.5 text-right sm:px-5">
              <a href="{{ route('admin.accounts.show', $account) }}" class="inline-flex rounded-xl border border-[#003E9F] px-3.5 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30">
                View
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="px-5 py-12 text-center sm:px-6">
              <div class="flex flex-col items-center gap-2">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
                  <svg class="h-7 w-7 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                  </svg>
                </div>
                <p class="text-sm font-medium text-slate-700">No user accounts found</p>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>

  <div class="border-t border-slate-100 px-5 py-3 sm:px-6">
    {{ $accounts->links() }}
  </div>
</x-ui.card>
@endsection
