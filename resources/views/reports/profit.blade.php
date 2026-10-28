@extends('layouts.app')

@section('title', __('reports.profit_report'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h1 class="h3 fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2"></i>{{ __('reports.profit_report') }}</h1>
        <a href="{{ route('reports.profit.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i> {{ __('reports.export_csv') }}
        </a>
    </div>

    {{-- Date Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Period</label>
                    <select name="range" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="today" @selected(request('range','today')==='today')>{{ __('reports.today') }}</option>
                        <option value="this_week" @selected(request('range')==='this_week')>{{ __('reports.this_week') }}</option>
                        <option value="this_month" @selected(request('range')==='this_month')>{{ __('reports.this_month') }}</option>
                        <option value="custom" @selected(request('range')==='custom')>{{ __('reports.custom') }}</option>
                    </select>
                </div>
                @if(request('range') === 'custom')
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">{{ __('reports.from') }}</label>
                    <input type="date" name="custom_from" class="form-control form-control-sm" value="{{ request('custom_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">{{ __('reports.to') }}</label>
                    <input type="date" name="custom_to" class="form-control form-control-sm" value="{{ request('custom_to') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-funnel me-1"></i> Apply</button>
                </div>
                @endif
            </form>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        @php
        $cards = [
            ['label' => __('reports.gross_sales'),   'value' => $summary['gross_sales'],   'icon' => 'cart-check',      'color' => 'primary'],
            ['label' => __('reports.net_sales'),     'value' => $summary['net_sales'],     'icon' => 'receipt',         'color' => 'info'],
            ['label' => __('reports.cogs'),          'value' => $summary['cogs'],          'icon' => 'box-seam',        'color' => 'secondary'],
            ['label' => __('reports.gross_profit'),  'value' => $summary['gross_profit'],  'icon' => 'graph-up',        'color' => 'success'],
            ['label' => __('reports.operating_expenses'), 'value' => $summary['total_expenses'], 'icon' => 'wallet2',  'color' => 'warning'],
            ['label' => __('reports.net_profit'),    'value' => $summary['net_profit'],    'icon' => 'graph-up-arrow',  'color' => $summary['net_profit'] >= 0 ? 'success' : 'danger'],
            ['label' => __('reports.cash_received'), 'value' => $summary['cash_received'], 'icon' => 'cash-coin',       'color' => 'teal'],
            ['label' => __('reports.outstanding'),   'value' => $summary['outstanding'],   'icon' => 'hourglass-split', 'color' => 'danger'],
        ];
        @endphp
        @foreach($cards as $card)
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">{{ $card['label'] }}</div>
                            <div class="h4 fw-bold text-{{ $card['color'] }} mt-1 mb-0">
                                Rs {{ number_format((float) $card['value'], 2) }}
                            </div>
                        </div>
                        <i class="bi bi-{{ $card['icon'] }} fs-3 text-{{ $card['color'] }} opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Detail Table --}}
    <div class="card border-0 shadow-sm" style="max-width:500px">
        <div class="card-header bg-white fw-semibold">{{ __('reports.profit_report') }} — {{ $summary['sale_count'] }} {{ __('reports.sale_count') }}</div>
        <table class="table mb-0">
            <tbody>
                @foreach([
                    ['Gross Sales', $summary['gross_sales']],
                    ['Tax Collected', $summary['tax_total']],
                    ['Returns', '('.$summary['return_total'].')'],
                    ['Net Sales', $summary['net_sales']],
                    ['COGS', '('.$summary['cogs'].')'],
                    ['Gross Profit', $summary['gross_profit']],
                    ['Operating Expenses', '('.$summary['total_expenses'].')'],
                    ['Net Profit', $summary['net_profit']],
                ] as [$label, $val])
                <tr>
                    <td class="text-muted">{{ $label }}</td>
                    <td class="text-end fw-semibold">Rs {{ is_string($val) ? $val : number_format((float)$val, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

