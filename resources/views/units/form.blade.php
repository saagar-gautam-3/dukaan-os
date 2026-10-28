<form method="POST" action="{{ isset($unit) ? route('units.update', $unit) : route('units.store') }}">
    @csrf
    @isset($unit)
        @method('PUT')
    @endisset

    <div class="mb-3">
        <label for="name{{ isset($unit) ? '_'.$unit->id : '' }}" class="form-label fw-semibold">
            {{ __('products.unit_name') }} <span class="text-danger">*</span>
        </label>
        <input type="text" name="name" id="name{{ isset($unit) ? '_'.$unit->id : '' }}" 
               class="form-control @error('name') is-invalid @enderror" 
               placeholder="e.g. Kilogram" 
               value="{{ old('name', $unit->name ?? '') }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if(!isset($unit))
    <div class="mb-3">
        <label for="symbol" class="form-label fw-semibold">
            {{ __('products.unit_symbol') }} <span class="text-danger">*</span>
        </label>
        <input type="text" name="symbol" id="symbol" 
               class="form-control @error('symbol') is-invalid @enderror" 
               placeholder="e.g. kg, pkt, ltr" 
               value="{{ old('symbol') }}" required>
        @error('symbol')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @endif

    <div class="mb-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" 
                   name="is_fractional" id="is_fractional{{ isset($unit) ? '_'.$unit->id : '' }}" value="1"
                   {{ old('is_fractional', $unit->is_fractional ?? false) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="is_fractional{{ isset($unit) ? '_'.$unit->id : '' }}">
                {{ __('products.unit_is_fractional') }}
            </label>
        </div>
    </div>

    <button type="submit" class="btn btn-primary {{ isset($unit) ? '' : 'w-100' }}">
        <i class="bi bi-check-circle me-1"></i>{{ __('common.save') }}
    </button>
</form>
