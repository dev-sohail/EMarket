<?php

namespace App\Providers;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DiscountCampaign;
use App\Models\Faq;
use App\Models\FlashSale;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Popup;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductReturn;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCity;
use App\Models\Tag;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Models that have store routes using slug as route key.
     */
    protected static array $slugModels = [
        'product' => Product::class,
        'category' => Category::class,
        'brand' => Brand::class,
        'page' => Page::class,
    ];

    /**
     * Route parameter names to model classes for models using PrefixedId
     * that need admin route binding (admin views pass numeric id).
     */
    protected static array $prefixedIdBindings = [
        'product' => Product::class,
        'category' => Category::class,
        'brand' => Brand::class,
        'page' => Page::class,
        'image' => ProductImage::class,
        'order' => Order::class,
        'coupon' => Coupon::class,
        'campaign' => DiscountCampaign::class,
        'flashSale' => FlashSale::class,
        'paymentMethod' => PaymentMethod::class,
        'zone' => ShippingZone::class,
        'method' => ShippingMethod::class,
        'rule' => ShippingRule::class,
        'tax' => TaxRule::class,
        'return' => ProductReturn::class,
        'review' => Review::class,
        'invoice' => Invoice::class,
        'faq' => Faq::class,
        'banner' => Banner::class,
        'popup' => Popup::class,
        'affiliate' => Affiliate::class,
        'commission' => AffiliateCommission::class,
        'user' => User::class,
        'role' => Role::class,
        'auditLog' => AuditLog::class,
        'customer' => Customer::class,
        'inventory' => Inventory::class,
        'wishlist' => Wishlist::class,
        'blogPost' => BlogPost::class,
        'city' => ShippingZoneCity::class,
        'attribute' => Attribute::class,
        'tag' => Tag::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('components.admin.sidebar', function ($view) {
            $pendingOrdersCount = Cache::remember(
                'admin.pending_orders_count',
                60,
                fn () => Order::where('status', 'pending')->count()
            );
            $view->with('pendingOrdersCount', $pendingOrdersCount);
        });

        View::composer('layouts.store', function ($view) {
            // Load every popup that targets at least one device. Requiring both
            // flags here meant a popup configured "desktop only" (or "mobile
            // only") matched nothing and never rendered; the per-device gate
            // lives in the popup component, which knows the request's device.
            $popups = Cache::remember(
                'store.active_popups',
                60,
                fn () => Popup::active()
                    ->where(fn ($q) => $q->where('show_on_mobile', true)->orWhere('show_on_desktop', true))
                    ->orderBy('sort_order')
                    ->get()
            );

            $view->with('popups', $popups);
        });

        foreach (self::$prefixedIdBindings as $param => $model) {
            Route::bind($param, $this->resolvePrefixedIdBinding($model));
        }

        Validator::extend('belongs_to_product', function ($attribute, $value, $parameters, $validator) {
            $productId = request()->input($parameters[0] ?? 'product_id');

            if (! $productId || ! $value) {
                return true;
            }

            return ProductVariant::where('_id', $value)
                ->where('product_id', $productId)
                ->exists();
        }, 'The selected variant does not belong to the specified product.');
    }

    /**
     * Resolve the route binding: all models use _id as primary key (string prefixed ID).
     * Admin routes receive _id. Store routes use slug for slug models, _id for others.
     */
    protected function resolvePrefixedIdBinding(string $model): callable
    {
        return function ($value) use ($model) {
            $isAdmin = request()->is('admin/*');
            $isSlugModel = array_search($model, self::$slugModels, true);

            if ($isAdmin) {
                return $model::where('_id', $value)->firstOrFail();
            }

            if ($isSlugModel !== false) {
                return $model::where('slug', $value)->firstOrFail();
            }

            return $model::where('_id', $value)->firstOrFail();
        };
    }
}
