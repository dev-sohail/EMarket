<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // Through the helpers so the dashboard agrees with the code that acts on
        // these numbers, settings table included.
        $lowStockThreshold = low_stock_threshold();
        $reservationTtl = reservation_ttl_minutes();

        $configWarnings = [];
        if ($lowStockThreshold === null) {
            $configWarnings[] = [
                'message' => 'Low stock threshold is not configured. Stock alerts and the low-stock report are disabled.',
                'action' => 'Set it in Settings → Order & Inventory.',
                'route' => 'admin.settings.index',
            ];
        }
        if ($reservationTtl === null) {
            // No field on the settings screen for this one, so pointing the
            // operator at that screen would be a dead end.
            $configWarnings[] = [
                'message' => 'Inventory reservation TTL is not configured, so the hourly reservation-expiry job is idle and abandoned carts hold their stock indefinitely.',
                'action' => 'Set INVENTORY_RESERVATION_TTL_MINUTES in .env.',
                'route' => null,
            ];
        }
        if (! setting('currency')) {
            $configWarnings[] = [
                'message' => 'Currency is not configured. Prices render without a symbol.',
                'action' => 'Set it in Settings → Display.',
                'route' => 'admin.settings.index',
            ];
        }
        if (! setting('store_name')) {
            $configWarnings[] = [
                'message' => 'Store name is not configured. The storefront falls back to the app name.',
                'action' => 'Set it in Settings → General.',
                'route' => 'admin.settings.index',
            ];
        }
        if (! setting('default_country')) {
            $configWarnings[] = [
                'message' => 'Default country is not configured. The checkout default is blank.',
                'action' => 'Set it in Settings → Contact & Location.',
                'route' => 'admin.settings.index',
            ];
        }
        if (! setting('timezone')) {
            $configWarnings[] = [
                'message' => 'Timezone is not configured. Dates and order timestamps use the server default.',
                'action' => 'Set it in Settings → Regional.',
                'route' => 'admin.settings.index',
            ];
        }
        if (! setting('store_email')) {
            $configWarnings[] = [
                'message' => 'Store email is not configured. Order confirmations and contact forms go nowhere.',
                'action' => 'Set it in Settings → Contact & Location.',
                'route' => 'admin.settings.index',
            ];
        }

        $stats = [
            'orders_today' => Order::whereDate('created_at', $today)->count(),
            'revenue_today' => (float) Order::whereDate('created_at', $today)
                ->where('status', '!=', 'cancelled')
                ->sum('grand_total'),
            'orders_total' => Order::where('status', '!=', 'cancelled')->count(),
            'low_stock_count' => $lowStockThreshold !== null
                ? Inventory::where('quantity', '<=', $lowStockThreshold)->where('quantity', '>=', 0)->count()
                : 0,
            'pending_orders' => Order::where('status', 'pending')->count(),
            'total_customers' => Customer::count(),
            'total_products' => Product::count(),
            'in_stock_products' => Inventory::where('quantity', '>', 0)->count(),
            'customers_with_orders' => Customer::whereHas('orders')->count(),
        ];

        $recentOrders = Order::with(['customer', 'user', 'items'])
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(10)
            ->get();

        $lowStockProducts = $lowStockThreshold !== null
            ? Inventory::with(['product', 'variant'])
                ->where('quantity', '<=', $lowStockThreshold)
                ->where('quantity', '>=', 0)
                ->orderBy('quantity', 'asc')
                ->limit(5)
                ->get()
            : collect();

        $topProducts = Product::with(['images'])
            ->withSum('orderItems as total_sold', 'quantity')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        $totalSales = (float) Order::where('status', '!=', 'cancelled')->sum('grand_total');
        $totalOrders = Order::where('status', '!=', 'cancelled')->count();
        $avgOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
        $totalCustomers = Customer::count();
        $totalProducts = Product::count();

        $recentOrdersByStatus = Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $monthlySalesData = Order::where('status', '!=', 'cancelled')
            ->whereYear('created_at', now()->year)
            ->selectRaw($this->monthExpression().' as month')
            ->selectRaw('SUM(grand_total) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $monthlyLabels = [];
        $monthlyValues = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyLabels[] = Carbon::create()->month($i)->format('M');
            $monthlyValues[] = (float) ($monthlySalesData[$i] ?? 0);
        }

        return view('admin.dashboard', compact(
            'stats',
            'recentOrders',
            'lowStockProducts',
            'topProducts',
            'recentOrdersByStatus',
            'totalSales',
            'totalOrders',
            'avgOrderValue',
            'totalCustomers',
            'totalProducts',
            'monthlyLabels',
            'monthlyValues',
            'lowStockThreshold',
            'configWarnings'
        ));
    }

    /**
     * SQL expression yielding the 1-12 month number of `created_at`.
     *
     * The dashboard previously hard-coded MySQL's MONTH(), which works in
     * production but throws "no such function: MONTH" on the sqlite database
     * the test suite runs against - making this screen the one admin page that
     * could not be covered by a test.
     */
    protected function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', created_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(MONTH FROM created_at)',
            'sqlsrv' => 'DATEPART(month, created_at)',
            default => 'MONTH(created_at)',
        };
    }
}
