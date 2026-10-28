<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->invoice_no ?? 'Invoice' }} - {{ $sale->shop->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Mukta', 'Noto Sans Devanagari', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            font-size: 14px;
        }
        .invoice-box {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background: #ffffff;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
        }
        .invoice-title {
            letter-spacing: 1px;
            color: #0d6efd;
        }
        .table-invoice th {
            background-color: #f1f5f9;
            font-weight: 600;
            border-bottom: 2px solid #cbd5e1;
        }
        .table-invoice td {
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }
        @media print {
            body {
                background: #ffffff;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .invoice-box {
                max-width: 100%;
                margin: 0;
                padding: 20px;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print text-center py-3 bg-dark text-white d-flex justify-content-center gap-3">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4">
            <i class="bi bi-printer me-2"></i> {{ __('sales.print_invoice') }}
        </button>
        <button onclick="window.close()" class="btn btn-outline-light">
            Close
        </button>
    </div>

    <div class="invoice-box">
        <!-- Header -->
        <div class="row align-items-center mb-4 pb-3 border-bottom">
            <div class="col-7">
                <h2 class="fw-bold mb-1 text-primary">{{ $sale->shop->name }}</h2>
                @if(!empty($sale->shop->settings['address']))
                    <p class="mb-0 text-muted">{{ $sale->shop->settings['address'] }}</p>
                @endif
                @if(!empty($sale->shop->settings['phone']))
                    <p class="mb-0 text-muted"><i class="bi bi-telephone me-1"></i>{{ $sale->shop->settings['phone'] }}</p>
                @endif
                @if(!empty($sale->shop->settings['pan_number']))
                    <p class="mb-0 fw-semibold text-dark">PAN/VAT: {{ $sale->shop->settings['pan_number'] }}</p>
                @endif
            </div>
            <div class="col-5 text-end">
                <h4 class="text-uppercase fw-bold text-secondary mb-1">{{ __('sales.invoice') }}</h4>
                <div class="fw-bold fs-6 text-dark">{{ $sale->invoice_no ?? ('Draft #' . $sale->id) }}</div>
                <div class="text-muted small">{{ __('sales.date') }}: {{ $sale->created_at->format('Y-m-d') }}</div>
                <div class="text-muted small">{{ __('sales.sold_by') }}: {{ $sale->postedBy?->name ?? 'Staff' }}</div>
            </div>
        </div>

        <!-- Bill To -->
        <div class="row mb-4">
            <div class="col-6">
                <div class="text-uppercase text-muted small fw-bold mb-1">{{ __('sales.bill_to') }}:</div>
                @if($sale->customer)
                    <h5 class="fw-bold mb-1 text-dark">{{ $sale->customer->name }}</h5>
                    @if($sale->customer->phone)
                        <div class="text-muted small"><i class="bi bi-telephone me-1"></i>{{ $sale->customer->phone }}</div>
                    @endif
                    @if($sale->customer->address)
                        <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $sale->customer->address }}</div>
                    @endif
                    @if($sale->customer->pan_no)
                        <div class="text-muted small">PAN: {{ $sale->customer->pan_no }}</div>
                    @endif
                @else
                    <div class="fw-semibold text-secondary">{{ __('sales.walk_in') }}</div>
                @endif
            </div>
            <div class="col-6 text-end">
                <div class="badge bg-{{ $sale->isPosted() ? 'success' : 'warning' }}-subtle text-{{ $sale->isPosted() ? 'success' : 'warning' }} fs-6 px-3 py-2 border">
                    {{ $sale->isPosted() ? __('sales.status_posted') : __('sales.status_draft') }}
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-invoice mb-0">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Item / विवरण</th>
                        <th class="text-center" style="width: 12%;">Qty / मात्रा</th>
                        <th class="text-end" style="width: 18%;">Rate / दर (Rs.)</th>
                        <th class="text-end" style="width: 20%;">Total / जम्मा (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->items as $idx => $item)
                    <tr>
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $item->product->name }}</div>
                            @if($item->product->name_ne)
                                <div class="text-muted small">{{ $item->product->name_ne }}</div>
                            @endif
                        </td>
                        <td class="text-center fw-semibold">
                            {{ (float) $item->quantity }} {{ $item->product->unit?->symbol }}
                        </td>
                        <td class="text-end">
                            {{ number_format((float) $item->unit_price, 2) }}
                        </td>
                        <td class="text-end fw-bold">
                            {{ number_format((float) $item->line_total, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Calculation & Totals -->
        <div class="row mb-4">
            <div class="col-6">
                @if($sale->notes)
                <div class="p-3 bg-light rounded">
                    <div class="text-uppercase text-muted small fw-bold mb-1">{{ __('sales.notes') }}:</div>
                    <p class="mb-0 small">{{ $sale->notes }}</p>
                </div>
                @endif

                @if($sale->paymentAllocations->isNotEmpty())
                <div class="mt-3">
                    <div class="text-uppercase text-muted small fw-bold mb-1">{{ __('sales.payment') }}:</div>
                    @foreach($sale->paymentAllocations as $alloc)
                        <div class="small text-muted">
                            <span class="text-uppercase fw-semibold">{{ $alloc->payment->method instanceof \BackedEnum ? $alloc->payment->method->value : $alloc->payment->method }}:</span>
                            Rs. {{ number_format((float) $alloc->amount, 2) }}
                            @if($alloc->payment->reference_number)
                                (Ref: {{ $alloc->payment->reference_number }})
                            @endif
                        </div>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="col-6">
                <div class="table-responsive">
                    <table class="table table-sm table-borderless text-end mb-0">
                        <tr>
                            <td class="text-muted">{{ __('sales.subtotal') }}:</td>
                            <td class="fw-semibold">Rs. {{ number_format((float) $sale->subtotal, 2) }}</td>
                        </tr>
                        @if((float) $sale->discount_total > 0)
                        <tr>
                            <td class="text-danger">{{ __('sales.discount') }}:</td>
                            <td class="text-danger">-Rs. {{ number_format((float) $sale->discount_total, 2) }}</td>
                        </tr>
                        @endif
                        @if((float) $sale->tax_total > 0)
                        <tr>
                            <td class="text-muted">{{ __('sales.tax') }}:</td>
                            <td class="fw-semibold">+Rs. {{ number_format((float) $sale->tax_total, 2) }}</td>
                        </tr>
                        @endif
                        <tr class="border-top">
                            <td class="fs-5 fw-bold text-dark">{{ __('sales.grand_total') }}:</td>
                            <td class="fs-5 fw-bold text-primary">Rs. {{ number_format((float) $sale->grand_total, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-success fw-semibold">{{ __('sales.paid') }}:</td>
                            <td class="text-success fw-semibold">Rs. {{ number_format((float) $sale->paid_total, 2) }}</td>
                        </tr>
                        @php $due = (float) $sale->dueAmount(); @endphp
                        @if($due > 0)
                        <tr class="border-top">
                            <td class="fw-bold text-danger">{{ __('sales.due') }}:</td>
                            <td class="fw-bold text-danger">Rs. {{ number_format($due, 2) }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center pt-4 border-top text-muted small">
            <p class="mb-1 fw-semibold">धन्यवाद! पुन: आउनुहोला (Thank you for your business!)</p>
            <p class="mb-0 opacity-75">Generated via {{ config('app.name', 'Dukaan OS') }}</p>
        </div>
    </div>
</body>
</html>
