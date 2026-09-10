@component('mail::message')
# {{ __('mail.review_request.heading') }}

{{ __('mail.review_request.intro', ['reference' => $order->reference]) }}

@foreach($products as $product)
@component('mail::button', ['url' => route('storefront.product', $product['slug']).'#danh-gia'])
{{ __('mail.review_request.button', ['product' => $product['name']]) }}
@endcomponent
@endforeach

{{ __('mail.review_request.outro') }}
@endcomponent
