<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Services\Analytics\AnalyticsService;
use App\Services\Shared\CsvSanitizer;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function overview(Request $request)
    {
        $request->validate([
            'range' => 'nullable|in:7,30,90,365',
        ]);

        [$startDate, $endDate, $rangeDays] = $this->resolveDateRange($request, 30);
        $data = $this->analyticsService->getOverviewData($startDate, $endDate);

        // AnalyticsService returns 'range' as the computed day count it walked,
        // which is one more than the requested span. The view compares it
        // against 7/30/90/365 to mark the selected option, so passing it
        // straight through meant the picker never reflected the live period.
        return view('admin.analytics.overview', array_merge($data, [
            'range' => $rangeDays,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]));
    }

    public function products(Request $request)
    {
        $request->validate([
            'range' => 'nullable|in:7,30,90,365',
            'sort_by' => 'nullable|in:revenue,quantity,orders,name',
            'sort_order' => 'nullable|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:200',
            'category_id' => 'nullable|exists:categories,_id',
            'brand_id' => 'nullable|exists:brands,_id',
            'status' => 'nullable|in:active,inactive',
        ]);

        [$startDate, $endDate, $rangeDays] = $this->resolveDateRange($request, 30);
        $filters = $request->only(['sort_by', 'sort_order', 'limit', 'category_id', 'brand_id', 'status']);
        $data = $this->analyticsService->getProductsData($startDate, $endDate, $filters);

        $categories = Category::where('is_active', true)->orderBy('name')->get(['_id', 'name']);
        $brands = Brand::where('is_active', true)->orderBy('name')->get(['_id', 'name']);

        return view('admin.analytics.products', array_merge($data, [
            'categories' => $categories,
            'brands' => $brands,
            // The service reports the days it walked, not the requested span,
            // so the picker could not mark the live period. See
            // resolveDateRange().
            'range' => $rangeDays,
            'filters' => $filters,
            'sortBy' => $filters['sort_by'] ?? 'revenue',
            'sortOrder' => $filters['sort_order'] ?? 'desc',
            'limit' => $filters['limit'] ?? 50,
        ]));
    }

    public function visitors(Request $request)
    {
        $request->validate([
            'range' => 'nullable|in:7,30,90,365',
        ]);

        [$startDate, $endDate, $rangeDays] = $this->resolveDateRange($request, 30);
        $range = (int) $startDate->diffInDays($endDate) + 1;
        $data = $this->analyticsService->getVisitorsData($startDate, $endDate, $range);

        return view('admin.analytics.visitors', array_merge($data, ['range' => $rangeDays]));
    }

    public function realtime(Request $request)
    {
        $data = $this->analyticsService->getRealtimeData();

        return view('admin.analytics.realtime', $data);
    }

    public function customers(Request $request)
    {
        $request->validate([
            'range' => 'nullable|in:7,30,90,365',
        ]);

        [$startDate, $endDate, $rangeDays] = $this->resolveDateRange($request, 30);
        $range = (int) $startDate->diffInDays($endDate) + 1;
        $data = $this->analyticsService->getCustomersData($startDate, $endDate, $range);

        return view('admin.analytics.customers', array_merge($data, ['range' => $rangeDays]));
    }

    public function marketing(Request $request)
    {
        $request->validate([
            'range' => 'nullable|in:7,30,90,365',
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request, 30);
        $data = $this->analyticsService->getMarketingData($startDate, $endDate);
        $data['range'] = (int) $request->input('range', 30);

        return view('admin.analytics.marketing', $data);
    }

    public function inventory(Request $request)
    {
        $request->validate([
            'range' => 'nullable|in:7,30,90,365',
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request, 30);
        $data = $this->analyticsService->getInventoryData($startDate, $endDate);
        $data['range'] = (int) $request->input('range', 30);

        return view('admin.analytics.inventory', $data);
    }

    public function customReport(Request $request)
    {
        $request->validate([
            'report_type' => 'nullable|in:sales,products,customers,orders',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'group_by' => 'nullable|in:hour,day,week,month',
            'metrics' => 'nullable|array',
        ]);

        $reportType = $request->input('report_type', 'sales');
        $startDate = $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : Carbon::now()->subDays(30);
        $endDate = $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : Carbon::now();
        $groupBy = $request->input('group_by', 'day');
        $metrics = $request->input('metrics', $this->analyticsService->getAvailableMetrics($reportType));

        $reportData = $this->analyticsService->generateCustomReport($reportType, $startDate, $endDate, $groupBy, $metrics);
        $availableMetrics = $this->analyticsService->getAvailableMetrics($reportType);
        $availableGroupBy = ['hour', 'day', 'week', 'month'];

        return view('admin.analytics.custom-report', compact(
            'reportType',
            'startDate',
            'endDate',
            'groupBy',
            'metrics',
            'reportData',
            'availableMetrics',
            'availableGroupBy'
        ));
    }

    public function customReportData(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:sales,products,customers,orders',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'group_by' => 'nullable|in:hour,day,week,month',
            'metrics' => 'array',
            'sort_by' => 'nullable|string',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $reportType = $request->input('report_type', 'sales');
        $startDate = $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : Carbon::now()->subDays(30);
        $endDate = $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : Carbon::now();
        $groupBy = $request->input('group_by', 'day');
        $metrics = $request->input('metrics', $this->analyticsService->getAvailableMetrics($reportType));

        $reportData = $this->analyticsService->generateCustomReport($reportType, $startDate, $endDate, $groupBy, $metrics);

        return response()->json([
            'data' => $reportData,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:overview,products,visitors,custom',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'format' => 'nullable|in:csv,xlsx',
        ]);

        $reportType = $request->input('report_type');
        $format = $request->input('format', 'csv');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $data = match ($reportType) {
            'overview' => $this->analyticsService->getOverviewExportData($dateFrom, $dateTo),
            'products' => $this->analyticsService->getProductsExportData($dateFrom, $dateTo),
            'visitors' => $this->analyticsService->getVisitorsExportData($dateFrom, $dateTo),
            'custom' => $this->getCustomExportData($request),
            default => [],
        };

        $filename = "analytics_{$reportType}_report_".now()->format('Y-m-d_His');

        if ($format === 'csv') {
            return $this->exportCsv($data, $filename);
        }

        return $this->exportXlsx($data, $filename);
    }

    protected function getCustomExportData(Request $request): array
    {
        $reportType = $request->input('report_type', 'sales');
        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from')) : Carbon::now()->subDays(30);
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to')) : Carbon::now();
        $groupBy = $request->input('group_by', 'day');
        $metrics = $request->input('metrics', $this->analyticsService->getAvailableMetrics($reportType));

        return $this->analyticsService->generateCustomReport($reportType, $dateFrom, $dateTo, $groupBy, $metrics);
    }

    protected function exportCsv(array $data, string $filename)
    {
        if (empty($data)) {
            return back()->with('error', 'No data to export.');
        }

        $headers = array_keys($data[0]);

        $callback = function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($data as $row) {
                $row = array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $row);
                fputcsv($handle, CsvSanitizer::row($row));
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    protected function exportXlsx(array $data, string $filename)
    {
        if (empty($data)) {
            return back()->with('error', 'No data to export.');
        }

        $headers = array_keys($data[0]);

        $callback = function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers, "\t");
            foreach ($data as $row) {
                $row = array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $row);
                fputcsv($handle, CsvSanitizer::row($row), "\t");
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xls\"",
        ]);
    }

    /**
     * Resolve the requested window to a half-open pair of bounds plus the
     * requested day count.
     *
     * The third element is what the range pickers must compare against: the
     * day count the service reports back is the number of days it walked, which
     * is `span + 1` because both bounds are inclusive calendar days.
     *
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    protected function resolveDateRange(Request $request, int $defaultDays): array
    {
        $range = (int) $request->input('range', $defaultDays);

        return [
            Carbon::now()->subDays($range)->startOfDay(),
            Carbon::now()->endOfDay(),
            $range,
        ];
    }
}
