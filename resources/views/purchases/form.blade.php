@extends('layouts.app')

@section('title', __('purchases.create_purchase') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-cart-plus me-2"></i>{{ __('purchases.create_purchase') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('purchases.subtitle') }}</p>
        </div>
        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
        </a>
    </div>

    <form method="POST" action="{{ route('purchases.store') }}" id="purchaseForm">
        @csrf

        <!-- Purchase Header Card -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold mb-0 text-dark">Order Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="supplier_id" class="form-label fw-semibold">{{ __('purchases.supplier') }} <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                            <option value="">{{ __('purchases.select_supplier') }}</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="warehouse_id" class="form-label fw-semibold">{{ __('purchases.warehouse') }} <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                            <option value="">{{ __('purchases.select_warehouse') }}</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="purchase_no" class="form-label fw-semibold">{{ __('purchases.purchase_no') }} <span class="text-danger">*</span></label>
                        <input type="text" name="purchase_no" id="purchase_no" class="form-control @error('purchase_no') is-invalid @enderror" value="{{ old('purchase_no', 'PO-' . date('Ymd') . '-' . rand(100, 999)) }}" required>
                        @error('purchase_no')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="purchase_date" class="form-label fw-semibold">{{ __('purchases.purchase_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="purchase_date" id="purchase_date" class="form-control @error('purchase_date') is-invalid @enderror" value="{{ old('purchase_date', date('Y-m-d')) }}" required>
                        @error('purchase_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-8">
                        <label for="notes" class="form-label fw-semibold">{{ __('purchases.notes') }}</label>
                        <input type="text" name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes') }}" placeholder="e.g. Delivered by truck #BA-2-KHA-1234">
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">{{ __('purchases.items') }}</h5>
                <button type="button" class="btn btn-outline-primary btn-sm" id="addItemBtn">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('purchases.add_item') }}
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="min-width: 250px;">{{ __('purchases.product') }}</th>
                                <th style="width: 150px;">{{ __('purchases.quantity') }}</th>
                                <th style="width: 170px;">{{ __('purchases.unit_cost') }}</th>
                                <th style="width: 140px;">{{ __('purchases.discount') }}</th>
                                <th class="text-end" style="width: 160px;">{{ __('purchases.line_total') }}</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <!-- Row 0 -->
                            <tr class="item-row">
                                <td class="ps-3">
                                    <select name="items[0][product_id]" class="form-select product-select" required>
                                        <option value="">{{ __('products.select_category') }} / Product</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}" data-cost="{{ $p->cost_price }}" data-symbol="{{ $p->unit?->symbol }}">
                                                {{ $p->name }} ({{ $p->unit?->symbol }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0.001" name="items[0][quantity]" class="form-control qty-input" value="1.000" required>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light small">Rs.</span>
                                        <input type="number" step="0.01" min="0" name="items[0][unit_cost]" class="form-control cost-input" value="0.00" required>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light small">Rs.</span>
                                        <input type="number" step="0.01" min="0" name="items[0][discount]" class="form-control discount-input" value="0.00">
                                    </div>
                                </td>
                                <td class="text-end fw-bold line-total-text">
                                    Rs. 0.00
                                </td>
                                <td class="text-center pe-3">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" disabled>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals Summary Footer -->
                <div class="p-3 bg-light border-top">
                    <div class="row justify-content-end">
                        <div class="col-12 col-md-5">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">{{ __('purchases.subtotal') }}:</span>
                                <span class="fw-semibold text-dark" id="summarySubtotal">Rs. 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">{{ __('purchases.discount') }}:</span>
                                <span class="text-danger" id="summaryDiscount">- Rs. 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2">
                                <span class="fw-bold fs-5 text-dark">{{ __('purchases.grand_total') }}:</span>
                                <span class="fw-bold fs-5 text-success" id="summaryGrandTotal">Rs. 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary px-4 py-2">
                <i class="bi bi-check-circle me-1"></i>Save Purchase Draft
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = 1;
    const itemsBody = document.getElementById('itemsBody');
    const addItemBtn = document.getElementById('addItemBtn');

    function calculateTotals() {
        let subtotal = 0;
        let discountTotal = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
            const disc = parseFloat(row.querySelector('.discount-input').value) || 0;

            const lineTotal = Math.max(0, (qty * cost) - disc);
            row.querySelector('.line-total-text').textContent = 'Rs. ' + lineTotal.toFixed(2);

            subtotal += (qty * cost);
            discountTotal += disc;
        });

        const grandTotal = Math.max(0, subtotal - discountTotal);

        document.getElementById('summarySubtotal').textContent = 'Rs. ' + subtotal.toFixed(2);
        document.getElementById('summaryDiscount').textContent = '- Rs. ' + discountTotal.toFixed(2);
        document.getElementById('summaryGrandTotal').textContent = 'Rs. ' + grandTotal.toFixed(2);
    }

    function attachRowListeners(row) {
        row.querySelector('.product-select').addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (selected && selected.dataset.cost) {
                row.querySelector('.cost-input').value = parseFloat(selected.dataset.cost).toFixed(2);
            }
            calculateTotals();
        });

        row.querySelector('.qty-input').addEventListener('input', calculateTotals);
        row.querySelector('.cost-input').addEventListener('input', calculateTotals);
        row.querySelector('.discount-input').addEventListener('input', calculateTotals);

        row.querySelector('.remove-row-btn').addEventListener('click', function () {
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calculateTotals();
                updateRemoveButtons();
            }
        });
    }

    function updateRemoveButtons() {
        const rows = document.querySelectorAll('.item-row');
        rows.forEach(r => {
            r.querySelector('.remove-row-btn').disabled = (rows.length === 1);
        });
    }

    // Attach to initial row
    document.querySelectorAll('.item-row').forEach(attachRowListeners);

    // Add new row
    addItemBtn.addEventListener('click', function () {
        const firstRow = document.querySelector('.item-row');
        const newRow = firstRow.cloneNode(true);

        // Update input names with new index
        newRow.querySelector('.product-select').name = `items[${rowIndex}][product_id]`;
        newRow.querySelector('.product-select').selectedIndex = 0;
        newRow.querySelector('.qty-input').name = `items[${rowIndex}][quantity]`;
        newRow.querySelector('.qty-input').value = '1.000';
        newRow.querySelector('.cost-input').name = `items[${rowIndex}][unit_cost]`;
        newRow.querySelector('.cost-input').value = '0.00';
        newRow.querySelector('.discount-input').name = `items[${rowIndex}][discount]`;
        newRow.querySelector('.discount-input').value = '0.00';
        newRow.querySelector('.line-total-text').textContent = 'Rs. 0.00';

        itemsBody.appendChild(newRow);
        attachRowListeners(newRow);
        rowIndex++;
        updateRemoveButtons();
        calculateTotals();
    });

    calculateTotals();
});
</script>
@endsection
