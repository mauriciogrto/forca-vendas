@if ($paginator->hasPages())
    <nav class="paginacao" role="navigation" aria-label="Paginação">
        @if ($paginator->onFirstPage())
            <span class="btn btn-pequeno" style="opacity:.4">&laquo; Anterior</span>
        @else
            <a class="btn btn-pequeno" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Anterior</a>
        @endif

        @if ($paginator->hasMorePages())
            <a class="btn btn-pequeno" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima &raquo;</a>
        @else
            <span class="btn btn-pequeno" style="opacity:.4">Próxima &raquo;</span>
        @endif
    </nav>
@endif
