// Product gallery helper (vanilla). A Swiper main slider + thumbnail strip,
// with a PhotoSwipe lightbox bound to the main slides (click → zoom). The SSR
// markup renders the initial set (#product-gallery); this re-renders the same
// structure when the chosen variant changes. Underscore-prefixed: imported by
// product-variant.js, not auto-run.
//
// Swiper + PhotoSwipe load as UMD globals (window.Swiper / window.PhotoSwipe*)
// from public/vendor via the layout.

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

let lightbox = null;
let mainSwiper = null;
let thumbsSwiper = null;

/**
 * Markup for the gallery (main + thumbs). Mirrors the SSR Blade structure so a
 * variant swap produces an identical DOM.
 *
 * @param {Array<{large,zoom,width,height}>} images
 * @param {string} name
 * @param {{width:number,height:number}} [zoomSize]  fallback pswp dimensions
 */
export function galleryHtml(images, name, zoomSize = {}) {
    if (!images || !images.length) {
        return '<div class="ratio ratio-4x3 bg-light rounded d-flex align-items-center justify-content-center text-muted">No image</div>';
    }

    const slides = images.map((img, i) => `
        <div class="swiper-slide">
            <a href="${esc(img.zoom || img.large)}" class="d-block product-gallery__item rounded"
               data-pswp-width="${esc(img.width || zoomSize.width)}" data-pswp-height="${esc(img.height || zoomSize.height)}"
               target="_blank" rel="noreferrer">
                <img src="${esc(img.large)}" alt="${esc(name)}" class="img-fluid rounded w-100"
                     loading="${i === 0 ? 'eager' : 'lazy'}">
            </a>
        </div>`).join('');

    // Thumbs use the small conversion (not large) and sit before the main image.
    const thumbs = images.length > 1 ? `
        <div class="swiper product-gallery__thumbs" data-gallery-thumbs>
            <div class="swiper-wrapper">
                ${images.map((img) => `
                    <div class="swiper-slide">
                        <img src="${esc(img.small || img.large)}" alt="${esc(name)}" class="img-fluid rounded" loading="lazy">
                    </div>`).join('')}
            </div>
        </div>` : '';

    return `
        ${thumbs}
        <div class="swiper product-gallery__main" data-gallery-main>
            <div class="swiper-wrapper">${slides}</div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>`;
}

/** Tear down Swiper + PhotoSwipe instances before a re-init. */
function destroy() {
    if (lightbox) { lightbox.destroy(); lightbox = null; }
    if (mainSwiper) { mainSwiper.destroy(true, true); mainSwiper = null; }
    if (thumbsSwiper) { thumbsSwiper.destroy(true, true); thumbsSwiper = null; }
}

/** Init Swiper (thumbs ↔ main) on the current #product-gallery DOM. */
function initSwiper(container) {
    if (!window.Swiper) return;

    const mainEl = container.querySelector('[data-gallery-main]');
    if (!mainEl) return;

    const thumbsEl = container.querySelector('[data-gallery-thumbs]');
    if (thumbsEl) {
        // Horizontal under the main on mobile; vertical column on the left ≥992px.
        const vertical = window.matchMedia('(min-width: 992px)').matches;
        thumbsSwiper = new window.Swiper(thumbsEl, {
            direction: vertical ? 'vertical' : 'horizontal',
            slidesPerView: 4,
            spaceBetween: 8,
            watchSlidesProgress: true,
            breakpoints: { 992: { slidesPerView: 5, direction: 'vertical' } },
        });
    }

    mainSwiper = new window.Swiper(mainEl, {
        spaceBetween: 8,
        navigation: {
            prevEl: mainEl.querySelector('.swiper-button-prev'),
            nextEl: mainEl.querySelector('.swiper-button-next'),
        },
        thumbs: thumbsSwiper ? { swiper: thumbsSwiper } : undefined,
    });
}

/** (Re)initialise the PhotoSwipe lightbox over the main slides. */
function initPhotoSwipe() {
    if (!window.PhotoSwipeLightbox || !window.PhotoSwipe) return;

    lightbox = new window.PhotoSwipeLightbox({
        gallery: '#product-gallery .product-gallery__main',
        children: 'a.product-gallery__item',
        pswpModule: window.PhotoSwipe,
        showHideAnimationType: 'zoom',
        close: false, zoom: false, counter: false,
        preloader: false, arrowPrev: false, arrowNext: false,
    });

    lightbox.on('uiRegister', () => {
        const pswp = lightbox.pswp;

        pswp.ui.registerElement({
            name: 'blsClose', className: 'pswp__button--bls--close', title: 'Close',
            order: 20, isButton: true,
            html: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
            onClick: 'close',
        });

        pswp.ui.registerElement({
            name: 'bottomBar', className: 'pswp__bottom-bar', appendTo: 'wrapper',
            html: `
                <button type="button" class="pswp__button pswp__button-next" aria-label="Next">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-right"><path d="M13.207 19.793a.707.707 0 0 1-1.207-.5V16a1 1 0 0 0-1-1H5a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h6a1 1 0 0 0 1-1V4.707a.707.707 0 0 1 1.207-.5l6.94 6.94a1.207 1.207 0 0 1 0 1.707z"/></svg>
                </button>
                <span class="pswp__counter"></span>
                <button type="button" class="pswp__button pswp__button-prev" aria-label="Previous">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-left"><path d="M10.793 19.793a.707.707 0 0 0 1.207-.5V16a1 1 0 0 1 1-1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-6a1 1 0 0 1-1-1V4.707a.707.707 0 0 0-1.207-.5l-6.94 6.94a1.207 1.207 0 0 0 0 1.707z"/></svg>
                </button>`,
            onInit: (el, pswp) => {
                const prev = el.querySelector('.pswp__button-prev');
                const next = el.querySelector('.pswp__button-next');
                const counter = el.querySelector('.pswp__counter');
                const update = () => {
                    const total = pswp.getNumItems();
                    counter.textContent = `${pswp.currIndex + 1} / ${total}`;
                    el.classList.toggle('pswp__bottom-bar--single', total <= 1);
                    prev.disabled = !pswp.options.loop && pswp.currIndex <= 0;
                    next.disabled = !pswp.options.loop && pswp.currIndex >= total - 1;
                };
                prev.addEventListener('click', () => pswp.prev());
                next.addEventListener('click', () => pswp.next());
                pswp.on('change', update);
                update();
            },
        });
    });

    lightbox.init();
}

function activate(container) {
    destroy();
    initSwiper(container);
    initPhotoSwipe();
}

/**
 * Re-render the gallery for a chosen variant's images, then re-init Swiper +
 * lightbox.
 *
 * @param {ParentNode} root
 * @param {Array<{large,zoom,width,height}>} images
 * @param {string} name
 */
export function MediaUrlGallery(root, images, name) {
    const container = (root.getElementById ? root : document).querySelector('#product-gallery');
    if (!container) return;

    container.innerHTML = galleryHtml(images, name);
    activate(container);
}

/** Init Swiper + PhotoSwipe on the SSR-rendered gallery (no re-render). */
export function initGalleryLightbox() {
    const container = document.querySelector('#product-gallery');
    if (container && container.querySelector('[data-gallery-main]')) {
        activate(container);
    }
}
