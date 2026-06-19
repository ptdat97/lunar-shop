@component('mail::message')
# Thank you for your order!

Hi {{ $order->shippingAddress?->first_name ?? 'there' }}, we’ve received your order **{{ $order->reference }}**.

@component('mail::table')
| Item | Qty | Subtotal |
| :--- | :-: | -------: |
@foreach($order->lines as $line)
| {{ $line->description }} | {{ $line->quantity }} | {{ $line->subTotal?->formatted() }} |
@endforeach
@endcomponent

**Total: {{ $order->total?->formatted() }}**

@if($order->shippingAddress)
**Shipping to**
{{ trim($order->shippingAddress->first_name.' '.$order->shippingAddress->last_name) }}
{{ $order->shippingAddress->line_one }}@if($order->shippingAddress->line_two), {{ $order->shippingAddress->line_two }}@endif
{{ $order->shippingAddress->city }}@if($order->shippingAddress->state), {{ $order->shippingAddress->state }}@endif
@endif

@component('mail::button', ['url' => url('/checkout/confirmation/'.$order->reference)])
View your order
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
