<form method="POST" action="{{ isset($category) ? route('categories.update', $category) : route('categories.store') }}">
    @csrf
    @isset($category)
        @method('PUT')
    @endisset

    <div class="mb-3">
        <label for="name{{ isset($category) ? '_'.$category->id : '' }}" class="form-label fw-semibold">
            {{ __('products.category_name') }} <span class="text-danger">*</span>
        </label>
        <input type="text" name="name" id="name{{ isset($category) ? '_'.$category->id : '' }}" 
               class="form-control @error('name') is-invalid @enderror" 
               placeholder="e.g. Spices & Masala" 
               value="{{ old('name', $category->name ?? '') }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if(!isset($category))
    <div class="mb-3">
        <label for="slug" class="form-label fw-semibold">{{ __('products.category_slug') }} (Optional)</label>
        <input type="text" name="slug" id="slug" class="form-control" placeholder="auto-generated if empty">
    </div>
    @endif

    <button type="submit" class="btn btn-primary {{ isset($category) ? '' : 'w-100' }}">
        <i class="bi bi-check-circle me-1"></i>{{ __('common.save') }}
    </button>
</form>
