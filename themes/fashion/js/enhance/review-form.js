/**
 * Gửi đánh giá mà không rời trang.
 *
 * Form vẫn là một `<form method="post" enctype="multipart/form-data">` thật trỏ
 * vào endpoint API đã có, nên phần ĐỌC đánh giá không phụ thuộc JS chút nào —
 * đó là phần quan trọng hơn. File này chỉ chặn submit để khách không mất chỗ
 * đang đứng trên trang.
 */

import api from '../api.js';
import { t } from '../i18n.js';

export default function init() {
    const form = document.querySelector('[data-review-form]');

    if (!form) return;

    const status = form.querySelector('[data-review-status]');
    const button = form.querySelector('button[type="submit"]');
    const photos = form.querySelector('input[type="file"][name="photos[]"]');

    // Trần số ảnh đọc từ chính ô chọn ảnh (server render ra), không hardcode:
    // đổi `Review::MAX_PHOTOS` là chỗ này đi theo, không phải sửa hai nơi.
    const maxPhotos = Number(photos?.dataset.maxPhotos) || 0;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const files = photos ? Array.from(photos.files) : [];

        // Chặn sớm ở client cho đỡ mất công tải lên rồi mới bị 422. Server vẫn
        // kiểm lại — đây là phép lịch sự, không phải lớp bảo vệ.
        if (maxPhotos && files.length > maxPhotos) {
            if (status) status.textContent = t('review.too_many', { max: maxPhotos }, `At most ${maxPhotos} photos.`);

            return;
        }

        if (button) button.disabled = true;
        if (status) status.textContent = t('common.please_wait', {}, 'Please wait…');

        try {
            const { data } = await api.post(form.getAttribute('action'), buildPayload(form, files));

            form.reset();

            // Cố ý KHÔNG chèn đánh giá vừa gửi vào danh sách: shop có thể bật
            // duyệt thủ công, và hiện ngay một đánh giá còn đang chờ duyệt là
            // nói dối khách rằng nó đã đăng. Cùng lý do, đánh giá có ảnh LUÔN
            // qua duyệt nên câu cảm ơn phải khác — `meta.pending` nói rõ ca nào.
            if (status) {
                status.textContent = data?.meta?.pending
                    ? t('review.pending', {}, 'Thank you. Your review appears once we have checked it.')
                    : t('review.thanks', {}, 'Thanks for your review.');
            }
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

/**
 * JSON khi không có ảnh, FormData khi có.
 *
 * Không gửi FormData cho mọi trường hợp: multipart biến `rating` thành chuỗi và
 * `body` rỗng thành `""` thay vì `null`, nên đường đi thường ngày (không ảnh)
 * giữ nguyên JSON đúng kiểu như trước.
 *
 * @param {HTMLFormElement} form
 * @param {File[]} files
 */
function buildPayload(form, files) {
    const author = form.querySelector('[name="author"]').value;
    const rating = Number(form.querySelector('[name="rating"]').value);
    const body = form.querySelector('[name="body"]').value || null;

    if (!files.length) {
        return { author, rating, body };
    }

    const payload = new FormData();
    payload.append('author', author);
    payload.append('rating', String(rating));
    if (body) payload.append('body', body);
    files.forEach((file) => payload.append('photos[]', file));

    return payload;
}
