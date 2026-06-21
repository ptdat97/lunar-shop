// Product gallery helper (vanilla). Renders an image set into #product-gallery
// and (re)initialises the PhotoSwipe lightbox — ported from the former Vue
// ProductGallery. The SSR markup renders the initial set; this re-renders when
// the chosen variant changes. Underscore-prefixed: imported, not auto-run.

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

let lightbox = null;

function refreshPhotoSwipe() {
    if (!window.PhotoSwipeLightbox || !window.PhotoSwipe) return;
    if (lightbox) { lightbox.destroy(); lightbox = null; }

    lightbox = new window.PhotoSwipeLightbox({
        gallery: '#product-gallery',
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

/**
 * Render `images` into #product-gallery and re-init the lightbox.
 * @param {ParentNode} root
 * @param {Array<{id,large,zoom,width,height}>} images
 * @param {string} name  alt text
 */
export function MediaUrlGallery(root, images, name) {
    const container = (root.getElementById ? root : document).querySelector('#product-gallery');
    if (!container) return;

    container.innerHTML = (images && images.length)
        ? images.map((img, i) => `
            <div class="${i === 0 ? 'col-12' : 'col-6'}">
                <a href="${esc(img.zoom || img.large)}" class="d-block product-gallery__item rounded"
                   data-pswp-width="${esc(img.width)}" data-pswp-height="${esc(img.height)}"
                   target="_blank" rel="noreferrer">
                    <img src="${esc(img.large)}" alt="${esc(name)}" class="img-fluid rounded w-100"
                         loading="${i === 0 ? 'eager' : 'lazy'}">
                </a>
            </div>`).join('')
        : '<div class="col-12 ratio ratio-4x3 bg-light rounded d-flex align-items-center justify-content-center text-muted">No image</div>';

    refreshPhotoSwipe();
}

/** Init PhotoSwipe on the SSR-rendered gallery (no re-render). */
export function initGalleryLightbox() {
    if (document.querySelector('#product-gallery a.product-gallery__item')) {
        refreshPhotoSwipe();
    }
}
