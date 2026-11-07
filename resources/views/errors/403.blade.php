@extends('layouts.auth')

@section('title', __('errors.forbidden_title') . ' — ' . config('app.name'))

@section('content')
@php
    $exceptionMessage = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $defaultMessages = ['This action is unauthorized.', 'This action is unauthorized', 'Forbidden'];
    // Keep the friendly copy for plain policy denials, but surface specific
    // abort(403, __('...')) reasons such as a missing tenant context.
    $message = $exceptionMessage !== '' && ! in_array($exceptionMessage, $defaultMessages, true)
        ? $exceptionMessage
        : __('errors.forbidden_message');
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body p-4 text-center">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 text-warning" style="width: 64px; height: 64px;">
                <i class="bi bi-shield-lock fs-2"></i>
            </span>
        </div>

        <h1 class="h4 fw-bold mb-2">{{ __('errors.forbidden_title') }}</h1>
        <p class="text-muted mb-4">{{ $message }}</p>

        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <i class="bi bi-speedometer2 me-1"></i>{{ __('errors.back_to_dashboard') }}
            </a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('ui.login') }}
            </a>
        @endauth
    </div>
</div>
@endsection
