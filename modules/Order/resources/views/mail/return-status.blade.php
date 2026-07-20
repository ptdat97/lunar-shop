@component('mail::message')
# {{ __('mail.return.heading') }}

{!! __('mail.return.intro', [
    'reference' => $return->reference,
    'order' => $return->order?->reference,
    'status' => $statusLabel,
]) !!}

@if($return->refund_amount && $return->order?->currency)
**{{ __('mail.return.refund_line', ['amount' => $return->order->currency->code . ' ' . number_format($return->refund_amount / (10 ** ($return->order->currency->decimal_places ?? 0)), $return->order->currency->decimal_places ?? 0)]) }}**
@endif

@if($return->staff_note)
> {{ $return->staff_note }}
@endif

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
