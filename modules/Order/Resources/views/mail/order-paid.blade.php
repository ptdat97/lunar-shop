@component('mail::message')
# {{ __('mail.paid.heading') }}

{!! __('mail.paid.intro', ['reference' => $order->reference]) !!}

@component('mail::table')
| {{ __('mail.table_item') }} | {{ __('mail.table_qty') }} | {{ __('mail.table_subtotal') }} |
| :--- | :-: | -------: |
@foreach($order->lines as $line)
| {{ $line->description }} | {{ $line->quantity }} | {{ $line->subTotal?->formatted() }} |
@endforeach
@endcomponent

**{{ __('mail.paid.amount_paid', ['total' => $order->total?->formatted()]) }}**

{{ __('mail.paid.preparing') }}

@component('mail::button', ['url' => url('/checkout/confirmation/'.$order->reference)])
{{ __('mail.view_order') }}
@endcomponent

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
