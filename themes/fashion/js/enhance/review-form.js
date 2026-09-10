/**
 * Gửi đánh giá mà không rời trang.
 *
 * Form vẫn là một `<form method="post">` thật trỏ vào endpoint API đã có, nên
 * phần ĐỌC đánh giá không phụ thuộc JS chút nào — đó là phần quan trọng hơn.
 * File này chỉ chặn submit để khách không mất chỗ đang đứng trên trang.
 */

import api from '../api.js';
import { t } from '../i18n.js';

export default function init() {
    const form = document.querySelector('[data-review-form]');

    if (!form) return;

    const status = form.querySelector('[data-review-status]');
    const button = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (button) button.disabled = true;
        if (status) status.textContent = t('common.please_wait', {}, 'Please wait…');

        try {
            await api.post(form.getAttribute('action'), {
                author: form.querySelector('[name="author"]').value,
                rating: Number(form.querySelector('[name="rating"]').value),
                body: form.querySelector('[name="body"]').value || null,
            });

            form.reset();

            // Cố ý KHÔNG chèn đánh giá vừa gửi vào danh sách: shop có thể bật
            // duyệt thủ công, và hiện ngay một đánh giá còn đang chờ duyệt là
            // nói dối khách rằng nó đã đăng.
            if (status) status.textContent = t('review.thanks', {}, 'Thanks for your review.');
        } catch (error) {
            if (status) {
                status.textContent = error.response?.data?.message
                    || t('review.failed', {}, 'Could not send your review. Please try again.');
            }
        } finally {
            if (button) button.disabled = false;
        }
    });
}
