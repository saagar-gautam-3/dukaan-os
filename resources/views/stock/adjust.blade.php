@extends('layouts.app')

@section('title', __('inventory.adjust_stock') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0" style="max-width: 800px;">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-arrow-left-right me-2"></i>{{ __('inventory.adjust_stock') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('inventory.subtitle') }}</p>
        </div>
        <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('stock.adjust.store') }}">
                @csrf

                <div class="row g-3">
                    <!-- Warehouse -->
                    <div class="col-12 col-md-6">
                        <label for="warehouse_id" class="form-label fw-semibold">{{ __('inventory.warehouse') }} <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                            <option value="">{{ __('inventory.select_warehouse') }}</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Product -->
                    <div class="col-12 col-md-6">
                        <label for="product_id" class="form-label fw-semibold">{{ __('inventory.product') }} <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                            <option value="">{{ __('inventory.select_product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" data-cost="{{ $product->cost_price }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }} ({{ $product->unit?->symbol }})
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Type -->
                    <div class="col-12 col-md-6">
                        <label for="type" class="form-label fw-semibold">{{ __('inventory.adjustment_type') }} <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="in" {{ old('type') === 'in' ? 'selected' : '' }}>
                                {{ __('inventory.stock_in') }} (+)
                            </option>
                            <option value="out" {{ old('type') === 'out' ? 'selected' : '' }}>
                                {{ __('inventory.stock_out') }} (-)
                            </option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Quantity -->
                    <div class="col-12 col-md-6">
                        <label for="quantity" class="form-label fw-semibold">{{ __('inventory.quantity') }} <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0.001" name="quantity" id="quantity" class="form-control @error('quantity') is-invalid @enderror" value="{{ old('quantity') }}" required placeholder="{{ __('inventory.quantity_placeholder') }}">
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Unit Cost -->
                    <div class="col-12 col-md-6">
                        <label for="unit_cost" class="form-label fw-semibold">{{ __('inventory.unit_cost') }} ({{ __('ui.optional') }})</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">{{ __('ui.currency_symbol') }}</span>
                            <input type="number" step="0.01" min="0" name="unit_cost" id="unit_cost" class="form-control @error('unit_cost') is-invalid @enderror" value="{{ old('unit_cost') }}" placeholder="{{ __('inventory.cost_placeholder') }}">
                        </div>
                        @error('unit_cost')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Reason -->
                    <div class="col-12 col-md-6">
                        <label for="reason" class="form-label fw-semibold">{{ __('inventory.reason') }} <span class="text-danger">*</span></label>
                        <input type="text" name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" placeholder="{{ __('inventory.reason_placeholder') }}" required>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="action-row mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle me-1"></i>{{ __('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
