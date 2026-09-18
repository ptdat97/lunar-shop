// Tiêu điểm thưởng ở trang thanh toán.
//
// Cùng khuôn với checkout-coupon.js: trang là SSR, ô nhập và số dư đã nằm sẵn
// trong HTML, file này chỉ gọi /api/v1/cart/loyalty rồi vẽ lại phần tổng tiền
// tại chỗ. Không có JS thì khách vẫn ĐỌC được mình có bao nhiêu điểm — chỉ mất
// khả năng đổi số điểm mà không tải lại trang.
//
// Số điểm nằm trên giỏ phía server (cart.meta), nên placeOrder() tự mang theo;
// không có input ẩn nào trong form thanh toán.

import api from '../api.js';
import { CART_UPDATED, emit } from '../events.js';
import { t } from '../i18n.js';

export default function (root = document) {
    const summary = root.querySelector('[data-checkout-summary]');
    if (!summary || summary.dataset.loyaltyInit) return;

    const form = summary.querySelector('[data-loyalty-form]');
    if (!form) return;

    summary.dataset.loyaltyInit = '1';

    const input = form.querySelector('[data-loyalty-input]');
    const status = form.querySelector('[data-loyalty-status]');
    const url = form.dataset.loyaltyUrl;

    function setStatus(message, ok) {
        if (!status) return;
        status.textContent = message ?? '';
        status.className = `small mt-1 ${ok ? 'text-success' : 'text-danger'}`;
    }

    // Đổi nút Áp dụng ↔ Bỏ dùng theo việc đang có điểm áp hay không.
    function renderButton(applied) {
        const button = form.querySelector('[data-loyalty-apply], [data-loyalty-remove]');
        if (!button) return;

        if (applied) {
            button.className = 'btn btn-outline-danger';
            button.textContent = t('loyalty.remove', {}, 'Remove points');
            button.dataset.loyaltyRemove = '';
            delete button.dataset.loyaltyApply;
        } else {
            button.className = 'btn btn-outline-dark';
            button.textContent = t('loyalty.apply', {}, 'Apply');
            button.dataset.loyaltyApply = '';
            delete button.dataset.loyaltyRemove;
        }
    }

    // Chỉ vẽ lại TỔNG, không vẽ lại tạm tính/giảm giá/thuế: điểm là một hình
    // thức thanh toán, nó trừ vào tổng sau thuế và cố ý không đụng gì khác.
    function applyCart(cart) {
        const total = summary.querySelector('[data-sum-total]');
        if (total) total.textContent = cart?.totals?.total ?? '—';

        const loyalty = cart?.loyalty ?? null;
        const applied = loyalty?.applied ?? 0;

        if (input) {
            input.value = applied || '';
            if (loyalty?.max != null) input.max = loyalty.max;
        }

        renderButton(applied > 0);

        setStatus(
            applied > 0
                ? t('loyalty.applied', { points: applied, value: loyalty.applied_value }, '')
                : '',
            true,
        );

        emit(CART_UPDATED); // giữ mini-cart và số lượng đồng bộ
    }

    async function send(points) {
        try {
            const { data } = points > 0
                ? await api.post(url, { points })
                : await api.delete(url);

            applyCart(data.data ?? data);
        } catch (error) {
            // Server trả lỗi theo TRƯỜNG `points` — hiện đúng câu đó, vì mỗi câu
            // nói một việc khách sửa được (dưới mức tối thiểu / quá trần).
            setStatus(
                error.response?.data?.errors?.points?.[0]
                    || error.response?.data?.message
                    || t('common.error_generic', {}, 'Something went wrong.'),
                false,
            );
        }
    }

    form.addEventListener('click', (event) => {
        if (event.target.closest('[data-loyalty-apply]')) {
            event.preventDefault();
            send(Math.trunc(Number(input?.value) || 0));
        } else if (event.target.closest('[data-loyalty-remove]')) {
            event.preventDefault();
            send(0);
        }
    });

    // Input-group không phải <form>, nên Enter phải tự bắt.
    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            send(Math.trunc(Number(input.value) || 0));
        }
    });
}
