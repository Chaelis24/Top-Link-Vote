@if ($paginator->hasPages())
    <nav class="d-flex gap-1 align-items-center justify-content-md-end mb-12" role="navigation"
        aria-label="Pagination Navigation">

        {{-- Previous Page Button --}}
        @if ($paginator->onFirstPage())
            <span class="page-btn disabled">
                <i class="bi bi-chevron-left"></i>
            </span>
        @else
            <button type="button" wire:click="previousPage" wire:loading.attr="disabled" class="page-btn">
                <i class="bi bi-chevron-left"></i>
            </button>
        @endif

        {{-- HARDCODED BLADE SLIDING LOGIC --}}
        @php
            $current = $paginator->currentPage();
            $last = $paginator->lastPage();
        @endphp

        @foreach ($elements as $element)
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    {{-- 1. IPREVENTS ANG UNANG PAHINA (Laging Litaw) --}}
                    @if ($page == 1)
                        @if ($page == $current)
                            <span class="page-btn active">1</span>
                        @else
                            <button type="button" wire:click="gotoPage(1)" wire:loading.attr="disabled"
                                class="page-btn">1</button>
                        @endif

                        {{-- 2. ELLIPSIS SA KALIWA: Kung malayo na ang active page sa simula --}}
                    @elseif ($page == 2 && $current > 3)
                        <span class="page-btn disabled">...</span>

                        {{-- 3. ANG MGA NUMERONG HUMAHAKBANG (Kasalukuyang Page, +1 sa kanan, -1 sa kaliwa) --}}
                    @elseif ($page >= $current - 1 && $page <= $current + 1 && $page < $last)
                        @if ($page == $current)
                            <span class="page-btn active">{{ $page }}</span>
                        @else
                            <button type="button" wire:click="gotoPage({{ $page }})"
                                wire:loading.attr="disabled" class="page-btn">
                                {{ $page }}
                            </button>
                        @endif

                        {{-- 4. ELLIPSIS SA KANAN: Kung malayo pa ang active page sa dulo --}}
                    @elseif ($page == $last - 1 && $current < $last - 2)
                        <span class="page-btn disabled">...</span>

                        {{-- 5. IPREVENTS ANG PINAKAHULING PAHINA (Laging Litaw sa Dulo) --}}
                    @elseif ($page == $last)
                        @if ($page == $current)
                            <span class="page-btn active">{{ $page }}</span>
                        @else
                            <button type="button" wire:click="gotoPage({{ $page }})"
                                wire:loading.attr="disabled" class="page-btn">
                                {{ $page }}
                            </button>
                        @endif
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Button --}}
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
