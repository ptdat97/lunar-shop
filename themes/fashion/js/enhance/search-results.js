// Search results page: same SSR-first grid behaviour as collection.
import { initShop } from './_shop.js';

export default function (root = document) {
    root.querySelectorAll('[data-shop="search"]').forEach(initShop);
}
