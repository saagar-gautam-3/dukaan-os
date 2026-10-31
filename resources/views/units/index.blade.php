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
        <!-- Units List -->
        <div class="col-12">
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
