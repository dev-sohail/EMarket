<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index(Request $request)
    {
        // The listing renders a search box and a status/sort picker, so the
        // values have to reach the query: this action used to take no Request
        // at all, so the form reloaded the same unfiltered list and looked
        // broken. Validated because `status` and `sort` select query branches
        // and order columns.
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:out,low,in',
            'sort' => 'nullable|in:latest,oldest,qty_asc,qty_desc,name_asc,name_desc',
        ]);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $sort = (string) $request->query('sort', 'latest');

        // Null when the threshold has not been configured, in which case the
        // 'low' branch has nothing to compare against.
        $lowStockThreshold = low_stock_threshold();

        $query = Inventory::with(['product' => fn ($q) => $q->with('images'), 'variant'])
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"))
                    ->orWhereHas('variant', fn ($v) => $v->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%"));
            }));

        if ($status === 'out') {
            $query->where('quantity', '<=', 0);
        } elseif ($status === 'low' && $lowStockThreshold !== null) {
            $query->where('quantity', '>', 0)->where('quantity', '<=', $lowStockThreshold);
        } elseif ($status === 'low') {
            // No threshold configured: every row would be "low", which is
            // meaningless, so the branch matches nothing.
            $query->whereRaw('1 = 0');
        } elseif ($status === 'in') {
            $query->where('quantity', '>', $lowStockThreshold ?? 0);
        }

        match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderByDesc('_id'),
            'qty_asc' => $query->orderBy('quantity')->orderByDesc('_id'),
            'qty_desc' => $query->orderByDesc('quantity')->orderByDesc('_id'),
            'name_asc', 'name_desc' => $query
                ->leftJoin('products', 'products._id', '=', 'inventories.product_id')
                ->select('inventories.*')
                ->orderBy('products.name', $sort === 'name_asc' ? 'asc' : 'desc')
                ->orderByDesc('inventories._id'),
            default => $query->orderByDesc('created_at')->orderByDesc('_id'),
        };

        $products = $query->paginate(15)->withQueryString();

        // Counted across the whole table so the cards stay stable while the
        // filters below the header narrow the rows.
        $stats = [
            'total' => Inventory::count(),
            'outOfStockCount' => Inventory::where('quantity', '<=', 0)->count(),
            'inStockCount' => $lowStockThreshold === null
                ? Inventory::where('quantity', '>', 0)->count()
                : Inventory::where('quantity', '>', $lowStockThreshold)->count(),
            'lowStockCount' => $lowStockThreshold === null
                ? 0
                : Inventory::where('quantity', '>', 0)->where('quantity', '<=', $lowStockThreshold)->count(),
        ];

        return view('admin.inventory.index', [
            'products' => $products,
            'stats' => $stats,
            'filters' => ['search' => $search, 'status' => $status, 'sort' => $sort],
            'lowStockThreshold' => $lowStockThreshold,
        ]);
    }

    public function show(Inventory $inventory)
    {
        $inventory->load(['product' => fn ($q) => $q->with('images'), 'variant']);
        $movements = InventoryMovement::where('inventory_id', $inventory->getKey())
            ->latest()
            ->get();

        return view('admin.inventory.show', compact('inventory', 'movements'));
    }

    public function update(Request $request, Inventory $inventory)
    {
        // Check if admin has inventory management permission
        $admin = auth()->guard('admin')->user();
        $hasPermission = $admin->roles()->whereIn('name', config('admin.inventory_roles', ['Super Admin', 'Admin', 'super_admin', 'admin', 'Inventory Manager']))->exists();

        if (! $hasPermission) {
            return back()->with('error', 'You do not have permission to adjust inventory.');
        }

        $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:increase,decrease,set',
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        $quantity = (int) $request->input('quantity');
        $type = $request->input('type');
        $note = $request->input('note');

        if ($type === 'set') {
            $diff = $quantity - $inventory->quantity;
        } elseif ($type === 'increase') {
            $diff = $quantity;
        } else {
            $diff = -$quantity;
        }

        // Prepare note for the movement
        $movementNote = $request->input('reason');
        if ($note) {
            $movementNote .= ': '.$note;
        }

        $this->inventoryService->adjustStock(
            $inventory->product_id,
            $inventory->variant_id,
            $diff,
            $movementNote
        );

        return back()->with('success', 'Stock adjusted successfully.');
    }

    public function movements(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            // The list offered "purchase", which is not a value
            // InventoryMovementType can produce, and omitted reserved/released.
            'type' => 'nullable|in:'.implode(',', array_column(InventoryMovementType::cases(), 'value')),
        ]);

        $query = InventoryMovement::with('inventory');

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $movements = $query->latest()->paginate(20);

        return view('admin.inventory.movements', compact('movements'));
    }

    public function lowStock()
    {
        $lowStockThreshold = low_stock_threshold();

        // Without a configured threshold there is no low-stock report to show,
        // and comparing against a hardcoded guess would report rows the
        // operator never asked for.
        $products = $lowStockThreshold === null
            ? collect()
            : Inventory::with(['product', 'variant'])
                ->where('quantity', '<=', $lowStockThreshold)
                ->where('quantity', '>', 0)
                ->orderBy('quantity', 'asc')
                ->get();

        return view('admin.inventory.low-stock', compact('products', 'lowStockThreshold'));
    }
}
