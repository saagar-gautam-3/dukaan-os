@extends('layouts.app')

@section('title', __('udhar.ageing'))

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('udhar.outstanding') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 fw-bold mb-0">
                    <i class="bi bi-bar-chart-steps text-primary me-2"></i>{{ __('udhar.ageing') }}
                </h1>
                <small class="text-muted">{{ __('udhar.subtitle') }}</small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('udhar.outstanding') }}" class="btn btn-outline-primary">
                <i class="bi bi-journal-bookmark me-1"></i>{{ __('udhar.outstanding') }}
            </a>
        </div>
    </div>

    <!-- Ageing Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom p-3">
            <h5 class="mb-0 fw-bold">{{ __('udhar.ageing') }}</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('sales.customer') }}</th>
                        <th>{{ __('udhar.phone') }}</th>
                        <th class="text-end text-success">{{ __('udhar.bucket_current') }}</th>
                        <th class="text-end text-warning">{{ __('udhar.bucket_days_30') }}</th>
                        <th class="text-end text-primary">{{ __('udhar.bucket_days_60') }}</th>
                        <th class="text-end text-danger">{{ __('udhar.bucket_days_90') }}</th>
                        <th class="text-end text-danger fw-bold">{{ __('udhar.bucket_over_90') }}</th>
                        <th class="text-end fw-bold">{{ __('udhar.total_due') }}</th>
                        <th class="text-end">{{ __('udhar.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td>
                            <a href="{{ route('customers.show', $customer) }}" class="fw-bold text-decoration-none">
                                {{ $customer->name }}
                            </a>
                        </td>
                        <td>
                            <small class="text-muted">{{ $customer->phone ?: '-' }}</small>
                        </td>
                        <td class="text-end">
                            @if(bccomp((string) $customer->ageing_buckets['current'], '0', 2) > 0)
                                NPR @npr($customer->ageing_buckets['current'])
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(bccomp((string) $customer->ageing_buckets['days_30'], '0', 2) > 0)
                                <span class="text-warning fw-semibold">NPR @npr($customer->ageing_buckets['days_30'])</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(bccomp((string) $customer->ageing_buckets['days_60'], '0', 2) > 0)
                                <span class="text-primary fw-semibold">NPR @npr($customer->ageing_buckets['days_60'])</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(bccomp((string) $customer->ageing_buckets['days_90'], '0', 2) > 0)
                                <span class="text-danger fw-semibold">NPR @npr($customer->ageing_buckets['days_90'])</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(bccomp((string) $customer->ageing_buckets['over_90'], '0', 2) > 0)
                                <span class="badge bg-danger">NPR @npr($customer->ageing_buckets['over_90'])</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end fw-bold text-danger">
                            NPR @npr($customer->balance)
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @can('collect', $customer)
                                <a href="{{ route('udhar.collect', $customer) }}" class="btn btn-success" title="{{ __('udhar.collect_payment') }}">
                                    <i class="bi bi-cash-stack"></i>
                                </a>
                                @endcan
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary" title="{{ __('udhar.view_ledger') }}">
                                    <i class="bi bi-journal-text"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                            <p class="mb-0">{{ __('udhar.no_outstanding') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if(count($customers) > 0)
                <tfoot class="table-light fw-bold border-top border-2">
                    <tr>
                        <th colspan="2">{{ __('common.total') }}</th>
                        <th class="text-end text-success">NPR @npr($totals['current'] ?? '0.00')</th>
                        <th class="text-end text-warning">NPR @npr($totals['days_30'] ?? '0.00')</th>
                        <th class="text-end text-primary">NPR @npr($totals['days_60'] ?? '0.00')</th>
                        <th class="text-end text-danger">NPR @npr($totals['days_90'] ?? '0.00')</th>
                        <th class="text-end text-danger">NPR @npr($totals['over_90'] ?? '0.00')</th>
                        <th class="text-end text-danger">NPR @npr($totals['total'] ?? '0.00')</th>
                        <th></th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
