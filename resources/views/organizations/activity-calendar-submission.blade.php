@extends('layouts.app')

@section('title', 'Activity Calendar')

@section('content')
  <!-- Toast (UI-only) -->
  <div id="toast-container" class="pointer-events-none fixed top-4 right-4 z-50">
      <div id="toast" class="pointer-events-auto invisible w-80 max-w-[calc(100vw-2rem)] translate-y-2 rounded-lg border border-gray-200 bg-white p-4 opacity-0 shadow-lg ring-1 ring-black/5 transition-opacity duration-200 ease-out transition-transform">
          <div class="flex items-start gap-3">
              <span id="toast-dot" class="mt-1 h-2.5 w-2.5 flex-none rounded-full bg-green-500"></span>
              <p id="toast-message" class="text-sm font-medium text-gray-900"></p>
              <button id="toast-close" type="button" class="ml-auto inline-flex h-7 w-7 flex-none items-center justify-center rounded-md text-gray-500 hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" aria-label="Close toast">
                  <span aria-hidden="true">×</span>
              </button>
          </div>
      </div>
  </div>

  <div class="min-h-screen bg-gray-50 py-10 sm:py-14">
      <div class="mx-auto w-full max-w-[95vw] px-4 sm:px-6 lg:px-10 2xl:max-w-screen-2xl 2xl:px-12">
          <header class="mb-8">
              <h1 class="text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">
                  Activity Calendar Submission
              </h1>
              <p class="mt-2 text-sm text-gray-600 sm:text-base">
                  Submit your organization’s term activity calendar for review.
              </p>
              <p class="mt-3 text-sm text-gray-600">
                  Please complete all required fields and list all proposed activities clearly.
              </p>
          </header>

          <form id="activity-calendar-form" method="POST" action="" class="space-y-6" novalidate>
              @csrf

              <!-- Organization Information -->
              <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                  <div class="border-b border-gray-100 px-6 py-4 sm:px-8 lg:px-10">
                      <h2 class="text-base font-semibold text-gray-900">Organization Information</h2>
                      <p class="mt-1 text-sm text-gray-600">Provide the details for this submission.</p>
                      <p class="mt-4 text-xs text-gray-500">
                          Fields marked with <span class="text-red-600">*</span> are required.
                      </p>
                  </div>

                  <div class="px-6 py-6 sm:px-8 lg:px-10">
                      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                          <div>
                              <label for="academic_year" class="block text-sm font-medium text-gray-900">
                                  Academic Year <span class="text-red-600">*</span>
                              </label>
                              <input
                                  id="academic_year"
                                  name="academic_year"
                                  type="text"
                                  placeholder="2025-2026"
                                  required
                                  class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                              />
                          </div>

                          <div>
                              <label for="term" class="block text-sm font-medium text-gray-900">
                                  Term <span class="text-red-600">*</span>
                              </label>
                              <div class="relative mt-2">
                                  <select
                                      id="term"
                                      name="term"
                                      required
                                      class="block w-full appearance-none rounded-lg border border-gray-300 bg-white px-4 py-2.5 pr-10 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                  >
                                      <option value="" disabled selected>Select term</option>
                                      <option value="term_1">Term 1</option>
                                      <option value="term_2">Term 2</option>
                                      <option value="term_3">Term 3</option>
                                  </select>
                                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500">
                                      <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                          <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.24 4.5a.75.75 0 0 1-1.08 0l-4.24-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                      </svg>
                                  </div>
                              </div>
                          </div>

                          <div>
                              <label for="organization_name" class="block text-sm font-medium text-gray-900">
                                  RSO Name / Organization Name <span class="text-red-600">*</span>
                              </label>
                              <input
                                  id="organization_name"
                                  name="organization_name"
                                  type="text"
                                  placeholder="e.g., Computer Society"
                                  required
                                  class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                              />
                          </div>

                          <div>
                              <label for="date_submitted" class="block text-sm font-medium text-gray-900">
                                  Date Submitted <span class="text-red-600">*</span>
                              </label>
                              <input
                                  id="date_submitted"
                                  name="date_submitted"
                                  type="date"
                                  required
                                  class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                              />
                          </div>
                      </div>
                  </div>
              </section>

              <!-- Activity Calendar Table -->
              <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                  <div class="border-b border-gray-100 px-6 py-4 sm:px-8 lg:px-10">
                      <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                          <h2 class="text-base font-semibold text-gray-900">Activity Calendar</h2>
                          <p class="text-sm text-gray-600">Status and Date Received are for admin use.</p>
                      </div>
                  </div>

                  <div class="px-6 py-6 sm:px-8 lg:px-10">
                      <div class="rounded-lg border border-gray-200 bg-gray-50/40 p-5 sm:p-6">
                          <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
                              <h3 id="activity-entry-title" class="text-sm font-semibold text-gray-900">Enter One Activity</h3>
                              <p class="text-xs text-gray-600">Add activities one at a time; they’ll appear below.</p>
                          </div>

                          <div class="mt-5 grid grid-cols-1 gap-5">
                              <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
                                  <div class="md:col-span-2">
                                      <label for="activity_date" class="block text-sm font-medium text-gray-900">Date <span class="text-red-600">*</span></label>
                                      <input id="activity_date" type="date" required class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                                  </div>

                                  <div class="md:col-span-2">
                                      <label for="activity_sdg" class="block text-sm font-medium text-gray-900">SDG <span class="text-red-600">*</span></label>
                                      <div class="relative mt-2">
                                          <select id="activity_sdg" required class="block w-full appearance-none rounded-lg border border-gray-300 bg-white px-4 py-2.5 pr-10 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
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
                                          </select>
                                          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500">
                                              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                  <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.24 4.5a.75.75 0 0 1-1.08 0l-4.24-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                              </svg>
                                          </div>
                                      </div>
                                  </div>

                                  <div class="md:col-span-2">
                                      <label for="activity_budget" class="block text-sm font-medium text-gray-900">Budget <span class="text-red-600">*</span></label>
                                      <input id="activity_budget" type="text" required placeholder="e.g., P1,500 or No Expenses" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                                  </div>
                              </div>

                              <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
                                  <div class="md:col-span-4">
                                      <label for="activity_name" class="block text-sm font-medium text-gray-900">Activity Name <span class="text-red-600">*</span></label>
                                      <input id="activity_name" type="text" required placeholder="e.g., Orientation Seminar" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                                  </div>
                                  <div class="md:col-span-2">
                                      <label for="activity_venue" class="block text-sm font-medium text-gray-900">Venue <span class="text-red-600">*</span></label>
                                      <input id="activity_venue" type="text" required placeholder="e.g., University Auditorium" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                                  </div>
                              </div>

                              <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
                                  <div class="md:col-span-6">
                                      <label for="activity_participant_program" class="block text-sm font-medium text-gray-900">Participant / Program Assigned <span class="text-red-600">*</span></label>
                                      <input id="activity_participant_program" type="text" required placeholder="e.g., 2nd Year CS / Program Committee" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-500 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                                  </div>
                              </div>

                              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                  <p class="text-xs text-gray-600">Status and Date Received will be set by the reviewing office.</p>
                                  <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                                      <button id="cancel-edit" type="button" class="hidden inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 sm:w-auto">
                                          Cancel Edit
                                      </button>
                                      <button id="add-activity" type="button" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 sm:w-auto">
                                          Add Activity
                                      </button>
                                  </div>
                              </div>
                          </div>
                      </div>

                      <div class="mt-6 rounded-xl border border-gray-200 bg-white shadow-sm" id="added-activities-section">
                          <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                              <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                                  <h3 class="text-sm font-semibold text-gray-900">Added Activities</h3>
                                  <p class="text-xs text-gray-600">Preview of activities included in this submission.</p>
                              </div>
                          </div>

                          <div class="px-5 py-5 sm:px-6">
                              <input type="hidden" name="activities_json" id="activities_json" value="[]" />
                              <div id="activities-hidden-inputs"></div>

                              <div class="overflow-x-auto rounded-lg border border-gray-200">
                                  <table class="w-full min-w-[1400px] table-fixed border-collapse text-left text-sm">
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
                                      <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
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
                                      <tbody id="activities-preview-body" class="divide-y divide-gray-200 bg-white">
                                          <tr id="activities-empty-state">
                                              <td colspan="9" class="px-5 py-10 text-center text-sm text-gray-600">
                                                  No activities added yet.
                                              </td>
                                          </tr>
                                      </tbody>
                                  </table>
                              </div>
                          </div>
                      </div>
                  </div>
              </section>

              <!-- Notes / Reminders -->
              <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                  <div class="border-b border-gray-100 px-6 py-4 sm:px-8 lg:px-10">
                      <h2 class="text-base font-semibold text-gray-900">Notes / Reminders</h2>
                      <p class="mt-1 text-sm text-gray-600">Please review before submitting.</p>
                  </div>
                  <div class="px-6 py-6 sm:px-8 lg:px-10">
                      <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-4 text-sm text-indigo-900">
                          <ul class="list-disc space-y-2 pl-5">
                              <li>Ensure all activities are aligned with the organization’s plan.</li>
                              <li>Budget entries may indicate “No Expenses” when applicable.</li>
                              <li>Status and Date Received will be completed by the reviewing office.</li>
                          </ul>
                      </div>
                  </div>
              </section>

              <!-- Actions -->
              <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                  <div class="px-6 py-6 sm:px-8 lg:px-10">
                      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                          <button
                              type="reset"
                              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 sm:w-auto"
                          >
                              Reset Form
                          </button>
                          <button
                              id="submit-activity-calendar"
                              type="submit"
                              formnovalidate
                              class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 sm:w-auto"
                          >
                              Submit Activity Calendar
                          </button>
                      </div>
                  </div>
              </section>
          </form>
      </div>
  </div>

@endsection