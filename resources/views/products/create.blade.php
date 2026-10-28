@extends('layouts.app')

@section('title', __('products.add_product') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0" style="max-width: 900px;">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-plus-circle me-2"></i>{{ __('products.create_product') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('products.subtitle') }}</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('products.store') }}">
                @csrf

                <div class="row g-3">
                    <!-- Product Name -->
                    <div class="col-12 col-md-8">
                        <label for="name" class="form-label fw-semibold">{{ __('products.name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Category -->
                    <div class="col-12 col-md-4">
                        <label for="category_id" class="form-label fw-semibold">{{ __('products.category') }}</label>
                        <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror">
                            <option value="">{{ __('products.select_category') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- SKU -->
                    <div class="col-12 col-md-4">
                        <label for="sku" class="form-label fw-semibold">{{ __('products.sku') }}</label>
                        <input type="text" name="sku" id="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku') }}" placeholder="e.g. WAI-WAI-75G">
                        @error('sku')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Barcode -->
                    <div class="col-12 col-md-4">
                        <label for="barcode" class="form-label fw-semibold">{{ __('products.barcode') }}</label>
                        <input type="text" name="barcode" id="barcode" class="form-control @error('barcode') is-invalid @enderror" value="{{ old('barcode') }}" placeholder="e.g. 890123456789">
                        @error('barcode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Unit -->
                    <div class="col-12 col-md-4">
                        <label for="unit_id" class="form-label fw-semibold">{{ __('products.unit') }} <span class="text-danger">*</span></label>
                        <select name="unit_id" id="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required>
                            <option value="">{{ __('products.select_unit') }}</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->name }} ({{ $unit->symbol }})
                                </option>
                            @endforeach
                        </select>
                        @error('unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Cost Price -->
                    <div class="col-12 col-md-4">
                        <label for="cost_price" class="form-label fw-semibold">{{ __('products.cost_price') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">NPR</span>
                            <input type="number" step="0.01" min="0" name="cost_price" id="cost_price" class="form-control @error('cost_price') is-invalid @enderror" value="{{ old('cost_price', '0.00') }}" required>
                        </div>
                        @error('cost_price')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Selling Price -->
                    <div class="col-12 col-md-4">
                        <label for="selling_price" class="form-label fw-semibold">{{ __('products.selling_price') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">NPR</span>
                            <input type="number" step="0.01" min="0" name="selling_price" id="selling_price" class="form-control @error('selling_price') is-invalid @enderror" value="{{ old('selling_price', '0.00') }}" required>
                        </div>
                        @error('selling_price')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Min Stock Alert Threshold -->
                    <div class="col-12 col-md-4">
                        <label for="min_stock_alert" class="form-label fw-semibold">{{ __('products.min_stock_alert') }}</label>
                        <input type="number" step="0.001" min="0" name="min_stock_alert" id="min_stock_alert" class="form-control @error('min_stock_alert') is-invalid @enderror" value="{{ old('min_stock_alert', '5.000') }}">
                        <div class="form-text small">Alert is triggered when total balance falls at or below this value.</div>
                        @error('min_stock_alert')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium" for="is_active">{{ __('products.active') }}</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle me-1"></i>{{ __('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
