@component('mail::message')
# Your order has an update

Order **{{ $order->reference }}** is now **{{ $statusLabel }}**.

@component('mail::panel')
Status: {{ $statusLabel }}
@endcomponent

@component('mail::button', ['url' => url('/checkout/confirmation/'.$order->reference)])
View your order
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
