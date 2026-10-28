@extends('layouts.auth')

@section('title', __('auth.register_title') . ' — ' . config('app.name'))

@section('content')
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-4 p-md-5">
        <h4 class="card-title fw-bold text-center mb-4">{{ __('auth.register_title') }}</h4>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <!-- User Personal Info -->
            <div class="mb-3">
                <label for="name" class="form-label">{{ __('auth.name') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                </div>
                @error('name')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('auth.email') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                </div>
                @error('email')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">{{ __('auth.phone') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="98XXXXXXXX">
                </div>
                @error('phone')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <hr class="my-4 text-muted">

            <!-- Shop Info -->
            <div class="mb-3">
                <label for="shop_name" class="form-label">{{ __('auth.shop_name') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-shop"></i></span>
                    <input type="text" class="form-control @error('shop_name') is-invalid @enderror" id="shop_name" name="shop_name" value="{{ old('shop_name') }}" required placeholder="e.g. Annapurna Kirana Store">
                </div>
                @error('shop_name')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="business_type" class="form-label">{{ __('auth.business_type') }}</label>
                <select class="form-select @error('business_type') is-invalid @enderror" id="business_type" name="business_type">
                    <option value="kirana" {{ old('business_type', 'kirana') === 'kirana' ? 'selected' : '' }}>{{ __('shops.types.kirana') }}</option>
                    <option value="clothing" {{ old('business_type') === 'clothing' ? 'selected' : '' }}>{{ __('shops.types.clothing') }}</option>
                    <option value="electronics" {{ old('business_type') === 'electronics' ? 'selected' : '' }}>{{ __('shops.types.electronics') }}</option>
                    <option value="pharmacy" {{ old('business_type') === 'pharmacy' ? 'selected' : '' }}>{{ __('shops.types.pharmacy') }}</option>
                    <option value="general" {{ old('business_type') === 'general' ? 'selected' : '' }}>{{ __('shops.types.general') }}</option>
                </select>
                @error('business_type')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <hr class="my-4 text-muted">

            <!-- Security Info -->
            <div class="mb-3">
                <label for="password" class="form-label">{{ __('auth.password_label') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="new-password">
                </div>
                @error('password')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">{{ __('auth.password_confirmation') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle me-1"></i>{{ __('auth.register_button') }}
                </button>
            </div>
        </form>
    </div>
    <div class="card-footer bg-white border-0 text-center pb-4 pt-0">
        <p class="text-muted mb-0 small">
            {{ __('auth.have_account') }}
            <a href="{{ route('login') }}" class="text-decoration-none fw-semibold">{{ __('auth.login_button') }}</a>
        </p>
    </div>
</div>
@endsection
