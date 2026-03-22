@extends('layouts.app')

@section('title', 'Register Student Organization')

@section('content')
  <div class="min-h-screen bg-gray-50 py-10 sm:py-14">
    <div class="mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">
                Student Organization Application Form
            </h1>
            <p class="mt-2 text-sm text-gray-600 sm:text-base">
                Please complete all required fields before submitting your application.
            </p>
        </header>

        <div
            id="submit-success"
            role="alert"
            aria-live="polite"
            tabindex="-1"
            class="hidden rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm"
        >
            Application submitted successfully.
        </div>

        <form id="organization-application-form" method="POST" action="" class="space-y-6">
            @csrf

            <!-- Application Information -->
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Application Information</h2>
                    <p class="mt-1 text-sm text-gray-600">Provide the application type and academic year.</p>
                    <p class="mt-4 text-xs text-gray-500">
                      Fields marked with <span class="text-red-600">*</span> are required.
                    </p>
                </div>

                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <fieldset class="md:col-span-1">
                            <legend class="text-sm font-medium text-gray-900">
                                Application For <span class="text-red-600">*</span>
                            </legend>
                            <div class="mt-3 space-y-3">
                                <label for="application_for_new" class="flex items-start gap-3">
                                    <input
                                        id="application_for_new"
                                        name="application_for"
                                        type="radio"
                                        value="new"
                                        class="mt-1 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        required
                                    />
                                    <span class="text-sm text-gray-700">New</span>
                                </label>

                                <label for="application_for_renewal" class="flex items-start gap-3">
                                    <input
                                        id="application_for_renewal"
                                        name="application_for"
                                        type="radio"
                                        value="renewal"
                                        class="mt-1 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        required
                                    />
                                    <span class="text-sm text-gray-700">Renewal</span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="md:col-span-1">
                            <label for="academic_year" class="block text-sm font-medium text-gray-900">
                                Academic Year <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="academic_year"
                                name="academic_year"
                                type="text"
                                inputmode="text"
                                placeholder="e.g., 2025-2026"
                                required
                                class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                            <p class="mt-2 text-xs text-gray-500">Use the format shown in the example.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Contact Information -->
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Contact Information</h2>
                    <p class="mt-1 text-sm text-gray-600">Enter the primary contact details for your organization.</p>
                </div>

                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="organization_name" class="block text-sm font-medium text-gray-900">
                                Organization Name <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="organization_name"
                                name="organization_name"
                                type="text"
                                placeholder="e.g., Computer Society"
                                required
                                class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </div>

                        <div>
                            <label for="contact_person" class="block text-sm font-medium text-gray-900">
                                Contact Person <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="contact_person"
                                name="contact_person"
                                type="text"
                                placeholder="Full name"
                                required
                                class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </div>

                        <div>
                            <label for="contact_no" class="block text-sm font-medium text-gray-900">
                                Contact No. <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="contact_no"
                                name="contact_no"
                                type="text"
                                inputmode="tel"
                                placeholder="e.g., 09XX XXX XXXX"
                                required
                                class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </div>

                        <div>
                            <label for="email_address" class="block text-sm font-medium text-gray-900">
                                Email Address <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="email_address"
                                name="email_address"
                                type="email"
                                autocomplete="email"
                                placeholder="e.g., surname@students.nu-lipa.edu.ph"
                                required
                                class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Organization Details -->
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Organization Details</h2>
                    <p class="mt-1 text-sm text-gray-600">Provide key information about your organization.</p>
                </div>

                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="date_organized" class="block text-sm font-medium text-gray-900">
                                Date Organized <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="date_organized"
                                name="date_organized"
                                type="date"
                                required
                                class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </div>

                        <fieldset>
                            <legend class="text-sm font-medium text-gray-900">
                                Type of Organization <span class="text-red-600">*</span>
                            </legend>
                            <div class="mt-3 space-y-3">
                                <label for="type_co_curricular" class="flex items-start gap-3">
                                    <input
                                        id="type_co_curricular"
                                        name="organization_type"
                                        type="radio"
                                        value="co_curricular"
                                        class="mt-1 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        required
                                    />
                                    <span class="text-sm text-gray-700">Co-Curricular Organization</span>
                                </label>
                                <label for="type_extra_curricular" class="flex items-start gap-3">
                                    <input
                                        id="type_extra_curricular"
                                        name="organization_type"
                                        type="radio"
                                        value="extra_curricular"
                                        class="mt-1 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        required
                                    />
                                    <span class="text-sm text-gray-700">Extra-Curricular Organization / Interest Clubs</span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="md:col-span-2">
                            <label for="purpose" class="block text-sm font-medium text-gray-900">
                                Purpose of Organization <span class="text-red-600">*</span>
                            </label>
                            <textarea
                                id="purpose"
                                name="purpose"
                                rows="5"
                                placeholder="Briefly describe the mission, goals, and primary activities of the organization."
                                required
                                class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            ></textarea>
                            <p class="mt-2 text-xs text-gray-500">Keep it clear and academic in tone.</p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="college" class="block text-sm font-medium text-gray-900">
                                College <span class="text-red-600">*</span>
                            </label>
                            <div class="relative mt-2">
                                <select
                                    id="college"
                                    name="college"
                                    required
                                    class="block w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2 pr-10 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="" disabled selected>Select a college</option>
                                    <option value="ccit">School of Architecture, Computer and Engineering</option>
                                    <option value="cba">School of Allied Health and Sciences</option>
                                    <option value="coe">School of Accounting and Busisness Management</option>
                                    <option value="ceas">Senior High School</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.24 4.5a.75.75 0 0 1-1.08 0l-4.24-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Requirements Attached -->
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Requirements Attached</h2>
                    <p class="mt-1 text-sm text-gray-600">Check the documents included with your application.</p>
                </div>

                <div class="px-6 py-6">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 sm:p-5">
                        <div id="requirements-note" class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-3 text-sm text-gray-700">
                            Please select an application type (New or Renewal) to view the required attachments.
                        </div>

                        <div id="requirements-new" class="mt-4 hidden">
                            <p class="text-sm font-medium text-gray-900">New Application Requirements</p>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_new_letter_intent" name="requirements[]" type="checkbox" value="letter_of_intent" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_new_letter_intent" class="text-sm text-gray-700">Letter of Intent</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_new_application_form" name="requirements[]" type="checkbox" value="application_form" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_new_application_form" class="text-sm text-gray-700">Application Form</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_new_by_laws" name="requirements[]" type="checkbox" value="by_laws" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_new_by_laws" class="text-sm text-gray-700">By Laws of the Organization</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_new_officers_founders" name="requirements[]" type="checkbox" value="updated_list_of_officers_founders" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_new_officers_founders" class="text-sm text-gray-700">Updated List of Officers/Founders</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_new_dean_endorsement" name="requirements[]" type="checkbox" value="dean_endorsement_faculty_adviser" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_new_dean_endorsement" class="text-sm text-gray-700">Letter from the College Dean endorsing the Faculty Adviser</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_new_proposed_projects" name="requirements[]" type="checkbox" value="proposed_projects_budget" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_new_proposed_projects" class="text-sm text-gray-700">List of Proposed Projects with Proposed Budget for the AY</label>
                                </div>

                                <div class="rounded-md p-2 hover:bg-white/60 sm:col-span-2">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                        <div class="flex items-center gap-3">
                                            <input id="req_new_others" name="requirements[]" type="checkbox" value="others" class="h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                            <label for="req_new_others" class="text-sm text-gray-700">Others</label>
                                        </div>
                                        <label for="req_new_others_text" class="sr-only">Please specify other requirements</label>
                                        <input
                                            id="req_new_others_text"
                                            name="requirements_other"
                                            type="text"
                                            placeholder="Please specify"
                                            class="block w-full border-0 border-b border-gray-400 bg-transparent px-0 py-1 text-sm text-gray-900 placeholder:text-gray-400 shadow-none focus:border-gray-600 focus:outline-none focus:ring-0 sm:max-w-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="requirements-renewal" class="mt-4 hidden">
                            <p class="text-sm font-medium text-gray-900">Renewal Application Requirements</p>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_letter_intent" name="requirements[]" type="checkbox" value="letter_of_intent" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_letter_intent" class="text-sm text-gray-700">Letter of Intent</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_application_form" name="requirements[]" type="checkbox" value="application_form" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_application_form" class="text-sm text-gray-700">Application Form</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_by_laws" name="requirements[]" type="checkbox" value="by_laws_updated_if_applicable" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_by_laws" class="text-sm text-gray-700">By Laws of the Organization (if updated last AY)</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_officers_founders" name="requirements[]" type="checkbox" value="updated_list_of_officers_founders_ay" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_officers_founders" class="text-sm text-gray-700">Updated List of Officers/Founders for the AY</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_dean_endorsement" name="requirements[]" type="checkbox" value="dean_endorsement_faculty_adviser" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_dean_endorsement" class="text-sm text-gray-700">Letter from the College Dean endorsing the Faculty Adviser</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_proposed_projects" name="requirements[]" type="checkbox" value="proposed_projects_budget" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_proposed_projects" class="text-sm text-gray-700">List of Proposed Projects with Proposed Budget for the AY</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_past_projects" name="requirements[]" type="checkbox" value="past_projects" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_past_projects" class="text-sm text-gray-700">List of Past Projects</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_financial_statement" name="requirements[]" type="checkbox" value="financial_statement_previous_ay" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_financial_statement" class="text-sm text-gray-700">Financial Statement of the Previous AY</label>
                                </div>
                                <div class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
                                    <input id="req_renew_evaluation_summary" name="requirements[]" type="checkbox" value="evaluation_summary_past_projects" class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                    <label for="req_renew_evaluation_summary" class="text-sm text-gray-700">Summary of Evaluation of Past Projects</label>
                                </div>

                                <div class="rounded-md p-2 hover:bg-white/60 sm:col-span-2">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                        <div class="flex items-center gap-3">
                                            <input id="req_renew_others" name="requirements[]" type="checkbox" value="others" class="h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                            <label for="req_renew_others" class="text-sm text-gray-700">Others</label>
                                        </div>
                                        <label for="req_renew_others_text" class="sr-only">Please specify other requirements</label>
                                        <input
                                            id="req_renew_others_text"
                                            name="requirements_other"
                                            type="text"
                                            placeholder="Please specify"
                                            class="block w-full border-0 border-b border-gray-400 bg-transparent px-0 py-1 text-sm text-gray-900 placeholder:text-gray-400 shadow-none focus:border-gray-600 focus:outline-none focus:ring-0 sm:max-w-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bottom Actions -->
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="px-6 py-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button
                            type="reset"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 sm:w-auto"
                        >
                            Reset Form
                        </button>
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 sm:w-auto"
                        >
                            Submit Application
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </div>
  </div>
@endsection