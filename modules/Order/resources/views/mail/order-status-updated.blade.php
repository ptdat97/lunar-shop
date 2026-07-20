@component('mail::message')
# {{ __('mail.status.heading') }}

{!! __('mail.status.intro', ['reference' => $order->reference, 'status' => $statusLabel]) !!}

@component('mail::panel')
{{ __('mail.status.status_line', ['status' => $statusLabel]) }}
@endcomponent

@component('mail::button', ['url' => url('/checkout/confirmation/'.$order->reference)])
{{ __('mail.view_order') }}
@endcomponent

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
