<?php

namespace App\Enums;

enum PopupPosition: string
{
    case Homepage = 'homepage';
    case AllPages = 'all_pages';
    case ProductPage = 'product_page';
    case CategoryPage = 'category_page';
    case Cart = 'cart';
    case Checkout = 'checkout';

    public function label(): string
    {
        return match ($this) {
            self::Homepage => 'Homepage',
            self::AllPages => 'All Pages',
            self::ProductPage => 'Product Page',
            self::CategoryPage => 'Category Page',
            self::Cart => 'Cart',
            self::Checkout => 'Checkout',
        };
    }
}
