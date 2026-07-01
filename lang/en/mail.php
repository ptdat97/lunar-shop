<?php

return [
    // Shared
    'greeting_hi' => 'Hi :name,',
    'greeting_fallback_name' => 'there',
    'view_order' => 'View your order',
    'thanks' => 'Thanks,',
    'table_item' => 'Item',
    'table_qty' => 'Qty',
    'table_subtotal' => 'Subtotal',
    'shipping_to' => 'Shipping to',

    // Order confirmation
    'confirmation' => [
        'subject' => 'Order confirmed — :reference',
        'heading' => 'Thank you for your order!',
        'intro' => 'we’ve received your order **:reference**.',
        'total' => 'Total: :total',
    ],

    // Order paid
    'paid' => [
        'subject' => 'Payment received — :reference',
        'heading' => 'Payment received',
        'intro' => 'We’ve received your payment for order **:reference** — thank you!',
        'amount_paid' => 'Amount paid: :total',
        'preparing' => 'Your order is now being prepared. We’ll let you know when it ships.',
    ],

    // Order status updated
    'status' => [
        'subject' => 'Order update — :reference',
        'heading' => 'Your order has an update',
        'intro' => 'Order **:reference** is now **:status**.',
        'status_line' => 'Status: :status',
    ],

    // Return / RMA status
    'return' => [
        'subject' => 'Return :reference — :status',
        'heading' => 'Your return request',
        'intro' => 'Your return **:reference** for order **:order** is now **:status**.',
        'refund_line' => 'Refunded amount: :amount',
        'status_requested' => 'received',
        'status_approved' => 'approved',
        'status_rejected' => 'rejected',
        'status_refunded' => 'refunded',
    ],

    // Invoice PDF
    'invoice' => [
        'title' => 'Invoice',
        'number' => 'Invoice No.',
        'date' => 'Date',
        'bill_to' => 'Bill to',
        'ship_to' => 'Ship to',
        'unit_price' => 'Unit price',
        'subtotal' => 'Subtotal',
        'shipping' => 'Shipping',
        'tax' => 'Tax',
        'total' => 'Total',
        'thank_you' => 'Thank you for your purchase!',
    ],
];
