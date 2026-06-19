@component('mail::message')
# Payment received

We’ve received your payment for order **{{ $order->reference }}** — thank you!

@component('mail::table')
| Item | Qty | Subtotal |
| :--- | :-: | -------: |
@foreach($order->lines as $line)
| {{ $line->description }} | {{ $line->quantity }} | {{ $line->subTotal?->formatted() }} |
@endforeach
@endcomponent

**Amount paid: {{ $order->total?->formatted() }}**

Your order is now being prepared. We’ll let you know when it ships.

@component('mail::button', ['url' => url('/checkout/confirmation/'.$order->reference)])
View your order
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
