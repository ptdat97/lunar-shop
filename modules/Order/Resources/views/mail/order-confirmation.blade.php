@component('mail::message')
# {{ __('mail.confirmation.heading') }}

{{ __('mail.greeting_hi', ['name' => $order->shippingAddress?->first_name ?? __('mail.greeting_fallback_name')]) }} {!! __('mail.confirmation.intro', ['reference' => $order->reference]) !!}

@component('mail::table')
| {{ __('mail.table_item') }} | {{ __('mail.table_qty') }} | {{ __('mail.table_subtotal') }} |
| :--- | :-: | -------: |
@foreach($order->lines as $line)
| {{ $line->description }} | {{ $line->quantity }} | {{ $line->sub_total?->formatted() }} |
@endforeach
@endcomponent

**{{ __('mail.confirmation.total', ['total' => $order->total?->formatted()]) }}**

@if($order->shippingAddress)
**{{ __('mail.shipping_to') }}**
{{ trim($order->shippingAddress->first_name.' '.$order->shippingAddress->last_name) }}
{{ $order->shippingAddress->line_one }}@if($order->shippingAddress->line_two), {{ $order->shippingAddress->line_two }}@endif
{{ $order->shippingAddress->city }}@if($order->shippingAddress->state), {{ $order->shippingAddress->state }}@endif
@endif

@component('mail::button', ['url' => url('/checkout/confirmation/'.$order->reference)])
{{ __('mail.view_order') }}
@endcomponent

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
