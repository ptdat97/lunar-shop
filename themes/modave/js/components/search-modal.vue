<script setup>
import { ref, watch } from 'vue';

const term = ref('');
const suggestions = ref([]);
const products = ref([]);
const loading = ref(false);
let timer = null;

// Popular keywords (could come from API later; static for now).
const keywords = ['Dress', 'Jacket', 'T-shirt', 'Trousers', 'Coat'];

watch(term, (value) => {
    clearTimeout(timer);
    if (value.trim().length < 2) {
        suggestions.value = [];
        products.value = [];
        return;
    }
    timer = setTimeout(fetchResults, 250);
});

async function fetchResults() {
    loading.value = true;
    try {
        const [sug, res] = await Promise.all([
            window.axios.get('/api/v1/search/suggest', { params: { q: term.value, limit: 6 } }),
            window.axios.get('/api/v1/search', { params: { q: term.value, per_page: 6 } }),
        ]);
        suggestions.value = sug.data.data ?? [];
        products.value = res.data.data ?? [];
    } catch (e) {
        suggestions.value = [];
        products.value = [];
    } finally {
        loading.value = false;
    }
}

function submit() {
    if (term.value.trim()) {
        window.location.href = `/search?q=${encodeURIComponent(term.value)}`;
    }
}

function pick(value) {
    term.value = value;
    fetchResults();
}

const PLACEHOLDER = '/themes/modave/images/products/womens/women-8.jpg';

// The search API exposes a single thumbnail; reuse it for the hover image so
// the modave card markup (img-product + img-hover) renders without gaps.
function image(p) {
    return p.thumbnail || PLACEHOLDER;
}
</script>

<template>
    <div class="search-modal-body">
        <div class="d-flex justify-content-between align-items-center">
            <h5>Search</h5>
            <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
        </div>

        <form class="form-search" @submit.prevent="submit">
            <fieldset class="text">
                <input type="text" v-model="term" placeholder="Searching..." autocomplete="off">
            </fieldset>
            <button type="submit">
                <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M21.35 21.0004L17 16.6504" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </form>

        <!-- Suggestions (autocomplete) -->
        <ul v-if="suggestions.length" class="search-suggestions list-tags mb_16">
            <li v-for="s in suggestions" :key="s">
                <a href="javascript:void(0);" class="radius-60 link" @click="pick(s)">{{ s }}</a>
            </li>
        </ul>

        <!-- Popular keywords (when nothing typed) -->
        <div v-else-if="!term">
            <h5 class="mb_16">Feature keywords Today</h5>
            <ul class="list-tags">
                <li v-for="k in keywords" :key="k">
                    <a href="javascript:void(0);" class="radius-60 link" @click="pick(k)">{{ k }}</a>
                </li>
            </ul>
        </div>

        <!-- Live product results -->
        <div v-if="loading" class="text-center mt_16">Searching…</div>
        <div v-else-if="products.length">
            <h6 class="mb_16">Products</h6>
            <div class="tf-grid-layout tf-col-2 lg-col-3 xl-col-4 loadmore-item">
                <div v-for="p in products" :key="p.id" class="fl-item card-product">
                    <div class="card-product-wrapper">
                        <a :href="`/products/${p.slug}`" class="product-img">
                            <img class="lazyload img-product" :src="image(p)" :alt="p.name">
                            <img class="lazyload img-hover" :src="image(p)" :alt="p.name">
                        </a>
                        <div class="list-product-btn">
                            <a href="javascript:void(0);" class="box-icon wishlist btn-icon-action">
                                <span class="icon icon-heart"></span>
                                <span class="tooltip">Wishlist</span>
                            </a>
                            <a href="#compare" data-bs-toggle="offcanvas" aria-controls="compare" class="box-icon compare btn-icon-action">
                                <span class="icon icon-gitDiff"></span>
                                <span class="tooltip">Compare</span>
                            </a>
                            <a :href="`/products/${p.slug}`" class="box-icon quickview tf-btn-loading">
                                <span class="icon icon-eye"></span>
                                <span class="tooltip">Quick View</span>
                            </a>
                        </div>
                        <div class="list-btn-main">
                            <a :href="`/products/${p.slug}`" class="btn-main-product">View product</a>
                        </div>
                    </div>
                    <div class="card-product-info">
                        <a :href="`/products/${p.slug}`" class="title link">{{ p.name }}</a>
                        <span class="price current-price" v-if="p.variants?.[0]">{{ p.variants[0].price.formatted }}</span>
                    </div>
                </div>
            </div>
            <a :href="`/search?q=${encodeURIComponent(term)}`" class="tf-btn btn-fill mt_16 d-inline-block">
                <span class="text text-btn-uppercase">View all results</span>
            </a>
        </div>
        <p v-else-if="term && !loading" class="mt_16 text-secondary">No products found for “{{ term }}”.</p>
    </div>
</template>
