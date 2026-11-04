@extends('layouts.auth')

@section('title', __('auth.reset_password_title') . ' — ' . config('app.name'))

@section('content')
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-4 p-md-5">
        <h4 class="card-title fw-bold text-center mb-4">{{ __('auth.reset_password_title') }}</h4>

        <form method="POST" action="{{ route('password.store') }}">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('auth.email') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
                </div>
                @error('email')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">{{ __('auth.password_label') }}</label>
                <x-password-input id="password" name="password" autocomplete="new-password" required suggest confirmation-id="password_confirmation" class="@error('password') is-invalid @enderror">
                    <x-slot:prepend><span class="input-group-text"><i class="bi bi-lock"></i></span></x-slot:prepend>
                </x-password-input>
                @error('password')
                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">{{ __('auth.password_confirmation') }}</label>
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                    <x-slot:prepend><span class="input-group-text"><i class="bi bi-lock-fill"></i></span></x-slot:prepend>
                </x-password-input>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-shield-check me-1"></i>{{ __('auth.reset_button') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
