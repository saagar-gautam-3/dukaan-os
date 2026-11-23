<form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ 'sp_'.time().'_'.bin2hex(random_bytes(8)) }}">
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="amount" class="form-label">{{ __('suppliers.payment_amount') }} <span class="text-danger">*</span></label>
            <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" inputmode="decimal" value="{{ old('amount') }}" required style="min-height:44px;">
        </div>
        <div class="col-12 col-md-6">
            <label for="payment_method" class="form-label">{{ __('suppliers.payment_method') }} <span class="text-danger">*</span></label>
            <select id="payment_method" name="payment_method" class="form-select" required style="min-height:44px;">
                @foreach(['cash' => __('ui.payment_cash'), 'bank' => __('ui.bank_transfer'), 'qr' => __('ui.qr_fonepay')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="payment_date" class="form-label">{{ __('suppliers.payment_date') }}</label>
            <input type="date" id="payment_date" name="payment_date" class="form-control" value="{{ old('payment_date', now($shopTimezone)->toDateString()) }}" style="min-height:44px;">
        </div>
        <div class="col-12 col-md-6">
            <label for="reference_number" class="form-label">{{ __('suppliers.reference_number') }}</label>
            <input type="text" id="reference_number" name="reference_number" class="form-control" maxlength="100" value="{{ old('reference_number') }}" style="min-height:44px;">
        </div>
        <div class="col-12">
            <label for="notes" class="form-label">{{ __('suppliers.notes') }}</label>
            <input type="text" id="notes" name="notes" class="form-control" maxlength="255" value="{{ old('notes') }}" style="min-height:44px;">
        </div>
    </div>
    <div class="mt-3 d-grid d-md-flex">
        <button type="submit" class="btn btn-primary w-100" style="min-height:44px;">
            <i class="bi bi-cash-coin me-1"></i>{{ __('suppliers.record_payment') }}
        </button>
    </div>
</form>
