@if ($paginator->hasPages())
    <nav class="d-flex gap-1 align-items-center justify-content-center" role="navigation"
        aria-label="Pagination Navigation">

        @if ($paginator->onFirstPage())
            <span class="page-btn disabled">
                <i class="bi bi-chevron-left"></i>
            </span>
        @else
            <button type="button" wire:click="previousPage" wire:loading.attr="disabled" class="page-btn">
                <i class="bi bi-chevron-left"></i>
            </button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-btn disabled">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-btn active">{{ $page }}</span>
                    @else
                        <button type="button" wire:click="gotoPage({{ $page }})" wire:loading.attr="disabled"
                            class="page-btn">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage" wire:loading.attr="disabled" class="page-btn">
                <i class="bi bi-chevron-right"></i>
            </button>
        @else
            <span class="page-btn disabled">
                <i class="bi bi-chevron-right"></i>
            </span>
        @endif

    </nav>
@endif
