@component('mail::message')
# Back in stock

Good news — **{{ $productName }}** is available again.

Items can sell out quickly, so grab yours before it's gone.

@if($url)
@component('mail::button', ['url' => $url])
Shop now
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
