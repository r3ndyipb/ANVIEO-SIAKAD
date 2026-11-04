@if ($paginator->hasPages())
    <nav aria-label="Navigasi halaman jadwal">
        <div class="dt-paging">
            @if ($paginator->onFirstPage())
                <span class="dt-paging-button disabled" aria-disabled="true">Sebelumnya</span>
            @else
                <a class="dt-paging-button previous" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="dt-paging-button disabled" aria-hidden="true">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="dt-paging-button current" aria-current="page" aria-label="Halaman {{ $page }}">{{ $page }}</span>
                        @else
                            <a class="dt-paging-button" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="dt-paging-button next" href="{{ $paginator->nextPageUrl() }}" rel="next">Selanjutnya</a>
            @else
                <span class="dt-paging-button disabled" aria-disabled="true">Selanjutnya</span>
            @endif
        </div>
    </nav>
@endif
