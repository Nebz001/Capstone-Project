@extends('layouts.admin')

@section('title', ($mode === 'create' ? 'Create' : 'Edit') . ' announcement — NU Lipa SDAO')

@section('content')
@php
  $isEdit = $mode === 'edit';
@endphp

<form
  method="POST"
  action="{{ $isEdit ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}"
  enctype="multipart/form-data"
  class="max-w-2xl"
>
  @csrf
  @if ($isEdit)
    @method('PUT')
  @endif

  <x-ui.card padding="p-0">
    <div class="space-y-6 p-5 sm:p-7 lg:p-8">
      <div>
        <x-forms.label for="title" :required="true">Title</x-forms.label>
        <x-forms.input
          id="title"
          name="title"
          :value="old('title', $announcement->title)"
          required
        />
        @error('title')
          <x-forms.error>{{ $message }}</x-forms.error>
        @enderror
      </div>

      <div>
        <x-forms.label for="body">Message (optional)</x-forms.label>
        <x-forms.textarea id="body" name="body" :rows="5">{{ old('body', $announcement->body) }}</x-forms.textarea>
        @error('body')
          <x-forms.error>{{ $message }}</x-forms.error>
        @enderror
      </div>

      <div>
        <x-forms.label for="image">Poster image (optional)</x-forms.label>
        <x-forms.helper>JPEG, PNG, WebP, or GIF. Max 4&nbsp;MB.</x-forms.helper>
        @if ($isEdit && $announcement->image_path)
          <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
            <img
              src="{{ $announcement->imagePublicUrl() }}"
              alt=""
              class="max-h-80 w-full object-contain"
            />
          </div>
        @endif
        <input
          type="file"
          name="image"
          id="image"
          accept="image/jpeg,image/png,image/webp,image/gif"
          class="mt-2 block w-full text-sm text-slate-600 file:mr-0 file:rounded-xl file:border-0 file:bg-[#003E9F] file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:bg-[#00327F]"
        />
        @error('image')
          <x-forms.error>{{ $message }}</x-forms.error>
        @enderror
      </div>

      <div class="grid gap-6 sm:grid-cols-2">
        <div>
          <x-forms.label for="link_url">Link URL (optional)</x-forms.label>
          <x-forms.input
            id="link_url"
            name="link_url"
            type="url"
            :value="old('link_url', $announcement->link_url)"
            placeholder="https://"
          />
          @error('link_url')
            <x-forms.error>{{ $message }}</x-forms.error>
          @enderror
        </div>
        <div>
          <x-forms.label for="link_label">Link label (optional)</x-forms.label>
          <x-forms.input
            id="link_label"
            name="link_label"
            :value="old('link_label', $announcement->link_label)"
            placeholder="Learn more"
          />
          @error('link_label')
            <x-forms.error>{{ $message }}</x-forms.error>
          @enderror
        </div>
      </div>

      <div class="grid gap-6 sm:grid-cols-2">
        <div>
          <x-forms.label for="status" :required="true">Status</x-forms.label>
          <x-forms.select id="status" name="status">
            <option value="ACTIVE" @selected(old('status', $announcement->status) === 'ACTIVE')>ACTIVE</option>
            <option value="INACTIVE" @selected(old('status', $announcement->status) === 'INACTIVE')>INACTIVE</option>
          </x-forms.select>
          @error('status')
            <x-forms.error>{{ $message }}</x-forms.error>
          @enderror
        </div>
        <div>
          <x-forms.label for="sort_order">Display order</x-forms.label>
          <x-forms.input
            id="sort_order"
            name="sort_order"
            type="number"
            min="0"
            :value="old('sort_order', $announcement->sort_order ?? 0)"
          />
          <x-forms.helper>Lower numbers appear first.</x-forms.helper>
          @error('sort_order')
            <x-forms.error>{{ $message }}</x-forms.error>
          @enderror
        </div>
      </div>

      <div class="grid gap-6 sm:grid-cols-2">
        <div>
          <x-forms.label for="starts_at">Starts at (optional)</x-forms.label>
          <x-forms.input
            id="starts_at"
            name="starts_at"
            type="datetime-local"
            :value="old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i'))"
          />
          @error('starts_at')
            <x-forms.error>{{ $message }}</x-forms.error>
          @enderror
        </div>
        <div>
          <x-forms.label for="ends_at">Ends at (optional)</x-forms.label>
          <x-forms.input
            id="ends_at"
            name="ends_at"
            type="datetime-local"
            :value="old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i'))"
          />
          @error('ends_at')
            <x-forms.error>{{ $message }}</x-forms.error>
          @enderror
        </div>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-5 py-4 sm:px-7 lg:px-8">
      <a
        href="{{ route('admin.announcements.index') }}"
        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20"
      >
        Cancel
      </a>
      <button
        type="submit"
        class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40"
      >
        {{ $isEdit ? 'Save changes' : 'Create announcement' }}
      </button>
    </div>
  </x-ui.card>
</form>
@endsection
