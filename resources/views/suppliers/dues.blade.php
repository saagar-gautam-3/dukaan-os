@extends('layouts.app')

@section('title', __('suppliers.dues_title').' — '.config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-cash-stack me-2"></i>{{ __('suppliers.dues_title') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('suppliers.subtitle') }}</p>
        </div>
        @canRoute('suppliers.index')
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm" style="min-height:44px;">
            <i class="bi bi-truck me-1"></i>{{ __('suppliers.title') }}
        </a>
        @endCanRoute
    </div>

    @if($suppliers->isEmpty())
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-50"></i>
                <p class="mb-0">{{ __('suppliers.no_dues') }}</p>
            </div>
        </div>
    @else
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">{{ __('suppliers.name') }}</th>
                                <th>{{ __('suppliers.oldest_purchase') }}</th>
                                <th>{{ __('suppliers.last_payment') }}</th>
                                <th class="text-end">{{ __('suppliers.balance') }}</th>
                                <th class="text-end pe-3">{{ __('products.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($suppliers as $supplier)
                            <tr>
                                <td class="ps-3 fw-bold">{{ $supplier->name }}</td>
                                <td class="text-nowrap">{{ $supplier->oldest_purchase_date ?? '—' }}</td>
                                <td class="text-nowrap">{{ $supplier->last_payment_date ?? '—' }}</td>
                                <td class="text-end fw-bold">@npr($supplier->balance)</td>
                                <td class="text-end pe-3">
                                    @canRoute('suppliers.payments.create', $supplier)
                                    <a href="{{ route('suppliers.payments.create', $supplier) }}" class="btn btn-sm btn-primary text-nowrap" style="min-height:44px;">
                                        <i class="bi bi-cash-coin me-1"></i>{{ __('suppliers.record_payment') }}
                                    </a>
                                    @endCanRoute
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="ps-3">{{ __('suppliers.total_payable') }}</th>
                                <th class="text-end">@npr($totalPayable)</th>
                                <th class="pe-3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
