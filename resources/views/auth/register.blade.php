@extends('layouts.app')

@section('title', 'Student Registration')

@section('content')
	<x-layout.page-shell>
		<div class="mx-auto w-full max-w-3xl">
			<x-ui.card>
				<x-ui.section-header
					title="Registration Details"
					subtitle="All fields below are for account setup only."
				/>

				<form id="student-register-form" method="POST" action="" class="mt-6 space-y-7 sm:mt-7">
					@csrf

					<section class="space-y-5">
						<h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Student Information</h3>

						<div>
							<x-forms.label for="school_email" required>School Email</x-forms.label>
							<x-forms.input
								id="school_email"
								name="school_email"
								type="email"
								required
								autocomplete="email"
								placeholder="tanbm@students.nu-lipa.edu.ph"
							/>
							<x-forms.helper>Use your official NU Lipa student email only.</x-forms.helper>
						</div>

						<div>
							<x-forms.label for="school-id-segment-0">School ID</x-forms.label>
							<x-forms.helper class="mt-1">Format preview: 2023-182854</x-forms.helper>

							<input id="school_id" name="school_id" type="hidden" value="" />

							<div
								id="school-id-group"
								class="mt-3 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-3 sm:gap-2.5 sm:p-4"
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

								<span class="mx-1 text-base font-semibold text-slate-500 sm:text-lg" aria-hidden="true">-</span>

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

					<section class="space-y-5 border-t border-slate-100 pt-6">
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
									class="mt-0 pr-20"
								/>
								<x-ui.button
									type="button"
									variant="secondary"
									data-password-toggle
									data-password-target="password"
									class="absolute inset-y-0 right-0 my-2 mr-2 rounded-lg px-3 py-1.5 text-xs"
								>
									Show
								</x-ui.button>
							</div>
							<x-forms.helper>Use at least 8 characters with a mix of letters and numbers.</x-forms.helper>
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
									class="mt-0 pr-20"
								/>
								<x-ui.button
									type="button"
									variant="secondary"
									data-password-toggle
									data-password-target="password_confirmation"
									class="absolute inset-y-0 right-0 my-2 mr-2 rounded-lg px-3 py-1.5 text-xs"
								>
									Show
								</x-ui.button>
							</div>

							<x-forms.helper id="password-match-status" aria-live="polite">
								Enter matching passwords to continue.
							</x-forms.helper>
						</div>
					</section>

					<div class="pt-2">
						<x-ui.button type="submit" :full-width="true">Create Account</x-ui.button>
					</div>
				</form>
			</x-ui.card>
		</div>
	</x-layout.page-shell>
@endsection
