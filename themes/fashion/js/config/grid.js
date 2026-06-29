// Grid configuration for product card layouts
// Centralised so breakpoints/column counts can be tweaked in one place.

export const gridConfig = {
    // Recently viewed strip: 2 cols mobile → 3 cols tablet → 4 cols desktop
    recentlyViewed: 'col-6 col-md-4 col-lg-3',

    // Default grid used by shop/collection/search (kept as-is for now)
    default: 'col-6 col-md-4 col-lg-4',
};