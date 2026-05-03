@extends('layouts.organization-portal')

@section('title', $pageTitle.' — NU Lipa SDAO')

@section('content')
@php
  $readonlyLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500';
  $noteClass = 'mt-2 rounded-xl border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold leading-relaxed text-yellow-900';
@endphp
<div class="mx-auto max-w-screen-lg px-4 py-8 sm:px-6 lg:px-10">
  <header class="mb-6">
    <a href="{{ $backRoute }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
      <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
      </svg>
      {{ $backLabel }}
    </a>
    <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">{{ $pageTitle }}</h1>
    @if (! empty($subtitle))
      <p class="mt-1 text-sm text-slate-600">{{ $subtitle }}</p>
    @endif
  </header>

  @if ($errors->any())
    <x-feedback.blocked-message variant="warning" class="mb-5">
      <ul class="list-inside list-disc text-sm">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </x-feedback.blocked-message>
    @if (! empty($revisionFiles))
      <x-feedback.blocked-message variant="warning" class="mb-5">
        <p class="text-sm">Replacement files cannot be kept after a failed submit. Please choose each file again if a new upload is required.</p>
      </x-feedback.blocked-message>
    @endif
  @endif

  <form id="activity-proposal-revise-form" method="POST" action="{{ $reviseSubmitUrl }}" enctype="multipart/form-data" class="space-y-8">
    @csrf
    @method('PUT')
    <input type="hidden" name="from" value="{{ $fromSource }}">

    @if (! empty($revisionStep1))
      <x-ui.card padding="p-0" class="border-slate-200">
        <x-ui.card-section-header title="Step 1: Activity Request Form" subtitle="Update each flagged field below." content-padding="px-6" />
        <div class="border-t border-slate-100 px-6 py-4.5 space-y-6">
          @foreach ($revisionStep1 as $item)
            @include('organizations.partials.activity-proposal-revise-field', [
              'item' => $item,
              'proposal' => $proposal,
              'requestForm' => $requestForm,
              'natureOptionKeys' => $natureOptionKeys,
              'typeOptionKeys' => $typeOptionKeys,
              'natureLabels' => $natureLabels,
              'typeLabels' => $typeLabels,
              'schoolOptions' => $schoolOptions,
              'budgetRowsDisplay' => $budgetRowsDisplay,
              'budgetRowsOriginal' => $budgetRowsOriginal,
              'readonlyLabelClass' => $readonlyLabelClass,
              'noteClass' => $noteClass,
            ])
          @endforeach
        </div>
      </x-ui.card>
    @endif

    @if (! empty($revisionStep2))
      <x-ui.card padding="p-0" class="border-slate-200">
        <x-ui.card-section-header title="Step 2: Proposal Submission" subtitle="Update each flagged field below." content-padding="px-6" />
        <div class="border-t border-slate-100 px-6 py-4.5 space-y-6">
          @foreach ($revisionStep2 as $item)
            @include('organizations.partials.activity-proposal-revise-field', [
              'item' => $item,
              'proposal' => $proposal,
              'requestForm' => $requestForm,
              'natureOptionKeys' => $natureOptionKeys,
              'typeOptionKeys' => $typeOptionKeys,
              'natureLabels' => $natureLabels,
              'typeLabels' => $typeLabels,
              'schoolOptions' => $schoolOptions,
              'budgetRowsDisplay' => $budgetRowsDisplay,
              'budgetRowsOriginal' => $budgetRowsOriginal,
              'readonlyLabelClass' => $readonlyLabelClass,
              'noteClass' => $noteClass,
            ])
          @endforeach
        </div>
      </x-ui.card>
    @endif

    @if (! empty($revisionFiles))
      <x-ui.card padding="p-0" class="border-slate-200">
        <x-ui.card-section-header title="Submitted files / Attached documents" subtitle="Upload a replacement for each file noted below." content-padding="px-6" />
        <div class="border-t border-slate-100 px-6 py-4.5 space-y-6">
          @foreach ($revisionFiles as $item)
            @php $ok = (string) ($item['officer_file_key'] ?? ''); @endphp
            <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-4" data-revision-file="{{ $ok }}">
              <p class="{{ $readonlyLabelClass }}">{{ $item['label'] ?? $ok }}</p>
              <p class="mt-1 text-sm text-slate-700"><span class="font-semibold text-slate-800">Current file:</span> {{ $item['current_file_name'] ?? '—' }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ $item['file_view_url'] ?? '#' }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#003E9F] hover:bg-[#003E9F]/5">View file</a>
                <a href="{{ $item['file_download_url'] ?? '#' }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#003E9F] hover:bg-[#003E9F]/5">Download</a>
              </div>
              @if (! empty($item['note']))
                <p class="{{ $noteClass }}"><span class="font-semibold">Revision note:</span> {{ $item['note'] }}</p>
              @endif
              <input type="file" name="file_{{ $ok }}" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="mt-3 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-[#003E9F] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white" data-file-replace="{{ $ok }}" data-revision-required="1" />
              <p class="mt-2 hidden text-xs font-bold uppercase tracking-wide text-emerald-700" data-new-file-badge>New file selected</p>
            </div>
          @endforeach
        </div>
      </x-ui.card>
    @endif

    <div class="flex flex-wrap items-center gap-3">
      <button type="submit" id="proposal-revision-submit" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00327F] disabled:cursor-not-allowed disabled:opacity-55" disabled>
        Submit Revisions
      </button>
      <p class="text-xs text-slate-600">Complete every item above to enable submission.</p>
    </div>
  </form>
</div>
@endsection

@section('scripts')
  <script>
    (() => {
      const form = document.getElementById('activity-proposal-revise-form');
      const submitBtn = document.getElementById('proposal-revision-submit');
      if (!form || !submitBtn) return;

      const budgetPayload = document.getElementById('field_step2_budget_items_payload');
      const recalcBudgetRow = (row) => {
        const q = parseFloat(row.querySelector('.budget-qty')?.value || '0');
        const u = parseFloat(row.querySelector('.budget-unit')?.value || '0');
        const price = row.querySelector('.budget-price');
        if (price) price.value = (Math.round(q * u * 100) / 100).toFixed(2);
      };
      const serializeBudget = () => {
        const root = document.getElementById('budget-rows-root');
        if (!root || !budgetPayload) return;
        const rows = [];
        root.querySelectorAll('.budget-row').forEach((row) => {
          rows.push({
            material: row.querySelector('.budget-material')?.value || '',
            quantity: row.querySelector('.budget-qty')?.value || '0',
            unit_price: row.querySelector('.budget-unit')?.value || '0',
            price: row.querySelector('.budget-price')?.value || '0',
          });
        });
        budgetPayload.value = JSON.stringify(rows);
      };

      document.getElementById('budget-rows-root')?.addEventListener('input', (e) => {
        const row = e.target.closest('.budget-row');
        if (row) recalcBudgetRow(row);
        serializeBudget();
      });
      serializeBudget();

      const norm = (v) => String(v ?? '').replace(/\r\n/g, '\n').trim();
      const normalizeMoney = (value) => {
        const s = String(value ?? '').replace(/,/g, '').trim();
        if (s === '') return '';
        const n = Number(s);
        if (Number.isNaN(n)) return s;
        return n.toFixed(2);
      };
      const reviseSdgRoot = document.querySelector('[data-revision-sdg-root]');
      const syncReviseSdgUi = () => {
        if (!reviseSdgRoot) return;
        const triggerText = reviseSdgRoot.querySelector('#revise-target-sdg-trigger-text');
        const wrap = reviseSdgRoot.querySelector('#revise-target-sdg-selected-wrap');
        const list = reviseSdgRoot.querySelector('#revise-target-sdg-selected-list');
        const boxes = Array.from(reviseSdgRoot.querySelectorAll('.revise-sdg-checkbox'));
        const selected = boxes.filter((c) => c.checked).map((c) => c.value);
        if (wrap) wrap.classList.toggle('hidden', selected.length === 0);
        if (list) {
          list.innerHTML = selected
            .map((sdg) => `<span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">${sdg}</span>`)
            .join('');
        }
        if (triggerText) {
          triggerText.textContent = selected.length > 0 ? selected.join(', ') : 'Select one or more SDGs';
          triggerText.classList.toggle('text-slate-500', selected.length === 0);
          triggerText.classList.toggle('text-slate-900', selected.length > 0);
        }
      };
      if (reviseSdgRoot) {
        const menu = reviseSdgRoot.querySelector('#revise-target-sdg-menu');
        const trigger = reviseSdgRoot.querySelector('#revise-target-sdg-trigger');
        const dropdown = reviseSdgRoot.querySelector('[data-revise-sdg-dropdown]');
        if (trigger && menu && dropdown) {
          trigger.addEventListener('click', () => {
            const isOpen = !menu.classList.contains('hidden');
            menu.classList.toggle('hidden', isOpen);
            trigger.setAttribute('aria-expanded', String(!isOpen));
          });
          document.addEventListener('click', (event) => {
            if (dropdown.contains(event.target)) return;
            menu.classList.add('hidden');
            trigger.setAttribute('aria-expanded', 'false');
          });
        }
        syncReviseSdgUi();
      }

      const fieldCardChanged = (card) => {
        const sdgInner = card.querySelector('[data-revision-sdg-root]');
        if (sdgInner) {
          const orig = norm(sdgInner.dataset.originalSdgSignature || '');
          const cur = Array.from(sdgInner.querySelectorAll('.revise-sdg-checkbox:checked'))
            .map((b) => norm(b.value))
            .filter((v) => v !== '')
            .sort()
            .join('|');
          if (cur === '') return false;
          return cur !== orig;
        }
        const budgetRoot = card.querySelector('#budget-rows-root');
        if (budgetRoot && budgetRoot.dataset.originalBudget) {
          try {
            const cur = [];
            budgetRoot.querySelectorAll('.budget-row').forEach((row) => {
              cur.push({
                material: norm(row.querySelector('.budget-material')?.value),
                quantity: norm(row.querySelector('.budget-qty')?.value),
                unit_price: norm(row.querySelector('.budget-unit')?.value),
                price: norm(row.querySelector('.budget-price')?.value),
              });
            });
            return JSON.stringify(cur) !== budgetRoot.dataset.originalBudget;
          } catch (_e) {
            return false;
          }
        }
        const tracked = card.querySelectorAll('[data-original-value]');
        if (!tracked.length) return false;
        for (const el of tracked) {
          const isTrackedControl =
            el instanceof HTMLSelectElement
            || el instanceof HTMLTextAreaElement
            || (el instanceof HTMLInputElement && el.type !== 'radio' && el.type !== 'checkbox');
          if (!isTrackedControl) continue;
          if (el instanceof HTMLInputElement && el.dataset.revisionMoney === '1') {
            if (normalizeMoney(el.value) !== normalizeMoney(el.dataset.originalValue)) return true;
            continue;
          }
          const want = norm(el.dataset.originalValue);
          if (norm(el.value) !== want) return true;
        }
        return false;
      };

      const refresh = () => {
        let ok = true;
        form.querySelectorAll('[data-revision-field]').forEach((card) => {
          const changed = fieldCardChanged(card);
          if (!changed) ok = false;
          const badge = card.querySelector('[data-updated-badge]');
          if (badge) badge.classList.toggle('hidden', !changed);
        });
        form.querySelectorAll('[data-file-replace][data-revision-required]').forEach((inp) => {
          if (!inp.files || inp.files.length === 0) ok = false;
        });
        submitBtn.disabled = !ok;
      };

      form.querySelectorAll('input, textarea, select').forEach((el) => {
        el.addEventListener('input', () => {
          refresh();
        });
        el.addEventListener('change', () => {
          if (el.classList && el.classList.contains('revise-sdg-checkbox')) {
            syncReviseSdgUi();
          }
          refresh();
          const wrap = el.closest('[data-revision-file]');
          if (wrap && el.type === 'file') {
            const b = wrap.querySelector('[data-new-file-badge]');
            if (b) b.classList.toggle('hidden', !(el.files && el.files.length));
          }
        });
      });

      form.addEventListener('submit', () => {
        serializeBudget();
      });

      refresh();
    })();
  </script>
@endsection
