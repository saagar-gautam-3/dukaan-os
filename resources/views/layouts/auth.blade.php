<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Dukaan OS'))</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light d-flex flex-column min-vh-100 justify-content-center py-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                <div class="text-center mb-4">
                    <a href="{{ route('home') }}" class="text-decoration-none text-dark">
                        <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                            <i class="bi bi-shop me-2"></i>{{ __('common.app_name') }}
                        </h2>
                    </a>
                    <p class="text-muted small">{{ __('common.tagline') }}</p>

                    <!-- Language Switcher -->
                    <div class="btn-group btn-group-sm mt-1" role="group">
                        <a href="{{ route('locale.switch', 'ne') }}" class="btn {{ app()->getLocale() === 'ne' ? 'btn-primary' : 'btn-outline-secondary' }}">नेपाली</a>
                        <a href="{{ route('locale.switch', 'en') }}" class="btn {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">English</a>
                    </div>
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
