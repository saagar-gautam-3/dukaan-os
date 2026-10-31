@extends('layouts.app')

@section('title', __('inventory.title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-stack me-2"></i>{{ __('inventory.current_stock') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('inventory.subtitle') }}</p>
        </div>

        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <a href="{{ route('stock.alerts') }}" class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-exclamation-triangle"></i>
                <span>{{ __('inventory.low_stock_alerts') }}</span>
            </a>
            @can('inventory.manage')
            <a href="{{ route('stock.adjust') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-arrow-left-right"></i>
                <span>{{ __('inventory.adjust_stock') }}</span>
            </a>
            @endcan
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('stock.index') }}" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('inventory.search_placeholder') }}" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select name="warehouse_id" class="form-select">
                        <option value="">{{ __('inventory.all_warehouses') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (string) request('warehouse_id') === (string) $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <select name="filter" class="form-select">
                        <option value="">{{ __('inventory.all_statuses') }}</option>
                        <option value="low_stock" {{ request('filter') === 'low_stock' ? 'selected' : '' }}>{{ __('inventory.low_stock') }}</option>
                        <option value="out_of_stock" {{ request('filter') === 'out_of_stock' ? 'selected' : '' }}>{{ __('inventory.out_of_stock') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>{{ __('inventory.filter') }}
                    </button>
                    @if(request()->hasAny(['search', 'warehouse_id', 'filter']))
                        <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Balances List -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            @if($balances->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted opacity-50"></i>
                    <p class="mb-0">{{ __('inventory.no_stock_found') }}</p>
                </div>
            @else
                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">{{ __('inventory.product') }}</th>
                                <th>{{ __('inventory.warehouse') }}</th>
                                <th class="text-end">{{ __('products.cost_price') }}</th>
                                <th class="text-end">{{ __('inventory.min_alert') }}</th>
                                <th class="text-center">{{ __('inventory.current_quantity') }}</th>
                                <th class="text-center">{{ __('inventory.status') }}</th>
                                <th class="text-end pe-3">{{ __('products.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($balances as $balance)
                            @php
                                $qty = (string) $balance->quantity;
                                $minAlert = (string) ($balance->product->min_stock_alert ?? '0');
                                $isLow = \App\Support\Money::compare($minAlert, '0', 3) > 0 && \App\Support\Money::compare($qty, $minAlert, 3) <= 0;
                                $isOut = \App\Support\Money::compare($qty, '0', 3) <= 0;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark">{{ $balance->product->name }}</div>
                                    <small class="text-muted"><code>{{ $balance->product->sku ?: __('ui.no_sku') }}</code> &bull; {{ $balance->product->category?->name ?? __('ui.general') }}</small>
                                </td>
                                <td>
                                    <i class="bi bi-building me-1 text-secondary"></i>{{ $balance->warehouse->name }}
                                </td>
                                <td class="text-end text-muted">@npr($balance->product->cost_price)</td>
                                <td class="text-end text-muted">
                                    {{ \App\Support\Money::compare($minAlert, '0', 3) > 0 ? format_quantity($minAlert, $balance->product->unit?->is_fractional).' '.$balance->product->unit?->symbol : '—' }}
                                </td>
                                <td class="text-center">
                                    <span class="fs-6 fw-bold {{ $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-success') }}">
                                        @qty($qty, $balance->product->unit?->is_fractional) {{ $balance->product->unit?->symbol }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($isOut)
                                        <span class="badge bg-danger-subtle text-danger px-2">{{ __('inventory.out_of_stock') }}</span>
                                    @elseif($isLow)
                                        <span class="badge bg-warning-subtle text-dark px-2">{{ __('inventory.low_stock') }}</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success px-2">{{ __('inventory.in_stock') }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('stock.history', $balance->product) }}" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-clock-history me-1"></i>{{ __('inventory.view_history') }}
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="d-md-none divide-y">
                    @foreach($balances as $balance)
                    @php
                        $qty = (string) $balance->quantity;
                        $minAlert = (string) ($balance->product->min_stock_alert ?? '0');
                        $isLow = \App\Support\Money::compare($minAlert, '0', 3) > 0 && \App\Support\Money::compare($qty, $minAlert, 3) <= 0;
                        $isOut = \App\Support\Money::compare($qty, '0', 3) <= 0;
                    @endphp
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ $balance->product->name }}</h6>
                                <small class="text-muted"><i class="bi bi-building me-1"></i>{{ $balance->warehouse->name }}</small>
                            </div>
                            <span class="badge {{ $isOut ? 'bg-danger' : ($isLow ? 'bg-warning text-dark' : 'bg-success') }} px-2 py-1">
                                @qty($qty, $balance->product->unit?->is_fractional) {{ $balance->product->unit?->symbol }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <small class="text-muted">{{ __('inventory.threshold_label') }} {{ \App\Support\Money::compare($minAlert, '0', 3) > 0 ? format_quantity($minAlert, $balance->product->unit?->is_fractional) : __('inventory.threshold_none') }}</small>
                            <a href="{{ route('stock.history', $balance->product) }}" class="btn btn-outline-info btn-sm py-0">
                                <i class="bi bi-clock-history me-1"></i>{{ __('inventory.history_btn') }}
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        {{ __('common.showing_of', ['first' => $balances->firstItem() ?? 0, 'last' => $balances->lastItem() ?? 0, 'total' => $balances->total(), 'resource' => __('inventory.current_stock')]) }}
                    </span>
                    <div>
                        {{ $balances->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
