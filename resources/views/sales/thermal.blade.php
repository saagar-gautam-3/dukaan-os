<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt - {{ $sale->invoice_no ?? '#' . $sale->id }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Mukta', 'Courier New', monospace;
            background: #f1f5f9;
            color: #000;
            font-size: 12px;
            line-height: 1.3;
        }
        .receipt-container {
            width: 80mm;
            max-width: 80mm;
            margin: 15px auto;
            background: #fff;
            padding: 4mm 3mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: 700; }
        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        .double-divider {
            border-top: 2px dashed #000;
            margin: 6px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 2px 0;
        }
        .print-btn-bar {
            text-align: center;
            padding: 10px;
            background: #1e293b;
        }
        .btn {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 6px 16px;
            font-size: 13px;
            border-radius: 4px;
            cursor: pointer;
        }
        @media print {
            body {
                background: #fff;
            }
            .print-btn-bar {
                display: none !important;
            }
            .receipt-container {
                width: 80mm;
                max-width: 80mm;
                margin: 0;
                padding: 2mm;
                box-shadow: none;
            }
            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button class="btn" onclick="window.print()">Print Receipt (80mm)</button>
    </div>

    <div class="receipt-container">
        <!-- Shop Header -->
        <div class="text-center">
            <h3 class="fw-bold" style="font-size: 15px;">{{ $sale->shop->name }}</h3>
            @if(!empty($sale->shop->settings['address']))
                <div>{{ $sale->shop->settings['address'] }}</div>
            @endif
            @if(!empty($sale->shop->settings['phone']))
                <div>Ph: {{ $sale->shop->settings['phone'] }}</div>
            @endif
            @if(!empty($sale->shop->settings['pan_number']))
                <div>PAN: {{ $sale->shop->settings['pan_number'] }}</div>
            @endif
        </div>

        <div class="divider"></div>

        <!-- Meta -->
        <div>
            <div><strong>Bill:</strong> {{ $sale->invoice_no ?? ('Draft #' . $sale->id) }}</div>
            <div><strong>Date:</strong> {{ $sale->created_at->format('Y-m-d H:i') }}</div>
            <div><strong>Customer:</strong> {{ $sale->customer?->name ?? 'Walk-in' }}</div>
            @if($sale->customer?->phone)
                <div><strong>Phone:</strong> {{ $sale->customer->phone }}</div>
            @endif
        </div>

        <div class="divider"></div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th class="text-left" style="width: 45%;">Item</th>
                    <th class="text-center" style="width: 15%;">Qty</th>
                    <th class="text-right" style="width: 20%;">Rate</th>
                    <th class="text-right" style="width: 20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td class="text-left">
                        <div class="fw-bold">{{ $item->product->name }}</div>
                    </td>
                    <td class="text-center">{{ (float) $item->quantity }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <!-- Totals -->
        <table>
            <tr>
                <td class="text-left">Subtotal:</td>
                <td class="text-right">Rs. {{ number_format((float) $sale->subtotal, 2) }}</td>
            </tr>
            @if((float) $sale->discount_total > 0)
            <tr>
                <td class="text-left">Discount:</td>
                <td class="text-right">-Rs. {{ number_format((float) $sale->discount_total, 2) }}</td>
            </tr>
            @endif
            @if((float) $sale->tax_total > 0)
            <tr>
                <td class="text-left">Tax:</td>
                <td class="text-right">+Rs. {{ number_format((float) $sale->tax_total, 2) }}</td>
            </tr>
            @endif
            <tr class="fw-bold" style="font-size: 13px;">
                <td class="text-left">Total:</td>
                <td class="text-right">Rs. {{ number_format((float) $sale->grand_total, 2) }}</td>
            </tr>
            <tr>
                <td class="text-left">Paid:</td>
                <td class="text-right">Rs. {{ number_format((float) $sale->paid_total, 2) }}</td>
            </tr>
            @php $due = (float) $sale->dueAmount(); @endphp
            @if($due > 0)
            <tr class="fw-bold">
                <td class="text-left">Due / बाँकी:</td>
                <td class="text-right">Rs. {{ number_format($due, 2) }}</td>
            </tr>
            @endif
        </table>

        @if($sale->paymentAllocations->isNotEmpty())
        <div class="divider"></div>
        <div>
            @foreach($sale->paymentAllocations as $alloc)
                <div>Paid via {{ strtoupper($alloc->payment->method instanceof \BackedEnum ? $alloc->payment->method->value : (string) $alloc->payment->method) }}: Rs. {{ number_format((float) $alloc->amount, 2) }}</div>
            @endforeach
        </div>
        @endif

        <div class="double-divider"></div>

        <div class="text-center">
            <div>धन्यवाद! फेरि आउनुहोला!</div>
            <div style="font-size: 10px; color: #555;">Dukaan OS POS</div>
        </div>
    </div>
</body>
</html>
