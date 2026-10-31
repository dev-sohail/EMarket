<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Models\AnalyticsConversion;
use App\Models\AnalyticsPageView;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function getOverviewData(Carbon $startDate, Carbon $endDate): array
    {
        $range = (int) $startDate->diffInDays($endDate) + 1;

        $previousStartDate = $startDate->copy()->subDays($range);
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();

        [$totalRevenue, $previousRevenue, $revenueChange] = $this->revenueMetrics($startDate, $endDate, $previousStartDate, $previousEndDate);
        [$totalOrders, $previousTotalOrders, $ordersChange] = $this->orderMetrics($startDate, $endDate, $previousStartDate, $previousEndDate);

        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $previousAvgOrderValue = $previousTotalOrders > 0 ? $previousRevenue / $previousTotalOrders : 0;
        $aovChange = $this->percentageChange($avgOrderValue, $previousAvgOrderValue);

        [$totalCustomers, $previousCustomers, $customersChange] = $this->customerMetrics($startDate, $endDate, $previousStartDate, $previousEndDate);
        [$totalVisitors, $previousVisitors, $visitorChange] = $this->visitorMetrics($startDate, $endDate, $previousStartDate, $previousEndDate);

        $conversionRate = $this->calculateConversionRate($startDate, $endDate);

        $salesByDay = Order::whereNotIn('status', OrderStatus::nonRevenue())
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->pluck('revenue', 'date')
            ->toArray();

        $ordersByDay = Order::where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders')
            ->groupBy('date')
            ->pluck('orders', 'date')
            ->toArray();

        $labels = [];
        $revenueData = [];
        $ordersData = [];

        for ($i = 0; $i < $range; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('M d');
            $revenueData[] = (float) ($salesByDay[$date] ?? 0);
            $ordersData[] = (int) ($ordersByDay[$date] ?? 0);
        }

        $topProducts = $this->getTopProducts($startDate, $endDate, 10);
        $topCategories = $this->getTopCategories($startDate, $endDate, 10);

        $recentOrders = Order::with(['customer', 'user', 'items'])
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(10)
            ->get();

        // `config('app.inventory.low_stock_threshold', 5)` does not fall back
        // when the setting is blank, and bound as '' MySQL reads it as 0, so
        // this counted nothing. Unconfigured means no low-stock reporting.
        $lowStockThreshold = low_stock_threshold();

        $lowStockCount = $lowStockThreshold === null
            ? 0
            : Inventory::where('quantity', '<=', $lowStockThreshold)
                ->where('quantity', '>=', 0)
                ->count();

        return [
            'range' => $range,
            'totalRevenue' => $totalRevenue,
            'revenueChange' => $revenueChange,
            'totalOrders' => $totalOrders,
            'ordersChange' => $ordersChange,
            'avgOrderValue' => round($avgOrderValue, 2),
            'aovChange' => $aovChange,
            'totalCustomers' => $totalCustomers,
            'customersChange' => $customersChange,
            'totalVisitors' => $totalVisitors,
            'visitorChange' => $visitorChange,
            'conversionRate' => $conversionRate,
            'topProducts' => $topProducts,
            'topCategories' => $topCategories,
            'labels' => $labels,
            'revenueData' => $revenueData,
            'ordersData' => $ordersData,
            'recentOrders' => $recentOrders,
            'lowStockCount' => $lowStockCount,
        ];
    }

    public function getProductsData(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $sortBy = $filters['sort_by'] ?? 'revenue';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $limit = (int) ($filters['limit'] ?? 50);
        $sortByColumn = match ($sortBy) {
            'revenue' => 'total_revenue',
            'quantity' => 'total_sold',
            'orders' => 'order_count',
            'name' => 'products.name',
            default => 'total_revenue',
        };

        // Create query without strict mode issues - we explicitly list all columns in GROUP BY
        $query = Product::query()
            ->with(['images', 'variants', 'inventory', 'categories', 'brands']);

        $query->leftJoin('order_items', function ($join) use ($startDate, $endDate) {
            $join->on('products._id', '=', 'order_items.product_id')
                ->whereBetween('order_items.created_at', [$startDate, $endDate])
                ->whereIn('order_items.order_id', Order::select('_id')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNotIn('status', OrderStatus::nonRevenue())
                    ->toBase());
        })->leftJoin('orders', function ($join) {
            $join->on('order_items.order_id', '=', 'orders._id');
        });

        // Explicitly list all product columns to comply with ONLY_FULL_GROUP_BY
        $query->select(
            'products._id',
            'products.name',
            'products.sku',
            'products.slug',
            'products.description',
            'products.base_price',
            'products.sale_price',
            'products.is_active',
            'products.deleted_at',
            'products.created_at',
            'products.updated_at'
        )->selectRaw('COALESCE(SUM(order_items.quantity), 0) as total_sold')
            ->selectRaw('COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) as total_revenue')
            ->selectRaw('COUNT(DISTINCT orders._id) as order_count')
            ->groupBy(
                'products._id',
                'products.name',
                'products.sku',
                'products.slug',
                'products.description',
                'products.base_price',
                'products.sale_price',
                'products.is_active',
                'products.deleted_at',
                'products.created_at',
                'products.updated_at'
            );

        if (! empty($filters['category_id'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->where('categories._id', $filters['category_id']);
            });
        }

        if (! empty($filters['brand_id'])) {
            $query->whereHas('brands', function ($q) use ($filters) {
                $q->whereIn('brands._id', is_array($filters['brand_id']) ? $filters['brand_id'] : [$filters['brand_id']]);
            });
        }

        if (isset($filters['status'])) {
            $query->where('products.is_active', $filters['status'] === 'active');
        }

        $products = $query->orderBy($sortByColumn, $sortOrder)
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                $sold = (int) $product->total_sold;
                $revenue = (float) $product->total_revenue;
                $product->total_sold = $sold;
                $product->total_revenue = $revenue;
                $product->order_count = (int) $product->order_count;

                // The view compared `inventory` against 0 and against 20% of
                // itself, but Product::inventory() is a HasMany, so
                // `$product['inventory']` is an Eloquent Collection and the
                // comparison raised "Object of class Collection could not be
                // converted to int" — a 500 on any products page. Expose a
                // scalar stock figure the view can actually colour.
                $product->stock_total = (int) $product->inventory->sum('quantity');
                $product->stock_reorder_level = (int) ($product->inventory->max('reorder_level') ?? 0);
                // The primary flag, not the first row: `images` is eager loaded here so
                // the read costs nothing extra, and an operator who marked an
                // image primary expects that one in the table.
                $product->image_path = $product->images->firstWhere('is_primary', true)?->path
                    ?? $product->images->first()?->path;
                $product->category_names = $product->categories->pluck('name')->join(', ');
                $product->brand_names = $product->brands->pluck('name')->join(', ');

                // Never set anywhere, so the Conv. Rate column rendered blank
                // on every row. Units sold per distinct order is the only
                // conversion signal available from this query.
                $product->conversion_rate = $product->order_count > 0
                    ? round(($product->total_sold / $product->order_count) * 100, 2)
                    : 0.0;

                return $product;
            });

        $totalProducts = Product::query()->count();
        $totalQuantitySold = $products->sum('total_sold');
        $totalProductsRevenue = $products->sum('total_revenue');
        $t = low_stock_threshold();
        $t = low_stock_threshold();
        $lowStock = $t === null ? 0 : Inventory::where('quantity', '<=', $t)->count();
        $outOfStock = Inventory::where('quantity', '<=', 0)->count();
        $productsWithSales = Product::whereHas('orderItems', function ($q) use ($startDate, $endDate) {
            $q->whereHas('order', function ($oq) use ($startDate, $endDate) {
                $oq->where('status', '!=', 'cancelled')
                    ->whereBetween('created_at', [$startDate, $endDate]);
            });
        })->count();
        $activeProducts = Product::where('is_active', true)->whereNull('deleted_at')->count();

        return [
            'products' => $products,
            'summary' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'total_quantity_sold' => $totalQuantitySold,
                'total_revenue' => $totalProductsRevenue,
                'products_with_sales' => $productsWithSales,
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
            ],
            'range' => (int) $startDate->diffInDays($endDate) + 1,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    public function getVisitorsData(Carbon $startDate, Carbon $endDate, int $range): array
    {
        $visitorData = AnalyticsSession::where('started_at', '>=', $startDate)
            ->where('started_at', '<=', $endDate)
            ->selectRaw('DATE(started_at) as date, COUNT(DISTINCT session_uuid) as sessions, COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $pageViewsByDay = AnalyticsPageView::where('viewed_at', '>=', $startDate)
            ->where('viewed_at', '<=', $endDate)
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as page_views')
            ->groupBy('date')
            ->pluck('page_views', 'date')
            ->toArray();

        $visitorData = $visitorData->map(function ($item) {
            return [
                'date' => $item->date,
                'sessions' => (int) $item->sessions,
                'unique_visitors' => (int) $item->unique_visitors,
                'page_views' => (int) ($pageViewsByDay[$item->date] ?? 0),
            ];
        });

        $labels = [];
        $pageViewsData = [];
        $uniqueVisitorsData = [];
        $sessionsData = [];

        for ($i = 0; $i < $range; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('M d');
            $dayData = $visitorData->firstWhere('date', $date);
            $pageViewsData[] = $dayData ? $dayData['page_views'] : 0;
            $uniqueVisitorsData[] = $dayData ? $dayData['unique_visitors'] : 0;
            $sessionsData[] = $dayData ? $dayData['sessions'] : 0;
        }

        $totalSessions = AnalyticsSession::where('started_at', '>=', $startDate)
            ->where('started_at', '<=', $endDate)
            ->count();

        $totalPageViews = AnalyticsPageView::where('viewed_at', '>=', $startDate)
            ->where('viewed_at', '<=', $endDate)
            ->count();

        $totalUniqueVisitors = AnalyticsSession::where('started_at', '>=', $startDate)
            ->where('started_at', '<=', $endDate)
            ->distinct('visitor_id')
            ->count('visitor_id');

        $totalBounces = AnalyticsSession::where('started_at', '>=', $startDate)
            ->where('started_at', '<=', $endDate)
            ->where('bounced', true)
            ->count();

        $bounceRate = $totalSessions > 0 ? round(($totalBounces / $totalSessions) * 100, 2) : 0;

        $totalDuration = AnalyticsSession::where('started_at', '>=', $startDate)
            ->where('started_at', '<=', $endDate)
            ->sum('duration_seconds');

        $avgSessionDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions, 2) : 0;

        $topPages = AnalyticsPageView::where('viewed_at', '>=', $startDate)
            ->where('viewed_at', '<=', $endDate)
            ->selectRaw('path, COUNT(*) as views')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($p) => ['path' => $p->path, 'views' => (int) $p->views]);

        $topReferrers = AnalyticsVisitor::where('last_seen_at', '>=', $startDate)
            ->where('last_seen_at', '<=', $endDate)
            ->whereNotNull('referrer_domain')
            ->selectRaw('referrer_domain, COUNT(DISTINCT _id) as visitors')
            ->groupBy('referrer_domain')
            ->orderByDesc('visitors')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['referrer' => $r->referrer_domain, 'visitors' => (int) $r->visitors]);

        $deviceBreakdown = AnalyticsVisitor::where('last_seen_at', '>=', $startDate)
            ->where('last_seen_at', '<=', $endDate)
            ->whereNotNull('device_type')
            ->selectRaw('device_type, COUNT(DISTINCT _id) as visitors')
            ->groupBy('device_type')
            ->orderByDesc('visitors')
            ->get()
            ->map(fn ($d) => ['device' => $d->device_type, 'visitors' => (int) $d->visitors]);

        $browserBreakdown = AnalyticsVisitor::where('last_seen_at', '>=', $startDate)
            ->where('last_seen_at', '<=', $endDate)
            ->whereNotNull('browser')
            ->selectRaw('browser, COUNT(DISTINCT _id) as visitors')
            ->groupBy('browser')
            ->orderByDesc('visitors')
            ->get()
            ->map(fn ($b) => ['browser' => $b->browser, 'visitors' => (int) $b->visitors]);

        $countryBreakdown = AnalyticsVisitor::where('last_seen_at', '>=', $startDate)
            ->where('last_seen_at', '<=', $endDate)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(DISTINCT _id) as visitors')
            ->groupBy('country')
            ->orderByDesc('visitors')
            ->limit(15)
            ->get()
            ->map(fn ($c) => ['country' => $c->country, 'visitors' => (int) $c->visitors]);

        return [
            'range' => $range,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'labels' => $labels,
            'pageViews' => $pageViewsData,
            'uniqueVisitors' => $uniqueVisitorsData,
            'sessions' => $sessionsData,
            'totalPageViews' => $totalPageViews,
            'totalUniqueVisitors' => $totalUniqueVisitors,
            'totalSessions' => $totalSessions,
            'avgSessionDuration' => $avgSessionDuration,
            'bounceRate' => $bounceRate,
            'topPages' => $topPages,
            'topReferrers' => $topReferrers,
            'deviceBreakdown' => $deviceBreakdown,
            'browserBreakdown' => $browserBreakdown,
            'countryBreakdown' => $countryBreakdown,
        ];
    }

    public function getRealtimeData(): array
    {
        $fiveMinutesAgo = Carbon::now()->subMinutes(5);

        $activeUsers = AnalyticsSession::where('ended_at', '>=', $fiveMinutesAgo)
            ->where('ended_at', '<=', now())
            ->distinct('visitor_id')
            ->count('visitor_id');

        $recentActivity = AnalyticsPageView::where('viewed_at', '>=', $fiveMinutesAgo)
            ->orderByDesc('viewed_at')
            ->limit(20)
            ->get()
            ->map(function ($pv) {
                $visitor = $pv->visitor;

                return [
                    'path' => $pv->path,
                    'viewed_at' => $pv->viewed_at,
                    'country' => $visitor?->country,
                    'device_type' => $visitor?->device_type,
                ];
            });

        $topActivePages = AnalyticsPageView::where('viewed_at', '>=', $fiveMinutesAgo)
            ->selectRaw('path, COUNT(*) as views')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($p) => ['path' => $p->path, 'views' => (int) $p->views]);

        $trafficSources = AnalyticsVisitor::where('last_seen_at', '>=', $fiveMinutesAgo)
            ->selectRaw('referrer_domain, COUNT(DISTINCT _id) as visitors')
            ->groupBy('referrer_domain')
            ->orderByDesc('visitors')
            ->limit(5)
            ->get()
            ->map(fn ($t) => ['referrer' => $t->referrer_domain, 'visitors' => (int) $t->visitors]);

        return [
            'activeUsers' => (int) $activeUsers,
            'recentActivity' => $recentActivity,
            'topActivePages' => $topActivePages,
            'trafficSources' => $trafficSources,
        ];
    }

    public function getCustomersData(Carbon $startDate, Carbon $endDate, int $range): array
    {
        [$totalCustomers, $previousCustomers, $customersChange] = $this->customerMetrics($startDate, $endDate, $startDate->copy()->subDays($range), $startDate->copy()->subDay());

        $repeatCustomers = Customer::whereHas('orders', function ($q) use ($startDate, $endDate) {
            $q->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$startDate, $endDate]);
        })->count();

        $customerLifetimeValue = Customer::leftJoin('orders', 'customers._id', '=', 'orders.customer_id')
            ->whereNotIn('orders.status', OrderStatus::nonRevenue())
            ->selectRaw('AVG(orders.grand_total) as avg_ltv, MAX(orders.grand_total) as max_order')
            ->first();

        $newCustomersByDay = Customer::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as new_customers')
            ->groupBy('date')
            ->pluck('new_customers', 'date')
            ->toArray();

        $labels = [];
        $newCustomersData = [];

        for ($i = 0; $i < $range; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('M d');
            $newCustomersData[] = (int) ($newCustomersByDay[$date] ?? 0);
        }

        $topCustomersBySpend = Customer::leftJoin('orders', 'customers._id', '=', 'orders.customer_id')
            ->whereNotIn('orders.status', OrderStatus::nonRevenue())
            ->selectRaw('customers._id, customers.first_name, customers.last_name, customers.email, COUNT(orders._id) as order_count, SUM(orders.grand_total) as total_spent')
            ->groupBy('customers._id', 'customers.first_name', 'customers.last_name', 'customers.email')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->getKey(),
                    'name' => $c->first_name.' '.$c->last_name,
                    'email' => $c->email,
                    'order_count' => (int) $c->order_count,
                    'total_spent' => (float) $c->total_spent,
                ];
            });

        return [
            'range' => $range,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalCustomers' => $totalCustomers,
            'previousCustomers' => $previousCustomers,
            'customersChange' => $customersChange,
            'repeatCustomers' => $repeatCustomers,
            'avgLifetimeValue' => (float) ($customerLifetimeValue?->avg_ltv ?? 0),
            'maxOrderValue' => (float) ($customerLifetimeValue?->max_order ?? 0),
            'labels' => $labels,
            'newCustomersData' => $newCustomersData,
            'topCustomersBySpend' => $topCustomersBySpend,
        ];
    }

    public function getMarketingData(Carbon $startDate, Carbon $endDate): array
    {
        $topReferrers = AnalyticsVisitor::where('last_seen_at', '>=', $startDate)
            ->where('last_seen_at', '<=', $endDate)
            ->whereNotNull('referrer_domain')
            ->selectRaw('referrer_domain, COUNT(DISTINCT _id) as visitors')
            ->groupBy('referrer_domain')
            ->orderByDesc('visitors')
            ->limit(15)
            ->get()
            ->map(fn ($r) => ['referrer' => $r->referrer_domain, 'visitors' => (int) $r->visitors]);

        $sourceConversions = AnalyticsConversion::where('converted_at', '>=', $startDate)
            ->where('converted_at', '<=', $endDate)
            ->selectRaw('revenue, COUNT(*) as conversions')
            ->groupBy('revenue')
            ->first();

        $totalConversions = AnalyticsConversion::where('converted_at', '>=', $startDate)
            ->where('converted_at', '<=', $endDate)->count();

        $totalConversionRevenue = AnalyticsConversion::where('converted_at', '>=', $startDate)
            ->where('converted_at', '<=', $endDate)->sum('revenue');

        $sessionsCount = AnalyticsSession::where('started_at', '>=', $startDate)
            ->where('started_at', '<=', $endDate)->count();

        $totalVisitors = AnalyticsVisitor::where('last_seen_at', '>=', $startDate)
            ->where('last_seen_at', '<=', $endDate)->count();

        $conversionRate = $sessionsCount > 0 ? round(($totalConversions / $sessionsCount) * 100, 2) : 0;

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'topReferrers' => $topReferrers,
            'totalVisitors' => $totalVisitors,
            'totalSessions' => $sessionsCount,
            'totalConversions' => $totalConversions,
            'totalConversionRevenue' => (float) $totalConversionRevenue,
            'conversionRate' => $conversionRate,
        ];
    }

    public function getInventoryData(Carbon $startDate, Carbon $endDate): array
    {
        $lowStockThreshold = low_stock_threshold();

        $lowStockItems = $lowStockThreshold === null
            ? collect()
            : Inventory::where('quantity', '<=', $lowStockThreshold)
                ->where('quantity', '>=', 0)
                ->with('product')
                ->get()
                ->map(function ($inv) use ($lowStockThreshold) {
                    return [
                        'id' => $inv->product_id,
                        'product_name' => $inv->product?->name ?? 'N/A',
                        'sku' => $inv->product?->sku ?? 'N/A',
                        'quantity' => $inv->quantity,
                        // Inventory has a `reorder_level` column; there is no
                        // `low_stock_threshold` attribute, so the Threshold column
                        // on the inventory tab rendered blank on every row. Null
                        // when neither the row nor the setting has a threshold.
                        'threshold' => $inv->reorder_level ?: $lowStockThreshold,
                    ];
                });

        $fastMovingProducts = Product::leftJoin('order_items', function ($join) use ($startDate, $endDate) {
            $join->on('products._id', '=', 'order_items.product_id')
                ->whereBetween('order_items.created_at', [$startDate, $endDate])
                ->whereIn('order_items.order_id', Order::select('_id')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNotIn('status', OrderStatus::nonRevenue())
                    ->toBase());
        })
            ->leftJoin('orders', function ($join) {
                $join->on('order_items.order_id', '=', 'orders._id');
            })
            ->selectRaw('products._id as id, products.name, SUM(order_items.quantity) as total_sold, SUM(order_items.quantity * order_items.unit_price) as total_revenue')
            ->groupBy('products._id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        $slowMovingProducts = Product::leftJoin('order_items', function ($join) use ($startDate, $endDate) {
            $join->on('products._id', '=', 'order_items.product_id')
                ->whereBetween('order_items.created_at', [$startDate, $endDate])
                ->whereIn('order_items.order_id', Order::select('_id')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNotIn('status', OrderStatus::nonRevenue())
                    ->toBase());
        })
            ->leftJoin('orders', function ($join) {
                $join->on('order_items.order_id', '=', 'orders._id');
            })
            ->selectRaw('products._id as id, products.name, COALESCE(SUM(order_items.quantity), 0) as total_sold, COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) as total_revenue')
            ->groupBy('products._id', 'products.name')
            ->having('total_sold', '=', 0)
            ->orderBy('products.name')
            ->limit(10)
            ->get();

        $stockTurnover = Product::leftJoin('order_items', function ($join) use ($startDate, $endDate) {
            $join->on('products._id', '=', 'order_items.product_id')
                ->whereBetween('order_items.created_at', [$startDate, $endDate])
                ->whereIn('order_items.order_id', Order::select('_id')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNotIn('status', OrderStatus::nonRevenue())
                    ->toBase());
        })
            ->leftJoin('orders', function ($join) {
                $join->on('order_items.order_id', '=', 'orders._id');
            })
            ->leftJoin('inventories', 'products._id', '=', 'inventories.product_id')
            ->selectRaw('products.name, COALESCE(SUM(order_items.quantity), 0) as sold, COALESCE(AVG(inventories.quantity), 0) as avg_stock, CASE WHEN COALESCE(AVG(inventories.quantity), 0) > 0 THEN COALESCE(SUM(order_items.quantity), 0) / COALESCE(AVG(inventories.quantity), 0) ELSE 0 END as turnover_rate')
            ->groupBy('products._id', 'products.name')
            ->orderByDesc('turnover_rate')
            ->limit(15)
            ->get();

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'lowStockItems' => $lowStockItems,
            'outOfStockCount' => Inventory::where('quantity', '<=', 0)->count(),
            'fastMovingProducts' => $fastMovingProducts,
            'slowMovingProducts' => $slowMovingProducts,
            'stockTurnover' => $stockTurnover,
        ];
    }

    public function generateCustomReport(string $reportType, Carbon $startDate, Carbon $endDate, string $groupBy, array $metrics): array
    {
        // Every branch aliases the expression as `period` so the custom reports
        // can select and group by that name regardless of the granularity.
        $groupExpression = match ($groupBy) {
            'hour' => DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as period"),
            'week' => DB::raw("DATE_FORMAT(created_at, '%x-W%v') as period"),
            'month' => DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
            'day' => DB::raw('DATE(created_at) as period'),
            default => DB::raw('DATE(created_at) as period'),
        };

        return match ($reportType) {
            'sales' => $this->customSalesReport($startDate, $endDate, $groupExpression, $metrics),
            'products' => $this->customProductsReport($startDate, $endDate, $groupExpression, $metrics),
            'customers' => $this->customCustomersReport($startDate, $endDate, $groupExpression, $metrics),
            'orders' => $this->customOrdersReport($startDate, $endDate, $groupExpression, $metrics),
            default => [],
        };
    }

    public function getAvailableMetrics(string $reportType): array
    {
        return match ($reportType) {
            'sales' => ['revenue', 'orders', 'avg_order_value', 'discount', 'tax', 'shipping'],
            'products' => ['quantity', 'revenue', 'orders', 'conversion_rate', 'views'],
            'customers' => ['new_customers', 'repeat_customers', 'lifetime_value', 'orders_per_customer'],
            'orders' => ['total', 'by_status', 'avg_value', 'fulfillment_time'],
            default => ['revenue', 'orders'],
        };
    }

    public function getOverviewExportData(?string $dateFrom, ?string $dateTo): array
    {
        $query = Order::where('status', '!=', 'cancelled');
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query->get()->map(fn ($order) => [
            'order_number' => $order->order_number,
            'date' => $order->created_at->format('Y-m-d'),
            'revenue' => (float) $order->grand_total,
            'status' => $order->status,
            'customer' => ($order->customer?->first_name ?? '').' '.($order->customer?->last_name ?? ''),
        ])->toArray();
    }

    public function getProductsExportData(?string $dateFrom, ?string $dateTo): array
    {
        $startDate = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->subDays(30);
        $endDate = $dateTo ? Carbon::parse($dateTo) : Carbon::now();

        return Product::query()
            ->leftJoin('order_items', function ($join) use ($startDate, $endDate) {
                $join->on('products._id', '=', 'order_items.product_id')
                    ->whereBetween('order_items.created_at', [$startDate, $endDate])
                    ->whereIn('order_items.order_id', Order::select('_id')
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->whereNotIn('status', OrderStatus::nonRevenue())
                        ->toBase());
            })
            ->leftJoin('orders', function ($join) {
                $join->on('order_items.order_id', '=', 'orders._id');
            })
            ->selectRaw('products._id as id, products.name, products.sku, products.base_price, COALESCE(SUM(order_items.quantity), 0) as total_sold, COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) as total_revenue')
            ->groupBy('products._id')
            ->get()
            ->map(fn ($product) => [
                'id' => $product->getKey(),
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->base_price,
                'total_sold' => (int) $product->total_sold,
                'total_revenue' => (float) $product->total_revenue,
            ])
            ->toArray();
    }

    public function getVisitorsExportData(?string $dateFrom, ?string $dateTo): array
    {
        $startDate = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->subDays(30);
        $endDate = $dateTo ? Carbon::parse($dateTo) : Carbon::now();

        return AnalyticsVisitor::where('last_seen_at', '>=', $startDate)
            ->where('last_seen_at', '<=', $endDate)
            ->get()
            ->map(fn ($visitor) => [
                'uuid' => $visitor->uuid,
                'country' => $visitor->country ?? 'N/A',
                'device_type' => $visitor->device_type ?? 'N/A',
                'browser' => $visitor->browser ?? 'N/A',
                'referrer_domain' => $visitor->referrer_domain ?? 'Direct',
                'first_seen_at' => $visitor->first_seen_at?->format('Y-m-d H:i:s'),
                'last_seen_at' => $visitor->last_seen_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();
    }

    public function recordOrderConversion(Order $order): void
    {
        $visitorUuid = request()->cookie('analytics_visitor_uuid');
        $sessionId = request()->cookie('analytics_session_id');

        if (! $visitorUuid && ! $sessionId) {
            return;
        }

        AnalyticsConversion::create([
            'visitor_id' => $visitorUuid
                ? AnalyticsVisitor::where('uuid', $visitorUuid)->value('_id')
                : null,
            'session_uuid' => $sessionId,
            'order_id' => $order->getKey(),
            'customer_id' => $order->customer_id,
            'revenue' => $order->grand_total,
            'conversion_type' => 'order',
            'converted_at' => now(),
        ]);
    }

    public function getTopProducts(Carbon $startDate, Carbon $endDate, int $limit): Collection
    {
        return Product::with('images')
            ->withSum(['orderItems as total_sold' => function ($q) use ($startDate, $endDate) {
                $q->whereHas('order', function ($oq) use ($startDate, $endDate) {
                    $oq->whereNotIn('status', OrderStatus::nonRevenue())
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });
            }], 'quantity')
            ->whereHas('orderItems', function ($q) use ($startDate, $endDate) {
                $q->whereHas('order', function ($oq) use ($startDate, $endDate) {
                    $oq->whereNotIn('status', OrderStatus::nonRevenue())
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });
            })
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) use ($startDate, $endDate) {
                $totalRevenue = $product->orderItems()
                    ->whereHas('order', function ($oq) use ($startDate, $endDate) {
                        $oq->whereNotIn('status', OrderStatus::nonRevenue())
                            ->whereBetween('created_at', [$startDate, $endDate]);
                    })
                    ->sum(DB::raw('quantity * unit_price'));

                return [
                    'id' => $product->getKey(),
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'image' => $product->images->first()?->path,
                    'total_sold' => (int) $product->total_sold,
                    'total_revenue' => (float) ($totalRevenue ?? 0),
                ];
            });
    }

    public function getTopCategories(Carbon $startDate, Carbon $endDate, int $limit): Collection
    {
        return Category::leftJoin('product_categories', 'categories._id', '=', 'product_categories.category_id')
            ->leftJoin('products', 'product_categories.product_id', '=', 'products._id')
            ->leftJoin('order_items', function ($join) use ($startDate, $endDate) {
                $join->on('products._id', '=', 'order_items.product_id')
                    ->whereBetween('order_items.created_at', [$startDate, $endDate])
                    ->whereIn('order_items.order_id', Order::select('_id')
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->whereNotIn('status', OrderStatus::nonRevenue())
                        ->toBase());
            })
            ->leftJoin('orders', 'order_items.order_id', '=', 'orders._id')
            ->select(
                'categories._id',
                'categories.name',
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(order_items.unit_price * order_items.quantity), 0) as total_revenue')
            )
            ->groupBy('categories._id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->getKey(),
                'name' => $cat->name,
                'total_quantity' => (int) $cat->total_quantity,
                'total_revenue' => (float) $cat->total_revenue,
            ]);
    }

    protected function revenueMetrics(Carbon $startDate, Carbon $endDate, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = (float) Order::whereNotIn('status', OrderStatus::nonRevenue())
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('grand_total');

        $previous = (float) Order::whereNotIn('status', OrderStatus::nonRevenue())
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('grand_total');

        return [$current, $previous, $this->percentageChange($current, $previous)];
    }

    protected function orderMetrics(Carbon $startDate, Carbon $endDate, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = Order::where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $previous = Order::where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        return [$current, $previous, $this->percentageChange($current, $previous)];
    }

    protected function customerMetrics(Carbon $startDate, Carbon $endDate, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = Customer::whereBetween('created_at', [$startDate, $endDate])->count();
        $previous = Customer::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        return [$current, $previous, $this->percentageChange($current, $previous)];
    }

    protected function visitorMetrics(Carbon $startDate, Carbon $endDate, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = AnalyticsVisitor::where('last_seen_at', '>=', $startDate)
            ->where('last_seen_at', '<=', $endDate)
            ->count();

        $previous = AnalyticsVisitor::where('last_seen_at', '>=', $prevStart)
            ->where('last_seen_at', '<=', $prevEnd)
            ->count();

        return [$current, $previous, $this->percentageChange($current, $previous)];
    }

    protected function calculateConversionRate(Carbon $startDate, Carbon $endDate): float
    {
        $sessions = AnalyticsSession::where('started_at', '>=', $startDate)
            ->where('started_at', '<=', $endDate)
            ->count();

        $orders = Order::where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return $sessions > 0 ? round(($orders / $sessions) * 100, 2) : 0;
    }

    protected function percentageChange(float $current, float $previous): float
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : 0;
    }

    protected function customSalesReport(Carbon $startDate, Carbon $endDate, $groupBy, array $metrics): array
    {
        $selects = [];
        if (in_array('revenue', $metrics)) {
            $selects[] = DB::raw('SUM(grand_total) as revenue');
        }
        if (in_array('orders', $metrics)) {
            $selects[] = DB::raw('COUNT(*) as orders');
        }
        if (in_array('avg_order_value', $metrics)) {
            $selects[] = DB::raw('AVG(grand_total) as avg_order_value');
        }
        if (in_array('discount', $metrics)) {
            $selects[] = DB::raw('SUM(discount) as discount');
        }
        if (in_array('tax', $metrics)) {
            $selects[] = DB::raw('SUM(tax) as tax');
        }
        if (in_array('shipping', $metrics)) {
            $selects[] = DB::raw('SUM(shipping_cost) as shipping');
        }

        $selects[] = $groupBy;

        return Order::whereNotIn('status', OrderStatus::nonRevenue())
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select($selects)
            ->groupBy('period')
            ->get()
            ->toArray();
    }

    protected function customProductsReport(Carbon $startDate, Carbon $endDate, $groupBy, array $metrics): array
    {
        $selects = ['products._id as id', 'products.name'];
        if (in_array('quantity', $metrics)) {
            $selects[] = DB::raw('COALESCE(SUM(order_items.quantity), 0) as quantity');
        }
        if (in_array('revenue', $metrics)) {
            $selects[] = DB::raw('COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) as revenue');
        }
        if (in_array('orders', $metrics)) {
            $selects[] = DB::raw('COUNT(DISTINCT orders._id) as orders');
        }

        $columns = implode(', ', $selects);

        return Product::leftJoin('order_items', function ($join) use ($startDate, $endDate) {
            $join->on('products._id', '=', 'order_items.product_id')
                ->whereBetween('order_items.created_at', [$startDate, $endDate])
                ->whereIn('order_items.order_id', Order::select('_id')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNotIn('status', OrderStatus::nonRevenue())
                    ->toBase());
        })
            ->leftJoin('orders', function ($join) {
                $join->on('order_items.order_id', '=', 'orders._id');
            })
            ->selectRaw($columns)
            ->groupBy('products._id')
            ->orderByRaw('COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) DESC')
            ->limit(100)
            ->get()
            ->toArray();
    }

    protected function customCustomersReport(Carbon $startDate, Carbon $endDate, $groupBy, array $metrics): array
    {
        $selects = [];
        if (in_array('new_customers', $metrics)) {
            $selects[] = DB::raw('COUNT(*) as new_customers');
        }

        $selects[] = $groupBy;

        return Customer::whereBetween('created_at', [$startDate, $endDate])
            ->select($selects)
            ->groupBy('period')
            ->get()
            ->toArray();
    }

    protected function customOrdersReport(Carbon $startDate, Carbon $endDate, $groupBy, array $metrics): array
    {
        return Order::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->toArray();
    }
}
