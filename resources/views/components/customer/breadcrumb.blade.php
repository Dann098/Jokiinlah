@props(['items' => []])
<nav aria-label='Breadcrumb' class='mb-4 overflow-x-auto'>
    <ol class='flex min-w-max items-center gap-2 text-xs font-semibold text-muted'>
        <li><a href='{{ route('customer.dashboard') }}' class='min-h-11 py-3 hover:text-navy'>Portal</a></li>
        @foreach($items as $item)
            <li aria-hidden='true'>/</li>
            <li>
                @if(!empty($item['url']))
                    <a href='{{ $item['url'] }}' class='min-h-11 py-3 hover:text-navy'>{{ $item['label'] }}</a>
                @else
                    <span aria-current='page' class='text-navy'>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
