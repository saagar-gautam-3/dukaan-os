@extends('layouts.auth')

@section('title', __('auth.rate_limited_title'))

@section('content')
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4 text-center">
            <h1 class="h4 fw-bold">{{ __('auth.rate_limited_title') }}</h1>
            <p class="text-muted mb-3">{{ $message }}</p>
            <a class="btn btn-primary" href="{{ route('login') }}">{{ __('auth.login_button') }}</a>
        </div>
    </div>
@endsection
