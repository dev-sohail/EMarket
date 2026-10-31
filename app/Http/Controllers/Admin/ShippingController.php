<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function index()
    {
        $zones = ShippingZone::with(['cities', 'rules.method'])
            ->withCount('rules')
            ->latest()
            ->paginate(15);

        $methods = ShippingMethod::with('rules')
            ->withCount('rules')
            ->latest()
            ->paginate(15);

        $stats = [
            'zones' => ShippingZone::count(),
            'zones_active' => ShippingZone::where('is_active', true)->count(),
            'methods' => ShippingMethod::count(),
            'methods_active' => ShippingMethod::where('is_active', true)->count(),
            'rules' => ShippingRule::count(),
        ];

        return view('admin.shipping.index', compact('zones', 'methods', 'stats'));
    }

    public function zonesIndex()
    {
        $zones = ShippingZone::with(['cities', 'rules.method'])
            ->latest()
            ->paginate(15);

        return view('admin.shipping.zones.index', compact('zones'));
    }

    public function zoneCreate()
    {
        return view('admin.shipping.zones.create');
    }

    public function zoneStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'cities' => 'required|array',
            'cities.*' => 'string|max:255',
        ]);

        $zone = ShippingZone::create($request->only(['name', 'is_active']));

        if ($request->has('cities')) {
            $zone->cities()->createMany(
                collect($request->input('cities'))->filter()->map(fn ($city) => ['city' => $city])->toArray()
            );
        }

        return redirect()->route('admin.shipping.zones.index')
            ->with('success', 'Shipping zone created successfully.');
    }

    public function zoneEdit(ShippingZone $zone)
    {
        $zone->load('cities', 'rules');

        return view('admin.shipping.zones.edit', compact('zone'));
    }

    public function zoneUpdate(Request $request, ShippingZone $zone)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'cities' => 'required|array',
            'cities.*' => 'string|max:255',
        ]);

        $zone->update($request->only(['name', 'is_active']));

        if ($request->has('cities')) {
            $zone->cities()->delete();
            $zone->cities()->createMany(
                collect($request->input('cities'))->filter()->map(fn ($city) => ['city' => $city])->toArray()
            );
        }

        return redirect()->route('admin.shipping.zones.index')
            ->with('success', 'Shipping zone updated successfully.');
    }

    public function zoneDestroy(ShippingZone $zone)
    {
        $zone->cities()->delete();
        $zone->rules()->delete();
        $zone->delete();

        return redirect()->route('admin.shipping.zones.index')
            ->with('success', 'Shipping zone deleted successfully.');
    }

    public function methodsIndex()
    {
        $methods = ShippingMethod::with('rules.shippingZone')
            ->latest()
            ->paginate(15);

        return view('admin.shipping.methods.index', compact('methods'));
    }

    public function methodStore(Request $request)
    {
        $request->validate([
            'id' => 'nullable|exists:shipping_methods,_id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'base_cost' => 'required|numeric|min:0',
            'per_kg_cost' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data = $request->only([
            'id', 'name', 'description', 'base_cost', 'per_kg_cost', 'estimated_days', 'is_active',
        ]);

        if (! empty($data['id'])) {
            $method = ShippingMethod::findOrFail($data['id']);
            $method->update($data);
        } else {
            ShippingMethod::create($data);
        }

        return back()->with('success', 'Shipping method saved successfully.');
    }

    public function methodEdit(ShippingMethod $method)
    {
        return view('admin.shipping.methods.edit', compact('method'));
    }

    public function methodUpdate(Request $request, ShippingMethod $method)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'base_cost' => 'required|numeric|min:0',
            'per_kg_cost' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data = $request->only([
            'name', 'description', 'base_cost', 'per_kg_cost', 'estimated_days', 'is_active',
        ]);

        $method->update($data);

        return redirect()->route('admin.shipping.methods.index')
            ->with('success', 'Shipping method updated successfully.');
    }

    public function methodDestroy(ShippingMethod $method)
    {
        $method->rules()->delete();
        $method->delete();

        return back()->with('success', 'Shipping method deleted successfully.');
    }

    public function rulesIndex(ShippingZone $zone)
    {
        $zone->load('rules.method');
        $methods = ShippingMethod::all();

        return view('admin.shipping.rules.index', compact('zone', 'methods'));
    }

    public function ruleStore(Request $request)
    {
        $request->validate([
            'id' => 'nullable|exists:shipping_rules,_id',
            'zone_id' => 'required|exists:shipping_zones,_id',
            'shipping_method_id' => 'required|exists:shipping_methods,_id',
            'min_weight' => 'nullable|numeric|min:0',
            'max_weight' => 'nullable|numeric|min:0|gte:min_weight',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_order_value' => 'nullable|numeric|min:0|gte:min_order_value',
            'price' => 'required|numeric|min:0',
            'free_shipping_threshold' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $data = $request->only([
            'id', 'zone_id', 'shipping_method_id', 'min_weight', 'max_weight',
            'min_order_value', 'max_order_value', 'price', 'free_shipping_threshold', 'is_active',
        ]);

        if (isset($data['zone_id'])) {
            $data['shipping_zone_id'] = $data['zone_id'];
            unset($data['zone_id']);
        }

        if (! empty($data['id'])) {
            $rule = ShippingRule::findOrFail($data['id']);
            $rule->update($data);
        } else {
            ShippingRule::create($data);
        }

        return back()->with('success', 'Shipping rule saved successfully.');
    }

    public function ruleEdit(ShippingRule $rule)
    {
        $zone = $rule->shippingZone;
        $methods = ShippingMethod::all();

        return view('admin.shipping.rules.edit', compact('rule', 'zone', 'methods'));
    }

    public function ruleUpdate(Request $request, ShippingRule $rule)
    {
        $request->validate([
            'shipping_method_id' => 'required|exists:shipping_methods,_id',
            'min_weight' => 'nullable|numeric|min:0',
            'max_weight' => 'nullable|numeric|min:0|gte:min_weight',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_order_value' => 'nullable|numeric|min:0|gte:min_order_value',
            'price' => 'required|numeric|min:0',
            'free_shipping_threshold' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $data = $request->only([
            'shipping_method_id', 'min_weight', 'max_weight',
            'min_order_value', 'max_order_value', 'price', 'free_shipping_threshold', 'is_active',
        ]);

        $rule->update($data);

        return redirect()->route('admin.shipping.rules.index', $rule->shipping_zone_id)
            ->with('success', 'Shipping rule updated successfully.');
    }

    public function ruleDestroy(ShippingRule $rule)
    {
        $rule->delete();

        return back()->with('success', 'Shipping rule deleted successfully.');
    }
}
