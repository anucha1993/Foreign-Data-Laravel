@if($paginator->hasPages())
<nav class="pagination" aria-label="หน้า">
  @if($paginator->onFirstPage())
    <span class="btn sm ghost is-disabled">‹ ก่อนหน้า</span>
  @else
    <a class="btn sm ghost" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ ก่อนหน้า</a>
  @endif
  <span class="muted">หน้า {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
  @if($paginator->hasMorePages())
    <a class="btn sm ghost" href="{{ $paginator->nextPageUrl() }}" rel="next">ถัดไป ›</a>
  @else
    <span class="btn sm ghost is-disabled">ถัดไป ›</span>
  @endif
</nav>
@endif
