@extends('layouts.app')
@section('title', __('products.import_success_title') . ' — ' . config('app.name'))
@section('content')
<div class="container-fluid p-0">
    <div class="alert alert-success">
        <h4 class="alert-heading">{{ __('products.import_success_title') }}</h4>
        <p>{{ __('products.import_created') }}: {{ $import->imported_rows }}</p>
        <p>{{ __('products.import_skipped') }}: {{ $import->skipped_rows }}</p>
        <p>{{ __('products.import_invalid') }}: {{ $import->invalid_rows }}</p>
        <a href="{{ route('products.index') }}" class="btn btn-primary">{{ __('products.title') }}</a>
    </div>
</div>
@endsection