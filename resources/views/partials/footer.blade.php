<footer class="bg-[#001229] text-white">

  {{-- CTA Banner --}}
  <div class="mx-auto max-w-7xl px-4 pt-12 sm:px-6 lg:px-8">
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#003E9F] via-[#0047B8] to-[#002266] px-8 py-10 shadow-xl sm:px-10">

      {{-- Subtle grid overlay --}}
      <div class="pointer-events-none absolute inset-0 opacity-[0.04]" aria-hidden="true"
        style="background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 40px 40px;">
      </div>

      <div class="relative z-10 grid gap-8 lg:grid-cols-2 lg:items-center">
        <div>
          <h2 class="text-2xl font-bold leading-tight text-white sm:text-3xl">
            Manage organization documents with clarity and speed
          </h2>
          <p class="mt-3 max-w-md text-sm leading-6 text-blue-100/80">
            Centralized submissions, approvals, and activity documentation — all managed through one official institutional portal.
          </p>
          <a
            href="{{ route('login') }}"
            class="mt-6 inline-flex items-center justify-center rounded-xl bg-[#F5C400] px-6 py-2.5 text-sm font-bold text-[#001A4D] shadow-lg transition hover:bg-[#E6B800] focus:outline-none focus:ring-4 focus:ring-[#F5C400]/30">
            Access Portal
          </a>
        </div>

        <div class="flex justify-center lg:justify-end">
          <img
            src="{{ asset('images/logos/nu-bulldog.png') }}"
            alt="NU Bulldog Mascot"
            class="h-44 w-auto object-contain drop-shadow-xl">
        </div>
      </div>
    </div>
  </div>

  {{-- Institutional Info Columns --}}
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#F5C400]">Office</p>
        <p class="mt-2 text-sm font-medium text-white">SDAO Office</p>
      </div>
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#F5C400]">Campus</p>
        <p class="mt-2 text-sm font-medium text-white">National University – Lipa</p>
      </div>
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#F5C400]">Contact</p>
        <p class="mt-2 text-sm font-medium text-white">sdao@nu-lipa.edu.ph</p>
      </div>
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#F5C400]">Office Hours</p>
        <p class="mt-2 text-sm font-medium text-white">Mon–Fri, 8:00 AM – 5:00 PM</p>
      </div>
    </div>

    {{-- Quick Links + Copyright --}}
    <div class="mt-8 flex flex-col gap-4 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
      <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-blue-200" aria-label="Footer navigation">
        <a href="{{ route('login') }}" class="transition hover:text-white">Login</a>
        <a href="{{ route('register-organization') }}" class="transition hover:text-white">Register Organization</a>
        <a href="#services" class="transition hover:text-white">Portal Services</a>
        <a href="#about" class="transition hover:text-white">About the System</a>
      </nav>
      <p class="text-xs text-blue-300/70">
        &copy; {{ date('Y') }} National University – Lipa. All rights reserved.
      </p>
    </div>
  </div>

</footer>