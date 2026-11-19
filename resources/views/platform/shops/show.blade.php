@extends('layouts.app')

@section('title', __('platform.shop_detail'))

@section('content')
<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('platform.shops.index') }}" class="btn btn-sm btn-outline-secondary" style="min-height:44px;">{{ __('platform.back_to_shops') }}</a>
        <a href="{{ route('platform.audit.index', ['shop' => $shop->id]) }}" class="btn btn-sm btn-outline-secondary" style="min-height:44px;">{{ __('platform.audit') }}</a>
    </div>

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $shop->name }}</h1>
            <div class="text-muted small">{{ $shop->slug }}</div>
        </div>
        <div>
            @if ($shop->is_active)
                <span class="badge text-bg-success">{{ __('platform.active') }}</span>
            @else
                <span class="badge text-bg-danger">{{ __('platform.suspended') }}</span>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-12 col-md-6"><strong>{{ __('platform.owner') }}:</strong> {{ $shop->ownerUser?->user?->name ?? '-' }} ({{ $shop->ownerUser?->user?->email ?? '-' }})</div>
                <div class="col-12 col-md-6"><strong>{{ __('platform.created') }}:</strong> {{ $shop->created_at?->timezone($shop->timezone) }}</div>
                <div class="col-12 col-md-6"><strong>{{ __('platform.timezone') }}:</strong> {{ $shop->timezone }}</div>
                <div class="col-12 col-md-6"><strong>{{ __('platform.business_type') }}:</strong> {{ $shop->business_type }}</div>
            </div>
        </div>
    </div>

    @if (! $shop->is_active)
        <div class="alert alert-danger">
            <div><strong>{{ __('platform.suspended_reason') }}:</strong> {{ $shop->suspended_reason }}</div>
            <div><strong>{{ __('platform.suspended_at') }}:</strong> {{ $shop->suspended_at?->timezone($shop->timezone) }}</div>
            <div><strong>{{ __('platform.suspended_by') }}:</strong> {{ $shop->suspendedBy?->email ?? '-' }}</div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => __('platform.users'), 'value' => $insights['users_count']],
            ['label' => __('platform.products'), 'value' => $insights['products_count']],
            ['label' => __('platform.customers'), 'value' => $insights['customers_count']],
            ['label' => __('platform.sales_count'), 'value' => $insights['sales_count']],
            ['label' => __('platform.sales_last_30_days'), 'value' => $insights['sales_last_30_days']],
            ['label' => __('platform.import_bytes'), 'value' => $insights['import_bytes']],
        ] as $card)
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="h4 mb-0">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('platform.last_sale_at') }}</div>
                    <div class="h6 mb-0">{{ $insights['last_sale_at']?->timezone($shop->timezone)?->format('Y-m-d H:i') ?? '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('platform.last_posted_purchase_at') }}</div>
                    <div class="h6 mb-0">{{ $insights['last_posted_purchase_at']?->timezone($shop->timezone)?->format('Y-m-d H:i') ?? '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('platform.auto_reminders_enabled') }}</div>
                    <div class="h6 mb-0">{{ $insights['auto_reminders_enabled'] ? __('common.yes') : __('common.no') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">{{ __('platform.subscription') }}</div>
                    @if ($insights['subscription'])
                        <div>{{ $insights['subscription']['plan'] ?? '-' }}</div>
                        <div class="small">{{ __('platform.status') }}: {{ $insights['subscription']['status'] }}</div>
                        <div class="small">{{ __('platform.starts_at') }}: {{ $insights['subscription']['starts_at']?->timezone($shop->timezone)?->format('Y-m-d') ?? '-' }}</div>
                        <div class="small">{{ __('platform.ends_at') }}: {{ $insights['subscription']['ends_at']?->timezone($shop->timezone)?->format('Y-m-d') ?? '-' }}</div>
                    @else
                        <div>-</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        @if ($shop->is_active)
            <a href="{{ route('platform.shops.suspend', $shop) }}" class="btn btn-danger" style="min-height:44px;">{{ __('platform.suspend') }}</a>
        @else
            <form method="POST" action="{{ route('platform.shops.reactivate', $shop) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success" style="min-height:44px;">{{ __('platform.reactivate') }}</button>
            </form>
        @endif
    </div>

    <h2 class="h5 mt-4">{{ __('platform.suspension_history') }}</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ __('platform.created') }}</th>
                    <th>{{ __('platform.event') }}</th>
                    <th>{{ __('platform.admin') }}</th>
                    <th>{{ __('platform.reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($platformAudits as $audit)
                    <tr>
                        <td>{{ $audit->created_at?->timezone($shop->timezone) }}</td>
                        <td>{{ $audit->event }}</td>
                        <td>{{ $audit->user?->email ?? '-' }}</td>
                        <td>{{ $audit->new_values['reason'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('platform.empty_history') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection