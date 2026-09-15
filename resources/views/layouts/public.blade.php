<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', config('app.name'))</title>
    @php $cssPath = public_path('css/public.css'); @endphp
    <link rel="stylesheet" href="{{ asset('css/public.css') }}?v={{ is_file($cssPath) ? filemtime($cssPath) : '1' }}">
</head>
<body>
<main class="page">
    @yield('content')
</main>
<footer class="footer">
    <span>{{ config('app.name') }}</span>
</footer>
</body>
</html>
