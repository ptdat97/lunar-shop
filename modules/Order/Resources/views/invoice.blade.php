<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; } /* DejaVu ships with dompdf → Unicode (VN) OK */
        body { color: #1a1a1a; font-size: 12px; margin: 0; }
        .wrap { padding: 32px 40px; }
        .header { border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 22px; letter-spacing: .5px; }
        .header .brand { float: right; text-align: right; font-size: 13px; font-weight: bold; }
        .meta td { padding: 2px 0; }
        .meta .label { color: #666; padding-right: 10px; }
        .addresses { width: 100%; margin: 18px 0; }
        .addresses td { width: 50%; vertical-align: top; padding-right: 16px; }
        .addresses .head { font-weight: bold; text-transform: uppercase; font-size: 11px; color: #666; margin-bottom: 4px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th { background: #f4f4f4; text-align: left; padding: 8px; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #ddd; }
        table.lines td { padding: 8px; border-bottom: 1px solid #eee; }
        .num { text-align: right; }
        .center { text-align: center; }
        .totals { width: 45%; float: right; margin-top: 12px; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { border-top: 2px solid #1a1a1a; font-weight: bold; font-size: 14px; }
        .foot { clear: both; padding-top: 40px; color: #666; text-align: center; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <span class="brand">{{ config('app.name') }}</span>
        <h1>{{ __('mail.invoice.title') }}</h1>
    </div>

    <table class="meta">
        <tr>
            <td class="label">{{ __('mail.invoice.number') }}</td>
            <td>{{ $order->reference }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('mail.invoice.date') }}</td>
            <td>{{ optional($order->placed_at ?? $order->created_at)->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="addresses">
        <tr>
            @php $ship = $order->shippingAddress; $bill = $order->billingAddress ?? $ship; @endphp
            <td>
                <div class="head">{{ __('mail.invoice.bill_to') }}</div>
                @if($bill)
                    {{ trim($bill->first_name.' '.$bill->last_name) }}<br>
                    {{ $bill->line_one }}@if($bill->line_two), {{ $bill->line_two }}@endif<br>
                    {{ $bill->city }}@if($bill->state), {{ $bill->state }}@endif
                    @if($bill->contact_phone)<br>{{ $bill->contact_phone }}@endif
                @endif
            </td>
            <td>
                <div class="head">{{ __('mail.invoice.ship_to') }}</div>
                @if($ship)
                    {{ trim($ship->first_name.' '.$ship->last_name) }}<br>
                    {{ $ship->line_one }}@if($ship->line_two), {{ $ship->line_two }}@endif<br>
                    {{ $ship->city }}@if($ship->state), {{ $ship->state }}@endif
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('mail.table_item') }}</th>
                <th class="center">{{ __('mail.table_qty') }}</th>
                <th class="num">{{ __('mail.invoice.unit_price') }}</th>
                <th class="num">{{ __('mail.table_subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="center">{{ $line->quantity }}</td>
                    <td class="num">{{ $line->unitPrice?->formatted() }}</td>
                    <td class="num">{{ $line->subTotal?->formatted() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('mail.invoice.subtotal') }}</td>
            <td class="num">{{ $order->subTotal?->formatted() }}</td>
        </tr>
        <tr>
            <td>{{ __('mail.invoice.shipping') }}</td>
            <td class="num">{{ $order->shippingTotal?->formatted() }}</td>
        </tr>
        <tr>
            <td>{{ __('mail.invoice.tax') }}</td>
            <td class="num">{{ $order->taxTotal?->formatted() }}</td>
        </tr>
        <tr class="grand">
            <td>{{ __('mail.invoice.total') }}</td>
            <td class="num">{{ $order->total?->formatted() }}</td>
        </tr>
    </table>

    <div class="foot">{{ __('mail.invoice.thank_you') }}</div>
</div>
</body>
</html>
