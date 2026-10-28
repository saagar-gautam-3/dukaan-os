<header class="app-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>

        @if (\App\Tenancy\TenantContext::has())
            <div class="d-none d-sm-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3">
                    <i class="bi bi-shop me-1"></i>{{ \App\Tenancy\TenantContext::currentShop()->name }}
                </span>
                <span class="badge bg-light text-muted border py-2 px-2">
                    {{ \App\Tenancy\TenantContext::currentShop()->business_type }}
                </span>
            </div>
        @endif
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Language Switcher -->
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('locale.switch', 'ne') }}" class="btn {{ app()->getLocale() === 'ne' ? 'btn-primary' : 'btn-outline-secondary' }}" title="नेपाली">नेपाली</a>
            <a href="{{ route('locale.switch', 'en') }}" class="btn {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}" title="English">EN</a>
        </div>

        <!-- Shop Switcher Link -->
        <a href="{{ route('shops.picker') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" title="{{ __('common.switch_shop') }}">
            <i class="bi bi-arrow-repeat"></i>
            <span class="d-none d-md-inline">{{ __('common.switch_shop') }}</span>
        </a>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle fs-5 text-secondary"></i>
                <span class="fw-medium d-none d-sm-inline">{{ auth()->user()->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenu">
                <li class="dropdown-header">
                    <div class="fw-semibold">{{ auth()->user()->name }}</div>
                    <small class="text-muted">{{ auth()->user()->email }}</small>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('settings.shop') }}">
                        <i class="bi bi-gear"></i>{{ __('common.nav.settings') }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('shops.picker') }}">
                        <i class="bi bi-shop"></i>{{ __('common.switch_shop') }}
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                            <i class="bi bi-box-arrow-right"></i>{{ __('common.logout') }}
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
