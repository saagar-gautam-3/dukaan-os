@extends('layouts.app')

@section('title', __('suppliers.title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-truck me-2"></i>{{ __('suppliers.title') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('suppliers.subtitle') }}</p>
        </div>

        @can('suppliers.manage')
        <div class="mt-3 mt-md-0">
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-plus-circle"></i>
                <span>{{ __('suppliers.add_supplier') }}</span>
            </a>
        </div>
        @endcan
    </div>

    <!-- Search -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('suppliers.index') }}" class="row g-2 align-items-center">
                <div class="col-12 col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('suppliers.search_placeholder') }}" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>Search
                    </button>
                    @if(request()->filled('search'))
                        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Suppliers List -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            @if($suppliers->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-truck fs-1 d-block mb-2 text-muted opacity-50"></i>
                    <p class="mb-0">{{ __('suppliers.no_suppliers_found') }}</p>
                </div>
            @else
                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">{{ __('suppliers.name') }}</th>
                                <th>{{ __('suppliers.contact_person') }}</th>
                                <th>{{ __('suppliers.phone') }}</th>
                                <th>{{ __('suppliers.pan_vat') }}</th>
                                <th>{{ __('suppliers.address') }}</th>
                                <th class="text-end pe-3">{{ __('products.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($suppliers as $supplier)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $supplier->name }}</div>
                                    @if($supplier->email)
                                        <small class="text-muted"><i class="bi bi-envelope me-1"></i>{{ $supplier->email }}</small>
                                    @endif
                                </td>
                                <td>{{ $supplier->contact_person ?: '—' }}</td>
                                <td>
                                    @if($supplier->phone)
                                        <a href="tel:{{ $supplier->phone }}" class="text-decoration-none text-dark">
                                            <i class="bi bi-telephone me-1 text-primary"></i>{{ $supplier->phone }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><code>{{ $supplier->pan_vat ?: '—' }}</code></td>
                                <td class="small text-muted">{{ $supplier->address ?: '—' }}</td>
                                <td class="text-end pe-3">
                                    @can('suppliers.manage')
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="d-inline" onsubmit="return confirm('Delete supplier {{ $supplier->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="d-md-none divide-y">
                    @foreach($suppliers as $supplier)
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ $supplier->name }}</h6>
                                <small class="text-muted">{{ $supplier->contact_person ?: 'Supplier' }}</small>
                            </div>
                            @if($supplier->phone)
                                <a href="tel:{{ $supplier->phone }}" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-telephone-fill"></i>
                                </a>
                            @endif
                        </div>
                        <div class="small text-muted mb-2">
                            <span>PAN: <code>{{ $supplier->pan_vat ?: '—' }}</code></span>
                            @if($supplier->address)
                                &bull; <span>{{ $supplier->address }}</span>
                            @endif
                        </div>
                        <div class="d-flex justify-content-end gap-1 pt-1 border-top">
                            @can('suppliers.manage')
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                            @endcan
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        Showing {{ $suppliers->firstItem() ?? 0 }} to {{ $suppliers->lastItem() ?? 0 }} of {{ $suppliers->total() }} suppliers
                    </span>
                    <div>
                        {{ $suppliers->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
