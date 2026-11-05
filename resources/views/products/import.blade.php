@extends('layouts.app')
@section('title', __('products.import_title') . ' — ' . config('app.name'))
@section('content')
<div class="container-fluid p-0">
    <h2 class="fw-bold mb-3"><i class="bi bi-upload me-2"></i>{{ __('products.import_title') }}</h2>
    <p class="text-muted small">{{ __('products.import_help') }}</p>
    @php
        $shop = \\App\\Tenancy\\TenantContext::currentShop();
        $autoSku = (bool) ($shop?->settings['sku_auto_generate'] ?? true);
    @endphp
    <p class="small text-muted">{{ $autoSku ? __('products.import_sku_auto') : __('products.import_sku_required') }}</p>
    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a href="{{ route('products.import.template.xlsx') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>{{ __('products.import_download_xlsx') }}
        </a>
        <a href="{{ route('products.import.template') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>{{ __('products.import_download_csv') }}
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body">
            <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('products.import_file') }}</label>
                    <input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx" required>
                    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary" id="importSubmit">
                    <i class="bi bi-upload me-1"></i>{{ __('products.import_upload') }}
                </button>
                <script>
                    document.querySelector('form').addEventListener('submit', function(){ var b=document.getElementById('importSubmit'); b.disabled=true; b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>{{ __('products.import_upload') }}'; });
                </script>
            </form>
        </div>
    </div>
</div>
@endsection