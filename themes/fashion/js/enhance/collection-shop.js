// Collection page: SSR-first grid with in-place filter/sort/page.
// All behaviour lives in the shared _shop helper; this just targets the page.
import { initShop } from './_shop.js';

export default function (root = document) {
    root.querySelectorAll('[data-shop="collection"]').forEach(initShop);
}
