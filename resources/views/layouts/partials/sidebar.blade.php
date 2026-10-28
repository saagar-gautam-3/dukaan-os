<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="{{ route('dashboard') }}" class="text-white text-decoration-none d-flex align-items-center">
            <i class="bi bi-shop me-2 fs-4 text-warning"></i>
            <span class="fw-bold">{{ \App\Tenancy\TenantContext::has() ? \App\Tenancy\TenantContext::currentShop()->name : config('app.name') }}</span>
        </a>
    </div>

    <div class="p-3 border-bottom border-white-10">
        <div class="d-flex align-items-center justify-content-between">
            <small class="text-white-50 text-uppercase">{{ __('common.current_shop') }}</small>
            <a href="{{ route('shops.picker') }}" class="badge bg-white text-dark text-decoration-none py-1 px-2" title="{{ __('common.switch_shop') }}">
                <i class="bi bi-arrow-repeat me-1"></i>{{ __('common.switch_shop') }}
            </a>
        </div>
        <div class="fw-semibold text-truncate text-white mt-1">
            {{ \App\Tenancy\TenantContext::has() ? \App\Tenancy\TenantContext::currentShop()->name : '' }}
        </div>
        <small class="text-white-50">
            {{ \App\Tenancy\TenantContext::currentMembership()?->role?->name ?? 'Staff' }}
        </small>
    </div>

    <nav class="nav flex-column my-2 flex-grow-1">
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>{{ __('common.nav.dashboard') }}</span>
        </a>

        @can('sales.create')
        <a href="{{ route('pos') }}" class="nav-link {{ request()->routeIs('pos') ? 'active' : '' }}">
            <i class="bi bi-calculator"></i>
            <span>{{ __('sales.pos_title') }}</span>
        </a>
        @endcan

        @can('sales.view')
        <a href="{{ route('sales.index') }}" class="nav-link {{ request()->routeIs('sales.*') && !request()->routeIs('pos') ? 'active' : '' }}">
            <i class="bi bi-receipt"></i>
            <span>{{ __('common.nav.sales') }}</span>
        </a>
        @endcan

        @can('customers.view')
        <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>{{ __('sales.customers') }}</span>
        </a>
        @endcan

        @can('products.view')
        <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') || request()->routeIs('categories.*') || request()->routeIs('units.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i>
            <span>{{ __('common.nav.products') }}</span>
        </a>
        @endcan

        @can('inventory.view')
        <a href="{{ route('stock.index') }}" class="nav-link {{ request()->routeIs('stock.*') ? 'active' : '' }}">
            <i class="bi bi-stack"></i>
            <span>{{ __('common.nav.stock') }}</span>
        </a>
        @endcan

        @can('purchases.view')
        <a href="{{ route('purchases.index') }}" class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
            <i class="bi bi-cart-check"></i>
            <span>{{ __('common.nav.purchases') }}</span>
        </a>
        @endcan

        @can('suppliers.view')
        <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
            <i class="bi bi-truck"></i>
            <span>{{ __('common.nav.suppliers') }}</span>
        </a>
        @endcan

        <a href="#" class="nav-link text-white-50 disabled">
            <i class="bi bi-wallet2"></i>
            <span>{{ __('common.nav.expenses') }}</span>
        </a>

        <a href="#" class="nav-link text-white-50 disabled">
            <i class="bi bi-graph-up"></i>
            <span>{{ __('common.nav.reports') }}</span>
        </a>

        <a href="#" class="nav-link text-white-50 disabled">
            <i class="bi bi-people"></i>
            <span>{{ __('common.nav.staff') }}</span>
        </a>

        <a href="{{ route('settings.shop') }}" class="nav-link {{ request()->routeIs('settings.shop') ? 'active' : '' }}">
            <i class="bi bi-gear"></i>
            <span>{{ __('common.nav.settings') }}</span>
        </a>
    </nav>

    <div class="p-3 border-top border-white-10 text-white-50 small">
        <div class="d-flex justify-content-between align-items-center">
            <span>Dukaan OS v1.0</span>
            <span class="badge bg-success text-white">NPR</span>
        </div>
    </div>
</aside>
