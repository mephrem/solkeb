@if ($p->hasPages())
    <div class="pager">
        @if ($p->onFirstPage())<span class="muted">‹ Prev</span>@else<a href="{{ $p->previousPageUrl() }}">‹ Prev</a>@endif
        <span class="current">Page {{ $p->currentPage() }} of {{ $p->lastPage() }}</span>
        @if ($p->hasMorePages())<a href="{{ $p->nextPageUrl() }}">Next ›</a>@else<span class="muted">Next ›</span>@endif
    </div>
@endif
