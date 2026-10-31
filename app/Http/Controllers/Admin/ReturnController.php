<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductReturn as ReturnModel;
use App\Services\Return\ReturnService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(
        protected ReturnService $returnService
    ) {}

    public function index(Request $request)
    {
        abort_unless((bool) setting('returns_enabled', true), 404);

        $search = trim((string) $request->query('q'));
        $status = $request->input('status');

        // An unknown status would otherwise widen to `where status = <garbage>`
        // and quietly return an empty page that looks like "no returns exist".
        $status = ReturnStatus::tryFrom((string) $status) ?? null;

        $base = ReturnModel::query()->with(['customer', 'order']);

        $returns = (clone $base)
            ->withCount('items')
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', '%'.$search.'%')
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', '%'.$search.'%'))
                    ->orWhereHas('customer', fn ($cq) => $cq->where('email', 'like', '%'.$search.'%'));
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Tab counts describe every return, not the filtered page.
        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = [];
        foreach (ReturnStatus::cases() as $case) {
            $statusCounts[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return view('admin.returns.index', [
            'returns' => $returns,
            'status' => $status,
            'search' => $search,
            'statusCounts' => $statusCounts,
            'totalCount' => array_sum($statusCounts),
        ]);
    }

    public function show(ReturnModel $return)
    {
        $return->load(['order', 'items.orderItem.product', 'items.returnReason', 'statusHistories', 'customer']);

        return view('admin.returns.show', compact('return'));
    }

    public function approve(ReturnModel $return)
    {
        $this->returnService->approve($return->getKey());

        return back()->with('success', 'Return approved successfully.');
    }

    public function reject(ReturnModel $return)
    {
        $this->returnService->reject($return->getKey());

        return back()->with('success', 'Return rejected successfully.');
    }

    public function complete(ReturnModel $return)
    {
        $this->returnService->complete($return->getKey());

        return back()->with('success', 'Return completed successfully.');
    }

    public function addNote(ReturnModel $return, Request $request)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $return->update(['admin_note' => $request->input('admin_note')]);

        return back()->with('success', 'Note saved successfully.');
    }
}
