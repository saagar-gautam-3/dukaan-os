@php
    $displayNumber = fn ($number) => \App\Support\NepaliNumber::shouldDisplayDevanagari()
        ? \App\Support\NepaliNumber::toDevanagari((string) $number)
        : (string) $number;
@endphp

<nav aria-label="{{ __('common.pagination') }}" class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
    <p class="pagination-summary small text-muted mb-0">
        {{ __('common.pagination_summary', [
            'first' => $displayNumber($paginator->firstItem() ?? 0),
            'last' => $displayNumber($paginator->lastItem() ?? 0),
            'total' => $displayNumber($paginator->total()),
        ]) }}
    </p>

    @if ($paginator->hasPages())
        <ul class="pagination mb-0">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-label="{{ __('common.previous_page') }}" aria-hidden="true">&lsaquo;</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('common.previous_page') }}">&lsaquo;</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @php($pageLabel = $displayNumber($page))
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $pageLabel }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $pageLabel }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('common.next_page') }}">&rsaquo;</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-label="{{ __('common.next_page') }}" aria-hidden="true">&rsaquo;</span>
                </li>
            @endif
        </ul>
    @endif
</nav>
