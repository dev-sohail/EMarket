<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UpdateProfileRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit()
    {
        $customer = auth()->user()->customer;

        return view('customer.profile.edit', compact('customer'));
    }

    public function update(UpdateProfileRequest $request)
    {
        try {
            $customer = auth()->user()->customer;
            $user = $customer->user;

            $data = $request->validated();

            if ($request->hasFile('avatar')) {
                if ($customer->avatar) {
                    Storage::disk('public')->delete($customer->avatar);
                }
                $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
            }

            $user->update([
                'name' => $data['first_name'].' '.$data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'avatar' => $data['avatar'] ?? null,
            ]);

            $customer->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            return redirect()->route('customer.profile')
                ->with('success', 'Profile updated successfully.');
        } catch (QueryException $e) {
            Log::error('Profile update failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()
                ->with('error', 'Failed to update profile due to a system error. Please try again.');
        }
    }
}
