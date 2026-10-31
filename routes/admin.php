<?php

use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ApiManagement\ApiDefinitionController;
use App\Http\Controllers\Admin\ApiManagement\ApiDefinitionReflectorController;
use App\Http\Controllers\Admin\ApiManagement\ApiEndpointController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChatShopController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\ImportExportController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderProcessorController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PopupController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/admin', [DashboardController::class, 'index'])
    ->middleware(['auth:admin', 'admin'])
    ->name('admin.dashboard');

Route::middleware(['guest:admin'])->prefix('admin')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('admin.login');
    Route::post('/login', [LoginController::class, 'login'])->name('admin.login.store')->middleware('throttle:5,1');
});

Route::middleware(['auth:admin', 'admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', function () {
        return redirect('/admin');
    })->name('admin.dashboard.redirect');
    Route::post('/logout', [LoginController::class, 'logout'])->name('admin.logout');
    Route::get('/delete-approval', [CustomerController::class, 'deleteApproval'])->name('admin.delete-approval');
    Route::post('/delete-approval/{id}/force-delete', [CustomerController::class, 'forceDelete'])->name('admin.force-delete');

    // Products
    Route::resource('products', ProductController::class)->names([
        'index' => 'admin.products.index',
        'create' => 'admin.products.create',
        'store' => 'admin.products.store',
        'edit' => 'admin.products.edit',
        'update' => 'admin.products.update',
        'destroy' => 'admin.products.destroy',
    ])->except('show');
    Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('admin.products.toggleStatus');
    Route::patch('/products/{product}/toggle-featured', [ProductController::class, 'toggleFeatured'])->name('admin.products.toggleFeatured');
    Route::get('/products/{product}/images', [ProductController::class, 'manageImages'])->name('admin.products.images');
    Route::post('/products/{product}/images', [ProductController::class, 'storeImages'])->name('admin.products.images.store');
    Route::delete('/products/{product}/images/bulk-delete', [ProductController::class, 'bulkDeleteImages'])->name('admin.products.images.bulk-delete');
    Route::delete('/products/{product}/images/{image}', [ProductController::class, 'deleteImage'])->name('admin.products.images.destroy');
    Route::post('/products/{product}/images/reorder', [ProductController::class, 'reorderImages'])->name('admin.products.images.reorder');
    Route::post('/products/{product}/images/{image}/primary', [ProductController::class, 'setPrimaryImage'])->name('admin.products.images.primary');

    // Categories
    Route::resource('categories', CategoryController::class)->names([
        'index' => 'admin.categories.index',
        'create' => 'admin.categories.create',
        'store' => 'admin.categories.store',
        'edit' => 'admin.categories.edit',
        'update' => 'admin.categories.update',
        'destroy' => 'admin.categories.destroy',
    ])->except('show');
    Route::patch('/categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('admin.categories.toggleStatus');

    // Brands
    Route::resource('brands', BrandController::class)->names([
        'index' => 'admin.brands.index',
        'create' => 'admin.brands.create',
        'store' => 'admin.brands.store',
        'edit' => 'admin.brands.edit',
        'update' => 'admin.brands.update',
        'destroy' => 'admin.brands.destroy',
    ])->except('show');
    Route::patch('/brands/{brand}/toggle-status', [BrandController::class, 'toggleStatus'])->name('admin.brands.toggleStatus');

    // Tags
    Route::get('/tags', [TagController::class, 'index'])->name('admin.tags.index');
    Route::get('/tags/create', [TagController::class, 'create'])->name('admin.tags.create');
    Route::post('/tags', [TagController::class, 'store'])->name('admin.tags.store');
    Route::get('/tags/{tag}/edit', [TagController::class, 'edit'])->name('admin.tags.edit');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->name('admin.tags.update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('admin.tags.destroy');

    // Attributes
    Route::resource('attributes', AttributeController::class)->names([
        'index' => 'admin.attributes.index',
        'create' => 'admin.attributes.create',
        'store' => 'admin.attributes.store',
        'edit' => 'admin.attributes.edit',
        'update' => 'admin.attributes.update',
        'destroy' => 'admin.attributes.destroy',
    ])->except('show');

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->name('admin.inventory.index');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('admin.inventory.movements');
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->name('admin.inventory.low-stock');
    Route::get('/inventory/{inventory}', [InventoryController::class, 'show'])->name('admin.inventory.show');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('admin.inventory.update');
    Route::post('/inventory/{inventory}/adjust', [InventoryController::class, 'update'])->name('admin.inventory.adjust');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('admin.orders.show');
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('admin.orders.status');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('admin.orders.cancel');
    Route::get('/orders/{order}/print', [OrderController::class, 'printOrder'])->name('admin.orders.print');

    // Order Processor
    Route::get('/order-processor', [OrderProcessorController::class, 'index'])->name('admin.order-processor.index');
    Route::match(['post', 'put'], '/order-processor/bulk', [OrderProcessorController::class, 'bulkUpdateStatus'])->name('admin.order-processor.bulk');
    Route::post('/order-processor/{order}/verify', [OrderProcessorController::class, 'verifyOrder'])->name('admin.order-processor.verify');
    Route::post('/order-processor/{order}/fulfill', [OrderProcessorController::class, 'markReadyForFulfillment'])->name('admin.order-processor.fulfill');
    Route::post('/order-processor/{order}/shipping', [OrderProcessorController::class, 'updateShippingDetails'])->name('admin.order-processor.shipping');
    Route::get('/order-processor/{order}/picking-list', [OrderProcessorController::class, 'generatePickingList'])->name('admin.order-processor.picking-list');
    Route::get('/order-processor/{order}/label', [OrderProcessorController::class, 'generateLabel'])->name('admin.order-processor.label');
    Route::get('/order-processor/{order}/receipt', [OrderProcessorController::class, 'generateReceipt'])->name('admin.order-processor.receipt');

    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])->name('admin.customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('admin.customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('admin.customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('admin.customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('admin.customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('admin.customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('admin.customers.destroy');
    Route::put('/customers/{customer}/status', [CustomerController::class, 'toggleStatus'])->name('admin.customers.status');
    Route::post('/customers/{customer}/user-account', [CustomerController::class, 'createUserAccount'])->name('admin.customers.user-account');
    Route::post('/customers/{customer}/impersonate', [CustomerController::class, 'impersonate'])->name('admin.customers.impersonate');

    // Coupons
    Route::resource('coupons', CouponController::class)->names([
        'index' => 'admin.coupons.index',
        'create' => 'admin.coupons.create',
        'store' => 'admin.coupons.store',
        'edit' => 'admin.coupons.edit',
        'update' => 'admin.coupons.update',
        'destroy' => 'admin.coupons.destroy',
    ])->except('show');
    Route::patch('/coupons/{coupon}/toggle-status', [CouponController::class, 'toggleStatus'])->name('admin.coupons.toggleStatus');

    // Campaigns
    Route::resource('campaigns', CampaignController::class)->names([
        'index' => 'admin.campaigns.index',
        'create' => 'admin.campaigns.create',
        'store' => 'admin.campaigns.store',
        'edit' => 'admin.campaigns.edit',
        'update' => 'admin.campaigns.update',
        'destroy' => 'admin.campaigns.destroy',
    ])->except('show');
    Route::patch('/campaigns/{campaign}/toggle-status', [CampaignController::class, 'toggleStatus'])->name('admin.campaigns.toggleStatus');

    // Flash Sales
    Route::resource('flash-sales', FlashSaleController::class)->names([
        'index' => 'admin.flash-sales.index',
        'create' => 'admin.flash-sales.create',
        'store' => 'admin.flash-sales.store',
        'edit' => 'admin.flash-sales.edit',
        'update' => 'admin.flash-sales.update',
        'destroy' => 'admin.flash-sales.destroy',
    ])->except('show')->parameters(['flash-sales' => 'flashSale']);
    Route::patch('/flash-sales/{flashSale}/toggle-status', [FlashSaleController::class, 'toggleStatus'])->name('admin.flash-sales.toggleStatus');

    // Payment Methods (fixed catalog: COD and Online; names, instructions and availability are configurable)
    Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('admin.payment-methods.index');
    Route::get('/payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])->name('admin.payment-methods.edit');
    Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('admin.payment-methods.update');

    // Shipping
    Route::get('/shipping', [ShippingController::class, 'index'])->name('admin.shipping.index');

    // Shipping Zones
    Route::get('/shipping/zones', [ShippingController::class, 'zonesIndex'])->name('admin.shipping.zones.index');
    Route::get('/shipping/zones/create', [ShippingController::class, 'zoneCreate'])->name('admin.shipping.zones.create');
    Route::post('/shipping/zones', [ShippingController::class, 'zoneStore'])->name('admin.shipping.zones.store');
    Route::get('/shipping/zones/{zone}/edit', [ShippingController::class, 'zoneEdit'])->name('admin.shipping.zones.edit');
    Route::put('/shipping/zones/{zone}', [ShippingController::class, 'zoneUpdate'])->name('admin.shipping.zones.update');
    Route::delete('/shipping/zones/{zone}', [ShippingController::class, 'zoneDestroy'])->name('admin.shipping.zones.destroy');

    // Shipping Methods
    Route::get('/shipping/methods', [ShippingController::class, 'methodsIndex'])->name('admin.shipping.methods.index');
    Route::get('/shipping/methods/{method}/edit', [ShippingController::class, 'methodEdit'])->name('admin.shipping.methods.edit');
    Route::put('/shipping/methods/{method}', [ShippingController::class, 'methodUpdate'])->name('admin.shipping.methods.update');
    Route::post('/shipping/methods', [ShippingController::class, 'methodStore'])->name('admin.shipping.methods.store');
    Route::delete('/shipping/methods/{method}', [ShippingController::class, 'methodDestroy'])->name('admin.shipping.methods.destroy');

    // Shipping Rules
    Route::get('/shipping/zones/{zone}/rules', [ShippingController::class, 'rulesIndex'])->name('admin.shipping.rules.index');
    Route::get('/shipping/rules/{rule}/edit', [ShippingController::class, 'ruleEdit'])->name('admin.shipping.rules.edit');
    Route::put('/shipping/rules/{rule}', [ShippingController::class, 'ruleUpdate'])->name('admin.shipping.rules.update');
    Route::post('/shipping/rules', [ShippingController::class, 'ruleStore'])->name('admin.shipping.rules.store');
    Route::delete('/shipping/rules/{rule}', [ShippingController::class, 'ruleDestroy'])->name('admin.shipping.rules.destroy');

    // Tax
    Route::get('/tax', [TaxController::class, 'index'])->name('admin.tax.index');
    Route::post('/tax', [TaxController::class, 'store'])->name('admin.tax.store');
    Route::put('/tax/{tax}', [TaxController::class, 'update'])->name('admin.tax.update');
    Route::delete('/tax/{tax}', [TaxController::class, 'destroy'])->name('admin.tax.destroy');
    Route::patch('/tax/{tax}/toggle-status', [TaxController::class, 'toggleStatus'])->name('admin.tax.toggleStatus');

    // Returns
    Route::get('/returns', [ReturnController::class, 'index'])->name('admin.returns.index');
    Route::get('/returns/{return}', [ReturnController::class, 'show'])->name('admin.returns.show');
    Route::post('/returns/{return}/approve', [ReturnController::class, 'approve'])->name('admin.returns.approve');
    Route::post('/returns/{return}/reject', [ReturnController::class, 'reject'])->name('admin.returns.reject');
    Route::post('/returns/{return}/complete', [ReturnController::class, 'complete'])->name('admin.returns.complete');
    Route::post('/returns/{return}/note', [ReturnController::class, 'addNote'])->name('admin.returns.addNote');

    // Reviews
    Route::get('/reviews', [ReviewController::class, 'index'])->name('admin.reviews.index');
    Route::post('/reviews/{review}/approve', [ReviewController::class, 'approve'])->name('admin.reviews.approve');
    Route::post('/reviews/{review}/reject', [ReviewController::class, 'reject'])->name('admin.reviews.reject');
    Route::post('/reviews/toggle-front', [ReviewController::class, 'toggleFront'])->name('admin.reviews.toggleFront');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('admin.reviews.destroy');

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('admin.invoices.index');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('admin.invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('admin.invoices.show');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('admin.invoices.download');

    // Pages
    Route::resource('pages', PageController::class)->names([
        'index' => 'admin.pages.index',
        'create' => 'admin.pages.create',
        'store' => 'admin.pages.store',
        'edit' => 'admin.pages.edit',
        'update' => 'admin.pages.update',
        'destroy' => 'admin.pages.destroy',
    ])->except('show');
    Route::patch('/pages/{page}/toggle-status', [PageController::class, 'toggleStatus'])->name('admin.pages.toggleStatus');

    // FAQs
    Route::resource('faqs', FaqController::class)->names([
        'index' => 'admin.faqs.index',
        'create' => 'admin.faqs.create',
        'store' => 'admin.faqs.store',
        'edit' => 'admin.faqs.edit',
        'update' => 'admin.faqs.update',
        'destroy' => 'admin.faqs.destroy',
    ])->except('show');
    Route::patch('/faqs/{faq}/toggle-status', [FaqController::class, 'toggleStatus'])->name('admin.faqs.toggleStatus');

    // Enquiries
    Route::get('/enquiries', [EnquiryController::class, 'index'])->name('admin.enquiries.index');
    Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('admin.enquiries.show');
    Route::put('/enquiries/{enquiry}', [EnquiryController::class, 'update'])->name('admin.enquiries.update');
    Route::delete('/enquiries/{enquiry}', [EnquiryController::class, 'destroy'])->name('admin.enquiries.destroy');

    // Banners
    Route::patch('/banners/reorder', [BannerController::class, 'reorder'])->name('admin.banners.reorder');
    Route::resource('banners', BannerController::class)->names([
        'index' => 'admin.banners.index',
        'create' => 'admin.banners.create',
        'store' => 'admin.banners.store',
        'edit' => 'admin.banners.edit',
        'update' => 'admin.banners.update',
        'destroy' => 'admin.banners.destroy',
    ])->except('show');
    Route::patch('/banners/{banner}/toggle-status', [BannerController::class, 'toggleStatus'])->name('admin.banners.toggleStatus');

    // Popups
    Route::patch('/popups/reorder', [PopupController::class, 'reorder'])->name('admin.popups.reorder');
    Route::resource('popups', PopupController::class)->names([
        'index' => 'admin.popups.index',
        'create' => 'admin.popups.create',
        'store' => 'admin.popups.store',
        'edit' => 'admin.popups.edit',
        'update' => 'admin.popups.update',
        'destroy' => 'admin.popups.destroy',
    ])->except('show');
    Route::patch('/popups/{popup}/toggle-status', [PopupController::class, 'toggleStatus'])->name('admin.popups.toggleStatus');

    // Affiliate
    Route::get('/affiliate', [AffiliateController::class, 'index'])->name('admin.affiliate.index');
    Route::put('/affiliate/settings', [AffiliateController::class, 'updateSettings'])->name('admin.affiliate.settings');
    Route::post('/affiliate', [AffiliateController::class, 'store'])->name('admin.affiliate.store');
    Route::put('/affiliate/{affiliate}', [AffiliateController::class, 'update'])->name('admin.affiliate.update');
    Route::delete('/affiliate/{affiliate}', [AffiliateController::class, 'destroy'])->name('admin.affiliate.destroy');
    Route::post('/affiliate/commissions/{commission}/approve', [AffiliateController::class, 'approveCommission'])->name('admin.affiliate.commissions.approve');
    Route::post('/affiliate/commissions/{commission}/reject', [AffiliateController::class, 'rejectCommission'])->name('admin.affiliate.commissions.reject');
    Route::post('/affiliate/commissions/{commission}/mark-paid', [AffiliateController::class, 'markCommissionPaid'])->name('admin.affiliate.commissions.markPaid');
    Route::post('/affiliate/commissions/approve-all', [AffiliateController::class, 'bulkApproveCommissions'])->name('admin.affiliate.commissions.approveAll');
    Route::post('/affiliate/commissions/pay-approved', [AffiliateController::class, 'bulkPayApprovedCommissions'])->name('admin.affiliate.commissions.payApproved');
    Route::get('/affiliate/commissions/export', [AffiliateController::class, 'exportCommissions'])->name('admin.affiliate.commissions.export');
    Route::get('/affiliate/export', [AffiliateController::class, 'exportAffiliates'])->name('admin.affiliate.export');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('admin.settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('admin.settings.update');

    // Chat Shop
    Route::get('/chat-shop', [ChatShopController::class, 'index'])->name('admin.chat-shop.index');
    Route::put('/chat-shop', [ChatShopController::class, 'update'])->name('admin.chat-shop.update');

    // Users
    Route::resource('users', UserController::class)->names([
        'index' => 'admin.users.index',
        'create' => 'admin.users.create',
        'store' => 'admin.users.store',
        'edit' => 'admin.users.edit',
        'update' => 'admin.users.update',
        'destroy' => 'admin.users.destroy',
    ])->except('show');

    // Roles
    Route::resource('roles', RoleController::class)->names([
        'index' => 'admin.roles.index',
        'create' => 'admin.roles.create',
        'store' => 'admin.roles.store',
        'edit' => 'admin.roles.edit',
        'update' => 'admin.roles.update',
        'destroy' => 'admin.roles.destroy',
    ])->except('show');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');

    // Import & Export
    Route::get('/import-export', [ImportExportController::class, 'index'])->name('admin.import-export.index');
    Route::get('/import-export/export', [ImportExportController::class, 'exportProducts'])->name('admin.import-export.export');
    Route::get('/import-export/sample', [ImportExportController::class, 'downloadSample'])->name('admin.import-export.sample');
    Route::post('/import-export/import', [ImportExportController::class, 'importProducts'])->name('admin.import-export.import');

    // Blogs
    Route::resource('blogs', BlogPostController::class)->names([
        'index' => 'admin.blogs.index',
        'create' => 'admin.blogs.create',
        'store' => 'admin.blogs.store',
        'show' => 'admin.blogs.show',
        'edit' => 'admin.blogs.edit',
        'update' => 'admin.blogs.update',
        'destroy' => 'admin.blogs.destroy',
    ])->parameters(['blogs' => 'blogPost']);
    Route::patch('/blogs/{blogPost}/toggle-show-on-site', [BlogPostController::class, 'toggleShowOnSite'])->name('admin.blogs.toggle-show-on-site');
    Route::patch('/blogs/{blogPost}/toggle-featured', [BlogPostController::class, 'toggleFeatured'])->name('admin.blogs.toggle-featured');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::post('/notifications/mark-read', [NotificationController::class, 'markRead'])->name('admin.notifications.mark-read');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('admin.notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('admin.notifications.destroy');
    Route::post('/notifications/bulk-read', [NotificationController::class, 'bulkRead'])->name('admin.notifications.bulk-read');
    Route::post('/notifications/bulk-delete', [NotificationController::class, 'bulkDelete'])->name('admin.notifications.bulk-delete');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('admin.reports.sales');
    Route::get('/reports/orders', [ReportController::class, 'orders'])->name('admin.reports.orders');
    Route::get('/reports/products', [ReportController::class, 'products'])->name('admin.reports.products');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('admin.reports.inventory');
    Route::get('/reports/customers', [ReportController::class, 'customers'])->name('admin.reports.customers');
    Route::get('/reports/returns', [ReportController::class, 'returns'])->name('admin.reports.returns');
    Route::get('/reports/coupons', [ReportController::class, 'coupons'])->name('admin.reports.coupons');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('admin.reports.export');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'overview'])->name('admin.analytics.overview');
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->name('admin.analytics.overview-page');
    Route::get('/analytics/products', [AnalyticsController::class, 'products'])->name('admin.analytics.products');
    Route::get('/analytics/visitors', [AnalyticsController::class, 'visitors'])->name('admin.analytics.visitors');
    Route::get('/analytics/realtime', [AnalyticsController::class, 'realtime'])->name('admin.analytics.realtime');
    Route::get('/analytics/customers', [AnalyticsController::class, 'customers'])->name('admin.analytics.customers');
    Route::get('/analytics/marketing', [AnalyticsController::class, 'marketing'])->name('admin.analytics.marketing');
    Route::get('/analytics/inventory', [AnalyticsController::class, 'inventory'])->name('admin.analytics.inventory');
    Route::get('/analytics/custom-report', [AnalyticsController::class, 'customReport'])->name('admin.analytics.custom-report');
    Route::get('/analytics/custom-report/data', [AnalyticsController::class, 'customReportData'])->name('admin.analytics.custom-report.data');
    Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('admin.analytics.export');

    // API Management
    //
    // Definitions are the containers that ApiRouteRegistrar turns into live
    // routes. A definition is a top-level API group (e.g. "Storefront"); its
    // endpoints are managed underneath it.
    //
    // Note for developers: creating or deleting a definition does not by itself
    // make a new URL live. Dynamic routes are compiled into the route cache, so
    // after any change run `php artisan route:clear` (dev) or `php artisan
    // route:cache` (production) or the endpoint stays 404.
    Route::get('/api-management', [ApiDefinitionController::class, 'index'])
        ->name('admin.api-management.definitions.index');
    Route::get('/api-management/create', [ApiDefinitionController::class, 'create'])
        ->name('admin.api-management.definitions.create');
    Route::post('/api-management', [ApiDefinitionController::class, 'store'])
        ->name('admin.api-management.definitions.store');
    // Reflection. Declared before the {apiDefinition} wildcard below, because
    // `/api-management/reflect` would otherwise be captured as a definition
    // lookup and 404 looking for a definition whose slug is "reflect".
    Route::get('/api-management/reflect', [ApiDefinitionReflectorController::class, 'index'])
        ->name('admin.api-management.definitions.reflect');
    Route::post('/api-management/reflect', [ApiDefinitionReflectorController::class, 'store'])
        ->name('admin.api-management.definitions.reflect.store');
    // Re-runs the reflector for a definition it produced, after a schema change.
    Route::post('/api-management/{apiDefinition}/regenerate', [ApiDefinitionReflectorController::class, 'regenerate'])
        ->name('admin.api-management.definitions.regenerate');
    // Read-only view of a whole definition: its endpoints, their declared
    // parameters and their permission grants. Kept separate from `edit` so the
    // tester never has to be worried about an unsaved form.
    Route::get('/api-management/{apiDefinition}', [ApiDefinitionController::class, 'show'])
        ->name('admin.api-management.definitions.show');
    // The placeholder is {apiDefinition} because UpdateApiDefinitionRequest reads
    // $this->route('apiDefinition') to build its unique-ignore rule. Naming it
    // {definition} would leave the ignore id empty and reject the current slug.
    Route::get('/api-management/{apiDefinition}/edit', [ApiDefinitionController::class, 'edit'])
        ->name('admin.api-management.definitions.edit');
    Route::put('/api-management/{apiDefinition}', [ApiDefinitionController::class, 'update'])
        ->name('admin.api-management.definitions.update');
    Route::delete('/api-management/{apiDefinition}', [ApiDefinitionController::class, 'destroy'])
        ->name('admin.api-management.definitions.destroy');

    // Endpoints are managed from inside their definition. Both parameters are
    // required so ApiEndpointController can reject a mismatched pair rather than
    // letting an endpoint be edited through the wrong API.
    Route::get('/api-management/{apiDefinition}/endpoints/create', [ApiEndpointController::class, 'create'])
        ->name('admin.api-management.endpoints.create');
    Route::post('/api-management/{apiDefinition}/endpoints', [ApiEndpointController::class, 'store'])
        ->name('admin.api-management.endpoints.store');
    Route::get('/api-management/{apiDefinition}/endpoints/{apiEndpoint}/edit', [ApiEndpointController::class, 'edit'])
        ->name('admin.api-management.endpoints.edit');
    Route::put('/api-management/{apiDefinition}/endpoints/{apiEndpoint}', [ApiEndpointController::class, 'update'])
        ->name('admin.api-management.endpoints.update');
    // Fires one real call at a registered endpoint and renders the result on
    // the definition's view screen. A POST because it performs the call and
    // writes an api_route_logs row.
    Route::post('/api-management/{apiDefinition}/endpoints/{apiEndpoint}/test', [ApiEndpointController::class, 'test'])
        ->name('admin.api-management.endpoints.test');
    Route::delete('/api-management/{apiDefinition}/endpoints/{apiEndpoint}', [ApiEndpointController::class, 'destroy'])
        ->name('admin.api-management.endpoints.destroy');
});

Route::middleware(['auth:admin', 'admin', 'impersonating'])->prefix('admin')->group(function () {
    Route::post('/stop-impersonating', [CustomerController::class, 'stopImpersonating'])
        ->name('admin.customers.stop-impersonating');
});
