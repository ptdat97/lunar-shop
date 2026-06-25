// Generic content sliders (Swiper). Auto-inits the home-page sliders that are
// pre-rendered server-side by SectionRenderer — those partials can't rely on
// inline @push('scripts') because the section HTML is rendered in isolation,
// outside the layout's @stack('scripts'), so the inline init never reaches the
// page. This vanilla enhancer (auto-run by app.js) initialises them instead.
//
// Swiper is loaded as a global (vendor/swiper) at the end of <body>; by the time
// this runs on DOMContentLoaded it's available (same as enhance/_gallery.js).

function initHeroSliders(root) {
    root.querySelectorAll('[data-hero-swiper]').forEach((el) => {
        if (el.dataset.swiperInit) return;
        el.dataset.swiperInit = '1';
        new window.Swiper(el, {
            loop: el.querySelectorAll('.swiper-slide').length > 1,
            autoplay: { delay: 5000 },
            pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
        });
    });
}

function initPromotionSliders(root) {
    root.querySelectorAll('[data-promotion-swiper]').forEach((el) => {
        if (el.dataset.swiperInit) return;
        el.dataset.swiperInit = '1';
        new window.Swiper(el, {
            slidesPerView: 2,
            spaceBetween: 16,
            navigation: {
                nextEl: el.querySelector('.swiper-button-next'),
                prevEl: el.querySelector('.swiper-button-prev'),
            },
            breakpoints: {
                576: { slidesPerView: 2 },
                768: { slidesPerView: 3 },
                992: { slidesPerView: 4 },
            },
        });
    });
}

export default function (root = document) {
    if (!window.Swiper) return; // vendor not loaded → nothing to enhance
    initHeroSliders(root);
    initPromotionSliders(root);
}
