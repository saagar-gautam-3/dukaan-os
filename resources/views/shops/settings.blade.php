@extends('layouts.app')

@section('title', __('shops.settings_title') . ' — ' . config('app.name'))

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">{{ __('shops.settings_title') }}</h3>
            <p class="text-muted small mb-0">{{ __('shops.settings_subtitle') }}</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('common.back') }}
        </a>
    </div>

    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4 p-md-5">
                    <form method="POST" action="{{ route('settings.shop.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">{{ __('shops.name') }}</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $shop->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="business_type" class="form-label fw-semibold">{{ __('shops.business_type') }}</label>
                            <select class="form-select @error('business_type') is-invalid @enderror" id="business_type" name="business_type" required>
                                <option value="kirana" {{ old('business_type', $shop->business_type) === 'kirana' ? 'selected' : '' }}>{{ __('shops.types.kirana') }}</option>
                                <option value="clothing" {{ old('business_type', $shop->business_type) === 'clothing' ? 'selected' : '' }}>{{ __('shops.types.clothing') }}</option>
                                <option value="electronics" {{ old('business_type', $shop->business_type) === 'electronics' ? 'selected' : '' }}>{{ __('shops.types.electronics') }}</option>
                                <option value="pharmacy" {{ old('business_type', $shop->business_type) === 'pharmacy' ? 'selected' : '' }}>{{ __('shops.types.pharmacy') }}</option>
                                <option value="hardware" {{ old('business_type', $shop->business_type) === 'hardware' ? 'selected' : '' }}>{{ __('shops.types.hardware') }}</option>
                                <option value="general" {{ old('business_type', $shop->business_type) === 'general' ? 'selected' : '' }}>{{ __('shops.types.general') }}</option>
                            </select>
                            @error('business_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="timezone" class="form-label fw-semibold">{{ __('shops.timezone') }}</label>
                            <select class="form-select @error('timezone') is-invalid @enderror" id="timezone" name="timezone" required>
                                <option value="Asia/Kathmandu" {{ old('timezone', $shop->timezone) === 'Asia/Kathmandu' ? 'selected' : '' }}>Asia/Kathmandu (Nepal Time UTC+5:45)</option>
                                <option value="UTC" {{ old('timezone', $shop->timezone) === 'UTC' ? 'selected' : '' }}>UTC</option>
                            </select>
                            @error('timezone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-lg me-1"></i>{{ __('shops.save_settings') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
