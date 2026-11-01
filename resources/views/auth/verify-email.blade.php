@extends('layouts.app')

@section('title', __('auth.verify_email_title'))

@section('content')
<div class="container py-5 text-center">
    <h1 class="h4 mb-3">{{ __('auth.verify_email_title') }}</h1>
    <p class="text-muted mb-3">{{ __('auth.verify_email_body') }}</p>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary">{{ __('auth.resend_verification') }}</button>
    </form>
</div>
@endsection