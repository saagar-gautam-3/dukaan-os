<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shops.picker_title') }} — {{ config('app.name', 'Dukaan OS') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light d-flex flex-column min-vh-100 justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="text-center mb-4">
                    <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                        <i class="bi bi-shop me-2"></i>{{ __('shops.picker_title') }}
                    </h2>
                    <p class="text-muted">{{ __('shops.picker_subtitle') }}</p>

                    <!-- Language Switcher -->
                    <div class="btn-group btn-group-sm mt-1" role="group">
                        <a href="{{ route('locale.switch', 'ne') }}" class="btn {{ app()->getLocale() === 'ne' ? 'btn-primary' : 'btn-outline-secondary' }}">नेपाली</a>
                        <a href="{{ route('locale.switch', 'en') }}" class="btn {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">English</a>
                    </div>
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if ($memberships->isEmpty())
                    <div class="card shadow-sm border-0 text-center p-5">
                        <div class="mb-3">
                            <i class="bi bi-shop-window display-4 text-muted"></i>
                        </div>
                        <h5>{{ __('shops.no_shops') }}</h5>
                        <p class="text-muted small mb-4">You do not have any active shop memberships yet.</p>
                        <div>
                            <a href="{{ route('register') }}" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>{{ __('shops.register_new') }}
                            </a>
                        </div>
                    </div>
                @else
                    <div class="row g-3">
                        @foreach ($memberships as $membership)
                            <div class="col-12 col-md-6">
                                <div class="card shadow-sm border-0 h-100 {{ (int) $currentShopId === (int) $membership->shop_id ? 'border-primary border-2' : '' }}">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="fw-bold mb-0 text-truncate text-dark">
                                                    <i class="bi bi-shop text-primary me-2"></i>{{ $membership->shop->name }}
                                                </h5>
                                                @if ((int) $currentShopId === (int) $membership->shop_id)
                                                    <span class="badge bg-success text-white">{{ __('shops.active_badge') }}</span>
                                                @endif
                                            </div>
                                            <p class="text-muted small mb-3">
                                                <span class="badge bg-light text-secondary border me-1">
                                                    {{ $membership->shop->business_type }}
                                                </span>
                                                <span class="text-muted">
                                                    <i class="bi bi-person-badge me-1"></i>{{ $membership->role?->name ?? 'Staff' }}
                                                </span>
                                            </p>
                                        </div>

                                        <form method="POST" action="{{ route('shops.switch', $membership->shop) }}" class="mt-3">
                                            @csrf
                                            <button type="submit" class="btn {{ (int) $currentShopId === (int) $membership->shop_id ? 'btn-primary' : 'btn-outline-primary' }} w-100">
                                                <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('shops.switch_button') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="text-center mt-4">
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-link text-danger text-decoration-none">
                            <i class="bi bi-box-arrow-right me-1"></i>{{ __('common.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
