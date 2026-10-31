<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreAddressRequest;
use App\Http\Requests\Store\UpdateAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddressController extends Controller
{
    public function index()
    {
        return view('customer.address.index', [
            'addresses' => $this->addresses($this->customer())->get(),
        ]);
    }

    public function create()
    {
        return view('customer.address.create', ['customer' => $this->customer()]);
    }

    public function store(StoreAddressRequest $request)
    {
        $customer = $this->customer();

        try {
            $data = $request->validated();

            // A customer who has addresses always has exactly one default. The
            // first address therefore becomes the default whether or not the box
            // was ticked — otherwise the only address a new customer had was not
            // the default, and checkout pre-selected no address for them.
            $data['is_default'] = $request->boolean('is_default') || ! $customer->addresses()->exists();

            DB::transaction(function () use ($customer, $data) {
                if ($data['is_default']) {
                    $this->clearDefault($customer);
                }

                $customer->addresses()->create($data);
            });

            return redirect()->route('customer.addresses')
                ->with('success', 'Address added successfully.');
        } catch (QueryException $e) {
            Log::error('Address creation failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()
                ->with('error', 'Could not save this address due to a system error. Please try again.');
        }
    }

    public function edit(CustomerAddress $address)
    {
        $customer = $this->customer();
        $this->authorizeAddress($address);

        return view('customer.address.edit', compact('address', 'customer'));
    }

    public function update(UpdateAddressRequest $request, CustomerAddress $address)
    {
        $customer = $this->customer();
        $this->authorizeAddress($address, $customer);

        try {
            $data = $request->validated();

            // The current default cannot un-default itself: clearing the box
            // would leave the customer with addresses but no default at all, and
            // checkout pre-selects from `is_default`. The default is moved by
            // ticking the box on another address, or with "Make default" on the
            // address list — never by clearing it here.
            $data['is_default'] = $request->boolean('is_default') || $address->is_default;

            DB::transaction(function () use ($address, $customer, $data) {
                if ($data['is_default']) {
                    $this->clearDefault($customer, $address);
                }

                $address->update($data);
            });

            return redirect()->route('customer.addresses')
                ->with('success', 'Address updated successfully.');
        } catch (QueryException $e) {
            Log::error('Address update failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()
                ->with('error', 'Could not update this address due to a system error. Please try again.');
        }
    }

    public function destroy(CustomerAddress $address)
    {
        $this->authorizeAddress($address);

        try {
            $address->delete();

            return redirect()->route('customer.addresses')
                ->with('success', 'Address deleted successfully.');
        } catch (QueryException $e) {
            Log::error('Address deletion failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->with('error', 'Failed to delete address due to a system error. Please try again.');
        }
    }

    public function setDefault(CustomerAddress $address)
    {
        $this->authorizeAddress($address);

        try {
            $address->customer->addresses()->where('is_default', true)->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return redirect()->route('customer.addresses')
                ->with('success', 'Default address updated.');
        } catch (QueryException $e) {
            Log::error('Set default address failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->with('error', 'Failed to set default address due to a system error. Please try again.');
        }
    }

    protected function customer(): Customer
    {
        $user = auth()->user();

        if (! $user->customer) {
            $nameParts = explode(' ', $user->name, 2);
            $customer = $user->customer()->create([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => true,
            ]);

            $user->setRelation('customer', $customer);
        }

        return $user->customer;
    }

    protected function addresses(Customer $customer)
    {
        return $customer->addresses()->latest();
    }

    protected function clearDefault(Customer $customer, ?CustomerAddress $exclude = null): void
    {
        $query = $customer->addresses()->where('is_default', true);

        if ($exclude) {
            $query->where('_id', '!=', $exclude->getKey());
        }

        $query->update(['is_default' => false]);
    }

    protected function authorizeAddress(CustomerAddress $address): void
    {
        abort_if($address->customer_id !== $this->customer()->getKey(), 403);
    }
}
