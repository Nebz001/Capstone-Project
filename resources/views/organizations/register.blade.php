@extends('layouts.organization')

@section('title', 'Register Organization — NU Lipa SDAO')

@section('content')

<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-10">

  {{-- ── Page Header ──────────────────────────────────────────────── --}}
  <header class="mb-8">
    <a href="{{ route('organizations.manage') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
      <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
      </svg>
      Back to Manage Organization
    </a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
      New Organization Registration
    </h1>
    <p class="mt-1 text-sm text-slate-500">
      Complete all required fields to register your student organization with SDAO.
    </p>
  </header>

  @if (session('success'))
  <div
    id="organization-register-success-alert-data"
    data-success-title="Registration Submitted"
    data-success-message="{{ session('success') }}"
    data-success-redirect-url="{{ session('registration_redirect_to', '') }}"
    data-success-redirect-delay="1800"
    hidden></div>
  @endif

  @if (session('error'))
  <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm" role="alert">
    {{ session('error') }}
  </div>
  @endif

  <form method="POST" action="{{ route('organizations.register.store') }}" class="space-y-6">
    @csrf

    {{-- Academic Year --}}
    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Application Information"
        subtitle="Provide the academic year for this registration."
        helper='Fields marked with <span class="text-red-600">*</span> are required.'
        :helper-html="true"
        content-padding="px-6" />
      <div class="px-6 py-6">
        <div class="max-w-xs">
          <x-forms.label for="academic_year" required>Academic Year</x-forms.label>
          <x-forms.input
            id="academic_year"
            name="academic_year"
            type="text"
            inputmode="text"
            placeholder="e.g., 2025-2026"
            :value="old('academic_year')"
            required />
          <x-forms.helper>Use the format shown in the example.</x-forms.helper>
          @error('academic_year') <x-forms.error>{{ $message }}</x-forms.error> @enderror
        </div>
      </div>
    </x-ui.card>

    {{-- Contact Information --}}
    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Contact Information"
        subtitle="Enter the primary contact details for your organization."
        content-padding="px-6" />
      <div class="px-6 py-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <x-forms.label for="organization_name" required>Organization Name</x-forms.label>
            <x-forms.input
              id="organization_name"
              name="organization_name"
              type="text"
              placeholder="e.g., Computer Society"
              :value="old('organization_name')"
              required />
            @error('organization_name') <x-forms.error>{{ $message }}</x-forms.error> @enderror
          </div>
          <div>
            <x-forms.label for="contact_person" required>Contact Person</x-forms.label>
            <x-forms.input
              id="contact_person"
              name="contact_person"
              type="text"
              placeholder="Full name"
              :value="old('contact_person')"
              required />
            @error('contact_person') <x-forms.error>{{ $message }}</x-forms.error> @enderror
          </div>
          <div>
            <x-forms.label for="contact_no" required>Contact No.</x-forms.label>
            <x-forms.input
              id="contact_no"
              name="contact_no"
              type="text"
              inputmode="tel"
              placeholder="e.g., 09XX XXX XXXX"
              :value="old('contact_no')"
              required />
            @error('contact_no') <x-forms.error>{{ $message }}</x-forms.error> @enderror
          </div>
          <div>
            <x-forms.label for="email_address" required>Email Address</x-forms.label>
            <x-forms.input
              id="email_address"
              name="email_address"
              type="email"
              autocomplete="email"
              placeholder="e.g., surname@students.nu-lipa.edu.ph"
              :value="old('email_address')"
              required />
            @error('email_address') <x-forms.error>{{ $message }}</x-forms.error> @enderror
          </div>
        </div>
      </div>
    </x-ui.card>

    {{-- Organization Details --}}
    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Organization Details"
        subtitle="Provide key information about your organization."
        content-padding="px-6" />
      <div class="px-6 py-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <x-forms.label for="date_organized" required>Date Organized</x-forms.label>
            <x-forms.input
              id="date_organized"
              name="date_organized"
              type="date"
              :value="old('date_organized')"
              required />
            @error('date_organized') <x-forms.error>{{ $message }}</x-forms.error> @enderror
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
              required>{{ old('purpose') }}</x-forms.textarea>
            <x-forms.helper>Keep it clear and academic in tone.</x-forms.helper>
            @error('purpose') <x-forms.error>{{ $message }}</x-forms.error> @enderror
          </div>
          <div class="md:col-span-2">
            <x-forms.label for="college" required>College</x-forms.label>
            <x-forms.select id="college" name="college" required>
              <option value="" disabled @unless(old('college')) selected @endunless>Select a college</option>
              <option value="School of Architecture, Computer and Engineering" @selected(old('college')==='ccit' )>School of Architecture, Computer and Engineering</option>
              <option value="School of Allied Health and Sciences" @selected(old('college')==='cba' )>School of Allied Health and Sciences</option>
              <option value="School of Accounting and Business Management" @selected(old('college')==='coe' )>School of Accounting and Business Management</option>
              <option value="Senior High School" @selected(old('college')==='ceas' )>Senior High School</option>
            </x-forms.select>
            @error('college') <x-forms.error>{{ $message }}</x-forms.error> @enderror
          </div>
        </div>
      </div>
    </x-ui.card>

    {{-- Requirements (New Registration Only) --}}
    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Requirements Attached"
        subtitle="Check the documents included with your new registration application."
        content-padding="px-6" />
      <div class="px-6 py-6">
        <div class="rounded-2xl border border-slate-200 bg-slate-100 p-4 sm:p-5">
          <p class="text-sm font-medium text-slate-900">New Registration Requirements</p>
          <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-forms.choice id="req_letter_intent" name="requirements[]" value="letter_of_intent" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
              Letter of Intent
            </x-forms.choice>
            <x-forms.choice id="req_application_form" name="requirements[]" value="application_form" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
              Application Form
            </x-forms.choice>
            <x-forms.choice id="req_by_laws" name="requirements[]" value="by_laws" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
              By Laws of the Organization
            </x-forms.choice>
            <x-forms.choice id="req_officers_founders" name="requirements[]" value="updated_list_of_officers_founders" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
              Updated List of Officers/Founders
            </x-forms.choice>
            <x-forms.choice id="req_dean_endorsement" name="requirements[]" value="dean_endorsement_faculty_adviser" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
              Letter from the College Dean endorsing the Faculty Adviser
            </x-forms.choice>
            <x-forms.choice id="req_proposed_projects" name="requirements[]" value="proposed_projects_budget" wrapper-class="flex items-start gap-3 rounded-md p-2 hover:bg-white/60">
              List of Proposed Projects with Proposed Budget for the AY
            </x-forms.choice>

            <div class="rounded-md p-2 hover:bg-white/60 sm:col-span-2">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                <div class="flex items-center gap-3">
                  <x-forms.choice id="req_others" name="requirements[]" value="others" wrapper-class="flex items-center gap-3" label-class="text-sm text-slate-700">
                    Others
                  </x-forms.choice>
                </div>
                <label for="req_others_text" class="sr-only">Please specify other requirements</label>
                <x-forms.input
                  id="req_others_text"
                  name="requirements_other"
                  type="text"
                  variant="underline"
                  placeholder="Please specify"
                  class="sm:max-w-sm"
                  :value="old('requirements_other')" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </x-ui.card>

    {{-- Bottom Actions --}}
    <x-ui.card padding="p-0">
      <div class="px-6 py-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
          <x-ui.button type="reset" variant="secondary" class="w-full sm:w-auto">Reset Form</x-ui.button>
          <x-ui.button type="submit" class="w-full sm:w-auto">Submit Registration</x-ui.button>
        </div>
      </div>
    </x-ui.card>
  </form>

</div>

@endsection