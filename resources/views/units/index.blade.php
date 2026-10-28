@extends('layouts.app')

@section('title', __('products.units') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-rulers me-2"></i>{{ __('products.units') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('products.manage_units') }}</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>{{ __('products.title') }}
        </a>
    </div>

    <div class="row g-4">
        <!-- Add Unit Form -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-plus-circle me-1 text-primary"></i>{{ __('products.add_unit') }}
                    </h5>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="{{ route('units.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">{{ __('products.unit_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Kilogram" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="symbol" class="form-label fw-semibold">{{ __('products.unit_symbol') }} <span class="text-danger">*</span></label>
                            <input type="text" name="symbol" id="symbol" class="form-control @error('symbol') is-invalid @enderror" placeholder="e.g. kg" value="{{ old('symbol') }}" required>
                            @error('symbol')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input type="hidden" name="is_fractional" value="0">
                            <input type="checkbox" name="is_fractional" id="is_fractional" class="form-check-input" value="1" {{ old('is_fractional', '1') == '1' ? 'checked' : '' }}>
                            <label for="is_fractional" class="form-check-label">{{ __('products.is_fractional') }}</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle me-1"></i>{{ __('common.save') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Units List -->
        <div class="col-12 col-md-8">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">{{ __('products.units') }} ({{ $units->count() }})</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">{{ __('products.unit_name') }}</th>
                                    <th>{{ __('products.unit_symbol') }}</th>
                                    <th>Decimals Allowed</th>
                                    <th class="text-center">{{ __('products.title') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($units as $unit)
                                <tr>
                                    <td class="ps-3 fw-medium text-dark">{{ $unit->name }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $unit->symbol }}</span></td>
                                    <td>
                                        <span class="badge {{ $unit->is_fractional ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                            {{ $unit->is_fractional ? 'Yes (Fractional)' : 'No (Whole integer)' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-muted border">
                                            {{ $unit->products_count }} products
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
