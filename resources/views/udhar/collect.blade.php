@extends('layouts.app')

@section('title', __('udhar.collect_payment') . ' - ' . $customer->name)

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 fw-bold mb-0">
                    <i class="bi bi-cash-stack text-success me-2"></i>{{ __('udhar.collect_payment') }}
                </h1>
                <small class="text-muted">{{ $customer->name }} ({{ $customer->phone ?: __('ui.no_phone') }})</small>
            </div>
        </div>
        <div>
            <a href="{{ route('udhar.outstanding') }}" class="btn btn-outline-primary">
                <i class="bi bi-journal-bookmark me-1"></i>{{ __('udhar.outstanding') }}
            </a>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>{{ __('common.error_generic') }}</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">
        <!-- Customer Info & Balance Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="mb-0 fw-bold">{{ __('udhar.customer_details') }}</h5>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('sales.customer') }}</small>
                        <span class="fs-5 fw-bold text-dark">{{ $customer->name }}</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('udhar.phone') }}</small>
                        <span class="fw-semibold">{{ $customer->phone ?: '-' }}</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('udhar.credit_limit') }}</small>
                        <span class="fw-semibold">
                            @if(\App\Support\Money::compare((string) $customer->credit_limit, '0', 2) > 0)
                                {{ __('ui.currency_symbol') }} @npr($customer->credit_limit)
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </span>
                    </div>
                    <hr class="my-3">
                    <div class="p-3 bg-light rounded text-center">
                        <small class="text-muted text-uppercase fw-semibold d-block mb-1">{{ __('udhar.balance') }}</small>
                        <div class="h3 fw-bold text-danger mb-0">
                            {{ __('ui.currency_symbol') }} @npr($balance)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collection Form -->
        <div class="col-lg-8">
            <form method="POST" action="{{ route('udhar.collect.store', $customer) }}" id="collectionForm">
                @csrf

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom p-3">
                        <h5 class="mb-0 fw-bold">{{ __('udhar.collect_payment') }}</h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="amountInput" class="form-label fw-bold">
                                    {{ __('udhar.collection_amount') }} <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold">{{ __('ui.currency_symbol') }}</span>
                                    <input type="number"
                                           name="amount"
                                           id="amountInput"
                                           class="form-control form-control-lg fw-bold text-success"
                                           step="0.01"
                                           min="0.01"
                                           value="{{ old('amount', $balance > 0 ? $balance : '') }}"
                                           required
                                           autofocus>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="paymentMethodSelect" class="form-label fw-bold">
                                    {{ __('udhar.payment_method') }} <span class="text-danger">*</span>
                                </label>
                                <select name="payment_method" id="paymentMethodSelect" class="form-select form-select-lg" required>
                                    <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>{{ __('ui.payment_cash') }}</option>
                                    <option value="qr" {{ old('payment_method') === 'qr' ? 'selected' : '' }}>{{ __('ui.qr_fonepay') }}</option>
                                    <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>{{ __('ui.bank_transfer') }}</option>
                                    <option value="cheque" {{ old('payment_method') === 'cheque' ? 'selected' : '' }}>{{ __('ui.cheque') }}</option>
                                    <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>{{ __('ui.other') }}</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="notesInput" class="form-label">{{ __('udhar.notes') }}</label>
                                <input type="text"
                                       name="notes"
                                       id="notesInput"
                                       class="form-control"
                                       placeholder="{{ __('udhar.reference_placeholder') }}"
                                       value="{{ old('notes') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice Allocations -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 fw-bold">{{ __('udhar.open_invoices') }}</h5>
                            <small class="text-muted">{{ __('udhar.oldest_first_hint') }}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="autoAllocateBtn">
                            <i class="bi bi-magic me-1"></i>{{ __('udhar.allocate_amount') }}
                        </button>
                    </div>

                    @if($openSales->isEmpty())
                        <div class="card-body p-4 text-center text-muted">
                            <i class="bi bi-info-circle fs-3 text-info d-block mb-2"></i>
                            <p class="mb-1">{{ __('udhar.no_open_invoices') }}</p>
                            <small>{{ __('udhar.advance_amount') }} {{ __('ui.advance_credit_note') }}</small>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="invoicesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('udhar.invoice_number') }}</th>
                                        <th>{{ __('udhar.due_date') }}</th>
                                        <th class="text-end">{{ __('udhar.original_total') }}</th>
                                        <th class="text-end">{{ __('udhar.remaining_due') }}</th>
                                        <th class="text-end" style="width: 200px;">{{ __('udhar.allocate_amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($openSales as $index => $sale)
                                    @php
                                        $due = $sale->dueAmount();
                                    @endphp
                                    <tr class="invoice-row" data-sale-id="{{ $sale->id }}" data-due="{{ $due }}">
                                        <td>
                                            <a href="{{ route('sales.show', $sale) }}" target="_blank" class="fw-bold text-decoration-none">
                                                {{ $sale->invoice_no ?? ('Draft #' . $sale->id) }}
                                            </a>
                                            <input type="hidden" name="allocations[{{ $index }}][sale_id]" value="{{ $sale->id }}">
                                        </td>
                                        <td>
                                            @if($sale->due_date)
                                                <span class="{{ $sale->due_date < now()->toDateString() ? 'text-danger fw-semibold' : '' }}">
                                                    {{ $sale->due_date }}
                                                </span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ __('ui.currency_symbol') }} @npr($sale->grand_total)</td>
                                        <td class="text-end fw-semibold text-danger">{{ __('ui.currency_symbol') }} @npr($due)</td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">{{ __('ui.currency_symbol') }}</span>
                                                <input type="number"
                                                       name="allocations[{{ $index }}][amount]"
                                                       class="form-control text-end allocation-input"
                                                       step="0.01"
                                                       min="0"
                                                       max="{{ $due }}"
                                                       value="{{ old('allocations.'.$index.'.amount', '0.00') }}"
                                                       data-index="{{ $index }}">
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="card-footer bg-light p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted">{{ __('udhar.advance_amount') }}:</span>
                                <span class="fw-bold fs-6 ms-2 text-info" id="advanceDisplay">{{ __('ui.currency_symbol') }} 0.00</span>
                            </div>
                            <div>
                                <span class="text-muted">{{ __('common.total') }}:</span>
                                <span class="fw-bold fs-6 ms-2 text-dark" id="totalAllocatedDisplay">{{ __('ui.currency_symbol') }} 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">
                        {{ __('common.cancel') }}
                    </a>
                    <button type="submit" class="btn btn-success px-4" id="submitBtn">
                        <i class="bi bi-check2-circle me-1"></i>{{ __('udhar.collect_payment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const currencySymbol = @json(__('ui.currency_symbol'));
document.addEventListener('DOMContentLoaded', function () {
    const amountInput = document.getElementById('amountInput');
    const autoAllocateBtn = document.getElementById('autoAllocateBtn');
    const allocationInputs = document.querySelectorAll('.allocation-input');
    const advanceDisplay = document.getElementById('advanceDisplay');
    const totalAllocatedDisplay = document.getElementById('totalAllocatedDisplay');

    function calculateAllocations() {
        const totalAmount = parseFloat(amountInput.value) || 0;
        let sumAllocated = 0;

        allocationInputs.forEach(input => {
            const val = parseFloat(input.value) || 0;
            sumAllocated += val;
        });

        const advance = Math.max(0, totalAmount - sumAllocated);

        if (advanceDisplay) {
            advanceDisplay.textContent = currencySymbol + ' ' + advance.toFixed(2);
        }
        if (totalAllocatedDisplay) {
            totalAllocatedDisplay.textContent = currencySymbol + ' ' + sumAllocated.toFixed(2);
        }
    }

    function autoAllocate() {
        let remaining = parseFloat(amountInput.value) || 0;

        allocationInputs.forEach(input => {
            const row = input.closest('.invoice-row');
            if (!row) return;
            const due = parseFloat(row.getAttribute('data-due')) || 0;
            const alloc = Math.min(remaining, due);
            input.value = alloc.toFixed(2);
            remaining -= alloc;
        });

        calculateAllocations();
    }

    if (autoAllocateBtn) {
        autoAllocateBtn.addEventListener('click', autoAllocate);
    }

    if (amountInput) {
        amountInput.addEventListener('input', function () {
            autoAllocate();
        });
    }

    allocationInputs.forEach(input => {
        input.addEventListener('input', calculateAllocations);
    });

    // Run auto-allocate on initial page load if amount has a value
    if (amountInput && parseFloat(amountInput.value) > 0) {
        autoAllocate();
    } else {
        calculateAllocations();
    }
});
</script>
@endsection
