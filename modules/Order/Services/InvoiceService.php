<?php

namespace Modules\Order\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Lunar\Models\Order;

/**
 * Renders an order invoice as a PDF. One place builds the document so the
 * storefront download route and the paid-order email attachment stay identical.
 *
 * The invoice reuses the transactional-mail translations (lang mail.*) so it's
 * bilingual (EN/VI) with no extra strings; the caller sets the locale.
 */
class InvoiceService
{
    /**
     * Build the PDF instance for an order (lines + addresses eager-loaded).
     */
    public function make(Order $order): PdfInstance
    {
        $order->loadMissing(['lines', 'shippingAddress', 'billingAddress']);

        return Pdf::loadView('order::invoice', ['order' => $order])
            ->setPaper('a4');
    }

    /**
     * Raw PDF bytes — used to attach the invoice to an email.
     */
    public function bytes(Order $order): string
    {
        return $this->make($order)->output();
    }

    /**
     * Suggested download filename, e.g. "invoice-000012.pdf".
     */
    public function filename(Order $order): string
    {
        return 'invoice-' . $order->reference . '.pdf';
    }
}
