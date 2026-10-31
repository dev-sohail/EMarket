<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    protected NotificationService $notificationService;

    protected AuditService $auditService;

    public function __construct(NotificationService $notificationService, AuditService $auditService)
    {
        $this->notificationService = $notificationService;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = Customer::with(['user'])
            ->withCount('orders')
            ->withSum(['orders as total_spent' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }], 'grand_total');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate(20);

        return view('admin.customer.index', compact('customers', 'search'));
    }

    public function show(Customer $customer)
    {
        $customer->load('orders.items.product');
        $orderHistory = $customer->orders;
        $totalSpent = $customer->orders->where('status', '!=', 'cancelled')->sum('grand_total');

        return view('admin.customer.show', compact('customer', 'orderHistory', 'totalSpent'));
    }

    public function create()
    {
        return view('admin.customer.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'create_user_account' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated) {
            $user = null;
            if ($request->boolean('create_user_account') && ! empty($validated['password'])) {
                // 'password' is not fillable and users.password is NOT NULL, so
                // create() would INSERT before the password was assigned. Build
                // the model, assign, save once. The 'hashed' cast runs on
                // assignment.
                $user = new User([
                    'name' => $validated['first_name'].' '.$validated['last_name'],
                    'email' => $validated['email'],
                    'is_active' => true,
                ]);

                $user->password = $validated['password'];
                $user->save();
            }

            $customer = Customer::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'user_id' => $user?->getKey(),
                'is_active' => true,
            ]);

            $this->notificationService->sendToAllAdmins(
                title: 'Customer Created',
                message: "Customer '{$customer->first_name} {$customer->last_name}' has been created.",
                data: ['customer_id' => $customer->getKey()]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'created',
                entity: Customer::class,
                entityId: $customer->getKey(),
                newValues: $customer->toArray()
            );

            return redirect()->route('admin.customers.index')
                ->with('success', 'Customer created successfully.');
        });
    }

    public function edit(Customer $customer)
    {
        return view('admin.customer.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $oldValues = $customer->toArray();
        $customer->update($validated);
        $newValues = $customer->fresh()->toArray();

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'updated',
            entity: Customer::class,
            entityId: $customer->getKey(),
            oldValues: $oldValues,
            newValues: $newValues
        );

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customerData = $customer->toArray();
        $customer->delete();

        $this->notificationService->sendToAllAdmins(
            title: 'Customer Deleted',
            message: "Customer '{$customerData['first_name']} {$customerData['last_name']}' has been deleted.",
            data: ['customer_id' => $customer->getKey()]
        );

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'deleted',
            entity: Customer::class,
            entityId: $customer->getKey(),
            oldValues: $customerData
        );

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    public function deleteApproval()
    {
        // Get all soft-deleted customers
        $trashedCustomers = Customer::onlyTrashed()
            ->with(['user'])
            ->withCount('orders')
            ->latest('deleted_at')
            ->paginate(20);

        return view('admin.customer.delete-approval', compact('trashedCustomers'));
    }

    public function forceDelete($id)
    {
        $customer = Customer::withTrashed()->findOrFail($id);
        $customerData = $customer->toArray();

        // Permanently delete the customer
        $customer->forceDelete();

        $this->notificationService->sendToAllAdmins(
            title: 'Customer Permanently Deleted',
            message: "Customer '{$customerData['first_name']} {$customerData['last_name']}' has been permanently deleted.",
            data: ['customer_id' => $id]
        );

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'permanently_deleted',
            entity: Customer::class,
            entityId: $id,
            oldValues: $customerData
        );

        return redirect()->route('admin.delete-approval')
            ->with('success', 'Customer permanently deleted successfully.');
    }

    public function createUserAccount(Request $request, Customer $customer)
    {
        if ($customer->user_id) {
            return back()->with('error', 'Customer already has a user account.');
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        // 'password' is not fillable and users.password is NOT NULL: see store().
        $user = new User([
            'name' => $customer->first_name.' '.$customer->last_name,
            'email' => $customer->email,
            'is_active' => true,
        ]);

        $user->password = $request->password;
        $user->save();

        $customer->update(['user_id' => $user->getKey()]);

        return back()->with('success', 'User account created successfully.');
    }

    public function impersonate(Request $request, Customer $customer)
    {
        if (! $customer->user_id) {
            return back()->with('error', 'Customer does not have a user account.');
        }

        $admin = auth()->guard('admin')->user();
        if (! $admin) {
            return back()->with('error', 'You do not have permission to impersonate.');
        }

        $isAdmin = $admin->roles()->whereIn('name', config('admin.roles', ['Super Admin', 'Admin', 'super_admin', 'admin']))->exists();
        if (! $isAdmin) {
            return back()->with('error', 'You do not have permission to impersonate.');
        }

        $request->session()->regenerate();

        session(['impersonator_id' => $admin->getKey()]);

        auth()->guard('web')->loginUsingId($customer->user_id);

        return redirect()->route('home')
            ->with('success', 'You are now impersonating '.$customer->first_name.' '.$customer->last_name.'.');
    }

    public function stopImpersonating(Request $request)
    {
        $adminId = session('impersonator_id');
        session()->forget('impersonator_id');

        auth()->guard('web')->logout();

        if ($adminId && $admin = User::find($adminId)) {
            $isAdmin = $admin->roles()->whereIn('name', config('admin.roles', ['Super Admin', 'Admin', 'super_admin', 'admin']))->exists();

            if ($isAdmin) {
                $request->session()->regenerate();
                auth()->guard('admin')->login($admin);
            }
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'Impersonation stopped.');
    }

    public function toggleStatus(Customer $customer)
    {
        $oldValues = $customer->toArray();
        $customer->update(['is_active' => ! $customer->is_active]);
        $newValues = $customer->fresh()->toArray();

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'updated',
            entity: Customer::class,
            entityId: $customer->getKey(),
            oldValues: $oldValues,
            newValues: $newValues
        );

        return back()->with('success', 'Customer status updated.');
    }
}
