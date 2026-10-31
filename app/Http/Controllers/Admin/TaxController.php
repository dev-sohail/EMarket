<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRule;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $type = $request->query('type');
        $status = $request->query('status');

        $taxRules = TaxRule::query()
            ->withCount('targets')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when(in_array($type, ['percentage', 'fixed'], true), fn ($query) => $query->where('type', $type))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => TaxRule::count(),
            'active' => TaxRule::where('is_active', true)->count(),
            'percentage' => TaxRule::where('type', 'percentage')->count(),
        ];

        return view('admin.tax.index', compact('taxRules', 'stats'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id' => 'nullable|exists:tax_rules,_id',
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:percentage,fixed',
            'is_active' => 'boolean',
        ]);

        $data = $request->only([
            'id', 'name', 'rate', 'type', 'is_active',
        ]);

        if (! empty($data['id'])) {
            $taxRule = TaxRule::findOrFail($data['id']);
            $taxRule->update($data);
        } else {
            TaxRule::create($data);
        }

        return back()->with('success', 'Tax rule saved successfully.');
    }

    public function update(Request $request, TaxRule $tax)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:percentage,fixed',
            'is_active' => 'boolean',
        ]);

        $tax->update($request->only(['name', 'rate', 'type', 'is_active']));

        return back()->with('success', 'Tax rule saved successfully.');
    }

    public function destroy(TaxRule $tax)
    {
        $tax->delete();

        return back()->with('success', 'Tax rule deleted successfully.');
    }

    public function toggleStatus(TaxRule $tax)
    {
        $tax->update(['is_active' => ! $tax->is_active]);

        return back()->with('success', 'Tax rule status updated.');
    }
}
