@extends('layouts.auth')

@section('title', __('auth.forgot_password_title') . ' — ' . config('app.name'))

@section('content')
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-4 p-md-5">
        <h4 class="card-title fw-bold text-center mb-3">{{ __('auth.forgot_password_title') }}</h4>
        <p class="text-muted text-center small mb-4">
            {{ __('ui.forgot_password_help') }}
        </p>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-4">
                <label for="email" class="form-label">{{ __('auth.email') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                @error('email')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-send me-1"></i>{{ __('auth.send_reset_link') }}
                </button>
            </div>
        </form>
    </div>
    <div class="card-footer bg-white border-0 text-center pb-4 pt-0">
        <a href="{{ route('login') }}" class="text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>{{ __('ui.back_to_login', ['login' => __('auth.login_button')]) }}
        </a>
    </div>
</div>
@endsection
