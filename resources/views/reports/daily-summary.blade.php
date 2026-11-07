@extends('layouts.app')

@section('title', __('daily.title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div class="min-w-0">
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-speedometer2 me-2"></i>{{ __('daily.title') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('daily.subtitle') }}</p>
        </div>

        <div class="action-row d-flex gap-2 mt-3 mt-md-0">
            <form method="GET" action="{{ route('reports.daily-summary') }}" class="d-flex gap-2 align-items-center flex-wrap">
                <button type="submit" name="date" value="{{ $prevDate }}" class="btn btn-outline-secondary btn-sm" title="{{ __('daily.previous_day') }}">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <input type="date" name="date" value="{{ $date }}" max="{{ $date }}" class="form-control form-control-sm">
                <button type="submit" name="date" value="{{ $nextDate }}" class="btn btn-outline-secondary btn-sm" title="{{ __('daily.next_day') }}" @disabled($nextDate > $date)>
                    <i class="bi bi-chevron-right"></i>
                </button>
                <a href="{{ route('reports.daily-summary') }}" class="btn btn-outline-primary btn-sm">
                    {{ __('daily.today') }}
                </a>
                @if($whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                    <i class="bi bi-whatsapp me-1"></i>{{ __('daily.share_whatsapp') }}
                </a>
                @endif
            </form>
        </div>
    </div>

    @if($error)
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('common.close') }}"></button>
        </div>
    @endif

    @if($summary)
        <div class="row g-3">
            @if($strings['sales'] ?? false)
            <div class="col-12 col-lg-6">
                <div class="card h-100 border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-receipt text-primary"></i>
                        <h5 class="fw-bold mb-0 text-dark">{{ __('daily.sales') }}</h5>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-wrap gap-4 mb-3">
                            <div>
                                <small class="text-muted text-uppercase">{{ __('daily.bills') }}</small>
                                <div class="h4 fw-bold mb-0">{{ $summary['sales']['bill_count'] }}</div>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase">{{ __('daily.net_sales') }}</small>
                                <div class="h4 fw-bold mb-0 text-primary">@npr($summary['sales']['net_sales'])</div>
                            </div>
                        </div>
                        <div class="small">
                            <div class="d-flex justify-content-between py-1 border-top">
                                <span class="text-muted">{{ __('daily.total_sales') }}</span>
                                <span class="fw-semibold amount-nowrap">@npr($summary['sales']['total'])</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-top">
                                <span class="text-muted"><i class="bi bi-cash-stack me-1"></i>{{ __('daily.cash_sales') }}</span>
                                <span class="fw-semibold amount-nowrap">@npr($summary['sales']['cash'])</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-top">
                                <span class="text-muted"><i class="bi bi-phone me-1"></i>{{ __('daily.digital_sales') }}</span>
                                <span class="fw-semibold amount-nowrap">@npr($summary['sales']['digital'])</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-top">
                                <span class="text-muted"><i class="bi bi-journal-bookmark me-1"></i>{{ __('daily.credit_sales') }}</span>
                                <span class="fw-semibold amount-nowrap">@npr($summary['sales']['credit'])</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-top">
                                <span class="text-muted"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('daily.returns') }} ({{ $summary['sales']['returns_count'] }})</span>
                                <span class="fw-semibold text-danger amount-nowrap">@npr($summary['sales']['returns_amount'])</span>
                            </div>
                            @canRoute('reports.profit')
                            <div class="d-flex justify-content-between py-1 border-top">
                                <span class="text-muted"><i class="bi bi-graph-up-arrow me-1"></i>{{ __('daily.gross_profit') }}</span>
                                <span class="fw-semibold amount-nowrap">@npr($summary['sales']['gross_profit'])</span>
                            </div>
                            @endCanRoute
                        </div>

                        @if(!empty($summary['top_products']))
                        <h6 class="fw-bold text-dark mt-3 mb-2">{{ __('daily.top_products') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('daily.product') }}</th>
                                        <th class="text-end">{{ __('daily.quantity') }}</th>
                                        <th class="text-end">{{ __('daily.amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summary['top_products'] as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="text-end amount-nowrap">@qty($row['qty'], 3)</td>
                                        <td class="text-end fw-semibold amount-nowrap">@npr($row['amount'])</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @if($strings['udhar'] ?? false)
            <div class="col-12 col-lg-6">
                <div class="card h-100 border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-journal-bookmark text-danger"></i>
                        <h5 class="fw-bold mb-0 text-dark">{{ __('daily.udhar') }}</h5>
                    </div>
                    <div class="card-body pt-0 small">
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ __('daily.udhar_given') }}</span>
                            <span class="fw-semibold amount-nowrap">@npr($summary['udhar']['given'])</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ __('daily.udhar_collected') }}</span>
                            <span class="fw-semibold amount-nowrap">@npr($summary['udhar']['collected'])</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ __('daily.receivable') }}</span>
                            <span class="fw-semibold text-danger amount-nowrap">@npr($summary['udhar']['receivable_now'])</span>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($strings['cash'] ?? false)
            <div class="col-12 col-lg-6">
                <div class="card h-100 border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-cash-stack text-success"></i>
                        <h5 class="fw-bold mb-0 text-dark">{{ __('daily.cash') }}</h5>
                    </div>
                    <div class="card-body pt-0 small">
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ __('daily.cash_in') }}</span>
                            <span class="fw-semibold amount-nowrap">@npr($summary['cash']['in'])</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ __('daily.cash_out') }}</span>
                            <span class="fw-semibold amount-nowrap">@npr($summary['cash']['out'])</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ __('daily.cash') }}</span>
                            <span class="fw-semibold amount-nowrap">@npr($summary['cash']['net'])</span>
                        </div>
                        @if($summary['cash']['open_drawer_balance'] !== null)
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ __('daily.drawer_balance') }}</span>
                            <span class="fw-semibold amount-nowrap">@npr($summary['cash']['open_drawer_balance'])</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @if($strings['expenses'] ?? false)
            <div class="col-12 col-lg-6">
                <div class="card h-100 border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-wallet2 text-warning"></i>
                        <h5 class="fw-bold mb-0 text-dark">{{ __('daily.expenses') }} <span class="fw-semibold amount-nowrap">@npr($summary['expenses']['total'])</span></h5>
                    </div>
                    <div class="card-body pt-0 small">
                        @if(!empty($summary['expenses']['top_categories']))
                        <h6 class="fw-bold text-dark mb-2">{{ __('daily.top_categories') }}</h6>
                        @foreach($summary['expenses']['top_categories'] as $row)
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ $row['name'] }}</span>
                            <span class="fw-semibold amount-nowrap">@npr($row['total'])</span>
                        </div>
                        @endforeach
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @canRoute('purchases.index')
            <div class="col-12 col-lg-6">
                <div class="card h-100 border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-cart3 text-info"></i>
                        <h5 class="fw-bold mb-0 text-dark">{{ __('daily.purchases') }}</h5>
                    </div>
                    <div class="card-body pt-0 small">
                        <div class="d-flex justify-content-between py-1 border-top">
                            <span class="text-muted">{{ __('daily.posted_purchases') }}</span>
                            <span class="fw-semibold amount-nowrap">@npr($summary['purchases']['total'])</span>
                        </div>
                    </div>
                </div>
            </div>
            @endCanRoute

            @if($strings['stock'] ?? false)
            <div class="col-12 col-lg-6">
                <div class="card h-100 border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-diamond text-warning"></i>
                        <h5 class="fw-bold mb-0 text-dark">{{ __('daily.stock') }} ({{ $summary['stock']['low_stock_count'] }})</h5>
                    </div>
                    <div class="card-body pt-0 small">
                        @if(!empty($summary['stock']['most_urgent']))
                        <h6 class="fw-bold text-dark mb-2">{{ __('daily.most_urgent') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('daily.product') }}</th>
                                        <th class="text-end">{{ __('daily.quantity') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summary['stock']['most_urgent'] as $row)
                                    <tr>
                                        <td>{{ $row['product']->name }}</td>
                                        <td class="text-end amount-nowrap">@qty($row['current'], 3) / @qty($row['threshold'], 3)</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    @endif
</div>
@endsection
