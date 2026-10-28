@extends('layouts.app')

@section('title', __('inventory.stock_movements') . ' — ' . $product->name . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-clock-history me-2"></i>{{ __('inventory.stock_movements') }}
            </h2>
            <p class="text-muted mb-0 small">
                <span class="fw-semibold text-dark">{{ $product->name }}</span> &bull; <code>{{ $product->sku ?: 'No SKU' }}</code> &bull; Unit: {{ $product->unit?->name }} ({{ $product->unit?->symbol }})
            </p>
        </div>

        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
            </a>
            @can('inventory.manage')
            <a href="{{ route('stock.adjust') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-left-right me-1"></i>{{ __('inventory.adjust_stock') }}
            </a>
            @endcan
        </div>
    </div>

    <!-- Movement Filter -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('stock.history', $product) }}" class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <select name="warehouse_id" class="form-select">
                        <option value="">{{ __('inventory.all_warehouses') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (string) request('warehouse_id') === (string) $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    @if(request()->filled('warehouse_id'))
                        <a href="{{ route('stock.history', $product) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            @if($movements->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted opacity-50"></i>
                    <p class="mb-0">{{ __('inventory.no_movements') }}</p>
                </div>
            @else
                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">{{ __('inventory.date_time') }}</th>
                                <th>{{ __('inventory.warehouse') }}</th>
                                <th>{{ __('inventory.movement_type') }}</th>
                                <th class="text-end">{{ __('inventory.quantity') }}</th>
                                <th class="text-end">{{ __('inventory.unit_cost') }}</th>
                                <th>{{ __('inventory.reference') }}</th>
                                <th>{{ __('inventory.adjusted_by') }}</th>
                                <th class="pe-3">{{ __('inventory.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movements as $movement)
                            @php
                                $isInflow = $movement->movement_type->isInflow();
                                $qty = (float) $movement->quantity;
                            @endphp
                            <tr>
                                <td class="ps-3 text-muted small">
                                    {{ $movement->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td>{{ $movement->warehouse->name }}</td>
                                <td>
                                    <span class="badge {{ $isInflow ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} text-uppercase">
                                        {{ str_replace('_', ' ', $movement->movement_type->value) }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold {{ $isInflow ? 'text-success' : 'text-danger' }}">
                                    {{ $isInflow ? '+' : '-' }}{{ number_format($qty, $product->unit?->is_fractional ? 3 : 0) }} {{ $product->unit?->symbol }}
                                </td>
                                <td class="text-end text-muted">@npr($movement->unit_cost)</td>
                                <td>
                                    <small class="text-muted">
                                        {{ class_basename($movement->reference_type ?: 'Manual') }} #{{ $movement->reference_id ?: '—' }}
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $movement->user?->name ?? 'System' }}</small>
                                </td>
                                <td class="pe-3 small text-muted text-truncate" style="max-width: 200px;">
                                    {{ $movement->notes ?: '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="d-md-none divide-y">
                    @foreach($movements as $movement)
                    @php
                        $isInflow = $movement->movement_type->isInflow();
                        $qty = (float) $movement->quantity;
                    @endphp
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge {{ $isInflow ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ str_replace('_', ' ', $movement->movement_type->value) }}
                            </span>
                            <span class="fw-bold fs-6 {{ $isInflow ? 'text-success' : 'text-danger' }}">
                                {{ $isInflow ? '+' : '-' }}{{ number_format($qty, $product->unit?->is_fractional ? 3 : 0) }} {{ $product->unit?->symbol }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center text-muted small">
                            <span><i class="bi bi-building me-1"></i>{{ $movement->warehouse->name }}</span>
                            <span>{{ $movement->created_at->format('M d, H:i') }}</span>
                        </div>
                        @if($movement->notes)
                            <div class="mt-1 small text-muted bg-light p-1 rounded">
                                {{ $movement->notes }}
                            </div
                        @endif
                    </div>
                    @endforeach
                </div>

                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        Showing {{ $movements->firstItem() ?? 0 }} to {{ $movements->lastItem() ?? 0 }} of {{ $movements->total() }} movements
                    </span>
                    <div>
                        {{ $movements->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
