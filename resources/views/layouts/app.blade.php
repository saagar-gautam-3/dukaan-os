<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Dukaan OS'))</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div id="network-status" class="alert alert-warning rounded-0 mb-0 d-none" role="status"
         data-offline="{{ __('ui.offline_message') }}"
         data-online="{{ __('ui.online_message') }}"
         data-slow="{{ __('ui.slow_network_message') }}"></div>
    <script>
        window.appMessages = { submitting: @json(__('ui.submitting')) };
    </script>
    <div class="app-layout">
        @include('layouts.partials.sidebar')
        <button type="button" class="sidebar-backdrop d-none" id="sidebarBackdrop" aria-label="{{ __('common.close_sidebar') }}"></button>

        <div class="app-content">
            @include('layouts.partials.topbar')

            <main class="app-page">
                @if (session('status') || session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>{{ session('status') ?? session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('common.close') }}"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('common.close') }}"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
