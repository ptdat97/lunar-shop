{{-- Product + BreadcrumbList JSON-LD for the product page. Pure formatting of
     data already in the view (standards §7): $product, $name, $description,
     $selectedVariant (controller) · $ogImage (Media composer) ·
     $lowestPriceAmount, $currencyCode (Pricing composer) · $inStock (page). --}}
@php
  $jsonLd = [
      '@context' => 'https://schema.org',
      '@type' => 'Product',
      'name' => $name,
      'description' => \Illuminate\Support\Str::limit(strip_tags((string) $description), 300),
      'sku' => $selectedVariant?->sku,
      'image' => $ogImage ? [$ogImage] : [],
      'brand' => $product->brand?->name ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
      'offers' =>
          $lowestPriceAmount !== null
              ? [
                  '@type' => 'Offer',
                  'price' => (string) $lowestPriceAmount,
                  'priceCurrency' => $currencyCode,
                  'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                  'url' => url()->current(),
              ]
              : null,
  ];
  $breadcrumbItems = [['name' => __('storefront.static.home_breadcrumb'), 'url' => route('storefront.home')]];
  if ($collection = $product->collections->first()) {
      $breadcrumbItems[] = [
          'name' => $collection->translateAttribute('name'),
          'url' => $collection->defaultUrl?->slug
              ? route('storefront.collection', $collection->defaultUrl->slug)
              : url()->current(),
      ];
  }
  $breadcrumbItems[] = ['name' => $name, 'url' => url()->current()];
  $breadcrumbLd = [
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => collect($breadcrumbItems)
          ->map(
              fn($item, $i) => [
                  '@type' => 'ListItem',
                  'position' => $i + 1,
                  'name' => $item['name'],
                  'item' => $item['url'],
              ],
          )
          ->all(),
  ];
@endphp
<script type="application/ld+json">@json(array_filter($jsonLd), JSON_UNESCAPED_SLASHES)</script>
<script type="application/ld+json">@json($breadcrumbLd, JSON_UNESCAPED_SLASHES)</script>
