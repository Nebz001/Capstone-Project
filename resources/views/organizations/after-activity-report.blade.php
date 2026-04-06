@extends('layouts.organization-portal')

@section('title', 'After Activity Report — NU Lipa SDAO')

@section('content')

@php
    $officerValidationPending = $officerValidationPending ?? false;
    $fileClass = 'block w-full cursor-pointer text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200/80';
    $saOrg = auth()->user()->isSuperAdmin()
        ? (optional($organization ?? null)->id ?: ($superAdminOrganizationId ?? null))
        : null;
    $saQ = $saOrg ? '?organization_id='.(int) $saOrg : '';
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

    <header class="mb-8">
        <a href="{{ route('organizations.submit-report') }}{{ $saQ }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Back to Submit Report
        </a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            After Activity Report
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Submit a structured report for a completed organization activity or event.
        </p>
    </header>

    @if (session('success'))
        <div
            id="after-activity-report-success-alert-data"
            data-success-title="Report Submitted"
            data-success-message="{{ session('success') }}"
            data-success-redirect-url="{{ session('after_activity_report_redirect_to', '') }}"
            data-success-redirect-delay="1800"
            hidden
        ></div>
    @endif

    @if (session('error'))
        <x-feedback.blocked-message variant="error" class="mb-6" :message="session('error')" />
    @endif

    @if ($officerValidationPending)
        <x-feedback.blocked-message message="Your student officer account is pending SDAO validation. You cannot submit reports until validation is complete." class="mb-6" />
    @endif

    @if ($organization)
    <form
        method="POST"
        action="{{ route('organizations.after-activity-report.store') }}"
        enctype="multipart/form-data"
        class="space-y-6 @if($officerValidationPending) pointer-events-none opacity-50 @endif"
    >
        @csrf
        @if (auth()->user()->isSuperAdmin())
            <input type="hidden" name="organization_id" value="{{ $organization->id }}" />
        @endif

        {{-- 1. Basic Information --}}
        <x-ui.card padding="p-0">
            <x-ui.card-section-header
                title="Basic Information"
                subtitle="Activity identity, school context, and event poster."
                helper='Fields marked with <span class="text-red-600">*</span> are required.'
                :helper-html="true"
                content-padding="px-6"
            />
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-forms.label for="activity_event_title" required>Name of Activity / Event Title</x-forms.label>
                        <x-forms.input
                            id="activity_event_title"
                            name="activity_event_title"
                            type="text"
                            :value="old('activity_event_title')"
                            required
                        />
                        @error('activity_event_title') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:col-span-2">
                        <div>
                            <x-forms.label for="school" required>School</x-forms.label>
                            <x-forms.select id="school" name="school" required>
                                <option value="" disabled @selected(! old('school'))>Select school</option>
                                @foreach ($schoolOptions as $code => $label)
                                    <option value="{{ $code }}" @selected(old('school') === $code)>{{ $label }}</option>
                                @endforeach
                            </x-forms.select>
                            @error('school') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                        </div>
                        <div>
                            <x-forms.label for="department" required>Department</x-forms.label>
                            <x-forms.input
                                id="department"
                                name="department"
                                type="text"
                                placeholder="e.g., Computer Engineering"
                                :value="old('department')"
                                required
                            />
                            @error('department') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <x-forms.label for="poster_image" required>Poster or Title Image of the Event</x-forms.label>
                        <input
                            id="poster_image"
                            name="poster_image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="{{ $fileClass }} mt-2"
                            @unless($officerValidationPending) required @endunless
                        />
                        <x-forms.helper>JPEG, PNG, or WebP. Max 5&nbsp;MB.</x-forms.helper>
                        @error('poster_image') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                    </div>
                </div>
            </div>
        </x-ui.card>

        {{-- 2. Event Details --}}
        <x-ui.card padding="p-0">
            <x-ui.card-section-header
                title="Event Details"
                subtitle="Event naming, schedule, leadership, and submission metadata."
                content-padding="px-6"
            />
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    @if ($optionalProposals->isNotEmpty())
                        <div class="md:col-span-2">
                            <x-forms.label for="proposal_id">Linked activity proposal (optional)</x-forms.label>
                            <x-forms.select id="proposal_id" name="proposal_id">
                                <option value="">— None —</option>
                                @foreach ($optionalProposals as $prop)
                                    <option value="{{ $prop->id }}" @selected((string) old('proposal_id') === (string) $prop->id)>
                                        {{ $prop->activity_title }} — {{ $prop->submission_date?->format('M j, Y') ?? 'N/A' }}
                                    </option>
                                @endforeach
                            </x-forms.select>
                            @error('proposal_id') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                        </div>
                    @endif
                    <div class="md:col-span-2">
                        <x-forms.label for="event_name" required>Name of Event</x-forms.label>
                        <x-forms.input
                            id="event_name"
                            name="event_name"
                            type="text"
                            :value="old('event_name')"
                            required
                        />
                        @error('event_name') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:col-span-2">
                        <div>
                            <x-forms.label for="event_starts_at" required>Date and Time of Event</x-forms.label>
                            <x-forms.input
                                id="event_starts_at"
                                name="event_starts_at"
                                type="datetime-local"
                                :value="old('event_starts_at')"
                                required
                            />
                            @error('event_starts_at') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                        </div>
                        <div>
                            <x-forms.label for="date_submitted_display">Date Submitted</x-forms.label>
                            <input
                                id="date_submitted_display"
                                type="text"
                                readonly
                                value="{{ now()->format('F j, Y') }}"
                                class="mt-2 block w-full cursor-not-allowed rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-600 shadow-sm"
                            />
                            <x-forms.helper>Recorded automatically when you submit this form.</x-forms.helper>
                        </div>
                    </div>
                    <div>
                        <x-forms.label for="activity_chairs" required>Activity Chair/s</x-forms.label>
                        <x-forms.input
                            id="activity_chairs"
                            name="activity_chairs"
                            type="text"
                            placeholder="Names separated by commas if multiple"
                            :value="old('activity_chairs')"
                            required
                        />
                        @error('activity_chairs') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                    </div>
                    <div>
                        <x-forms.label for="prepared_by" required>Prepared By</x-forms.label>
                        <x-forms.input
                            id="prepared_by"
                            name="prepared_by"
                            type="text"
                            :value="old('prepared_by', $prefillPreparedBy)"
                            required
                        />
                        @error('prepared_by') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                    </div>
                </div>
            </div>
        </x-ui.card>

        {{-- 3. Report Content --}}
        <x-ui.card padding="p-0">
            <x-ui.card-section-header
                title="Report Content"
                subtitle="Narrative summary and program outline."
                content-padding="px-6"
            />
            <div class="px-6 py-6 space-y-6">
                <div>
                    <x-forms.label for="summary_description" required>Summary / Description of the Activity</x-forms.label>
                    <x-forms.textarea id="summary_description" name="summary_description" rows="5" required>{{ old('summary_description') }}</x-forms.textarea>
                    @error('summary_description') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                </div>
                <div>
                    <x-forms.label for="program_content" required>Program</x-forms.label>
                    <x-forms.textarea id="program_content" name="program_content" rows="5" placeholder="Outline sessions, flow, or segments as applicable." required>{{ old('program_content') }}</x-forms.textarea>
                    @error('program_content') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                </div>
            </div>
        </x-ui.card>

        {{-- 4. Photo and Supporting Media --}}
        <x-ui.card padding="p-0">
            <x-ui.card-section-header
                title="Photo and Supporting Media"
                subtitle="Event photos and sample participation documentation."
                content-padding="px-6"
            />
            <div class="px-6 py-6 space-y-6">
                <div>
                    <x-forms.label for="photos">Photos (multiple)</x-forms.label>
                    <input
                        id="photos"
                        name="photos[]"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        class="{{ $fileClass }} mt-2"
                        multiple
                    />
                    <x-forms.helper>Optional. Up to 15 images (JPEG, PNG, WebP), 5&nbsp;MB each.</x-forms.helper>
                    @error('photos') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                    @error('photos.*') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                </div>
                <div>
                    <x-forms.label for="certificate_sample">Sample Certificate of Recognition / Attendance / Participation</x-forms.label>
                    <input
                        id="certificate_sample"
                        name="certificate_sample"
                        type="file"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                        class="{{ $fileClass }} mt-2"
                    />
                    <x-forms.helper>Optional. PDF, Word, or image. Max 10&nbsp;MB.</x-forms.helper>
                    @error('certificate_sample') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                </div>
            </div>
        </x-ui.card>

        {{-- 5. Evaluation --}}
        <x-ui.card padding="p-0">
            <x-ui.card-section-header
                title="Evaluation"
                subtitle="Outcomes, reach, and evaluation artifacts."
                content-padding="px-6"
            />
            <div class="px-6 py-6 space-y-6">
                <div>
                    <x-forms.label for="evaluation_report" required>Activity Evaluation Report</x-forms.label>
                    <x-forms.textarea id="evaluation_report" name="evaluation_report" rows="4" required>{{ old('evaluation_report') }}</x-forms.textarea>
                    @error('evaluation_report') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                </div>
                <div>
                    <x-forms.label for="participants_reached_percent" required>Percentage of Target Participants Reached</x-forms.label>
                    <div class="mt-2 flex max-w-md items-center gap-2">
                        <x-forms.input
                            id="participants_reached_percent"
                            name="participants_reached_percent"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            placeholder="e.g., 85"
                            :value="old('participants_reached_percent')"
                            required
                        />
                        <span class="text-sm font-semibold text-slate-600">%</span>
                    </div>
                    @error('participants_reached_percent') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                </div>
                <div>
                    <x-forms.label for="evaluation_form_sample">Sample Evaluation Form Used</x-forms.label>
                    <input
                        id="evaluation_form_sample"
                        name="evaluation_form_sample"
                        type="file"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                        class="{{ $fileClass }} mt-2"
                    />
                    <x-forms.helper>Optional. PDF, Word, or image. Max 10&nbsp;MB.</x-forms.helper>
                    @error('evaluation_form_sample') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                </div>
            </div>
        </x-ui.card>

        {{-- 6. Attachments --}}
        <x-ui.card padding="p-0">
            <x-ui.card-section-header
                title="Attachments"
                subtitle="Attendance documentation."
                content-padding="px-6"
            />
            <div class="px-6 py-6">
                <div>
                    <x-forms.label for="attendance_sheet" required>Attendance Sheet</x-forms.label>
                    <input
                        id="attendance_sheet"
                        name="attendance_sheet"
                        type="file"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                        class="{{ $fileClass }} mt-2"
                        @unless($officerValidationPending) required @endunless
                    />
                    <x-forms.helper>PDF, Word, or image. Max 10&nbsp;MB.</x-forms.helper>
                    @error('attendance_sheet') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="px-6 py-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <a
                        href="{{ route('organizations.submit-report') }}{{ $saQ }}"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20"
                    >
                        Cancel
                    </a>
                    <x-ui.button type="submit" :disabled="$officerValidationPending">
                        Submit Report
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>
    </form>
    @endif
</div>

@endsection
