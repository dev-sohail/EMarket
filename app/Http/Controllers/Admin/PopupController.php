<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePopupRequest;
use App\Http\Requests\Admin\UpdatePopupRequest;
use App\Models\Popup;
use App\Services\Shared\ImageCompression;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PopupController extends Controller
{
    public function __construct(protected ImageCompression $compression) {}

    private function normalizeDates(array $validated): array
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = Carbon::createFromFormat('Y-m-d\TH:i', $validated[$field])->toDateTimeString();
            }
        }

        return $validated;
    }

    public function index()
    {
        $popups = Popup::orderBy('sort_order')->get();

        return view('admin.popups.index', compact('popups'));
    }

    public function create()
    {
        return view('admin.popups.create');
    }

    public function store(StorePopupRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $this->compression->compress($request->file('image'), 1400, 82)->store('popups', 'public');
        }

        $validated = $this->normalizeDates($validated);

        Popup::create($validated);

        return redirect()->route('admin.popups.index')
            ->with('success', 'Popup created successfully.');
    }

    public function edit(Popup $popup)
    {
        return view('admin.popups.edit', compact('popup'));
    }

    public function update(UpdatePopupRequest $request, Popup $popup)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            if ($popup->image) {
                Storage::disk('public')->delete($popup->image);
            }

            $validated['image'] = $this->compression->compress($request->file('image'), 1400, 82)->store('popups', 'public');
        }

        $validated = $this->normalizeDates($validated);

        $popup->update($validated);

        return redirect()->route('admin.popups.index')
            ->with('success', 'Popup updated successfully.');
    }

    public function destroy(Popup $popup)
    {
        if ($popup->image) {
            Storage::disk('public')->delete($popup->image);
        }

        $popup->delete();

        return redirect()->route('admin.popups.index')
            ->with('success', 'Popup deleted successfully.');
    }

    public function toggleStatus(Popup $popup)
    {
        $popup->update(['is_active' => ! $popup->is_active]);

        return back()->with('success', 'Popup status updated.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'popups' => 'required|array',
            'popups.*' => 'exists:popups,_id',
        ]);

        foreach ($request->input('popups') as $index => $id) {
            Popup::where('_id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
