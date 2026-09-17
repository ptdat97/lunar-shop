<?php

return [
    // New reviews are visible immediately. Set false to require admin approval.
    'auto_approve' => env('REVIEW_AUTO_APPROVE', true),

    // Ảnh khách gửi kèm đánh giá.
    //
    // Ở lại config chứ không ra admin (roadmap § "cái gì ra admin"): đây là
    // trần kỹ thuật của đường tải lên, không phải quyết định kinh doanh. Số
    // ảnh tối đa KHÔNG nằm ở đây mà là hằng số `Review::MAX_PHOTOS`, vì nó
    // phải khớp với số cột ảnh của hàng đợi duyệt trong panel.
    'photos' => [
        // Tắt là form ẩn ô chọn ảnh và endpoint từ chối file — một công tắc,
        // hai đầu, không có đường vòng.
        'enabled' => env('REVIEW_PHOTOS_ENABLED', true),

        // KB. Ảnh điện thoại ngày nay 3–8 MB là bình thường.
        'max_size_kb' => (int) env('REVIEW_PHOTOS_MAX_SIZE_KB', 8192),
    ],
];
