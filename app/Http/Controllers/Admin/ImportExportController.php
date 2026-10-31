<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Services\Shared\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportExportController extends Controller
{
    public function index()
    {
        return view('admin.import-export.index');
    }

    public function exportProducts()
    {
        $products = Product::with(['categories', 'brands', 'tags'])->get();

        $filename = 'products-'.date('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($products) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'name', 'slug', 'sku', 'short_description', 'description',
                'base_price', 'cost_price', 'sale_price', 'stock_status',
                'weight', 'is_active', 'is_featured', 'categories', 'brand', 'tags',
            ]);

            foreach ($products as $product) {
                fputcsv($handle, [
                    $this->sanitizeCsvCell((string) $product->name),
                    $this->sanitizeCsvCell((string) $product->slug),
                    $this->sanitizeCsvCell((string) $product->sku),
                    $this->sanitizeCsvCell((string) $product->short_description),
                    $this->sanitizeCsvCell((string) $product->description),
                    $product->base_price,
                    $product->cost_price,
                    $product->sale_price,
                    $product->stock_status,
                    $product->weight,
                    $product->is_active ? 1 : 0,
                    $product->is_featured ? 1 : 0,
                    $this->sanitizeCsvCell($product->categories->pluck('name')->implode('|')),
                    $this->sanitizeCsvCell($product->brands->first()?->name ?? ''),
                    $this->sanitizeCsvCell($product->tags->pluck('name')->implode('|')),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importProducts(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle);

        if (! $header) {
            return back()->with('error', 'The CSV file is empty or invalid.');
        }

        $header = array_map(fn ($col) => strtolower(trim($col)), $header);

        $imported = 0;
        $updated = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);

            try {
                DB::beginTransaction();

                $slug = $data['slug'] ?? Str::slug($data['name'] ?? '');

                // Prevent CSV formula injection (Excel executes =, +, -, @).
                $data['name'] = $this->sanitizeCsvCell($data['name'] ?? '');
                $slug = $this->sanitizeCsvCell($slug);

                if (empty($slug)) {
                    throw new \Exception('Name or slug is required.');
                }

                $productData = [
                    'name' => $data['name'] ?? '',
                    'slug' => $slug,
                    'sku' => $data['sku'] ?? $slug,
                    'short_description' => $data['short_description'] ?? null,
                    'description' => $data['description'] ?? null,
                    'base_price' => $data['base_price'] ?? 0,
                    'cost_price' => $data['cost_price'] ?? null,
                    'sale_price' => $data['sale_price'] ?? null,
                    'stock_status' => $data['stock_status'] ?? 'in_stock',
                    'weight' => $data['weight'] ?? null,
                    'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
                    'is_featured' => isset($data['is_featured']) ? (bool) $data['is_featured'] : false,
                ];

                $exists = Product::where('slug', $slug)->orWhere('sku', $productData['sku'])->first();

                if ($exists) {
                    $exists->update($productData);
                    $exists->cost_price = $data['cost_price'] ?? null;
                    $exists->save();
                    $updated++;
                    $product = $exists;
                } else {
                    $product = Product::create($productData);
                    $product->cost_price = $data['cost_price'] ?? null;
                    $product->save();
                    $imported++;
                }

                if (! empty($data['categories'])) {
                    $categoryIds = collect(explode('|', $data['categories']))
                        ->map(fn ($name) => trim($name))
                        ->filter()
                        ->map(fn ($name) => Category::firstOrCreate(['name' => $name, 'slug' => Str::slug($name)])->getKey())
                        ->toArray();
                    $product->categories()->sync($categoryIds);
                }

                if (! empty($data['brand'])) {
                    $brand = Brand::firstOrCreate(
                        ['name' => trim($data['brand'])],
                        ['slug' => Str::slug($data['brand']), 'is_active' => true]
                    );
                    $product->brands()->sync([$brand->getKey()]);
                }

                if (! empty($data['tags'])) {
                    $tagIds = collect(explode('|', $data['tags']))
                        ->map(fn ($name) => trim($name))
                        ->filter()
                        ->map(fn ($name) => Tag::firstOrCreate(['name' => $name, 'slug' => Str::slug($name)])->getKey())
                        ->toArray();
                    $product->tags()->sync($tagIds);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = 'Row '.($imported + $updated + count($errors) + 1).': import failed.';
            }
        }

        fclose($handle);

        $message = "Import complete. {$imported} imported, {$updated} updated.";

        if (! empty($errors)) {
            $message .= ' '.count($errors).' errors.';

            return back()->with('success', $message)->with('import_errors', $errors);
        }

        return back()->with('success', $message);
    }

    protected function sanitizeCsvCell(string $value): string
    {
        // Shared with ReportController, AnalyticsController and
        // AffiliateController so every CSV export in the back office gets the
        // same formula-injection defence.
        return CsvSanitizer::cell($value);
    }

    public function downloadSample()
    {
        $filename = 'products-sample.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'name', 'slug', 'sku', 'short_description', 'description',
                'base_price', 'cost_price', 'sale_price', 'stock_status',
                'weight', 'is_active', 'is_featured', 'categories', 'brand', 'tags',
            ]);

            fputcsv($handle, [
                'Sample Product', 'sample-product', 'SKU001', 'Short desc', 'Full description here',
                '1000', '800', '900', 'in_stock', '0.5', 1, 0,
                'Electronics|Gadgets', 'Sample Brand', 'new|featured',
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
