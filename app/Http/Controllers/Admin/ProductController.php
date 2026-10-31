<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tag;
use App\Services\Catalog\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:36',
            'brand' => 'nullable|string|max:36',
            'status' => 'nullable|in:active,inactive',
            'stock_status' => 'nullable|in:in_stock,out_of_stock,on_backorder',
            'sort_by' => 'nullable|in:name,sku,base_price,stock_status,is_active,is_featured,created_at,updated_at',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $filters = $request->only([
            'search', 'category', 'brand', 'status', 'stock_status', 'sort_by', 'sort_order',
        ]);

        $products = $this->productService->getFilteredProducts($filters);

        $categories = $this->productService->getCategoriesForSelect();
        $brands = $this->productService->getBrandsForSelect();

        $stats = [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'out_of_stock' => Product::where('stock_status', 'out_of_stock')->count(),
            'featured' => Product::where('is_featured', true)->count(),
        ];

        return view('admin.product.index', compact('products', 'filters', 'categories', 'brands', 'stats'));
    }

    public function create()
    {
        $categories = $this->productService->getCategoriesForSelect();
        $brands = $this->productService->getBrandsForSelect();
        $attributes = $this->productService->getAttributesForSelect();
        $tags = Tag::orderBy('name')->get();

        return view('admin.product.create', compact('categories', 'brands', 'attributes', 'tags'));
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();

        if (array_key_exists('tags', $data)) {
            $data['tag_ids'] = $data['tags'] ?? [];
            unset($data['tags']);
        }

        // Extract images from data to upload separately
        $images = $data['images'] ?? [];
        unset($data['images']);

        $product = $this->productService->create($data);

        // Upload the product images if any were provided
        if (! empty($images)) {
            $this->productService->uploadImages($product, $images);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        // `variants.attributeValues` is eager loaded because the form's variant
        // rows pre-check the attribute combination each variant is stored
        // with; without it the page ran one query per variant.
        $product->load(['categories', 'brands', 'images', 'variants.attributeValues', 'attributeValues.attribute']);
        $categories = $this->productService->getCategoriesForSelect();
        $brands = $this->productService->getBrandsForSelect();
        $attributes = $this->productService->getAttributesForSelect();
        $tags = Tag::orderBy('name')->get();

        return view('admin.product.edit', compact('product', 'categories', 'brands', 'attributes', 'tags'));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        if (array_key_exists('tags', $data)) {
            $data['tag_ids'] = $data['tags'] ?? [];
            unset($data['tags']);
        }

        // Extract images from data to upload separately if any were provided
        $images = $data['images'] ?? [];
        unset($data['images']);

        $this->productService->update($product->getKey(), $data);

        // Upload any new images that were submitted
        if (! empty($images)) {
            $this->productService->uploadImages($product, $images);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $this->productService->delete($product->getKey());

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function toggleStatus(Product $product)
    {
        $this->productService->toggleStatus($product);

        return back()->with('success', 'Product status updated.');
    }

    public function toggleFeatured(Product $product)
    {
        $this->productService->toggleFeatured($product);

        return back()->with('success', 'Product featured status updated.');
    }

    public function manageImages(Product $product)
    {
        $product->load('images');

        return view('admin.product.images', compact('product'));
    }

    public function storeImages(Request $request, Product $product)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|max:5120|mimes:jpeg,png,jpg,gif,webp',
        ]);

        $this->productService->uploadImages($product, $request->file('images'));

        return back()->with('success', 'Images uploaded successfully.');
    }

    public function deleteImage(Product $product, ProductImage $image)
    {
        abort_if($image->product_id !== $product->getKey(), 403);

        $this->productService->deleteImage($image);

        return back()->with('success', 'Image deleted successfully.');
    }

    public function reorderImages(Request $request, Product $product)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'exists:product_images,_id',
        ]);

        $imageIds = $request->input('images');

        $productImages = $product->images()->whereIn('_id', $imageIds)->get();
        if ($productImages->count() !== count($imageIds)) {
            abort(403);
        }

        $this->productService->reorderImages($imageIds);

        return response()->json(['success' => true]);
    }

    public function setPrimaryImage(Product $product, ProductImage $image)
    {
        abort_if($image->product_id !== $product->getKey(), 403);

        $this->productService->setPrimaryImage($product, $image);

        return back()->with('success', 'Primary image updated.');
    }

    public function bulkDeleteImages(Request $request, Product $product)
    {
        $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:product_images,_id',
        ]);

        $imageIds = $request->input('image_ids');

        foreach ($product->images()->whereIn('_id', $imageIds)->get() as $image) {
            $this->productService->deleteImage($image);
        }

        return back()->with('success', 'Selected images deleted.');
    }
}
