<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        @php
            $siteIcon = \App\Support\SharedUiCache::appSettings()['site_icon'] ?? null;
            $siteIconHref = is_string($siteIcon) && trim($siteIcon) !== ''
                ? (\Illuminate\Support\Str::startsWith($siteIcon, ['http://', 'https://', '/'])
                    ? $siteIcon
                    : url('/media-files/' . ltrim($siteIcon, '/')))
                : null;
        @endphp
        @if ($siteIconHref)
            <link rel="icon" href="{{ $siteIconHref }}">
            <link rel="apple-touch-icon" href="{{ $siteIconHref }}">
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@200;300;400;500;700;800;900&display=swap" rel="stylesheet">

        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
