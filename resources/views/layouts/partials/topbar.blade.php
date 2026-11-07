<header class="app-topbar">
    <div class="d-flex align-items-center gap-2 gap-lg-3 min-w-0">
        <button class="btn btn-outline-secondary d-lg-none flex-shrink-0" type="button" id="sidebarToggle" aria-label="{{ __('common.toggle_sidebar') }}" aria-controls="appSidebar" aria-expanded="false">
            <i class="bi bi-list fs-5"></i>
        </button>

        @if (\App\Tenancy\TenantContext::has())
            {{-- Full chips at lg and up --}}
            <div class="d-none d-lg-flex align-items-center gap-2 min-w-0">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 text-truncate" style="max-width: 260px;" title="{{ \App\Tenancy\TenantContext::currentShop()->name }}">
                    <i class="bi bi-shop me-1"></i>{{ \App\Tenancy\TenantContext::currentShop()->name }}
                </span>
                <span class="badge bg-light text-muted border py-2 px-2 flex-shrink-0">
                    {{ \App\Tenancy\TenantContext::currentShop()->business_type }}
                </span>
            </div>

            {{-- Compact shop name below lg --}}
            <span class="d-lg-none badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-2 text-truncate min-w-0" style="max-width: 32vw;" title="{{ \App\Tenancy\TenantContext::currentShop()->name }}">
                <i class="bi bi-shop me-1"></i>{{ \App\Tenancy\TenantContext::currentShop()->name }}
            </span>
        @endif
    </div>

    <div class="d-flex align-items-center gap-2 gap-lg-3 flex-shrink-0">
        <!-- Language Switcher (full at lg and up) -->
        <div class="btn-group btn-group-sm d-none d-lg-inline-flex" role="group">
            <a href="{{ route('locale.switch', 'ne') }}" class="btn {{ app()->getLocale() === 'ne' ? 'btn-primary' : 'btn-outline-secondary' }}" title="नेपाली">नेपाली</a>
            <a href="{{ route('locale.switch', 'en') }}" class="btn {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}" title="{{ __('ui.language_english') }}">{{ __('ui.language_english') }}</a>
        </div>

        <!-- Compact language dropdown below lg -->
        <div class="dropdown d-lg-none">
            <button class="btn btn-sm btn-outline-secondary" type="button" id="langMenuCompact" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('ui.language_english') }}">
                {{ app()->getLocale() === 'ne' ? 'ने' : 'EN' }}
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="langMenuCompact">
                <li>
                    <a class="dropdown-item" href="{{ route('locale.switch', 'ne') }}">नेपाली</a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('locale.switch', 'en') }}">{{ __('ui.language_english') }}</a>
                </li>
            </ul>
        </div>

        <!-- Shop Switcher Link (lg and up; compact menu below lg) -->
        @if (can_switch_shop())
            <a href="{{ route('shops.picker') }}" class="btn btn-sm btn-outline-secondary d-none d-lg-inline-flex align-items-center gap-1" title="{{ __('common.switch_shop') }}">
                <i class="bi bi-arrow-repeat"></i>
                <span>{{ __('common.switch_shop') }}</span>
            </a>
        @endif

        @canRoute('platform.shops.index')
            <a href="{{ route('platform.shops.index') }}" class="btn btn-sm btn-outline-primary d-none d-lg-inline-flex">{{ __('common.platform_shops') }}</a>
        @endCanRoute

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle fs-5 text-secondary"></i>
                <span class="fw-medium d-none d-xl-inline text-truncate" style="max-width: 160px;">{{ auth()->user()->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenu">
                <li class="dropdown-header">
                    <div class="fw-semibold">{{ auth()->user()->name }}</div>
                    <small class="text-muted">{{ auth()->user()->email }}</small>
                </li>
                <li><hr class="dropdown-divider"></li>
                @canRoute('settings.shop')
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('settings.shop') }}">
                        <i class="bi bi-gear"></i>{{ __('common.nav.settings') }}
                    </a>
                </li>
                @endCanRoute
                @if (\App\Tenancy\TenantContext::has())
                <li class="d-lg-none dropdown-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shop"></i>
                        <span class="text-truncate" style="max-width: 200px;">{{ \App\Tenancy\TenantContext::currentShop()->name }}</span>
                    </div>
                    <small class="text-muted">{{ \App\Tenancy\TenantContext::currentShop()->business_type }}</small>
                </li>
                <li class="d-lg-none"><hr class="dropdown-divider"></li>
                @endif
                @if (can_switch_shop())
                <li class="d-lg-none">
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('shops.picker') }}">
                        <i class="bi bi-arrow-repeat"></i>{{ __('common.switch_shop') }}
                    </a>
                </li>
                @endif
                @canRoute('platform.shops.index')
                <li class="d-lg-none">
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('platform.shops.index') }}">
                        <i class="bi bi-building"></i>{{ __('common.platform_shops') }}
                    </a>
                </li>
                @endCanRoute
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
