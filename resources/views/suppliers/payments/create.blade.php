@extends('layouts.app')

@section('title', __('suppliers.record_payment') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">{{ __('suppliers.record_payment') }}</h2>
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('common.back') }}</a>
    </div>

    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <h5 class="fw-bold">{{ $supplier->name }}</h5>
            <p class="mb-0">{{ __('suppliers.outstanding_balance') }}: <strong>{{ $balance }}</strong></p>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ 'sp_' . time() . '_' . uniqid() }}">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('suppliers.payment_amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('suppliers.payment_method') }}</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">{{ __('sales.cash') }}</option>
                            <option value="bank">{{ __('sales.bank') }}</option>
                            <option value="qr">{{ __('sales.qr') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('suppliers.payment_date') }}</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ now($supplier->shop->timezone ?? config('app.timezone'))->toDateString() }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('suppliers.reference_number') }}</label>
                        <input type="text" name="reference_number" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('suppliers.notes') }}</label>
                        <input type="text" name="notes" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" style="min-height:44px;">{{ __('suppliers.record_payment') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <h5 class="p-3 mb-0 border-bottom">{{ __('suppliers.ledger') }}</h5>
            @if($entries->isEmpty())
                <div class="text-center py-4 text-muted">{{ __('suppliers.no_ledger_entries') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('ui.date') }}</th>
                                <th>{{ __('suppliers.type') }}</th>
                                <th class="text-end">{{ __('suppliers.debit') }}</th>
                                <th class="text-end">{{ __('suppliers.credit') }}</th>
                                <th>{{ __('suppliers.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $entry)
                            <tr>
                                <td>{{ $entry->created_at?->format('Y-m-d') }}</td>
                                <td>{{ $entry->entry_type->label() ?? $entry->entry_type->value }}</td>
                                <td class="text-end">{{ $entry->debit }}</td>
                                <td class="text-end">{{ $entry->credit }}</td>
                                <td class="small text-muted">{{ $entry->notes }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection