@props(['paginator'])
@if($paginator->hasPages())<nav class='mt-10' aria-label='Navigasi halaman'>{{ $paginator->onEachSide(1)->links('pagination::tailwind') }}</nav>@endif
