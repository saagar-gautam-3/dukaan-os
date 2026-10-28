<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Dukaan OS') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="text-center py-5">
        <h1 class="display-5 fw-bold mb-2" style="color: #1a3a5c;">
            <i class="bi bi-shop me-2"></i>Dukaan OS
        </h1>
        <p class="text-muted mb-4">Multi-tenant SaaS for Nepali shops &mdash; Laravel {{ app()->version() }}</p>
        @if (Route::has('login'))
            <div class="d-flex gap-3 justify-content-center">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary">Log in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
                    @endif
                @endauth
            </div>
        @endif
    </div>
</body>
</html>
