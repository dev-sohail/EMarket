<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentMethodController extends Controller
{
    public function index(Request $request): View
    {
        // The page rendered a search box and a submit button, but this action
        // never received the Request, so the box did nothing at all: it
        // reloaded the same list and looked broken. The filter values below now
        // reach the query, and they are validated because `status` and `type`
        // land in where clauses — an unrecognised value used to yield a
        // silently empty table instead of reporting the typo.
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'type' => 'nullable|in:cod,online',
            'sort_by' => 'nullable|in:_id,name,code,sort_order,created_at,updated_at',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $query = PaymentMethod::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->input('status') !== null && $request->input('status') !== '') {
            $query->where('is_active', $request->input('status') === 'active' ? 1 : 0);
        }

        if ($type = $request->input('type')) {
            $query->where('is_cod', $type === 'cod' ? 1 : 0);
        }

        $sortBy = $request->input('sort_by', 'sort_order');
        $sortDir = $request->input('sort_dir', 'asc');

        // sort_order first regardless of direction: it is the tie-breaker that
        // makes the admin-chosen storefront order deterministic.
        if ($sortBy === 'sort_order') {
            $query->orderBy('sort_order', $sortDir)->orderBy('name');
        } else {
            $query->orderBy($sortBy, $sortDir)->orderBy('sort_order');
        }

        $paymentMethods = $query->get();

        // Counted across all methods, not the filtered list, so the cards stay
        // stable while the table below them narrows.
        $stats = [
            'total' => PaymentMethod::count(),
            'active' => PaymentMethod::where('is_active', 1)->count(),
            'inactive' => PaymentMethod::where('is_active', 0)->count(),
            'stripe_ready' => PaymentMethod::where('stripe_enabled', 1)->count(),
        ];

        return view('admin.payment-methods.index', compact('paymentMethods', 'stats'));
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        return view('admin.payment-methods.edit', compact('paymentMethod'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'stripe_enabled' => ['sometimes', 'boolean'],
            'stripe_sandbox' => ['sometimes', 'boolean'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $validator->validate();

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? $paymentMethod->sort_order;
        $data['stripe_enabled'] = $request->boolean('stripe_enabled');
        $data['stripe_sandbox'] = $request->boolean('stripe_sandbox');

        // `filled()`, not `!== ''`. The web group runs ConvertEmptyStringsToNull,
        // so a left-blank field arrives as null rather than an empty string —
        // the old `!== ''` test was therefore true for a blank input and wrote
        // null over the stored key, silently destroying the Stripe secret every
        // time the form was saved without retyping it.
        $secretKey = $request->input('stripe_secret_key');
        $data['stripe_secret_key'] = filled($secretKey) ? $secretKey : $paymentMethod->stripe_secret_key;

        // Keep existing publishable key if input is blank
        $publishableKey = $request->input('stripe_publishable_key');
        $data['stripe_publishable_key'] = filled($publishableKey) ? $publishableKey : $paymentMethod->stripe_publishable_key;

        // Validate Stripe configuration if enabled
        if ($data['stripe_enabled']) {
            $errors = [];

            if (! $data['stripe_publishable_key'] || ! $data['stripe_secret_key']) {
                $errors[] = 'Stripe publishable and secret keys are required when Stripe is enabled.';
            } else {
                $prefix = $data['stripe_sandbox'] ? 'test' : 'live';
                if (! str_starts_with($data['stripe_publishable_key'], "pk_{$prefix}_")) {
                    $errors[] = 'Stripe publishable key does not match the selected environment.';
                }
                if (! str_starts_with($data['stripe_secret_key'], "sk_{$prefix}_")) {
                    $errors[] = 'Stripe secret key does not match the selected environment.';
                }
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages([
                    'stripe_keys' => $errors,
                ]);
            }
        }

        $paymentMethod->update($data);

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Payment method updated successfully.');
    }
}
