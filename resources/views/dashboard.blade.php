@extends('layouts.app')

@section('title', __('dashboard.title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <!-- Welcome Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                {{ __('dashboard.welcome', ['name' => auth()->user()->name]) }}
            </h2>
            <p class="text-muted mb-0 small">
                {{ __('dashboard.overview') }} &mdash; <span class="fw-medium text-dark">{{ $shop->name }}</span>
                <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $membership?->role?->name ?? 'Staff' }}</span>
            </p>
        </div>

        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="#" class="btn btn-primary d-flex align-items-center gap-1">
                <i class="bi bi-cart-plus"></i>
                <span>{{ __('dashboard.new_sale') }}</span>
            </a>
            <a href="{{ route('settings.shop') }}" class="btn btn-outline-secondary d-flex align-items-center gap-1">
                <i class="bi bi-gear"></i>
                <span class="d-none d-sm-inline">{{ __('common.nav.settings') }}</span>
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- Today's Sales -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card--sales h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">{{ __('dashboard.stats.today_sales') }}</span>
                    <i class="bi bi-receipt text-primary fs-4"></i>
                </div>
                <div class="stat-value text-primary mb-1">@npr(0)</div>
                <small class="text-muted">{{ __('dashboard.bills_today', ['count' => 0]) }}</small>
            </div>
        </div>

        <!-- Cash in Hand -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card--cash h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">{{ __('dashboard.stats.cash_in_hand') }}</span>
                    <i class="bi bi-cash-stack text-success fs-4"></i>
                </div>
                <div class="stat-value text-success mb-1">@npr(0)</div>
                <small class="text-muted">{{ __('dashboard.drawer_active') }}</small>
            </div>
        </div>

        <!-- Udhar Dues -->
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('udhar.outstanding') }}" class="text-decoration-none">
                <div class="stat-card stat-card--udhar h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="stat-label text-dark">{{ __('dashboard.stats.udhar_dues') }}</span>
                        <i class="bi bi-journal-bookmark text-danger fs-4"></i>
                    </div>
                    <div class="stat-value text-danger mb-1">@npr($totalUdhar ?? '0.00')</div>
                    <small class="text-muted">{{ __('dashboard.from_customers', ['count' => $udharCustomerCount ?? 0]) }}</small>
                </div>
            </a>
        </div>

        <!-- Low Stock Alert -->
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('stock.alerts') }}" class="text-decoration-none">
                <div class="stat-card stat-card--stock h-100 {{ $lowStockCount > 0 ? 'border-warning' : '' }}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="stat-label text-dark">{{ __('dashboard.stats.low_stock') }}</span>
                        <i class="bi bi-exclamation-diamond {{ $lowStockCount > 0 ? 'text-warning' : 'text-info' }} fs-4"></i>
                    </div>
                    <div class="stat-value {{ $lowStockCount > 0 ? 'text-warning fw-bold' : 'text-info' }} mb-1">
                        @qty($lowStockCount)
                    </div>
                    <small class="text-muted">{{ __('dashboard.below_reorder') }}</small>
                </div>
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-lightning-charge text-warning me-2"></i>{{ __('dashboard.quick_actions') }}
            </h5>
        </div>
        <div class="card-body pt-0">
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <a href="{{ route('pos') }}" class="btn btn-light border w-100 py-3 text-start d-flex align-items-center gap-2">
                        <i class="bi bi-cart-plus fs-4 text-primary"></i>
                        <div>
                            <div class="fw-semibold text-dark">{{ __('dashboard.new_sale') }}</div>
                            <small class="text-muted">{{ __('dashboard.pos_billing') }}</small>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="{{ route('udhar.outstanding') }}" class="btn btn-light border w-100 py-3 text-start d-flex align-items-center gap-2">
                        <i class="bi bi-wallet2 fs-4 text-success"></i>
                        <div>
                            <div class="fw-semibold text-dark">{{ __('dashboard.receive_udhar') }}</div>
                            <small class="text-muted">{{ __('dashboard.customer_khata') }}</small>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="{{ route('products.create') }}" class="btn btn-light border w-100 py-3 text-start d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam fs-4 text-info"></i>
                        <div>
                            <div class="fw-semibold text-dark">{{ __('dashboard.add_product') }}</div>
                            <small class="text-muted">{{ __('dashboard.inventory_catalog') }}</small>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="#" class="btn btn-light border w-100 py-3 text-start d-flex align-items-center gap-2">
                        <i class="bi bi-cash fs-4 text-danger"></i>
                        <div>
                            <div class="fw-semibold text-dark">{{ __('dashboard.record_expense') }}</div>
                            <small class="text-muted">{{ __('dashboard.shop_outflow') }}</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Placeholder -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-clock-history text-secondary me-2"></i>{{ __('dashboard.recent_activity') }}
            </h5>
        </div>
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2 text-muted opacity-50"></i>
            <p class="mb-0">{{ __('dashboard.no_activity') }}</p>
        </div>
    </div>
</div>
@endsection
