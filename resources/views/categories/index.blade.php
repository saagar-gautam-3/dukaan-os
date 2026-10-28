@extends('layouts.app')

@section('title', __('products.categories') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1a3a5c;">
                <i class="bi bi-tags me-2"></i>{{ __('products.categories') }}
            </h2>
            <p class="text-muted mb-0 small">{{ __('products.manage_categories') }}</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>{{ __('products.title') }}
        </a>
    </div>

    <div class="row g-4">
        <!-- Add Category Form -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-plus-circle me-1 text-primary"></i>{{ __('products.add_category') }}
                    </h5>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="{{ route('categories.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">{{ __('products.category_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Spices & Masala" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label fw-semibold">{{ __('products.category_slug') }} (Optional)</label>
                            <input type="text" name="slug" id="slug" class="form-control" placeholder="auto-generated if empty">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle me-1"></i>{{ __('common.save') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Categories List -->
        <div class="col-12 col-md-8">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">{{ __('products.categories') }} ({{ $categories->count() }})</h5>
                </div>
                <div class="card-body p-0">
                    @if($categories->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-tag fs-1 d-block mb-2 text-muted opacity-50"></i>
                            <p class="mb-0">No categories found.</p>
                        </div>
                    @else
                        <!-- Desktop Table -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">{{ __('products.category_name') }}</th>
                                        <th>{{ __('products.category_slug') }}</th>
                                        <th class="text-center">{{ __('products.title') }}</th>
                                        <th class="text-end pe-3">{{ __('products.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($categories as $category)
                                    <tr>
                                        <td class="ps-3">
                                            <form method="POST" action="{{ route('categories.update', $category) }}" class="d-flex align-items-center gap-2">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" class="form-control form-control-sm" value="{{ $category->name }}" required style="max-width: 250px;">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Save name">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td><code>{{ $category->slug }}</code></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-secondary px-2">
                                                {{ $category->products_count }} items
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            @can('products.manage')
                                            <form method="POST" action="{{ route('categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Delete category {{ $category->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            @endcan
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card List -->
                        <div class="d-md-none divide-y">
                            @foreach($categories as $category)
                            <div class="p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-dark">{{ $category->name }}</h6>
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        {{ $category->products_count }} items
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <code class="small text-muted">{{ $category->slug }}</code>
                                    @can('products.manage')
                                    <form method="POST" action="{{ route('categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Delete category {{ $category->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
