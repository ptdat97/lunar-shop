@component('mail::message')
# {{ __('mail.abandoned_cart.heading') }}

{{ __('mail.abandoned_cart.intro') }}

@component('mail::table')
| {{ __('mail.table_item') }} | {{ __('mail.table_qty') }} | {{ __('mail.table_subtotal') }} |
| :--- | :-: | -------: |
@foreach($cart->lines as $line)
| {{ $line->purchasable?->getDescription() ?? __('mail.abandoned_cart.item_fallback') }} | {{ $line->quantity }} | {{ $line->subTotal?->formatted ?? '' }} |
@endforeach
@endcomponent

{{-- Giỏ được khôi phục bằng public_token chứ không phải id: token là thứ
     TokenAwareCartSession vốn đã dùng để nhận lại giỏ của khách trên thiết bị
     khác, nên link này không cần thêm cơ chế nào. Dùng id thì bất kỳ ai đoán số
     cũng mở được giỏ người khác. --}}
@component('mail::button', ['url' => route('storefront.cart.recover', ['token' => $cart->public_token])])
{{ __('mail.abandoned_cart.button') }}
@endcomponent

{{ __('mail.abandoned_cart.outro') }}
@endcomponent
