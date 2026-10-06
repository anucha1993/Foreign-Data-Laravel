@props(['title', 'company', 'tools' => null])
@php($extraFont = \App\Support\Locales::font(app()->getLocale()))
<!doctype html>
<html lang="{{ app()->getLocale() }}"@if($extraFont) style="--font-extra:'{{ $extraFont }}',"@endif>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#01ce55">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('logo-192.png') }}">
<title>{{ $title }} · {{ $company['name'] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700{{ $extraFont ? '&family='.str_replace(' ', '+', $extraFont).':wght@400;500;600;700' : '' }}&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/foreign.css') }}?v={{ @filemtime(public_path('css/foreign.css')) }}">
</head>
<body>
<header class="topbar"><div class="topbar-in">
  <a class="brand" href="{{ route('foreign.profile') }}">
    <img src="{{ asset('logo-192.png') }}" alt="{{ $company['name'] }}" width="42" height="42">
    <span><b>{{ $company['name'] }}</b><small>{{ __('ระบบข้อมูลแรงงาน') }}</small></span>
  </a>
  <div class="tools">
    <details class="lang">
      <summary aria-label="Language / {{ __('ภาษา') }}"><span aria-hidden="true">🌐</span> {{ \App\Support\Locales::label(app()->getLocale()) }}</summary>
      <nav class="lang-menu" aria-label="Language">
        @foreach(\App\Support\Locales::SUPPORTED as $code => [$label])
          <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" hreflang="{{ $code }}" @if(app()->getLocale() === $code) class="on" aria-current="true"@endif>{{ $label }}</a>
        @endforeach
      </nav>
    </details>
    {{ $tools }}
  </div>
</div></header>
<main class="page">
{{ $slot }}
</main>
<footer class="foot">© {{ date('Y') }} {{ $company['name'] }}</footer>
<script>
document.addEventListener('click', function (e) {
  document.querySelectorAll('details.lang[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
});
</script>
</body>
</html>
