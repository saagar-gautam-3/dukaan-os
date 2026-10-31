@extends('layouts.app')

@section('title', __('sales.pos_title'))

@section('content')
<div class="pos-container" id="posApp">
    <div class="row g-3">
        <!-- Left: Product Catalog & Search (Desktop 7 cols, Mobile 12 cols) -->
        <div class="col-lg-7 col-md-6 col-12 d-flex flex-column">
            <div class="card border-0 shadow-sm flex-grow-1">
                <div class="card-header bg-white border-bottom p-3">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="productSearchInput" class="form-control bg-light border-0" 
                               placeholder="{{ __('sales.search_products') }}" autofocus autocomplete="off">
                        <button class="btn btn-outline-secondary border-0" type="button" id="clearSearchBtn">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>

                <div class="card-body p-3 overflow-auto" style="max-height: calc(100vh - 230px); min-height: 380px;">
                    <!-- Live Search Results Grid -->
                    <div id="productGrid" class="row g-2">
                        <!-- Populated by JavaScript -->
                        <div class="col-12 text-center py-5 text-muted" id="searchPlaceholder">
                            <i class="bi bi-upc-scan fs-1 d-block mb-2 text-primary opacity-50"></i>
                            <p class="mb-0">{{ __('sales.search_products') }}</p>
                            <small class="text-muted">{{ __('sales.search_hint') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Cart & Payment Checkout Panel (Desktop 5 cols, Mobile 12 cols) -->
        <div class="col-lg-5 col-md-6 col-12">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 text-primary me-2"></i>{{ __('sales.cart') }}</h5>
                        <small class="text-muted" id="cartCountBadge">{{ __('ui.items_count', ['count' => 0]) }}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" id="clearCartBtn" type="button">
                        <i class="bi bi-trash3 me-1"></i>{{ __('sales.clear_cart') }}
                    </button>
                </div>

                <!-- Customer Picker -->
                <div class="p-3 bg-light border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" id="customerSearchInput" class="form-control bg-white" 
                               placeholder="{{ __('sales.select_customer') }}" autocomplete="off">
                        <input type="hidden" id="selectedCustomerId" value="">
                        <button class="btn btn-outline-secondary" type="button" id="clearCustomerBtn" title="{{ __('sales.walk_in') }}">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div id="selectedCustomerBadge" class="mt-2 small text-primary fw-semibold d-none">
                        <i class="bi bi-check-circle-fill me-1"></i><span id="selectedCustomerName"></span>
                    </div>
                    <div id="creditLimitWarningBadge" class="mt-2 alert alert-warning py-1 px-2 small d-none mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span id="creditLimitWarningText"></span>
                    </div>
                    <!-- Customer search dropdown -->
                    <div id="customerResults" class="list-group position-absolute shadow-sm d-none mt-1" style="z-index: 1050; max-height: 200px; overflow-y: auto; width: 90%;"></div>
                </div>

                <!-- Cart Items List (scrollable) -->
                <div class="card-body p-0 flex-grow-1 overflow-auto" style="max-height: 280px; min-height: 180px;">
                    <div id="cartEmptyState" class="text-center py-4 text-muted">
                        <i class="bi bi-basket2 fs-2 d-block mb-2 opacity-50"></i>
                        <span>{{ __('sales.cart_empty') }}</span>
                    </div>

                    <table class="table table-hover align-middle mb-0 d-none" id="cartTable">
                        <thead class="table-light small">
                            <tr>
                                <th>{{ __('sales.item_col') }}</th>
                                <th style="width: 100px;">{{ __('sales.qty') }}</th>
                                <th class="text-end" style="width: 80px;">{{ __('sales.price') }}</th>
                                <th class="text-end" style="width: 80px;">{{ __('sales.line_total') }}</th>
                                <th style="width: 35px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cartTableBody"></tbody>
                    </table>
                </div>

                <!-- Totals & Payment Checkout -->
                <div class="card-footer bg-white border-top p-3">
                    <div class="d-flex justify-content-between mb-1 small text-muted">
                        <span>{{ __('sales.subtotal') }}:</span>
                        <span id="subtotalDisplay">{{ __('ui.currency_symbol') }} 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 fs-5 fw-bold text-dark border-top pt-2">
                        <span>{{ __('sales.grand_total') }}:</span>
                        <span id="grandTotalDisplay" class="text-primary">{{ __('ui.currency_symbol') }} 0.00</span>
                    </div>

                    <!-- Payment Section -->
                    <div class="border rounded p-2 bg-light mb-3">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-6">
                                <label class="form-label small mb-1 fw-semibold">{{ __('sales.payment_method') }}</label>
                                <select id="paymentMethod" class="form-select form-select-sm">
                                    <option value="cash" selected>{{ __('sales.cash') }}</option>
                                    <option value="qr">{{ __('sales.qr') }}</option>
                                    <option value="bank">{{ __('sales.bank') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1 fw-semibold">{{ __('sales.amount_paid') }}</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">{{ __('ui.currency_symbol') }}</span>
                                    <input type="number" id="amountPaidInput" step="0.01" min="0" class="form-control" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Change Due -->
                        <div class="d-flex justify-content-between small text-secondary">
                            <span>{{ __('sales.change') }}:</span>
                            <span id="changeDisplay" class="fw-semibold text-success">{{ __('ui.currency_symbol') }} 0.00</span>
                        </div>
                    </div>

                    <!-- Hidden Form for submission -->
                    <form id="posSaleForm" action="{{ route('sales.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="warehouse_id" value="{{ $warehouse?->id ?? '' }}">
                        <input type="hidden" name="customer_id" id="formCustomerId" value="">
                        <input type="hidden" name="payment_method" id="formPaymentMethod" value="cash">
                        <input type="hidden" name="payment_amount" id="formPaymentAmount" value="0.00">
                        <input type="hidden" name="post_immediately" id="formPostImmediately" value="1">
                        <input type="hidden" name="idempotency_key" id="formIdempotencyKey" value="">
                        <div id="formItemsContainer"></div>

                        <div class="row g-2">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-secondary w-100" id="saveDraftBtn">
                                    <i class="bi bi-file-earmark me-1"></i>{{ __('sales.save_draft') }}
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-success w-100 fw-bold" id="postSaleBtn">
                                    <i class="bi bi-check2-circle me-1"></i>{{ __('sales.post_sale') }}
                                </button>
                            </div>
                        </div>
                    </form>
                    <div id="posNetworkError" class="alert alert-danger mt-2 d-none" role="alert" aria-live="assertive">
                        <div id="posErrorMessage">{{ __('ui.sale_network_failed') }}</div>
                        <small>{{ __('ui.sale_retry_help') }}</small>
                        <button type="button" id="retrySaleBtn" class="btn btn-sm btn-outline-danger mt-2">{{ __('ui.retry_sale') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const currencySymbol = @json(__('ui.currency_symbol'));
    const noSkuText = @json(__('ui.no_sku'));
    const noPhoneText = @json(__('ui.no_phone'));
    const itemCountText = @json(__('ui.items_count', ['count' => ':count']));
    const creditLimitWarningTemplate = @json(__('ui.credit_limit_warning'));
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('productSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const productGrid = document.getElementById('productGrid');
    const searchPlaceholder = document.getElementById('searchPlaceholder');
    
    const customerSearchInput = document.getElementById('customerSearchInput');
    const customerResults = document.getElementById('customerResults');
    const selectedCustomerId = document.getElementById('selectedCustomerId');
    const selectedCustomerBadge = document.getElementById('selectedCustomerBadge');
    const selectedCustomerName = document.getElementById('selectedCustomerName');
    const clearCustomerBtn = document.getElementById('clearCustomerBtn');

    const cartTable = document.getElementById('cartTable');
    const cartTableBody = document.getElementById('cartTableBody');
    const cartEmptyState = document.getElementById('cartEmptyState');
    const cartCountBadge = document.getElementById('cartCountBadge');
    const clearCartBtn = document.getElementById('clearCartBtn');

    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const amountPaidInput = document.getElementById('amountPaidInput');
    const changeDisplay = document.getElementById('changeDisplay');

    const posSaleForm = document.getElementById('posSaleForm');
    const formCustomerId = document.getElementById('formCustomerId');
    const formPaymentMethod = document.getElementById('formPaymentMethod');
    const formPaymentAmount = document.getElementById('formPaymentAmount');
    const formPostImmediately = document.getElementById('formPostImmediately');
    const formIdempotencyKey = document.getElementById('formIdempotencyKey');
    const formItemsContainer = document.getElementById('formItemsContainer');
    const saveDraftBtn = document.getElementById('saveDraftBtn');
    const postSaleBtn = document.getElementById('postSaleBtn');
    const retrySaleBtn = document.getElementById('retrySaleBtn');
    const posNetworkError = document.getElementById('posNetworkError');
    const posErrorMessage = document.getElementById('posErrorMessage');

    let cart = []; // { product_id, name, unit, price, quantity, max_stock }
    let searchDebounce = null;

    function scaledInteger(value, scale) {
        const raw = String(value ?? '0').trim();
        if (!/^[+-]?\d+(?:\.\d*)?$/.test(raw)) return 0n;
        const negative = raw.startsWith('-');
        const unsigned = raw.replace(/^[+-]/, '');
        const [whole, fraction = ''] = unsigned.split('.');
        const scaled = BigInt(whole + fraction.slice(0, scale).padEnd(scale, '0'));
        return negative ? -scaled : scaled;
    }

    function scaledString(value, scale) {
        const negative = value < 0n;
        const digits = (negative ? -value : value).toString().padStart(scale + 1, '0');
        const result = scale ? `${digits.slice(0, -scale)}.${digits.slice(-scale)}` : digits;
        return `${negative ? '-' : ''}${result}`;
    }

    function itemTotalCents(item) {
        const product = scaledInteger(item.price, 2) * scaledInteger(item.quantity, 3);
        return (product + (product >= 0n ? 500n : -500n)) / 1000n;
    }

    function cartTotalCents() {
        return cart.reduce((total, item) => total + itemTotalCents(item), 0n);
    }

    function getCartSignature() {
        const sorted = [...cart].sort((a, b) => a.product_id - b.product_id);
        const items = sorted.map(i => `${i.product_id}:${i.quantity}:${i.price}`).join('|');
        const custId = selectedCustomerId.value || '';
        return `${custId}__${items}`;
    }

    function getOrCreateIdempotencyKey() {
        const sig = getCartSignature();
        if (!sig || sig === '__') {
            return '';
        }
        try {
            const raw = sessionStorage.getItem('dukaan_pos_idempotency');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed.sig === sig && parsed.key) {
                    return parsed.key;
                }
            }
        } catch (e) {}

        const newKey = 'pos_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
        try {
            sessionStorage.setItem('dukaan_pos_idempotency', JSON.stringify({ sig: sig, key: newKey }));
        } catch (e) {}
        return newKey;
    }

    function clearIdempotencyStorage() {
        try {
            sessionStorage.removeItem('dukaan_pos_idempotency');
            sessionStorage.removeItem('dukaan_pos_saved_cart');
        } catch (e) {}
    }

    function saveCartToSession() {
        try {
            sessionStorage.setItem('dukaan_pos_saved_cart', JSON.stringify({
                cart: cart,
                customer: currentCustomer,
            }));
            if (cart.length > 0) {
                formIdempotencyKey.value = getOrCreateIdempotencyKey();
            } else {
                formIdempotencyKey.value = '';
                clearIdempotencyStorage();
            }
        } catch (e) {}
    }

    function restoreCartFromSession() {
        try {
            const raw = sessionStorage.getItem('dukaan_pos_saved_cart');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed.cart) && parsed.cart.length > 0) {
                    cart = parsed.cart;
                    if (parsed.customer) {
                        selectCustomer(parsed.customer);
                    }
                    renderCart();
                }
            }
        } catch (e) {}
    }

    // Restore any cart from sessionStorage on load
    restoreCartFromSession();

    // Initial load: search all products
    fetchProducts('');

    searchInput.addEventListener('input', function () {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            fetchProducts(this.value.trim());
        }, 250);
    });

    clearSearchBtn.addEventListener('click', function () {
        searchInput.value = '';
        fetchProducts('');
        searchInput.focus();
    });

    function fetchProducts(query) {
        fetch(`{{ route('pos.products') }}?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(products => {
                renderProductGrid(products);
            })
            .catch(err => console.error(err));
    }

    function renderProductGrid(products) {
        productGrid.innerHTML = '';
        if (products.length === 0) {
            productGrid.innerHTML = `
                <div class="col-12 text-center py-4 text-muted">
                    <i class="bi bi-search fs-2 d-block mb-1 opacity-50"></i>
                    <span>No products found.</span>
                </div>
            `;
            return;
        }

        products.forEach(p => {
            const stock = String(p.stock || '0.000');
            const price = String(p.price || '0.00');
            const inStock = !p.track_stock || scaledInteger(stock, 3) > 0n;

            const card = document.createElement('div');
            card.className = 'col-sm-6 col-md-4 col-6';
            card.innerHTML = `
                <div class="card h-100 border text-start p-2 shadow-sm product-item-card ${!inStock ? 'opacity-50' : 'cursor-pointer'}" 
                     style="transition: transform 0.15s, box-shadow 0.15s;">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="badge bg-light text-dark text-truncate" style="max-width: 100px;">${p.sku || noSkuText}</span>
                        <small class="badge ${stock > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}">
                            ${p.track_stock ? stock + ' ' + (p.unit || '') : 'Non-stock'}
                        </small>
                    </div>
                    <div class="fw-bold text-dark text-truncate mb-1" title="${p.name}">
                        ${p.name}
                        ${p.name_ne ? `<div class="small text-muted fw-normal text-truncate">${p.name_ne}</div>` : ''}
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <span class="fw-bold text-primary">${currencySymbol} ${price}</span>
                        <button type="button" class="btn btn-sm btn-primary py-0 px-2 add-to-cart-btn" ${!inStock ? 'disabled' : ''}>
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
            `;

            card.querySelector('.product-item-card').addEventListener('click', function (e) {
                if (!inStock) return;
                addToCart(p);
            });

            productGrid.appendChild(card);
        });
    }

    // Customer Search
    let customerDebounce = null;
    customerSearchInput.addEventListener('input', function () {
        clearTimeout(customerDebounce);
        const query = this.value.trim();
        if (query.length < 1) {
            customerResults.classList.add('d-none');
            return;
        }

        customerDebounce = setTimeout(() => {
            fetch(`{{ route('pos.customers') }}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(customers => {
                    customerResults.innerHTML = '';
                    if (customers.length === 0) {
                        customerResults.innerHTML = '<div class="list-group-item text-muted small">No customer found</div>';
                    } else {
                        customers.forEach(c => {
                            const a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action small py-2';
                            a.innerHTML = `<strong>${c.name}</strong> <span class="text-muted">(${c.phone || noPhoneText})</span>`;
                            a.addEventListener('click', (e) => {
                                e.preventDefault();
                                selectCustomer(c);
                            });
                            customerResults.appendChild(a);
                        });
                    }
                    customerResults.classList.remove('d-none');
                });
        }, 200);
    });

    let currentCustomer = null;
    const creditLimitWarningBadge = document.getElementById('creditLimitWarningBadge');
    const creditLimitWarningText = document.getElementById('creditLimitWarningText');

    function checkCreditLimit() {
        if (!currentCustomer) {
            creditLimitWarningBadge.classList.add('d-none');
            return;
        }

        const limitCents = scaledInteger(currentCustomer.credit_limit, 2);
        if (limitCents <= 0n) {
            creditLimitWarningBadge.classList.add('d-none');
            return;
        }

        const total = cartTotalCents();
        const paid = scaledInteger(amountPaidInput.value, 2);
        const due = total > paid ? total - paid : 0n;
        const currentBalance = scaledInteger(currentCustomer.balance, 2);
        const projectedBalance = currentBalance + due;

        if (projectedBalance > limitCents) {
            creditLimitWarningText.textContent = creditLimitWarningTemplate
                .replace(':limit', scaledString(limitCents, 2))
                .replace(':balance', scaledString(projectedBalance, 2));
            creditLimitWarningBadge.classList.remove('d-none');
        } else {
            creditLimitWarningBadge.classList.add('d-none');
        }
    }

    function selectCustomer(c) {
        currentCustomer = c;
        selectedCustomerId.value = c.id;
        selectedCustomerName.textContent = c.name + (c.phone ? ' (' + c.phone + ')' : '');
        selectedCustomerBadge.classList.remove('d-none');
        customerSearchInput.value = '';
        customerResults.classList.add('d-none');
        checkCreditLimit();
    }

    clearCustomerBtn.addEventListener('click', function () {
        currentCustomer = null;
        selectedCustomerId.value = '';
        selectedCustomerBadge.classList.add('d-none');
        creditLimitWarningBadge.classList.add('d-none');
        customerSearchInput.value = '';
        customerResults.classList.add('d-none');
    });

    // Close customer dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!customerSearchInput.contains(e.target) && !customerResults.contains(e.target)) {
            customerResults.classList.add('d-none');
        }
    });

    // Cart Actions
    function addToCart(p) {
        const existing = cart.find(item => item.product_id === p.id);
        if (existing) {
            existing.quantity = scaledString(scaledInteger(existing.quantity, 3) + 1000n, 3);
        } else {
            cart.push({
                product_id: p.id,
                name: p.name,
                unit: p.unit || '',
                price: String(p.price || '0.00'),
                quantity: '1.000',
                track_stock: p.track_stock,
                max_stock: String(p.stock || '0.000'),
            });
        }
        renderCart();
    }

    function renderCart() {
        cartTableBody.innerHTML = '';
        if (cart.length === 0) {
            cartTable.classList.add('d-none');
            cartEmptyState.classList.remove('d-none');
            cartCountBadge.textContent = itemCountText.replace(':count', '0');
            updateTotals();
            return;
        }

        cartTable.classList.remove('d-none');
        cartEmptyState.classList.add('d-none');
        cartCountBadge.textContent = itemCountText.replace(':count', String(cart.length));

        cart.forEach((item, index) => {
            const row = document.createElement('tr');
            const lineTotal = itemTotalCents(item);

            row.innerHTML = `
                <td class="py-2">
                    <div class="fw-semibold text-truncate small" style="max-width: 140px;" title="${item.name}">${item.name}</div>
                </td>
                <td class="py-2">
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary px-2 btn-qty-minus" type="button">-</button>
                        <input type="number" step="0.001" min="0.001" class="form-control text-center p-0 item-qty-input" value="${item.quantity}">
                        <button class="btn btn-outline-secondary px-2 btn-qty-plus" type="button">+</button>
                    </div>
                </td>
                <td class="text-end py-2 small">${currencySymbol} ${item.price}</td>
                <td class="text-end py-2 fw-bold text-dark small">${currencySymbol} ${scaledString(lineTotal, 2)}</td>
                <td class="text-end py-2">
                    <button class="btn btn-sm btn-link text-danger p-0 btn-remove-item" type="button">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </td>
            `;

            // Qty +/- listeners
            row.querySelector('.btn-qty-minus').addEventListener('click', () => {
                if (scaledInteger(item.quantity, 3) > 1000n) {
                    item.quantity = scaledString(scaledInteger(item.quantity, 3) - 1000n, 3);
                } else {
                    cart.splice(index, 1);
                }
                renderCart();
            });

            row.querySelector('.btn-qty-plus').addEventListener('click', () => {
                item.quantity = scaledString(scaledInteger(item.quantity, 3) + 1000n, 3);
                renderCart();
            });

            row.querySelector('.item-qty-input').addEventListener('change', function () {
                const val = scaledInteger(this.value, 3);
                if (val > 0n) {
                    item.quantity = scaledString(val, 3);
                } else {
                    cart.splice(index, 1);
                }
                renderCart();
            });

            row.querySelector('.btn-remove-item').addEventListener('click', () => {
                cart.splice(index, 1);
                renderCart();
            });

            cartTableBody.appendChild(row);
        });

        updateTotals();
    }

    clearCartBtn.addEventListener('click', () => {
        cart = [];
        renderCart();
    });

    function updateTotals() {
        const total = cartTotalCents();

        subtotalDisplay.textContent = currencySymbol + ' ' + scaledString(total, 2);
        grandTotalDisplay.textContent = currencySymbol + ' ' + scaledString(total, 2);

        // Auto-fill paid amount with grand total if not manually edited or method is cash
        if (!amountPaidInput.dataset.manual) {
            amountPaidInput.value = total > 0n ? scaledString(total, 2) : '';
        }

        calcChange();
    }

    amountPaidInput.addEventListener('input', () => {
        amountPaidInput.dataset.manual = 'true';
        calcChange();
    });

    function calcChange() {
        const total = cartTotalCents();
        const paid = scaledInteger(amountPaidInput.value, 2);
        const change = paid > total ? paid - total : 0n;
        changeDisplay.textContent = currencySymbol + ' ' + scaledString(change, 2);
        checkCreditLimit();
    }

    // Submit logic
    let pendingPostImmediately = true;
    let submissionPending = false;

    async function submitOrder(postImmediately) {
        if (submissionPending) return;

        if (cart.length === 0) {
            alert(@json(__('sales.cart_empty_alert')));
            return;
        }

        pendingPostImmediately = postImmediately;
        submissionPending = true;
        posNetworkError.classList.add('d-none');

        formCustomerId.value = selectedCustomerId.value;
        formPaymentMethod.value = paymentMethodSelect.value;
        formPaymentAmount.value = amountPaidInput.value || '0.00';
        formPostImmediately.value = postImmediately ? '1' : '0';

        formItemsContainer.innerHTML = '';
        cart.forEach((item, idx) => {
            formItemsContainer.innerHTML += `
                <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
                <input type="hidden" name="items[${idx}][quantity]" value="${item.quantity}">
                <input type="hidden" name="items[${idx}][unit_price]" value="${item.price}">
            `;
        });

        formIdempotencyKey.value = getOrCreateIdempotencyKey();
        const buttons = [postSaleBtn, saveDraftBtn, retrySaleBtn];
        buttons.forEach(button => { button.disabled = true; });

        try {
            const response = await fetch(posSaleForm.action, {
                method: 'POST',
                body: new FormData(posSaleForm),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok) {
                posErrorMessage.textContent = result.message || @json(__('common.error_generic'));
                posNetworkError.classList.remove('d-none');
                retrySaleBtn.classList.toggle('d-none', response.status < 500);
                return;
            }
            if (!result.redirect) {
                throw new Error(@json(__('common.error_generic')));
            }

            clearIdempotencyStorage();
            window.location.assign(result.redirect);
        } catch (error) {
            posErrorMessage.textContent = @json(__('ui.sale_network_failed'));
            posNetworkError.classList.remove('d-none');
            retrySaleBtn.classList.remove('d-none');
        } finally {
            submissionPending = false;
            buttons.forEach(button => { button.disabled = false; });
        }
    }

    postSaleBtn.addEventListener('click', () => submitOrder(true));
    saveDraftBtn.addEventListener('click', () => submitOrder(false));
    retrySaleBtn.addEventListener('click', () => submitOrder(pendingPostImmediately));
});
</script>
@endpush
@endsection
