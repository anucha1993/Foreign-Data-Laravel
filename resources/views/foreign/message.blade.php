<x-layout :title="$title" :company="$company">
<section class="card msg-page">
  <h1>{{ $title }}</h1>
  <p>{{ $message }}</p>
  <a class="btn" href="{{ route('foreign.login') }}">{{ __('กลับไปหน้าเข้าสู่ระบบ') }}</a>
</section>
</x-layout>
