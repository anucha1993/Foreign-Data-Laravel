@props(['title', 'company'])
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#01ce55">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
<title>{{ $title }} · Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/foreign.css') }}?v={{ @filemtime(public_path('css/foreign.css')) }}">
</head>
<body class="admin">
<header class="topbar"><div class="topbar-in">
  <a class="brand" href="{{ route('admin.tickets.index') }}">
    <img src="{{ asset('logo-192.png') }}" alt="{{ $company['name'] }}" width="42" height="42">
    <span><b>{{ $company['name'] }}</b><small>Admin · Ticket</small></span>
  </a>
  @auth
  <div class="tools">
    <span class="admin-user">{{ auth()->user()->name }}</span>
    <form method="post" action="{{ route('admin.logout') }}">@csrf
      <button class="btn sm ghost" type="submit">ออกจากระบบ</button>
    </form>
  </div>
  @endauth
</div></header>
<main class="page">
{{ $slot }}
</main>
</body>
</html>
