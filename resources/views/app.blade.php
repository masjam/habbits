<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Sistem Pantauan Habit') }}</title>

        <!-- PWA & Favicon Meta Tags -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#059669">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="GobitSDAM">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="192x192" href="/img/icon-192.png">
        <link rel="icon" type="image/png" sizes="512x512" href="/img/icon-512.png">
        <link rel="apple-touch-icon" sizes="192x192" href="/img/icon-192.png">
        <link rel="apple-touch-icon" sizes="512x512" href="/img/icon-512.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Amiri+Quran&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">

        <!-- Prevent Theme & Dark Mode FOUC -->
        <script>
            (function() {
                try {
                    const savedTheme = localStorage.getItem('app-theme');
                    if (savedTheme && savedTheme !== 'default') {
                        document.documentElement.classList.add(savedTheme);
                    }
                    const savedDark = localStorage.getItem('habit-dark-mode');
                    if (savedDark === 'true') {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        <!-- Scripts & Styles -->
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
