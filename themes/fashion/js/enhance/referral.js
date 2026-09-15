/**
 * Nút copy link mời.
 *
 * Khối giới thiệu đã render sẵn từ server (mã và link nằm trong HTML), nên file
 * này chỉ thêm đúng việc mà server không làm được: đưa link vào clipboard. Không
 * có JS thì khách vẫn chọn và copy tay được — phần ĐỌC không phụ thuộc JS, đúng
 * nguyên tắc SSR-first của storefront.
 */

import { t } from '../i18n.js';

export default function init(root = document) {
    const box = root.querySelector('[data-referral]');
    const button = box?.querySelector('[data-referral-copy]');
    const input = box?.querySelector('[data-referral-link]');

    if (!button || !input) return;

    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(input.value);
            button.textContent = t('referral.copied', {}, 'Link copied');
        } catch {
            // Không có clipboard API (trang chạy http, trình duyệt cũ): chọn sẵn
            // nội dung để khách bấm Ctrl/Cmd+C, và nói thật là chưa copy được —
            // đổi nhãn thành "đã sao chép" trong lúc chưa có gì trong clipboard
            // là nói dối khách.
            input.select();
            button.textContent = t('referral.copy_failed', {}, 'Press Ctrl/Cmd + C to copy');
        }
    });
}