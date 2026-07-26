@if($errors->any())
    <div data-error-summary tabindex='-1' role='alert' aria-labelledby='customer-error-title' {{ $attributes->merge(['class' => 'mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900']) }}>
        <h2 id='customer-error-title' class='font-bold'>Periksa kembali formulir</h2>
        <ul class='mt-2 list-disc space-y-1 pl-5 text-sm'>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
