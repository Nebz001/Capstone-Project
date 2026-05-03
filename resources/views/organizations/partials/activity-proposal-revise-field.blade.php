@php
  $fk = (string) ($item['field_key'] ?? '');
  $baselineNatureKey = (is_array($requestForm?->nature_of_activity) && count($requestForm->nature_of_activity))
    ? (string) $requestForm->nature_of_activity[0]
    : '';
  $nOld = old('field_step1_nature_of_activity');
  $selectedNatureKey = is_array($nOld)
    ? (string) ($nOld[0] ?? '')
    : (string) ($nOld !== null ? $nOld : $baselineNatureKey);
  $baselineTypeKey = (is_array($requestForm?->activity_types) && count($requestForm->activity_types))
    ? (string) $requestForm->activity_types[0]
    : '';
  $tOld = old('field_step1_type_of_activity');
  $selectedTypeKey = is_array($tOld)
    ? (string) ($tOld[0] ?? '')
    : (string) ($tOld !== null ? $tOld : $baselineTypeKey);
  $budgetRowsDisplay = $budgetRowsDisplay ?? $budgetRowsPrefill ?? [];
  $budgetRowsOriginal = $budgetRowsOriginal ?? $budgetRowsDisplay;
  $propCol = match ($fk) {
    'step2_overall_goal' => 'overall_goal',
    'step2_specific_objectives' => 'specific_objectives',
    'step2_criteria_mechanics' => 'criteria_mechanics',
    'step2_program_flow' => 'program_flow',
    default => null,
  };
  $st = $proposal->proposed_start_time;
  $et = $proposal->proposed_end_time;
  $stStr = $st ? (\Illuminate\Support\Str::length((string) $st) <= 5 ? (string) $st : \Illuminate\Support\Carbon::parse($st)->format('H:i')) : '';
  $etStr = $et ? (\Illuminate\Support\Str::length((string) $et) <= 5 ? (string) $et : \Illuminate\Support\Carbon::parse($et)->format('H:i')) : '';
@endphp
<div class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-4" data-revision-field="{{ $fk }}">
  <p class="{{ $readonlyLabelClass }}">{{ $item['label'] ?? $fk }}</p>
  <p class="mt-1 text-sm text-slate-700"><span class="font-semibold text-slate-800">Current value:</span> {{ $item['current_display'] ?? '—' }}</p>
  @if (! empty($item['note']))
    <p class="{{ $noteClass }}"><span class="font-semibold">Revision note:</span> {{ $item['note'] }}</p>
  @endif

  @if ($fk === 'step1_nature_of_activity')
    <select name="field_step1_nature_of_activity" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required data-original-value="{{ $baselineNatureKey }}">
      @foreach ($natureOptionKeys as $opt)
        <option value="{{ $opt }}" @selected($selectedNatureKey === $opt)>{{ $natureLabels[$opt] ?? $opt }}</option>
      @endforeach
    </select>
  @elseif ($fk === 'step1_type_of_activity')
    <select name="field_step1_type_of_activity" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required data-original-value="{{ $baselineTypeKey }}">
      @foreach ($typeOptionKeys as $opt)
        <option value="{{ $opt }}" @selected($selectedTypeKey === $opt)>{{ $typeLabels[$opt] ?? $opt }}</option>
      @endforeach
    </select>
  @elseif ($fk === 'step1_activity_date')
    <x-forms.input class="mt-3" type="date" name="field_step1_activity_date" :value="old('field_step1_activity_date', optional($requestForm?->activity_date)->format('Y-m-d'))" required data-original-value="{{ optional($requestForm?->activity_date)->format('Y-m-d') ?? '' }}" />
  @elseif ($fk === 'step1_proposed_budget')
    @php
      $step1BudgetBaseline = $requestForm?->proposed_budget !== null
        ? number_format((float) $requestForm->proposed_budget, 2, '.', '')
        : '';
    @endphp
    <x-forms.input
      class="mt-3"
      type="number"
      step="any"
      min="0"
      name="field_step1_proposed_budget"
      :value="old('field_step1_proposed_budget', $step1BudgetBaseline)"
      required
      data-revision-money="1"
      data-original-value="{{ $step1BudgetBaseline }}"
    />
  @elseif ($fk === 'step1_budget_source')
    <select name="field_step1_budget_source" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required data-original-value="{{ (string) ($requestForm?->budget_source ?? '') }}">
      @foreach (['RSO Fund', 'RSO Savings', 'External'] as $opt)
        <option value="{{ $opt }}" @selected(old('field_step1_budget_source', $requestForm?->budget_source) === $opt)>{{ $opt }}</option>
      @endforeach
    </select>
  @elseif ($fk === 'step1_target_sdg')
    @php
      $baselineSdgRaw = $requestForm?->target_sdg ?? [];
      if (is_array($baselineSdgRaw)) {
        $baselineSdgList = array_values(array_filter($baselineSdgRaw, fn ($v) => is_string($v) && $v !== ''));
      } elseif (is_string($baselineSdgRaw) && trim($baselineSdgRaw) !== '') {
        $baselineSdgList = array_values(array_filter(array_map('trim', explode(',', $baselineSdgRaw))));
      } else {
        $baselineSdgList = [];
      }
      $originalSdgSignatureBaseline = collect($baselineSdgList)
        ->map(fn ($v) => trim((string) $v))
        ->filter()
        ->sort()
        ->values()
        ->implode('|');
      $rawSdg = old('field_step1_target_sdg', $baselineSdgList);
      if (is_array($rawSdg)) {
        $selectedTargetSdgsRev = array_values(array_filter($rawSdg, fn ($v) => is_string($v) && $v !== ''));
      } elseif (is_string($rawSdg) && trim($rawSdg) !== '') {
        $selectedTargetSdgsRev = array_values(array_filter(array_map('trim', explode(',', $rawSdg))));
      } else {
        $selectedTargetSdgsRev = [];
      }
    @endphp
    @include('organizations.partials.activity-proposal-target-sdg-multiselect-revise', [
      'selectedTargetSdgs' => $selectedTargetSdgsRev,
      'originalSdgSignature' => $originalSdgSignatureBaseline,
    ])
  @elseif (in_array($fk, ['step1_activity_title', 'step1_partner_entities', 'step1_venue'], true))
    <x-forms.input class="mt-3" name="field_{{ $fk }}" :value="old('field_'.$fk, match ($fk) {
        'step1_activity_title' => $requestForm?->activity_title,
        'step1_partner_entities' => $requestForm?->partner_entities,
        default => $requestForm?->venue,
    })" required data-original-value="{{ match ($fk) {
        'step1_activity_title' => (string) ($requestForm?->activity_title ?? ''),
        'step1_partner_entities' => (string) ($requestForm?->partner_entities ?? ''),
        default => (string) ($requestForm?->venue ?? ''),
    } }}" />
  @elseif ($fk === 'step2_department')
    <select name="field_step2_department" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required data-original-value="{{ (string) ($proposal->school_code ?? '') }}">
      @foreach ($schoolOptions as $code => $label)
        <option value="{{ $code }}" @selected(old('field_step2_department', $proposal->school_code) === $code)>{{ $label }}</option>
      @endforeach
    </select>
  @elseif ($fk === 'step2_program')
    <x-forms.input class="mt-3" name="field_step2_program" :value="old('field_step2_program', $proposal->program)" required data-original-value="{{ (string) ($proposal->program ?? '') }}" />
  @elseif ($fk === 'step2_activity_title')
    <x-forms.input class="mt-3" name="field_step2_activity_title" :value="old('field_step2_activity_title', $proposal->activity_title)" required data-original-value="{{ (string) ($proposal->activity_title ?? '') }}" />
  @elseif ($fk === 'step2_proposed_dates')
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
      <x-forms.input type="date" name="field_step2_proposed_start" label="Start date" :value="old('field_step2_proposed_start', optional($proposal->proposed_start_date)->format('Y-m-d'))" required data-original-value="{{ optional($proposal->proposed_start_date)->format('Y-m-d') ?? '' }}" />
      <x-forms.input type="date" name="field_step2_proposed_end" label="End date" :value="old('field_step2_proposed_end', optional($proposal->proposed_end_date)->format('Y-m-d'))" required data-original-value="{{ optional($proposal->proposed_end_date)->format('Y-m-d') ?? '' }}" />
    </div>
  @elseif ($fk === 'step2_proposed_time')
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
      <x-forms.input type="time" name="field_step2_proposed_start_time" label="Start time" :value="old('field_step2_proposed_start_time', $stStr)" required data-original-value="{{ $stStr }}" />
      <x-forms.input type="time" name="field_step2_proposed_end_time" label="End time" :value="old('field_step2_proposed_end_time', $etStr)" required data-original-value="{{ $etStr }}" />
    </div>
  @elseif ($fk === 'step2_venue')
    <x-forms.input class="mt-3" name="field_step2_venue" :value="old('field_step2_venue', $proposal->venue)" required data-original-value="{{ (string) ($proposal->venue ?? '') }}" />
  @elseif ($fk === 'step2_budget_total')
    <x-forms.input class="mt-3" type="number" step="0.01" name="field_step2_budget_total" :value="old('field_step2_budget_total', $proposal->estimated_budget)" required data-original-value="{{ $proposal->estimated_budget !== null ? (string) $proposal->estimated_budget : '' }}" />
  @elseif ($fk === 'step2_source_of_funding')
    <select name="field_step2_source_of_funding" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required data-original-value="{{ (string) ($proposal->source_of_funding ?? '') }}">
      @foreach (['RSO Fund', 'RSO Savings', 'External'] as $opt)
        <option value="{{ $opt }}" @selected(old('field_step2_source_of_funding', $proposal->source_of_funding) === $opt)>{{ $opt }}</option>
      @endforeach
    </select>
  @elseif ($fk === 'step2_budget_table')
    <p class="mt-2 text-xs text-slate-600">Each row total equals quantity × unit price. All rows must sum to the current proposed budget total.</p>
    <div id="budget-rows-root" class="mt-3 space-y-3" data-original-budget="{{ e(json_encode($budgetRowsOriginal)) }}">
      @foreach ($budgetRowsDisplay as $br)
        <div class="budget-row grid gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-4">
          <label class="block text-xs font-semibold text-slate-600">Material
            <input type="text" class="budget-material mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" value="{{ $br['material'] ?? '' }}" required />
          </label>
          <label class="block text-xs font-semibold text-slate-600">Qty
            <input type="number" step="0.01" class="budget-qty mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" value="{{ $br['quantity'] ?? '' }}" required />
          </label>
          <label class="block text-xs font-semibold text-slate-600">Unit price
            <input type="number" step="0.01" class="budget-unit mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" value="{{ $br['unit_price'] ?? '' }}" required />
          </label>
          <label class="block text-xs font-semibold text-slate-600">Price
            <input type="number" step="0.01" class="budget-price mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm" value="{{ $br['price'] ?? '' }}" readonly />
          </label>
        </div>
      @endforeach
    </div>
    <input type="hidden" name="field_step2_budget_items_payload" id="field_step2_budget_items_payload" value="{{ old('field_step2_budget_items_payload', '') }}">
  @elseif ($fk === 'step2_submitted')
    <x-forms.input class="mt-3" type="date" name="field_step2_submitted" :value="old('field_step2_submitted', optional($proposal->submission_date)->format('Y-m-d'))" required data-original-value="{{ optional($proposal->submission_date)->format('Y-m-d') ?? '' }}" />
  @elseif ($propCol)
    <x-forms.textarea class="mt-3" name="field_{{ $fk }}" rows="5" required data-revision-input data-field-key="{{ $fk }}" data-original-value="{{ (string) ($proposal->{$propCol} ?? '') }}">{{ old('field_'.$fk, $proposal->{$propCol} ?? '') }}</x-forms.textarea>
  @else
    <x-forms.textarea class="mt-3" name="field_{{ $fk }}" rows="3" required placeholder="Enter the updated value" data-original-value="">{{ old('field_'.$fk) }}</x-forms.textarea>
  @endif

  <p class="mt-2 hidden text-xs font-bold uppercase tracking-wide text-emerald-700" data-updated-badge>Updated</p>
</div>
