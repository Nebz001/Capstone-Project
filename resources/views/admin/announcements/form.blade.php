@extends('layouts.admin')

@section('title', ($mode === 'create' ? 'Create' : 'Edit') . ' announcement — NU Lipa SDAO')

@section('content')
@php
  $isEdit = $mode === 'edit';
@endphp

<header class="mb-6">
  <a href="{{ route('admin.announcements.index') }}" class="text-xs font-semibold text-[#003E9F] hover:text-[#00327F]">← Back to announcements</a>
  <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
    {{ $isEdit ? 'Edit announcement' : 'New announcement' }}
  </h1>
  <p class="mt-1 text-sm text-slate-500">Set schedule, visibility, and optional poster image.</p>
</header>

<form
  method="POST"
  action="{{ $isEdit ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}"
  enctype="multipart/form-data"
  class="max-w-2xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
>
  @csrf
  @if ($isEdit)
    @method('PUT')
  @endif

  <div>
    <label for="title" class="block text-sm font-semibold text-slate-700">Title</label>
    <input
      type="text"
      name="title"
      id="title"
      value="{{ old('title', $announcement->title) }}"
      required
      class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
    />
    @error('title')
      <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
    @enderror
  </div>

  <div>
    <label for="body" class="block text-sm font-semibold text-slate-700">Message (optional)</label>
    <textarea
      name="body"
      id="body"
      rows="5"
      class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
    >{{ old('body', $announcement->body) }}</textarea>
    @error('body')
      <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
    @enderror
  </div>

  <div>
    <label for="image" class="block text-sm font-semibold text-slate-700">Poster image (optional)</label>
    <p class="mt-0.5 text-xs text-slate-500">JPEG, PNG, WebP, or GIF. Max 4&nbsp;MB.</p>
    @if ($isEdit && $announcement->image_path)
      <div class="mt-2 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
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
      class="mt-2 block w-full text-sm text-slate-600 file:mr-0 file:rounded-lg file:border-0 file:bg-[#003E9F] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[#00327F]"
    />
    @error('image')
      <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
    @enderror
  </div>

  <div class="grid gap-4 sm:grid-cols-2">
    <div>
      <label for="link_url" class="block text-sm font-semibold text-slate-700">Link URL (optional)</label>
      <input
        type="url"
        name="link_url"
        id="link_url"
        value="{{ old('link_url', $announcement->link_url) }}"
        placeholder="https://"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
      />
      @error('link_url')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
    <div>
      <label for="link_label" class="block text-sm font-semibold text-slate-700">Link label (optional)</label>
      <input
        type="text"
        name="link_label"
        id="link_label"
        value="{{ old('link_label', $announcement->link_label) }}"
        placeholder="Learn more"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
      />
      @error('link_label')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
  </div>

  <div class="grid gap-4 sm:grid-cols-2">
    <div>
      <label for="status" class="block text-sm font-semibold text-slate-700">Status</label>
      <select
        name="status"
        id="status"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
      >
        <option value="ACTIVE" @selected(old('status', $announcement->status) === 'ACTIVE')>ACTIVE</option>
        <option value="INACTIVE" @selected(old('status', $announcement->status) === 'INACTIVE')>INACTIVE</option>
      </select>
      @error('status')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
    <div>
      <label for="sort_order" class="block text-sm font-semibold text-slate-700">Display order</label>
      <input
        type="number"
        name="sort_order"
        id="sort_order"
        min="0"
        value="{{ old('sort_order', $announcement->sort_order ?? 0) }}"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
      />
      <p class="mt-1 text-xs text-slate-500">Lower numbers appear first.</p>
      @error('sort_order')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
  </div>

  <div class="grid gap-4 sm:grid-cols-2">
    <div>
      <label for="starts_at" class="block text-sm font-semibold text-slate-700">Starts at (optional)</label>
      <input
        type="datetime-local"
        name="starts_at"
        id="starts_at"
        value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
      />
      @error('starts_at')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
    <div>
      <label for="ends_at" class="block text-sm font-semibold text-slate-700">Ends at (optional)</label>
      <input
        type="datetime-local"
        name="ends_at"
        id="ends_at"
        value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
      />
      @error('ends_at')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
  </div>

  <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
    <a
      href="{{ route('admin.announcements.index') }}"
      class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
    >
      Cancel
    </a>
    <button
      type="submit"
      class="rounded-lg bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#00327F]"
    >
      {{ $isEdit ? 'Save changes' : 'Create announcement' }}
    </button>
  </div>
</form>
@endsection
