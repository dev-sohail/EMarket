<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CRUD for storefront FAQs.
 *
 * The listing is search/filter/sort driven because a catalogue of questions is
 * unusable as one long table once a shop has more than a screenful of them.
 */
class FaqController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $query = Faq::query();

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        match ($request->get('status')) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        match ($request->get('sort')) {
            'sort_asc' => $query->orderBy('sort_order')->orderBy('question'),
            'sort_desc' => $query->orderByDesc('sort_order')->orderBy('question'),
            'question_asc' => $query->orderBy('question'),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            default => $query->orderBy('sort_order')->orderByDesc('created_at'),
        };

        $faqs = $query->paginate(self::PER_PAGE)->withQueryString();

        $stats = [
            'total' => Faq::count(),
            'active' => Faq::where('is_active', true)->count(),
            'inactive' => Faq::where('is_active', false)->count(),
        ];

        return view('admin.faqs.index', compact('faqs', 'stats'));
    }

    public function create(): View
    {
        return view('admin.faqs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:5000',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->has('is_active');

        Faq::create($validated);

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ created successfully.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.edit', compact('faq'));
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:5000',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->has('is_active');

        $faq->update($validated);

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ updated successfully.');
    }

    public function toggleStatus(Faq $faq): RedirectResponse
    {
        $faq->update(['is_active' => ! $faq->is_active]);

        return back()->with('success', $faq->is_active
            ? 'FAQ is now visible on the storefront.'
            : 'FAQ is now hidden from the storefront.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ deleted successfully.');
    }
}
