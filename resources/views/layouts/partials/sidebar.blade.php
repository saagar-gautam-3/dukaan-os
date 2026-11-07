<aside class="app-sidebar" id="appSidebar" aria-label="{{ __('common.navigation') }}">
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="{{ route('dashboard') }}" class="text-white text-decoration-none d-flex align-items-center">
            <i class="bi bi-shop me-2 fs-4 text-warning"></i>
            <span class="fw-bold">{{ \App\Tenancy\TenantContext::has() ? \App\Tenancy\TenantContext::currentShop()->name : config('app.name') }}</span>
        </a>
    </div>

    <div class="sidebar-shop p-3 border-bottom border-white-10">
        <div class="d-flex align-items-center justify-content-between">
            <small class="text-white-50 text-uppercase">{{ __('common.current_shop') }}</small>
            @if (can_switch_shop())
            <a href="{{ route('shops.picker') }}" class="badge bg-white text-dark text-decoration-none py-1 px-2" title="{{ __('common.switch_shop') }}">
                <i class="bi bi-arrow-repeat me-1"></i>{{ __('common.switch_shop') }}
            </a>
            @endif
        </div>
        <div class="fw-semibold text-truncate text-white mt-1">
            {{ \App\Tenancy\TenantContext::has() ? \App\Tenancy\TenantContext::currentShop()->name : '' }}
        </div>
        <small class="text-white-50">
            {{ \App\Tenancy\TenantContext::currentMembership()?->role?->name ?? __('ui.staff') }}
        </small>
    </div>

    <nav class="nav flex-column my-2 flex-grow-1 sidebar-nav" data-sidebar-scroll-region>
        @canRoute('dashboard')
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>{{ __('common.nav.dashboard') }}</span>
        </a>
        @endCanRoute

        @canRoute('pos')
        <a href="{{ route('pos') }}" class="nav-link {{ request()->routeIs('pos') ? 'active' : '' }}">
            <i class="bi bi-calculator"></i>
            <span>{{ __('sales.pos_title') }}</span>
        </a>
        @endCanRoute

        @canRoute('sales.index')
        <a href="{{ route('sales.index') }}" class="nav-link {{ request()->routeIs('sales.*') && !request()->routeIs('pos') ? 'active' : '' }}">
            <i class="bi bi-receipt"></i>
            <span>{{ __('common.nav.sales') }}</span>
        </a>
        @endCanRoute

        @canRoute('customers.index')
        <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>{{ __('sales.customers') }}</span>
        </a>
        @endCanRoute

        @canRoute('udhar.outstanding')
        <a href="{{ route('udhar.outstanding') }}" class="nav-link {{ request()->routeIs('udhar.*') ? 'active' : '' }}">
            <i class="bi bi-journal-bookmark"></i>
            <span>{{ __('udhar.title') }}</span>
        </a>
        @endCanRoute

        @canRoute('products.index')
        <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') || request()->routeIs('categories.*') || request()->routeIs('units.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i>
            <span>{{ __('common.nav.products') }}</span>
        </a>
        @endCanRoute

        @canRoute('stock.index')
        <a href="{{ route('stock.index') }}" class="nav-link {{ request()->routeIs('stock.*') ? 'active' : '' }}">
            <i class="bi bi-stack"></i>
            <span>{{ __('common.nav.stock') }}</span>
        </a>
        @endCanRoute

        @canRoute('purchases.index')
        <a href="{{ route('purchases.index') }}" class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
            <i class="bi bi-cart-check"></i>
            <span>{{ __('common.nav.purchases') }}</span>
        </a>
        @endCanRoute

        @canRoute('suppliers.index')
        <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
            <i class="bi bi-truck"></i>
            <span>{{ __('common.nav.suppliers') }}</span>
        </a>
        @endCanRoute

        @canRoute('expenses.index')
        <a href="{{ route('expenses.index') }}" class="nav-link {{ request()->routeIs('expenses.*','expense-categories.*') ? 'active' : '' }}">
            <i class="bi bi-wallet2"></i>
            <span>{{ __('common.nav.expenses') }}</span>
        </a>
        @endCanRoute

        @canRoute('reports.profit')
        <a href="{{ route('reports.profit') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up"></i>
            <span>{{ __('common.nav.reports') }}</span>
        </a>
        @endCanRoute

        @canRoute('cash.index')
        <a href="{{ route('cash.index') }}" class="nav-link {{ request()->routeIs('cash.*') ? 'active' : '' }}">
            <i class="bi bi-cash-coin"></i>
            <span>{{ __('cash.sessions_history') }}</span>
        </a>
        @endCanRoute

        @canRoute('staff.index')
        <a href="{{ route('staff.index') }}" class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>{{ __('common.nav.staff') }}</span>
        </a>
        @endCanRoute

        @canRoute('settings.shop')
        <a href="{{ route('settings.shop') }}" class="nav-link {{ request()->routeIs('settings.shop') ? 'active' : '' }}">
            <i class="bi bi-gear"></i>
            <span>{{ __('common.nav.settings') }}</span>
        </a>
        @endCanRoute

        @canRoute('platform.shops.index')
        <a href="{{ route('platform.shops.index') }}" class="nav-link {{ request()->routeIs('platform.*') ? 'active' : '' }}">
            <i class="bi bi-building"></i>
            <span>{{ __('common.platform_shops') }}</span>
        </a>
        @endCanRoute

        <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
            @csrf
            <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent">
                <i class="bi bi-box-arrow-right"></i>
                <span>{{ __('common.logout') }}</span>
            </button>
        </form>
    </nav>

    <div class="p-3 border-top border-white-10 text-white-50 small">
        <div class="d-flex justify-content-between align-items-center">
            <span>Dukaan OS v1.0</span>
            <span class="badge bg-success text-white">NPR</span>
        </div>
    </div>
</aside>
