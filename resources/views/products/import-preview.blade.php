@extends('layouts.app')
@section('title', __('products.import_preview_title') . ' — ' . config('app.name'))
@section('content')
<div class="container-fluid p-0">
    <h2 class="fw-bold mb-3">{{ __('products.import_preview_title') }}</h2>
    @php $imp = $import; @endphp
    <div class="mb-3">
        <span class="badge bg-info">{{ __('products.import_total') }}: {{ $imp->total_rows }}</span>
        <span class="badge bg-success">{{ __('products.import_valid') }}: {{ $imp->valid_rows }}</span>
        <span class="badge bg-danger">{{ __('products.import_invalid') }}: {{ $imp->invalid_rows }}</span>
        <span class="badge bg-secondary">{{ __('products.import_skipped') }}: {{ $imp->skipped_rows }}</span>
    </div>

    <div class="card shadow-sm border-0 rounded-3 mb-3">
        <div class="card-body">
            <h5>{{ __('products.import_valid_rows') }}</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>#</th><th>{{ __('products.name') }}</th><th>{{ __('products.sku') }}</th><th>{{ __('products.selling_price') }}</th></tr></thead>
                    <tbody>
                    @foreach(array_slice($imp->valid_rows_json ?? [], 0, 20) as $i => $r)
                        <tr><td>{{ $i+1 }}</td><td>{{ $r['name'] }}</td><td>{{ $r['sku'] }}</td><td>{{ $r['selling_price'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php
        $grouped = [];
        foreach (($imp->errors_json ?? []) as $e) {
            $grouped[$e['line']][] = $e;
        }
    @endphp
    @if(!empty($grouped))
    <div class="card shadow-sm border-0 rounded-3 mb-3">
        <div class="card-body">
            <h5>{{ __('products.import_errors') }}</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('products.import_line') }}</th><th>{{ __('products.field') }}</th><th>{{ __('products.import_errors') }}</th><th>{{ __('products.value') }}</th></tr></thead>
                    <tbody>
                    @foreach($grouped as $line => $errs)
                        @foreach($errs as $e)
                        <tr>
                            <td>{{ $line }}</td>
                            <td>{{ $e['field'] }}</td>
                            <td>{{ $e['message'] }}</td>
                            <td class="text-break">{{ $e['value'] }}</td>
                        </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2 d-flex gap-2 flex-wrap">
                <a href="{{ route('products.import.errors.xlsx', $imp) }}" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-download me-1"></i>{{ __('products.import_download_errors_xlsx') }}
                </a>
                <a href="{{ route('products.import.errors', $imp) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1"></i>{{ __('products.import_download_errors_csv') }}
                </a>
            </div>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('products.import.confirm', $imp) }}">
        @csrf
        <button type="submit" class="btn btn-success" id="confirmImport" @disabled(($imp->valid_rows ?? 0) === 0)>
            <i class="bi bi-check-circle me-1"></i>{{ __('products.import_confirm_n', ['count' => $imp->valid_rows]) }}
        </button>
        <script>
            document.querySelector('form').addEventListener('submit', function(){ var b=document.getElementById('confirmImport'); if(b){ b.disabled=true; b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>{{ __('products.import_confirm') }}'; } });
        </script>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">{{ __('products.cancel') }}</a>
    </form>
</div>
@endsection