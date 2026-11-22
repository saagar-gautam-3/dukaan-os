@extends('layouts.app')

@section('title', __('suppliers.dues_title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">{{ __('suppliers.dues_title') }}</h2>
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
                                <th class="text-end pe-3">{{ __('suppliers.balance') }}</th>
                                <th class="text-end pe-3">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($suppliers as $supplier)
                            <tr>
                                <td class="ps-3 fw-bold">{{ $supplier->name }}</td>
                                <td>{{ $supplier->oldest_purchase_at ? \Carbon\Carbon::parse($supplier->oldest_purchase_at)->format('Y-m-d') : '—' }}</td>
                                <td>{{ $supplier->last_payment_at ? \Carbon\Carbon::parse($supplier->last_payment_at)->format('Y-m-d') : '—' }}</td>
                                <td class="text-end pe-3 fw-bold">{{ $supplier->balance }}</td>
                                <td class="text-end pe-3">
                                    @canRoute('suppliers.payments.create', $supplier)
                                    <a href="{{ route('suppliers.payments.create', $supplier) }}" class="btn btn-sm btn-primary" style="min-height:44px;">{{ __('suppliers.record_payment') }}</a>
                                    @endCanRoute
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="ps-3">{{ __('suppliers.total_payable') }}</th>
                                <th class="text-end pe-3 fw-bold">{{ $totalPayable }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection