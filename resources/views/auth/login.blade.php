@extends('layouts.auth')

@section('title', __('auth.login_title') . ' — ' . config('app.name'))

@section('content')
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-4 p-md-5">
        <h4 class="card-title fw-bold text-center mb-4">{{ __('auth.login_title') }}</h4>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('auth.email') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                @error('email')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label mb-0">{{ __('auth.password_label') }}</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="small text-decoration-none">{{ __('auth.forgot_password') }}</a>
                    @endif
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="current-password">
                </div>
                @error('password')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">{{ __('auth.remember_me') }}</label>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('auth.login_button') }}
                </button>
            </div>
        </form>
    </div>
    <div class="card-footer bg-white border-0 text-center pb-4 pt-0">
        <p class="text-muted mb-0 small">
            {{ __('auth.no_account') }}
            <a href="{{ route('register') }}" class="text-decoration-none fw-semibold">{{ __('auth.register_button') }}</a>
        </p>
    </div>
</div>
@endsection
