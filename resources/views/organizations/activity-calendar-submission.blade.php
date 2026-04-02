@extends('layouts.organization')

@section('title', 'Activity Calendar Submission — NU Lipa SDAO')

@section('content')

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

  <header class="mb-8">
    <a href="{{ route('organizations.manage') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
      <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
      </svg>
      Back to Manage Organization
    </a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
      Activity Calendar Submission
    </h1>
    <p class="mt-1 text-sm text-slate-500">
      Submit your organization&rsquo;s term activity calendar for review.
    </p>
  </header>

  <form id="activity-calendar-form" method="POST" action="" class="space-y-6" novalidate>
    @csrf

    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Organization Information"
        subtitle="Provide the details for this submission."
        helper='Fields marked with <span class="text-red-600">*</span> are required.'
        :helper-html="true"
        content-padding="px-6" />

      <div class="px-6 py-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <x-forms.label for="academic_year" required>Academic Year</x-forms.label>
            <x-forms.input
              id="academic_year"
              name="academic_year"
              type="text"
              placeholder="2025-2026"
              required />
          </div>

          <div>
            <x-forms.label for="term" required>Term</x-forms.label>
            <x-forms.select id="term" name="term" required>
              <option value="" disabled selected>Select term</option>
              <option value="term_1">Term 1</option>
              <option value="term_2">Term 2</option>
              <option value="term_3">Term 3</option>
            </x-forms.select>
          </div>

          <div>
            <x-forms.label for="organization_name" required>RSO Name / Organization Name</x-forms.label>
            <x-forms.input
              id="organization_name"
              name="organization_name"
              type="text"
              placeholder="e.g., Computer Society"
              required />
          </div>

          <div>
            <x-forms.label for="date_submitted" required>Date Submitted</x-forms.label>
            <x-forms.input
              id="date_submitted"
              name="date_submitted"
              type="date"
              required />
          </div>
        </div>
      </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Activity Calendar"
        subtitle="Status and Date Received are for admin use."
        content-padding="px-6" />

      <div class="px-6 py-6">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
            <h3 id="activity-entry-title" class="text-sm font-semibold text-slate-900">Enter One Activity</h3>
            <p class="text-xs text-slate-600">Add activities one at a time; they&rsquo;ll appear below.</p>
          </div>

          <div class="mt-5 grid grid-cols-1 gap-5">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
              <div class="md:col-span-2">
                <x-forms.label for="activity_date" required>Date</x-forms.label>
                <x-forms.input id="activity_date" type="date" required />
              </div>

              <div class="md:col-span-2">
                <x-forms.label for="activity_sdg" required>SDG</x-forms.label>
                <x-forms.select id="activity_sdg" required>
                  <option value="" selected>Select SDG</option>
                  <option value="SDG 1">SDG 1</option>
                  <option value="SDG 2">SDG 2</option>
                  <option value="SDG 3">SDG 3</option>
                  <option value="SDG 4">SDG 4</option>
                  <option value="SDG 5">SDG 5</option>
                  <option value="SDG 6">SDG 6</option>
                  <option value="SDG 7">SDG 7</option>
                  <option value="SDG 8">SDG 8</option>
                  <option value="SDG 9">SDG 9</option>
                  <option value="SDG 10">SDG 10</option>
                  <option value="SDG 11">SDG 11</option>
                  <option value="SDG 12">SDG 12</option>
                  <option value="SDG 13">SDG 13</option>
                  <option value="SDG 14">SDG 14</option>
                  <option value="SDG 15">SDG 15</option>
                  <option value="SDG 16">SDG 16</option>
                  <option value="SDG 17">SDG 17</option>
                </x-forms.select>
              </div>

              <div class="md:col-span-2">
                <x-forms.label for="activity_budget" required>Budget</x-forms.label>
                <x-forms.input id="activity_budget" type="text" required placeholder="e.g., P1,500 or No Expenses" />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
              <div class="md:col-span-4">
                <x-forms.label for="activity_name" required>Activity Name</x-forms.label>
                <x-forms.input id="activity_name" type="text" required placeholder="e.g., Orientation Seminar" />
              </div>
              <div class="md:col-span-2">
                <x-forms.label for="activity_venue" required>Venue</x-forms.label>
                <x-forms.input id="activity_venue" type="text" required placeholder="e.g., University Auditorium" />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
              <div class="md:col-span-6">
                <x-forms.label for="activity_participant_program" required>Participant / Program Assigned</x-forms.label>
                <x-forms.input id="activity_participant_program" type="text" required placeholder="e.g., 2nd Year CS / Program Committee" />
              </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-xs text-slate-600">Status and Date Received will be set by the reviewing office.</p>
              <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                <x-ui.button id="cancel-edit" type="button" variant="secondary" class="hidden w-full sm:w-auto">Cancel Edit</x-ui.button>
                <x-ui.button id="add-activity" type="button" class="w-full sm:w-auto">Add Activity</x-ui.button>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm" id="added-activities-section">
          <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
              <h3 class="text-sm font-semibold text-slate-900">Added Activities</h3>
              <p class="text-xs text-slate-600">Preview of activities included in this submission.</p>
            </div>
          </div>

          <div class="px-5 py-5 sm:px-6">
            <input type="hidden" name="activities_json" id="activities_json" value="[]" />
            <div id="activities-hidden-inputs"></div>

            <div class="overflow-x-auto rounded-2xl border border-slate-200">
              <table class="w-full min-w-[1280px] table-fixed border-collapse text-left text-sm">
                <colgroup>
                  <col class="w-36" />
                  <col class="w-[22rem]" />
                  <col class="w-32" />
                  <col class="w-[18rem]" />
                  <col class="w-[22rem]" />
                  <col class="w-[14rem]" />
                  <col class="w-40" />
                  <col class="w-44" />
                  <col class="w-36" />
                </colgroup>
                <thead class="bg-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-600">
                  <tr>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">Date</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">Activity Name</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">SDG</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">Venue</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">Participant / Program Assigned</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">Budget</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">Status</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">Date Received</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3.5">Actions</th>
                  </tr>
                </thead>
                <tbody id="activities-preview-body" class="divide-y divide-slate-200 bg-white">
                  <tr id="activities-empty-state">
                    <td colspan="9" class="px-5 py-10 text-center text-sm text-slate-600">
                      No activities added yet.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Notes / Reminders"
        subtitle="Please review before submitting."
        content-padding="px-6" />
      <div class="px-6 py-6">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
          <ul class="list-disc space-y-2 pl-5">
            <li>Ensure all activities are aligned with the organization&rsquo;s plan.</li>
            <li>Budget entries may indicate &ldquo;No Expenses&rdquo; when applicable.</li>
            <li>Status and Date Received will be completed by the reviewing office.</li>
          </ul>
        </div>
      </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
      <div class="px-6 py-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
          <x-ui.button type="reset" variant="secondary" class="w-full sm:w-auto">Reset Form</x-ui.button>
          <x-ui.button id="submit-activity-calendar" type="submit" formnovalidate class="w-full sm:w-auto">Submit Activity Calendar</x-ui.button>
        </div>
      </div>
    </x-ui.card>
  </form>

</div>

@endsection