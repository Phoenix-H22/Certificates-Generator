<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/public.css') }}">
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
