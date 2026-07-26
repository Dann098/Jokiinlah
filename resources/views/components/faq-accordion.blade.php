@props(['faq'])
<article class='border-b border-navy/10 py-2' x-data='faqItem'>
    <h3><button id='faq-button-{{ $faq->id }}' type='button' class='flex min-h-14 w-full items-center justify-between gap-5 py-3 text-left font-bold text-navy' @click='toggle' x-bind:aria-expanded='open.toString()' aria-controls='faq-panel-{{ $faq->id }}'><span>{{ $faq->question }}</span><span aria-hidden='true' class='text-xl text-gold' x-text='open ? `−` : `+`'>+</span></button></h3>
    <div id='faq-panel-{{ $faq->id }}' role='region' aria-labelledby='faq-button-{{ $faq->id }}' x-show='open' x-transition.opacity><p class='pb-5 pr-8 text-sm leading-7 text-muted'>{{ $faq->answer }}</p></div>
</article>
