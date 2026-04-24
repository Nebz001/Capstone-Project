@extends('layouts.app')

@section('title', 'Student Registration')

@section('content')
	@if (session('success') || session('status'))
		<div
			id="register-success-alert-data"
			data-message="{{ session('success') ?? session('status') ?? 'Account created successfully.' }}"
			hidden
		></div>
	@endif

	<x-layout.page-shell>
		<div class="mx-auto w-full max-w-3xl">
			<x-ui.card>
				<x-ui.section-header
					title="Registration Details"
					subtitle="All fields below are for account setup only."
					helper='Fields marked with <span class="text-red-600">*</span> are required.'
					:helper-html="true"
				/>

				<form id="student-register-form" method="POST" action="{{ route('register.submit') }}" class="mt-5 space-y-5 sm:mt-6">
					@csrf

					<section class="space-y-4">
						<h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Student Information</h3>

						<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
							<div>
								<x-forms.label for="first_name" required>First Name</x-forms.label>
								<x-forms.input
									id="first_name"
									name="first_name"
									type="text"
									required
									autocomplete="given-name"
									value="{{ old('first_name') }}"
									placeholder="Enter your first name"
								/>
								@error('first_name')
									<x-forms.error>{{ $message }}</x-forms.error>
								@enderror
							</div>

							<div>
								<x-forms.label for="last_name" required>Last Name</x-forms.label>
								<x-forms.input
									id="last_name"
									name="last_name"
									type="text"
									required
									autocomplete="family-name"
									value="{{ old('last_name') }}"
									placeholder="Enter your last name"
								/>
								@error('last_name')
									<x-forms.error>{{ $message }}</x-forms.error>
								@enderror
							</div>
						</div>

						<div>
							<x-forms.label for="school_email" required>School Email</x-forms.label>
							<x-forms.input
								id="school_email"
								name="school_email"
								type="email"
								required
								autocomplete="email"
								value="{{ old('school_email') }}"
								placeholder="sample@students.nu-lipa.edu.ph"
							/>
							<x-forms.helper>Use your official NU Lipa student email only.</x-forms.helper>
							@error('school_email')
								<x-forms.error>{{ $message }}</x-forms.error>
							@enderror
						</div>

						<div>
							<x-forms.label for="school-id-segment-0">School ID</x-forms.label>
							<x-forms.helper class="mt-1">Format preview: 2023-123456</x-forms.helper>

							<input id="school_id" name="school_id" type="hidden" value="" />

							<div
								id="school-id-group"
								class="mt-2 flex w-full flex-wrap items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100/70 p-3 sm:gap-2 sm:p-3.5"
								role="group"
								aria-label="School ID digits"
							>
								@for ($i = 0; $i < 4; $i++)
									<x-forms.input
										type="text"
										variant="digit"
										id="school-id-segment-{{ $i }}"
										data-school-id-segment="{{ $i }}"
										inputmode="numeric"
										pattern="[0-9]*"
										maxlength="1"
										aria-label="School ID digit {{ $i + 1 }}"
									/>
								@endfor

								<span class="mx-1 inline-flex items-center self-center text-base leading-none font-semibold text-slate-500 sm:text-lg" aria-hidden="true">-</span>

								@for ($i = 4; $i < 10; $i++)
									<x-forms.input
										type="text"
										variant="digit"
										id="school-id-segment-{{ $i }}"
										data-school-id-segment="{{ $i }}"
										inputmode="numeric"
										pattern="[0-9]*"
										maxlength="1"
										aria-label="School ID digit {{ $i + 1 }}"
									/>
								@endfor
							</div>
						</div>
					</section>

					<section class="space-y-4 border-t border-slate-100 pt-5">
						<h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Security</h3>

						<div>
							<x-forms.label for="password" required>Password</x-forms.label>
							<div class="relative mt-2">
								<x-forms.input
									id="password"
									name="password"
									type="password"
									required
									autocomplete="new-password"
									placeholder="Create a secure password"
									data-password-input
									aria-describedby="password-requirements-title password-requirements-error"
									class="mt-0 pr-12"
								/>
								<button
									type="button"
									data-password-toggle
									data-password-target="password"
									aria-label="Show password"
									title="Show password"
									class="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500/25"
								>
									<svg data-icon-eye-open xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
										<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
										<circle cx="12" cy="12" r="3" />
									</svg>
									<svg data-icon-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
										<path d="m3 3 18 18" />
										<path d="M10.477 10.488a3 3 0 0 0 4.242 4.242" />
										<path d="M9.88 5.09A10.94 10.94 0 0 1 12 4.875c5.523 0 10 3.358 10 7.5a6.87 6.87 0 0 1-1.333 3.895" />
										<path d="M6.61 6.61C4.149 7.97 2.5 10.044 2.5 12.375c0 4.142 4.477 7.5 10 7.5a10.93 10.93 0 0 0 4.84-1.13" />
									</svg>
								</button>
							</div>
              <x-forms.error id="password-requirements-error" class="hidden" aria-live="polite">
								Password must meet all the requirements below.
							</x-forms.error>
							<x-forms.helper id="password-requirements-title">Password must include:</x-forms.helper>
							<ul class="mt-3 space-y-2" aria-labelledby="password-requirements-title">
								<li data-password-rule-item="length" class="flex items-center gap-2 text-xs text-slate-500">
									<span data-password-rule-indicator class="h-1.5 w-1.5 rounded-full bg-slate-300" aria-hidden="true"></span>
									<span>At least 8 characters</span>
								</li>
								<li data-password-rule-item="uppercase" class="flex items-center gap-2 text-xs text-slate-500">
									<span data-password-rule-indicator class="h-1.5 w-1.5 rounded-full bg-slate-300" aria-hidden="true"></span>
									<span>At least one uppercase letter</span>
								</li>
								<li data-password-rule-item="lowercase" class="flex items-center gap-2 text-xs text-slate-500">
									<span data-password-rule-indicator class="h-1.5 w-1.5 rounded-full bg-slate-300" aria-hidden="true"></span>
									<span>At least one lowercase letter</span>
								</li>
								<li data-password-rule-item="number" class="flex items-center gap-2 text-xs text-slate-500">
									<span data-password-rule-indicator class="h-1.5 w-1.5 rounded-full bg-slate-300" aria-hidden="true"></span>
									<span>At least one number</span>
								</li>
							</ul>
						</div>

						<div>
							<x-forms.label for="password_confirmation" required>Confirm Password</x-forms.label>
							<div class="relative mt-2">
								<x-forms.input
									id="password_confirmation"
									name="password_confirmation"
									type="password"
									required
									autocomplete="new-password"
									placeholder="Re-enter your password"
									class="mt-0 pr-12"
								/>
								<button
									type="button"
									data-password-toggle
									data-password-target="password_confirmation"
									aria-label="Show password"
									title="Show password"
									class="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500/25"
								>
									<svg data-icon-eye-open xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
										<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
										<circle cx="12" cy="12" r="3" />
									</svg>
									<svg data-icon-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
										<path d="m3 3 18 18" />
										<path d="M10.477 10.488a3 3 0 0 0 4.242 4.242" />
										<path d="M9.88 5.09A10.94 10.94 0 0 1 12 4.875c5.523 0 10 3.358 10 7.5a6.87 6.87 0 0 1-1.333 3.895" />
										<path d="M6.61 6.61C4.149 7.97 2.5 10.044 2.5 12.375c0 4.142 4.477 7.5 10 7.5a10.93 10.93 0 0 0 4.84-1.13" />
									</svg>
								</button>
							</div>

							<x-forms.helper id="password-match-status" aria-live="polite">
								Enter matching passwords to continue.
							</x-forms.helper>
						</div>
					</section>

					<div class="pt-2">
						<x-ui.button type="submit" :full-width="true">Create Account</x-ui.button>
					</div>

					<p class="text-center text-sm text-slate-600">
						Already have an account?
						<a href="{{ route('login') }}" class="font-semibold text-sky-700 hover:underline">Login</a>
					</p>
				</form>
			</x-ui.card>
		</div>
	</x-layout.page-shell>
@endsection
