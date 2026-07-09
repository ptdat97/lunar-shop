<?php

namespace Modules\Content\Support;

/**
 * Default settings for each section type — taken from the Modave template so a
 * freshly seeded page looks identical to the original design, but every value
 * is now editable from the admin.
 *
 * Single source of truth used by both the seeder and the Filament form.
 */
class SectionSchemas
{
    /**
     * @return array<string, mixed> default settings for a section type
     */
    public static function defaults(string $type): array
    {
        return match ($type) {
            'hero-slider' => [
                'slides' => [
                    [
                        'image' => '/demo/DTT_9009.jpg',
                        'title' => 'Fresh Fashion Finds',
                        'subtitle' => 'Revitalize your style with our latest, exciting fashion selections.',
                        'button_text' => 'Explore Collection',
                        'button_url' => '/search',
                    ],
                    [
                        'image' => '/demo/DTT_9123.jpg',
                        'title' => 'Luxury Outerwear',
                        'subtitle' => 'Indulge in our luxurious outerwear for unmatched elegance and warmth.',
                        'button_text' => 'Shop Sale',
                        'button_url' => '/search',
                    ],
                    [
                        'image' => '/demo/DTT_9150.jpg',
                        'title' => 'Timeless Elegance',
                        'subtitle' => 'Discover pieces that blend classic style with modern comfort.',
                        'button_text' => 'Shop Now',
                        'button_url' => '/search',
                    ],
                ],
            ],

            'collection-grid' => [
                'heading' => 'Shop by collections',
                // Curated by the admin: each item picks a collection + optional
                // image override. Empty until curated (no hard-coded ids).
                'items' => [],
            ],

            'product-tabs' => [
                'heading' => 'Shop the edit',
                // Each tab: an editable label + hand-picked product_ids. Empty
                // product_ids falls back to the newest products so a freshly
                // seeded page still shows something until curated in the admin.
                'limit' => 8,
                'tabs' => [
                    ['label' => 'New Arrivals', 'product_ids' => []],
                    ['label' => 'Best Seller', 'product_ids' => []],
                    ['label' => 'On Sale', 'product_ids' => []],
                ],
            ],

            'promotions-strip' => [],

            'promotion-slider' => [
                'heading' => 'On Sale Now',
                'subheading' => 'Grab these deals before they’re gone.',
                // How many product cards the slider holds (configurable in admin).
                'limit' => 12,
                // Optional: pin to one promotion by handle; empty = all on-sale.
                'promotion' => '',
            ],

            'iconbox' => [
                'items' => [
                    ['icon' => 'icon-return', 'heading' => '14-Day Returns', 'text' => 'Risk-free shopping with easy returns.'],
                    ['icon' => 'icon-shipping', 'heading' => 'Free Shipping', 'text' => 'No extra costs, just the price you see.'],
                    ['icon' => 'icon-headset', 'heading' => '24/7 Support', 'text' => '24/7 support, always here just for you.'],
                    ['icon' => 'icon-sealCheck', 'heading' => 'Member Discounts', 'text' => 'Special prices for our loyal customers.'],
                ],
            ],

            'lookbook' => [
                'heading' => 'Shop The Look',
                'subheading' => 'Get inspired by our curated looks — tap a pin to shop the piece.',
                'slides' => [
                    [
                        'banner' => '/demo/blog-7.jpg',
                        'position' => 'position3',
                        'pin_image' => '/demo/DTT_9602.jpg',
                        'pin_title' => 'Rattan bag with handle',
                        'pin_price' => '$159.99',
                        'pin_url' => '/search',
                    ],
                    [
                        'banner' => '/demo/blog-15.jpg',
                        'position' => 'position5',
                        'pin_image' => '/demo/DTT_9618.jpg',
                        'pin_title' => 'Rattan bag with handle',
                        'pin_price' => '$159.99',
                        'pin_url' => '/search',
                    ],
                ],
            ],

            'testimonial' => [
                'heading' => 'Customer Say!',
                'subheading' => 'Our customers adore our products, and we constantly aim to delight them.',
                'items' => [
                    [
                        'image' => '/demo/IMG_5805.jpeg',
                        'rating' => 5,
                        'text' => 'Fantastic shop! Great selection, fair prices, and friendly staff. Highly recommended. The quality of the products is exceptional, and the prices are very reasonable!',
                        'author' => 'Sybil Sharp',
                        'avatar' => '/demo/IMG_5806.jpeg',
                        'product_name' => 'Contrasting sheepskin sweatshirt',
                        'product_price' => '$60.00',
                    ],
                    [
                        'image' => '/demo/IMG_5807.jpeg',
                        'rating' => 5,
                        'text' => 'Amazing quality and fast shipping. The team was super helpful with my questions. I will definitely shop here again!',
                        'author' => 'Coyle Eric',
                        'avatar' => '/demo/DTT_8954.jpg',
                        'product_name' => 'Yarn-dyed striped sweater',
                        'product_price' => '$45.00',
                    ],
                    [
                        'image' => '/demo/DTT_9009.jpg',
                        'rating' => 5,
                        'text' => 'Beautiful pieces and excellent customer service. Everything arrived perfectly packaged. A wonderful shopping experience overall.',
                        'author' => 'Sybil Sharp',
                        'avatar' => '/demo/IMG_5806.jpeg',
                        'product_name' => 'Contrasting sheepskin sweatshirt',
                        'product_price' => '$60.00',
                    ],
                ],
            ],

            default => [],
        };
    }

    /**
     * Whether this section type pulls dynamic data from Lunar (vs pure settings).
     */
    public static function isDynamic(string $type): bool
    {
        return in_array($type, ['collection-grid', 'product-tabs', 'promotion-slider', 'promotions-strip'], true);
    }
}
