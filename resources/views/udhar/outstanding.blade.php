@extends('layouts.app')

@section('title', __('udhar.title'))

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1">
                <i class="bi bi-journal-bookmark text-primary me-2"></i>{{ __('udhar.title') }}
            </h1>
            <p class="text-muted mb-0">{{ __('udhar.subtitle') }}</p>
        </div>
        <div class="action-row d-flex gap-2">
            @canRoute('udhar.ageing')
            <a href="{{ route('udhar.ageing') }}" class="btn btn-outline-primary">
                <i class="bi bi-bar-chart-steps me-1"></i>{{ __('udhar.ageing') }}
            </a>
            @endCanRoute
            @canRoute('customers.index')
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-people me-1"></i>{{ __('udhar.all_customers') }}
            </a>
            @endCanRoute
        </div>
    </div>

    <!-- Summary KPI Card -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 border-danger h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">
                        {{ __('udhar.total_outstanding') }}
                    </div>
                    <div class="h3 fw-bold text-danger mb-0">
                        @npr($totalOutstanding)
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">
                        {{ __('sales.customers') }}
                    </div>
                    <div class="h3 fw-bold text-primary mb-0">
                        {{ $customers->total() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 border-info h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">
                        {{ __('udhar.ageing') }}
                    </div>
                    @canRoute('udhar.ageing')
                    <a href="{{ route('udhar.ageing') }}" class="text-decoration-none fw-semibold">
                        {{ __('udhar.ageing') }} <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    @else
                    <span class="fw-semibold">{{ __('udhar.ageing') }}</span>
                    @endCanRoute
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('udhar.outstanding') }}" class="row g-2 align-items-center">
                <div class="col-md-9 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text"
                               name="search"
                               class="form-control border-start-0 ps-0"
                               placeholder="{{ __('udhar.search_customer') }}"
                               value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>{{ __('common.filter') }}
                    </button>
                    @if($search)
                    <a href="{{ route('udhar.outstanding') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Outstanding Customers Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom p-3">
            <h5 class="mb-0 fw-bold">{{ __('udhar.outstanding') }}</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('sales.customer') }}</th>
                        <th>{{ __('udhar.phone') }}</th>
                        <th class="text-end">{{ __('udhar.credit_limit') }}</th>
                        <th class="text-end">{{ __('udhar.balance') }}</th>
                        <th class="text-end">{{ __('udhar.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td>
                            @canRoute('customers.show', $customer)
                            <a href="{{ route('customers.show', $customer) }}" class="fw-bold text-decoration-none">
                                {{ $customer->name }}
                            </a>
                            @else
                            <span class="fw-bold">{{ $customer->name }}</span>
                            @endCanRoute
                            @if(\App\Support\Money::compare((string) $customer->credit_limit, '0', 2) > 0 && \App\Support\Money::compare((string) $customer->balance, (string) $customer->credit_limit, 2) >= 0)
                                <span class="badge bg-danger ms-1" title="{{ __('udhar.credit_limit_exceeded_notice') }}">
                                    <i class="bi bi-exclamation-triangle"></i> {{ __('udhar.credit_limit') }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($customer->phone)
                                <span>{{ $customer->phone }}</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(\App\Support\Money::compare((string) $customer->credit_limit, '0', 2) > 0)
                                @npr($customer->credit_limit)
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="fw-bold fs-6 text-danger">
                                @npr($customer->balance)
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @canRoute('udhar.collect', $customer)
                                <a href="{{ route('udhar.collect', $customer) }}" class="btn btn-success" title="{{ __('udhar.collect_payment') }}">
                                    <i class="bi bi-cash-stack me-1"></i>{{ __('udhar.collect') }}
                                </a>
                                @endCanRoute
                                @canRoute('customers.show', $customer)
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary" title="{{ __('udhar.view_ledger') }}">
                                    <i class="bi bi-journal-text"></i>
                                </a>
                                @endCanRoute
                                @canRoute('udhar.reminders.send', $customer)
                                @php
                                    $lastReminder = $customer->reminderLogs()->latest()->first();
                                    $canRemind = $customer->phone && \App\Support\NepaliPhone::isValidMobile($customer->phone)
                                        && \App\Support\Money::compare((string) $customer->balance, '0', 2) > 0;
                                @endphp
                                <form method="POST" action="{{ route('udhar.reminders.send', $customer) }}" class="d-inline" target="_blank">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success btn-sm" @disabled(!$canRemind) title="{{ __('udhar.send_reminder') }}">
                                        <i class="bi bi-whatsapp"></i>
                                    </button>
                                </form>
                                @if($lastReminder)
                                    <small class="text-muted d-block">{{ __('udhar.last_reminded', ['time' => $lastReminder->created_at->diffForHumans()]) }}</small>
                                    @if($lastReminder->created_at->gt(now()->subDay()))
                                        <span class="badge bg-info">{{ __('udhar.reminded_recently') }}</span>
                                    @endif
                                @endif
                                @endCanRoute
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                            <p class="mb-0">{{ __('udhar.no_outstanding') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($customers->hasPages())
        <div class="card-footer bg-white border-top p-3">
            {{ $customers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
