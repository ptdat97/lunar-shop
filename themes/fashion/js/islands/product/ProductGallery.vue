<script setup>
// Product gallery, driven by the currently-selected variant. Shows the variant's
// own images when it has any, otherwise the product-level images. PhotoSwipe is
// (re)initialised whenever the image set changes, since the lightbox binds to the
// rendered DOM.
import { computed, nextTick, onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps({
    // Product-level images (the default / fallback set).
    images: { type: Array, default: () => [] },
    name: { type: String, default: '' },
    // The variant chosen in the purchase panel (may be null).
    variant: { type: Object, default: null },
});

// Variant images win when present; otherwise fall back to product images.
const gallery = computed(() => {
    const vImages = props.variant?.images;
    if (Array.isArray(vImages) && vImages.length) return vImages;
    return props.images;
});

let lightbox = null;

async function refreshPhotoSwipe() {
    await nextTick();
    if (!window.PhotoSwipeLightbox || !window.PhotoSwipe) return;
    if (lightbox) { lightbox.destroy(); lightbox = null; }

    lightbox = new window.PhotoSwipeLightbox({
        gallery: '#product-gallery',
        children: 'a.product-gallery__item',
        pswpModule: window.PhotoSwipe,
        showHideAnimationType: 'zoom',
        close: false,
        zoom: false,
        counter: false,
        preloader: false,
        arrowPrev: false,
        arrowNext: false,
    });

    lightbox.on('uiRegister', () => {
        const pswp = lightbox.pswp;

        pswp.ui.registerElement({
            name: 'blsClose',
            className: 'pswp__button--bls--close',
            title: 'Close',
            order: 20,
            isButton: true,
            html: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
            onClick: 'close',
        });

        pswp.ui.registerElement({
            name: 'bottomBar',
            className: 'pswp__bottom-bar',
            appendTo: 'wrapper',
            html: `
                <button type="button" class="pswp__button pswp__button-next" aria-label="Next">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-right"><path d="M13.207 19.793a.707.707 0 0 1-1.207-.5V16a1 1 0 0 0-1-1H5a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h6a1 1 0 0 0 1-1V4.707a.707.707 0 0 1 1.207-.5l6.94 6.94a1.207 1.207 0 0 1 0 1.707z"/></svg>
                </button>
                <span class="pswp__counter"></span>
                <button type="button" class="pswp__button pswp__button-prev" aria-label="Previous">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-left"><path d="M10.793 19.793a.707.707 0 0 0 1.207-.5V16a1 1 0 0 1 1-1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-6a1 1 0 0 1-1-1V4.707a.707.707 0 0 0-1.207-.5l-6.94 6.94a1.207 1.207 0 0 0 0 1.707z"/></svg>
                </button>
            `,
            onInit: (el, pswp) => {
                const prevButton = el.querySelector('.pswp__button-prev');
                const nextButton = el.querySelector('.pswp__button-next');
                const counter = el.querySelector('.pswp__counter');

                const updateBottomBar = () => {
                    const total = pswp.getNumItems();
                    counter.textContent = `${pswp.currIndex + 1} / ${total}`;
                    el.classList.toggle('pswp__bottom-bar--single', total <= 1);
                    prevButton.disabled = !pswp.options.loop && pswp.currIndex <= 0;
                    nextButton.disabled = !pswp.options.loop && pswp.currIndex >= total - 1;
                };

                prevButton.addEventListener('click', () => pswp.prev());
                nextButton.addEventListener('click', () => pswp.next());
                pswp.on('change', updateBottomBar);
                updateBottomBar();
            },
        });
    });

    lightbox.init();
}

// Re-init whenever the visible image set changes (variant swap) or on mount.
watch(gallery, refreshPhotoSwipe);
onMounted(refreshPhotoSwipe);
onBeforeUnmount(() => { if (lightbox) { lightbox.destroy(); lightbox = null; } });
</script>

<template>
    <div class="row g-2" id="product-gallery" data-pswp-gallery>
        <template v-if="gallery.length">
            <div v-for="(img, i) in gallery" :key="img.id ?? i" :class="i === 0 ? 'col-12' : 'col-6'">
                <a :href="img.zoom || img.large" class="d-block product-gallery__item rounded"
                   :data-pswp-width="img.width" :data-pswp-height="img.height"
                   target="_blank" rel="noreferrer">
                    <img :src="img.large" :alt="name" class="img-fluid rounded w-100"
                         :loading="i === 0 ? 'eager' : 'lazy'">
                </a>
            </div>
        </template>
        <div v-else
             class="col-12 ratio ratio-4x3 bg-light rounded d-flex align-items-center justify-content-center text-muted">
            No image
        </div>
    </div>
</template>
