<?php

use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Customer\AddressController;
use App\Http\Controllers\Customer\AffiliateController as CustomerAffiliateController;
use App\Http\Controllers\Customer\Auth\ForgotPasswordController;
use App\Http\Controllers\Customer\Auth\LoginController;
use App\Http\Controllers\Customer\Auth\RegisterController;
use App\Http\Controllers\Customer\Auth\VerificationController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\ReturnController;
use App\Http\Controllers\Customer\ReviewController;
use App\Http\Controllers\Customer\WishlistController;
use App\Http\Controllers\Store\AffiliateController as StoreAffiliateController;
use App\Http\Controllers\Store\BlogController;
use App\Http\Controllers\Store\BrandController;
use App\Http\Controllers\Store\CampaignController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CategoryController;
use App\Http\Controllers\Store\FaqController;
use App\Http\Controllers\Store\FlashSaleController;
use App\Http\Controllers\Store\HomeController;
use App\Http\Controllers\Store\PageController;
use App\Http\Controllers\Store\ReferralController;
use App\Http\Controllers\Store\SearchController;
use App\Http\Controllers\Store\ShopController;
use App\Http\Controllers\StripeController;
use App\Models\Enquiry;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Store Frontend Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/product/{product}', [ShopController::class, 'show'])->name('product.show');
Route::get('/category/{category}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/brand/{brand}', [BrandController::class, 'show'])->name('brand.show');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/flash-sales', [FlashSaleController::class, 'index'])->name('flash-sales.index');
Route::get('/flash-sales/{flashSale}', [FlashSaleController::class, 'show'])->name('flash-sales.show');
Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Affiliate referral capture
Route::get('/ref/{code}', [ReferralController::class, 'go'])->name('referral.go');

// Affiliate program landing page
Route::get('/affiliate', [StoreAffiliateController::class, 'landing'])->name('affiliate');

// Contact
Route::get('/contact', fn () => view('store.pages.contact'))->name('store.contact');
Route::post('/contact', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'subject' => 'required|string|max:255',
        'message' => 'required|string|max:5000',
    ]);

    Enquiry::create($validated);

    return back()->with('success', 'Thank you! Your message has been sent.');
})->middleware('throttle:5,1')->name('store.contact.submit');

// FAQ
Route::get('/faq', [FaqController::class, 'index'])->name('faq');

// System Status Page
//
// Behind auth + a permission, not public. It renders the PHP/Laravel versions,
// APP_ENV, whether APP_DEBUG is on, the server software banner and which
// integrations are enabled — an anonymous visitor could read all of that to pick
// a target, and the database branch below used to concatenate the raw PDO
// exception into the response ("Access denied for user 'x'@'localhost'"),
// disclosing the DB user and host precisely when the database was unreachable.
// That branch is now a static string with the exception handed to the log.
Route::middleware(['auth:admin', 'admin:audit_logs.view'])
    ->get('/up-status', function () {
        $statuses = [];
        $allOperational = true;

        // Check Database Connection
        try {
            DB::connection()->getPdo();
            $statuses['database'] = [
                'name' => 'Database',
                'status' => 'operational',
                'icon' => 'fa-database',
                'message' => 'MySQL connection is healthy',
            ];
        } catch (Exception $e) {
            // Never concatenate $e->getMessage() here: a PDO auth failure reads
            // "SQLSTATE[HY000] [1045] Access denied for user '*'@'localhost'",
            // which hands an anonymous visitor the DB username and host. report() to
            // the log, show nothing.
            report($e);

            $statuses['database'] = [
                'name' => 'Database',
                'status' => 'critical',
                'icon' => 'fa-database',
                'message' => 'Database connection failed',
            ];
            $allOperational = false;
        }

        // Check Cache
        try {
            Cache::put('status_check', 'ok', 60);
            if (Cache::get('status_check') === 'ok') {
                $statuses['cache'] = [
                    'name' => 'Cache System',
                    'status' => 'operational',
                    'icon' => 'fa-server',
                    'message' => 'Cache is working properly',
                ];
            } else {
                throw new Exception('Cache write failed');
            }
        } catch (Exception $e) {
            $statuses['cache'] = [
                'name' => 'Cache System',
                'status' => 'warning',
                'icon' => 'fa-server',
                'message' => 'Cache system experiencing issues',
            ];
            $allOperational = false;
        }

        // Check File Storage
        try {
            $storagePath = storage_path('app');
            if (is_writable($storagePath)) {
                $statuses['storage'] = [
                    'name' => 'File Storage',
                    'status' => 'operational',
                    'icon' => 'fa-folder',
                    'message' => 'Storage directories are writable',
                ];
            } else {
                throw new Exception('Storage not writable');
            }
        } catch (Exception $e) {
            $statuses['storage'] = [
                'name' => 'File Storage',
                'status' => 'warning',
                'icon' => 'fa-folder',
                'message' => 'Storage permissions issue detected',
            ];
            $allOperational = false;
        }

        // Feature Flags from Settings
        $features = [
            'returns_enabled' => 'Returns System',
            'reviews_enabled' => 'Product Reviews',
            'affiliate_enabled' => 'Affiliate Program',
            'wishlist_enabled' => 'Wishlist',
            'compare_enabled' => 'Product Comparison',
            'stock_notifications_enabled' => 'Stock Notifications',
            'newsletter_enabled' => 'Newsletter',
            'coupons_enabled' => 'Coupon System',
            'flash_sales_enabled' => 'Flash Sales',
            'b2b_enabled' => 'B2B Features',
        ];

        foreach ($features as $key => $name) {
            $enabled = setting($key, false);
            $statuses[$key] = [
                'name' => $name,
                'status' => $enabled ? 'enabled' : 'disabled',
                'icon' => 'fa-toggle-'.($enabled ? 'on' : 'off'),
                'message' => $enabled ? 'Feature is active' : 'Feature is disabled',
            ];
        }

        // Check Active Plugins/Modules
        $modules = [
            'payment_stripe' => 'Stripe Payments',
            'payment_paypal' => 'PayPal Payments',
            'shipping_royal_mail' => 'Royal Mail Shipping',
            'shipping_dhl' => 'DHL Shipping',
            'tax_eu' => 'EU VAT Calculation',
        ];

        foreach ($modules as $key => $name) {
            $enabled = setting($key, false);
            $statuses[$key] = [
                'name' => $name,
                'status' => $enabled ? 'enabled' : 'disabled',
                'icon' => 'fa-plug',
                'message' => $enabled ? 'Integration active' : 'Integration inactive',
            ];
        }

        // Get System Information
        $systemInfo = [
            'php_version' => phpversion(),
            'laravel_version' => Application::VERSION,
            'app_url' => config('app.url'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug') ? 'Enabled' : 'Disabled',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'uptime' => file_exists('/proc/uptime') ? file_get_contents('/proc/uptime') : 'N/A',
        ];

        return view('store.pages.up-status', compact('statuses', 'allOperational', 'systemInfo'));
    })->name('up-status');

// Auth Routes (Customer)
// The write endpoints are throttled at the same rate as the admin login
// (routes/admin.php, throttle:5,1) so the customer login is no longer the
// weakest-credentialed entry point in the app.
Route::get('/register', [RegisterController::class, 'showForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
Route::get('/email/verify', [VerificationController::class, 'show'])->middleware('auth')->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [RegisterController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/email/resend', [VerificationController::class, 'resend'])->middleware(['auth', 'throttle:6,1'])->name('verification.resend');
Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/password/forgot', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:6,1')->name('password.email');
Route::get('/password/reset/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/password/reset', [ForgotPasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');

// Customer Dashboard Routes (Auth Required)
Route::middleware(['auth'])->prefix('customer')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('customer.dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('customer.profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('customer.profile.update');
    Route::get('/addresses', [AddressController::class, 'index'])->name('customer.addresses');
    Route::get('/addresses/create', [AddressController::class, 'create'])->name('customer.addresses.create');
    Route::post('/addresses', [AddressController::class, 'store'])->name('customer.addresses.store');
    Route::get('/addresses/{address}/edit', [AddressController::class, 'edit'])->name('customer.addresses.edit');
    Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('customer.addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('customer.addresses.destroy');
    Route::post('/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('customer.addresses.default');

    Route::middleware('customer.has_address')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('customer.orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('customer.orders.show');
        Route::get('/returns', [ReturnController::class, 'index'])->name('customer.returns');
        Route::get('/returns/create/{order}', [ReturnController::class, 'create'])->name('customer.returns.create');
        Route::post('/returns', [ReturnController::class, 'store'])->name('customer.returns.store');
        Route::get('/returns/{return}', [ReturnController::class, 'show'])->name('customer.returns.show');
    });

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('customer.wishlist');
    Route::post('/wishlist/{product}/toggle', [WishlistController::class, 'toggle'])->name('customer.wishlist.toggle');
    Route::get('/reviews', [ReviewController::class, 'index'])->name('customer.reviews');
    Route::get('/reviews/create/{product}', [ReviewController::class, 'create'])->name('customer.reviews.create');
    Route::post('/reviews', [ReviewController::class, 'store'])->name('customer.reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('customer.reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('customer.reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('customer.reviews.destroy');
    Route::get('/affiliate', [CustomerAffiliateController::class, 'index'])->name('customer.affiliate');
    Route::post('/affiliate/apply', [CustomerAffiliateController::class, 'apply'])->name('customer.affiliate.apply');
});

// Checkout Routes (Auth or Guest)
Route::middleware(['guest_or_customer'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::get('/order/success/{orderNumber}', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/stripe/payment/{orderNumber}', [StripeController::class, 'success'])->name('stripe.payment');
    Route::get('/stripe/status/{orderNumber}', [StripeController::class, 'status'])->name('stripe.status');
    Route::post('/stripe/cancel/{orderNumber}', [StripeController::class, 'cancel'])->name('stripe.cancel');
});

// Stripe Webhook (no CSRF, public endpoint)
Route::post('/stripe/webhook', [StripeController::class, 'webhook'])->middleware('throttle:60,1')->name('stripe.webhook');

// Order Tracking (Guest Allowed)
Route::middleware(['throttle:10,1'])->group(function () {
    Route::get('/order/track', [OrderController::class, 'trackForm'])->name('order.track');
    Route::post('/order/track', [OrderController::class, 'track'])->name('order.track.result');
});

// Checkout PDF export
// guest_or_customer rather than no middleware: the middleware lets guests
// through (checkout is reachable before sign-in) while steering back-office
// staff away, and the controller already scopes the cart to
// auth()->user()?->customer or the request session.
Route::middleware('guest_or_customer')
    ->get('ajax/checkout/pdf', [CheckoutController::class, 'generatePdf'])
    ->name('checkout.pdf');

// Public page routes (resolved by slug, placed after all specific routes)
Route::get('/{page}', [PageController::class, 'show'])->name('page.show')->whereIn('page', [
    'about-us', 'terms', 'privacy-policy', 'delivery-info', 'return-policy', 'peptide-storage',
]);
