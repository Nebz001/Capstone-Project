@extends('layouts.app')

@section('title', 'Register Student Organization')

@section('content')
    <x-layout.page-shell max-width="max-w-4xl">
        <div class="mx-auto w-full max-w-4xl">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                Student Organization Application Form
            </h1>
            <p class="mt-2 text-sm text-slate-600 sm:text-base">
                Please complete all required fields before submitting your application.
            </p>
        </header>

        <div
            id="submit-success"
            role="alert"
            aria-live="polite"
            tabindex="-1"
            class="hidden rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm"
        >
            Application submitted successfully.
        </div>

        <form id="organization-application-form" method="POST" action="" class="space-y-6">
            @csrf

            <!-- Application Information -->
            <x-ui.card padding="p-0">
                <x-ui.card-section-header
                    title="Application Information"
                    subtitle="Provide the application type and academic year."
                    helper='Fields marked with <span class="text-red-600">*</span> are required.'
                    :helper-html="true"
                    content-padding="px-6"
                />

                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <fieldset class="md:col-span-1">
                            <legend class="text-sm font-medium text-slate-900">
                                Application For <span class="text-red-600">*</span>
                            </legend>
                            <div class="mt-3 space-y-3">
                                <x-forms.choice id="application_for_new" name="application_for" type="radio" value="new" required>
                                    New
                                </x-forms.choice>

                                <x-forms.choice id="application_for_renewal" name="application_for" type="radio" value="renewal" required>
                                    Renewal
                                </x-forms.choice>
                            </div>
                        </fieldset>

                        <div class="md:col-span-1">
                            <x-forms.label for="academic_year" required>Academic Year</x-forms.label>
                            <x-forms.input
                                id="academic_year"
                                name="academic_year"
                                type="text"
                                inputmode="text"
                                placeholder="e.g., 2025-2026"
                                required
                            />
                            <x-forms.helper>Use the format shown in the example.</x-forms.helper>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <!-- Contact Information -->
            <x-ui.card padding="p-0">
                <x-ui.card-section-header
                    title="Contact Information"
                    subtitle="Enter the primary contact details for your organization."
                    content-padding="px-6"
                />

                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <x-forms.label for="organization_name" required>Organization Name</x-forms.label>
                            <x-forms.input
                                id="organization_name"
                                name="organization_name"
                                type="text"
                                placeholder="e.g., Computer Society"
                                required
                            />
                        </div>

                        <div>
                            <x-forms.label for="contact_person" required>Contact Person</x-forms.label>
                            <x-forms.input
                                id="contact_person"
                                name="contact_person"
                                type="text"
                                placeholder="Full name"
                                required
                            />
                        </div>

                        <div>
                            <x-forms.label for="contact_no" required>Contact No.</x-forms.label>
                            <x-forms.input
                                id="contact_no"
                                name="contact_no"
                                type="text"
                                inputmode="tel"
                                placeholder="e.g., 09XX XXX XXXX"
                                required
                            />
                        </div>

                        <div>
                            <x-forms.label for="email_address" required>Email Address</x-forms.label>
                            <x-forms.input
                                id="email_address"
                                name="email_address"
                                type="email"
                                autocomplete="email"
                                placeholder="e.g., surname@students.nu-lipa.edu.ph"
                                required
                            />
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <!-- Organization Details -->
            <x-ui.card padding="p-0">
                <x-ui.card-section-header
                    title="Organization Details"
                    subtitle="Provide key information about your organization."
                    content-padding="px-6"
                />

                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <x-forms.label for="date_organized" required>Date Organized</x-forms.label>
                            <x-forms.input
                                id="date_organized"
                                name="date_organized"
                                type="date"
                                required
                            />
                        </div>

                        <fieldset>
                            <legend class="text-sm font-medium text-slate-900">
                                Type of Organization <span class="text-red-600">*</span>
                            </legend>
                            <div class="mt-3 space-y-3">
                                <x-forms.choice id="type_co_curricular" name="organization_type" type="radio" value="co_curricular" required>
                                    Co-Curricular Organization
                                </x-forms.choice>
                                <x-forms.choice id="type_extra_curricular" name="organization_type" type="radio" value="extra_curricular" required>
                                    Extra-Curricular Organization / Interest Clubs
                                </x-forms.choice>
                            </div>
                        </fieldset>

                        <div class="md:col-span-2">
                            <x-forms.label for="purpose" required>Purpose of Organization</x-forms.label>
                            <x-forms.textarea
                                id="purpose"
                                name="purpose"
                                rows="5"
                                placeholder="Briefly describe the mission, goals, and primary activities of the organization."
                                required
                            ></x-forms.textarea>
                            <x-forms.helper>Keep it clear and academic in tone.</x-forms.helper>
                        </div>

                        <div class="md:col-span-2">
                            <x-forms.label for="college" required>College</x-forms.label>
                            <x-forms.select id="college" name="college" required>
                                <option value="" disabled selected>Select a college</option>
                                <option value="ccit">School of Architecture, Computer and Engineering</option>
                                <option value="cba">School of Allied Health and Sciences</option>
                                <option value="coe">School of Accounting and Busisness Management</option>
                                <option value="ceas">Senior High School</option>
                            </x-forms.select>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <!-- Requirements Attached -->
            <x-ui.card padding="p-0">
                <x-ui.card-section-header
                    title="Requirements Attached"
                    subtitle="Check the documents included with your application."
                    content-padding="px-6"
                />

                <div class="px-6 py-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-100 p-4 sm:p-5">
                        <div id="requirements-note" class="rounded-md border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-700">
                            Please select an application type (New or Renewal) to view the required attachments.
                        </div>

                        <div id="requirements-new" class="mt-4 hidden">
                            <p class="text-sm font-medium text-slate-900">New Application Requirements</p>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-forms.choice id="req_new_letter_intent" name="requirements[]" value="letter_of_intent" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Letter of Intent</x-forms.choice>
                                <x-forms.choice id="req_new_application_form" name="requirements[]" value="application_form" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Application Form</x-forms.choice>
                                <x-forms.choice id="req_new_by_laws" name="requirements[]" value="by_laws" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">By Laws of the Organization</x-forms.choice>
                                <x-forms.choice id="req_new_officers_founders" name="requirements[]" value="updated_list_of_officers_founders" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Updated List of Officers/Founders</x-forms.choice>
                                <x-forms.choice id="req_new_dean_endorsement" name="requirements[]" value="dean_endorsement_faculty_adviser" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Letter from the College Dean endorsing the Faculty Adviser</x-forms.choice>
                                <x-forms.choice id="req_new_proposed_projects" name="requirements[]" value="proposed_projects_budget" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">List of Proposed Projects with Proposed Budget for the AY</x-forms.choice>

                                <div class="rounded-md p-2 hover:bg-white/60 sm:col-span-2">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                        <div class="flex items-center gap-3">
                                            <x-forms.choice id="req_new_others" name="requirements[]" value="others" wrapper-class="flex items-center gap-3" label-class="text-sm text-slate-700">Others</x-forms.choice>
                                        </div>
                                        <label for="req_new_others_text" class="sr-only">Please specify other requirements</label>
                                        <x-forms.input
                                            id="req_new_others_text"
                                            name="requirements_other"
                                            type="text"
                                            variant="underline"
                                            placeholder="Please specify"
                                            class="sm:max-w-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="requirements-renewal" class="mt-4 hidden">
                            <p class="text-sm font-medium text-slate-900">Renewal Application Requirements</p>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-forms.choice id="req_renew_letter_intent" name="requirements[]" value="letter_of_intent" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Letter of Intent</x-forms.choice>
                                <x-forms.choice id="req_renew_application_form" name="requirements[]" value="application_form" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Application Form</x-forms.choice>
                                <x-forms.choice id="req_renew_by_laws" name="requirements[]" value="by_laws_updated_if_applicable" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">By Laws of the Organization (if updated last AY)</x-forms.choice>
                                <x-forms.choice id="req_renew_officers_founders" name="requirements[]" value="updated_list_of_officers_founders_ay" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Updated List of Officers/Founders for the AY</x-forms.choice>
                                <x-forms.choice id="req_renew_dean_endorsement" name="requirements[]" value="dean_endorsement_faculty_adviser" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Letter from the College Dean endorsing the Faculty Adviser</x-forms.choice>
                                <x-forms.choice id="req_renew_proposed_projects" name="requirements[]" value="proposed_projects_budget" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">List of Proposed Projects with Proposed Budget for the AY</x-forms.choice>
                                <x-forms.choice id="req_renew_past_projects" name="requirements[]" value="past_projects" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">List of Past Projects</x-forms.choice>
                                <x-forms.choice id="req_renew_financial_statement" name="requirements[]" value="financial_statement_previous_ay" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Financial Statement of the Previous AY</x-forms.choice>
                                <x-forms.choice id="req_renew_evaluation_summary" name="requirements[]" value="evaluation_summary_past_projects" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">Summary of Evaluation of Past Projects</x-forms.choice>

                                <div class="rounded-md p-2 hover:bg-white/60 sm:col-span-2">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                        <div class="flex items-center gap-3">
                                            <x-forms.choice id="req_renew_others" name="requirements[]" value="others" wrapper-class="flex items-center gap-3" label-class="text-sm text-slate-700">Others</x-forms.choice>
                                        </div>
                                        <label for="req_renew_others_text" class="sr-only">Please specify other requirements</label>
                                        <x-forms.input
                                            id="req_renew_others_text"
                                            name="requirements_other"
                                            type="text"
                                            variant="underline"
                                            placeholder="Please specify"
                                            class="sm:max-w-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <!-- Bottom Actions -->
            <x-ui.card padding="p-0">
                <div class="px-6 py-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <x-ui.button type="reset" variant="secondary" class="w-full sm:w-auto">Reset Form</x-ui.button>
                        <x-ui.button type="submit" class="w-full sm:w-auto">Submit Application</x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        </form>
    </div>
  </x-layout.page-shell>
@endsection