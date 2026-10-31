<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Back Office Role Names
    |--------------------------------------------------------------------------
    |
    | Role *name* strings, matched by NotificationService, InventoryController
    | and CustomerController with a whereIn('name', ...) clause. RoleSeeder
    | only creates 'Super Admin', 'Admin', 'Editor' and 'Moderator', so the
    | lowercase entries below never match anything; they are kept because
    | existing code depends on this key. The permissions seeded by
    | PermissionSeeder, not these strings, are the authorisation source of
    | truth.
    |
    */

    'roles' => ['Super Admin', 'Admin', 'super_admin', 'admin'],

    'inventory_roles' => ['Super Admin', 'Admin', 'super_admin', 'admin', 'Inventory Manager'],

    /*
    |--------------------------------------------------------------------------
    | Per Route Back Office Permissions
    |--------------------------------------------------------------------------
    |
    | Consumed by AdminMiddleware. Keyed by route name (exact match first, then
    | Str::is wildcards); the value is a `module.action` permission seeded by
    | PermissionSeeder. A route that is not listed keeps the previous
    | behaviour: any user holding a role may reach it.
    |
    | Every permission below is granted to the seeded 'Admin' role, with the
    | single exception of 'roles.delete' on admin.roles.destroy — which
    | Admin\RoleController already refuses for non-Super-Admins. So no route
    | regresses for Admin/Super Admin; the effect is to stop 'Editor' and
    | 'Moderator' reaching screens and mutations their roles were never
    | granted (for example orders.cancel, settings.update, users.delete,
    | popups.*, blogs.*, api-management.*).
    |
    | Read-only index/create/edit routes map to the matching `.view`
    | permission and mutation routes to `.create`/`.update`/`.delete`, so a
    | role that holds only the seeded view permission can still browse but not
    | change. api-management is gated the same way as every other module even
    | though its rows live in the database: AdminMiddleware resolves this map
    | before any dynamic-route logic runs.
    */

    'route_permissions' => [
        // Orders
        'admin.orders.index' => 'orders.view',
        'admin.orders.show' => 'orders.view',
        'admin.orders.print' => 'orders.view',
        'admin.orders.status' => 'orders.update',
        'admin.orders.cancel' => 'orders.cancel',
        'admin.order-processor.index' => 'orders.view',
        'admin.order-processor.picking-list' => 'orders.view',
        'admin.order-processor.label' => 'orders.view',
        'admin.order-processor.receipt' => 'orders.view',
        'admin.order-processor.bulk' => 'orders.update',
        'admin.order-processor.verify' => 'orders.update',
        'admin.order-processor.fulfill' => 'orders.update',
        'admin.order-processor.shipping' => 'orders.update',

        // Products
        'admin.products.index' => 'products.view',
        'admin.products.create' => 'products.view',
        'admin.products.edit' => 'products.view',
        'admin.products.store' => 'products.create',
        'admin.products.update' => 'products.update',
        'admin.products.destroy' => 'products.delete',
        'admin.products.images.bulk-delete' => 'products.delete',
        'admin.products.toggleStatus' => 'products.update',
        'admin.products.toggleFeatured' => 'products.update',
        'admin.products.images.store' => 'products.update',
        'admin.products.images.destroy' => 'products.update',
        'admin.products.images.reorder' => 'products.update',
        'admin.products.images.primary' => 'products.update',

        // Catalogue
        'admin.categories.index' => 'categories.view',
        'admin.categories.create' => 'categories.view',
        'admin.categories.edit' => 'categories.view',
        'admin.categories.store' => 'categories.create',
        'admin.categories.update' => 'categories.update',
        'admin.categories.destroy' => 'categories.delete',
        'admin.categories.toggleStatus' => 'categories.update',
        'admin.brands.index' => 'brands.view',
        'admin.brands.create' => 'brands.view',
        'admin.brands.edit' => 'brands.view',
        'admin.brands.store' => 'brands.create',
        'admin.brands.update' => 'brands.update',
        'admin.brands.destroy' => 'brands.delete',
        'admin.attributes.index' => 'attributes.view',
        'admin.attributes.create' => 'attributes.view',
        'admin.attributes.edit' => 'attributes.view',
        'admin.attributes.store' => 'attributes.create',
        'admin.attributes.update' => 'attributes.update',
        'admin.attributes.destroy' => 'attributes.delete',

        // Inventory
        'admin.inventory.index' => 'inventory.view',
        'admin.inventory.show' => 'inventory.view',
        'admin.inventory.movements' => 'inventory.view',
        'admin.inventory.low-stock' => 'inventory.view',
        'admin.inventory.update' => 'inventory.update',
        'admin.inventory.adjust' => 'inventory.update',

        // Customers
        'admin.customers.index' => 'customers.view',
        'admin.customers.show' => 'customers.view',
        'admin.customers.create' => 'customers.view',
        'admin.customers.edit' => 'customers.view',
        'admin.customers.store' => 'customers.update',
        'admin.customers.update' => 'customers.update',
        'admin.customers.destroy' => 'customers.update',
        'admin.customers.status' => 'customers.update',
        'admin.customers.user-account' => 'customers.update',
        'admin.customers.impersonate' => 'users.update',
        'admin.delete-approval' => 'customers.update',
        'admin.force-delete' => 'customers.update',

        // Returns
        'admin.returns.index' => 'returns.view',
        'admin.returns.show' => 'returns.view',
        'admin.returns.approve' => 'returns.update',
        'admin.returns.reject' => 'returns.update',
        'admin.returns.complete' => 'returns.update',
        'admin.returns.addNote' => 'returns.update',

        // Reviews
        'admin.reviews.index' => 'reviews.view',
        'admin.reviews.approve' => 'reviews.update',
        'admin.reviews.reject' => 'reviews.update',
        'admin.reviews.toggleFront' => 'reviews.update',
        'admin.reviews.destroy' => 'reviews.delete',

        // Marketing
        'admin.coupons.index' => 'coupons.view',
        'admin.coupons.create' => 'coupons.view',
        'admin.coupons.edit' => 'coupons.view',
        'admin.coupons.store' => 'coupons.create',
        'admin.coupons.update' => 'coupons.update',
        'admin.coupons.destroy' => 'coupons.delete',
        'admin.coupons.toggleStatus' => 'coupons.update',
        'admin.campaigns.index' => 'campaigns.view',
        'admin.campaigns.create' => 'campaigns.view',
        'admin.campaigns.edit' => 'campaigns.view',
        'admin.campaigns.store' => 'campaigns.create',
        'admin.campaigns.update' => 'campaigns.update',
        'admin.campaigns.destroy' => 'campaigns.delete',
        'admin.campaigns.toggleStatus' => 'campaigns.update',
        'admin.flash-sales.index' => 'flash_sales.view',
        'admin.flash-sales.create' => 'flash_sales.view',
        'admin.flash-sales.edit' => 'flash_sales.view',
        'admin.flash-sales.store' => 'flash_sales.create',
        'admin.flash-sales.update' => 'flash_sales.update',
        'admin.flash-sales.destroy' => 'flash_sales.delete',
        'admin.flash-sales.toggleStatus' => 'flash_sales.update',
        'admin.banners.index' => 'banners.view',
        'admin.banners.create' => 'banners.view',
        'admin.banners.edit' => 'banners.view',
        'admin.banners.store' => 'banners.create',
        'admin.banners.update' => 'banners.update',
        'admin.banners.destroy' => 'banners.delete',
        'admin.banners.toggleStatus' => 'banners.update',
        'admin.banners.reorder' => 'banners.update',

        // Content
        'admin.pages.index' => 'pages.view',
        'admin.pages.create' => 'pages.view',
        'admin.pages.edit' => 'pages.view',
        'admin.pages.store' => 'pages.create',
        'admin.pages.update' => 'pages.update',
        'admin.pages.destroy' => 'pages.delete',
        'admin.pages.toggleStatus' => 'pages.update',
        'admin.invoices.index' => 'invoices.view',
        'admin.invoices.show' => 'invoices.view',
        'admin.invoices.download' => 'invoices.view',
        'admin.invoices.store' => 'invoices.create',

        // Store configuration
        'admin.shipping.index' => 'shipping.view',
        'admin.shipping.zones.index' => 'shipping.view',
        'admin.shipping.zones.create' => 'shipping.view',
        'admin.shipping.zones.edit' => 'shipping.view',
        'admin.shipping.zones.store' => 'shipping.update',
        'admin.shipping.zones.update' => 'shipping.update',
        'admin.shipping.zones.destroy' => 'shipping.update',
        'admin.shipping.methods.index' => 'shipping.view',
        'admin.shipping.methods.edit' => 'shipping.view',
        'admin.shipping.methods.store' => 'shipping.update',
        'admin.shipping.methods.update' => 'shipping.update',
        'admin.shipping.methods.destroy' => 'shipping.update',
        'admin.shipping.rules.index' => 'shipping.view',
        'admin.shipping.rules.edit' => 'shipping.view',
        'admin.shipping.rules.store' => 'shipping.update',
        'admin.shipping.rules.update' => 'shipping.update',
        'admin.shipping.rules.destroy' => 'shipping.update',
        'admin.tax.index' => 'tax.view',
        'admin.tax.store' => 'tax.update',
        'admin.tax.update' => 'tax.update',
        'admin.tax.destroy' => 'tax.update',
        'admin.tax.toggleStatus' => 'tax.update',
        'admin.settings.index' => 'settings.view',
        'admin.settings.update' => 'settings.update',

        // Accounts
        'admin.users.index' => 'users.view',
        'admin.users.create' => 'users.view',
        'admin.users.edit' => 'users.view',
        'admin.users.store' => 'users.create',
        'admin.users.update' => 'users.update',
        'admin.users.destroy' => 'users.delete',
        'admin.roles.index' => 'roles.view',
        'admin.roles.create' => 'roles.view',
        'admin.roles.edit' => 'roles.view',
        'admin.roles.store' => 'roles.create',
        'admin.roles.update' => 'roles.update',
        'admin.roles.destroy' => 'roles.delete',

        // Reporting
        'admin.reports.index' => 'reports.view',
        'admin.reports.orders' => 'reports.view',
        'admin.reports.customers' => 'reports.view',
        'admin.reports.products' => 'reports.view',
        'admin.reports.inventory' => 'reports.view',
        'admin.reports.sales' => 'reports.view',
        'admin.reports.returns' => 'reports.view',
        'admin.reports.coupons' => 'reports.view',
        'admin.reports.export' => 'reports.export',
        'admin.audit-logs.index' => 'audit_logs.view',
        'admin.audit-logs.show' => 'audit_logs.view',

        // Tags
        'admin.tags.index' => 'tags.view',
        'admin.tags.create' => 'tags.view',
        'admin.tags.edit' => 'tags.view',
        'admin.tags.store' => 'tags.create',
        'admin.tags.update' => 'tags.update',
        'admin.tags.destroy' => 'tags.delete',

        // FAQs
        'admin.faqs.index' => 'faqs.view',
        'admin.faqs.create' => 'faqs.view',
        'admin.faqs.edit' => 'faqs.view',
        'admin.faqs.store' => 'faqs.create',
        'admin.faqs.update' => 'faqs.update',
        'admin.faqs.toggleStatus' => 'faqs.update',
        'admin.faqs.destroy' => 'faqs.delete',

        // Enquiries
        'admin.enquiries.index' => 'enquiries.view',
        'admin.enquiries.show' => 'enquiries.view',
        'admin.enquiries.update' => 'enquiries.update',
        'admin.enquiries.destroy' => 'enquiries.delete',

        // Chat shop
        'admin.chat-shop.index' => 'chat_shop.view',
        'admin.chat-shop.update' => 'chat_shop.update',

        // Affiliates
        'admin.affiliate.index' => 'affiliate.view',
        'admin.affiliate.store' => 'affiliate.create',
        'admin.affiliate.update' => 'affiliate.update',
        'admin.affiliate.settings' => 'affiliate.update',
        'admin.affiliate.destroy' => 'affiliate.delete',
        'admin.affiliate.export' => 'affiliate.export',
        'admin.affiliate.commissions.approve' => 'affiliate.update',
        'admin.affiliate.commissions.reject' => 'affiliate.update',
        'admin.affiliate.commissions.markPaid' => 'affiliate.update',
        'admin.affiliate.commissions.approveAll' => 'affiliate.update',
        'admin.affiliate.commissions.payApproved' => 'affiliate.update',
        'admin.affiliate.commissions.export' => 'affiliate.export',

        // Catalogue import / export
        'admin.import-export.index' => 'import_export.view',
        'admin.import-export.sample' => 'import_export.view',
        'admin.import-export.export' => 'import_export.export',
        'admin.import-export.import' => 'import_export.import',

        // Notifications
        'admin.notifications.index' => 'notifications.view',
        'admin.notifications.mark-read' => 'notifications.update',
        'admin.notifications.read' => 'notifications.update',
        'admin.notifications.bulk-read' => 'notifications.update',
        'admin.notifications.destroy' => 'notifications.delete',
        'admin.notifications.bulk-delete' => 'notifications.delete',

        // Analytics
        'admin.analytics.overview' => 'analytics.view',
        'admin.analytics.overview-page' => 'analytics.view',
        'admin.analytics.products' => 'analytics.view',
        'admin.analytics.visitors' => 'analytics.view',
        'admin.analytics.realtime' => 'analytics.view',
        'admin.analytics.customers' => 'analytics.view',
        'admin.analytics.marketing' => 'analytics.view',
        'admin.analytics.inventory' => 'analytics.view',
        'admin.analytics.custom-report' => 'analytics.view',
        'admin.analytics.custom-report.data' => 'analytics.view',
        'admin.analytics.export' => 'analytics.export',

        // Blogs
        'admin.blogs.index' => 'blogs.view',
        'admin.blogs.show' => 'blogs.view',
        'admin.blogs.create' => 'blogs.view',
        'admin.blogs.edit' => 'blogs.view',
        'admin.blogs.store' => 'blogs.create',
        'admin.blogs.update' => 'blogs.update',
        'admin.blogs.toggle-show-on-site' => 'blogs.update',
        'admin.blogs.toggle-featured' => 'blogs.update',
        'admin.blogs.destroy' => 'blogs.delete',

        // Popups
        'admin.popups.index' => 'popups.view',
        'admin.popups.create' => 'popups.view',
        'admin.popups.edit' => 'popups.view',
        'admin.popups.store' => 'popups.create',
        'admin.popups.update' => 'popups.update',
        'admin.popups.reorder' => 'popups.update',
        'admin.popups.toggleStatus' => 'popups.update',
        'admin.popups.destroy' => 'popups.delete',

        // Payment methods
        'admin.payment-methods.index' => 'payment_methods.view',
        'admin.payment-methods.edit' => 'payment_methods.view',
        'admin.payment-methods.update' => 'payment_methods.update',

        // API management
        'admin.api-management.definitions.index' => 'api_management.view',
        'admin.api-management.definitions.show' => 'api_management.view',
        'admin.api-management.definitions.create' => 'api_management.view',
        'admin.api-management.definitions.edit' => 'api_management.view',
        'admin.api-management.definitions.reflect' => 'api_management.view',
        'admin.api-management.definitions.reflect.store' => 'api_management.update',
        'admin.api-management.definitions.regenerate' => 'api_management.update',
        'admin.api-management.definitions.store' => 'api_management.create',
        'admin.api-management.definitions.update' => 'api_management.update',
        'admin.api-management.definitions.destroy' => 'api_management.delete',
        'admin.api-management.endpoints.create' => 'api_management.view',
        'admin.api-management.endpoints.edit' => 'api_management.view',
        'admin.api-management.endpoints.store' => 'api_management.create',
        'admin.api-management.endpoints.update' => 'api_management.update',
        'admin.api-management.endpoints.test' => 'api_management.update',
        'admin.api-management.endpoints.destroy' => 'api_management.delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonation Exempt Routes
    |--------------------------------------------------------------------------
    |
    | AdminMiddleware refuses every back office route while
    | session('impersonator_id') is set, so that an impersonating session
    | cannot act through the admin guard. These routes stay reachable,
    | otherwise the impersonation could never be unwound.
    |
    */

    'impersonation_exempt_routes' => [
        'admin.customers.stop-impersonating',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeded Administrator Password
    |--------------------------------------------------------------------------
    |
    | Consumed by AdminSeeder. It lives in config rather than being read with
    | env() directly because a cached bootstrap/cache/config.php makes Laravel
    | skip .env loading entirely: env() then returns null and AdminSeeder
    | silently falls back to 'password', resetting the admin account on any
    | re-seed. Reading it from config means the cached value is the real one.
    |
    | Like every other config entry it is resolved at cache time, so changing
    | ADMIN_PASSWORD in .env requires `php artisan config:clear && config:cache`
    | before the next seed picks it up.
    |
    */

    'seed_password' => env('ADMIN_PASSWORD'),
    'seed_email' => env('ADMIN_EMAIL'),
];
