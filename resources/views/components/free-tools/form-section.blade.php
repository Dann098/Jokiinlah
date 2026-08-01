@props(['title', 'description' => null, 'open' => false])

<details class='cv-form-card group' @if($open) open @endif>
    <summary class='cv-form-card__summary'>
        <span>
            <span class='block text-base font-bold text-navy'>{{ $title }}</span>
            @if($description)<span class='mt-1 block text-xs font-normal leading-5 text-muted'>{{ $description }}</span>@endif
        </span>
        <svg class='h-5 w-5 shrink-0 transition group-open:rotate-180' aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='m6 9 6 6 6-6'/></svg>
    </summary>
    <div class='cv-form-card__body'>{{ $slot }}</div>
</details>
