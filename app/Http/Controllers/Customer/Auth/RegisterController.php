<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\RegisterRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

class RegisterController extends Controller
{
    public function showForm()
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('customer.dashboard');
        }

        return view('customer.auth.register');
    }

    /**
     * Register a customer, reusing an existing order history where there is one.
     *
     * Both `users` and `customers` soft-delete, and both carry a PLAIN unique
     * index on their business key, so a trashed row still occupies its email
     * address. Every probe below therefore runs through `withTrashed()`: the old
     * soft-delete-blind `Model::where(…)` returned null, the application
     * concluded the address was free, and the following `create()` died on the
     * index as a 500 rather than a validation message.
     *
     * What happens once a trashed row is found differs per table, and the
     * difference is deliberate rather than an inconsistency:
     *
     *  - `customers` is reused and RESTORED, which is the same policy
     *    `CheckoutService::resolveGuestCustomer()` already applies to a returning
     *    guest. The row is the customer's own order history, it holds no
     *    credentials, and restoring it is what links the history the customer is
     *    about to see.
     *  - `users` is refused. A user row carries `roles`, `is_active` and
     *    `email_verified_at`; silently resurrecting a deleted account from a
     *    public self-registration form would hand whoever knows the address the
     *    deleted account's role assignments, and would undo an administrator's
     *    deliberate action without anyone deciding to. Refusing is also what the
     *    `unique:users,email` rules elsewhere in the application already do,
     *    because those rules query through the query builder and therefore see
     *    trashed rows too.
     */
    public function register(RegisterRequest $request)
    {
        try {
            $email = $request->input('email');

            $existingUser = User::withTrashed()->where('email', $email)->first();

            if ($existingUser && $existingUser->trashed()) {
                return back()->withInput()
                    ->with('error', 'This email address belongs to a deactivated account. Please contact support to restore it.');
            }

            $existingCustomer = Customer::withTrashed()->where('email', $email)->first();

            if ($existingCustomer) {
                if ($existingCustomer->user_id) {
                    return back()->withInput()
                        ->with('error', 'An account with this email already exists. Please sign in instead.');
                }

                if ($existingCustomer->trashed()) {
                    $existingCustomer->restore();
                }

                if ($existingUser) {
                    $existingCustomer->update([
                        'user_id' => $existingUser->getKey(),
                        'first_name' => $request->input('first_name'),
                        'last_name' => $request->input('last_name'),
                        'phone' => $request->input('phone'),
                        'is_active' => true,
                    ]);

                    return redirect()->route('login')
                        ->with('success', 'We found an existing account with this email. Please sign in to link your order history.');
                }

                // 'password' is not fillable, and User::create() would INSERT
                // before the password was ever assigned — users.password is NOT
                // NULL, so that fails. Build the model, assign the password, then
                // save once.
                $user = new User([
                    'name' => $request->input('first_name').' '.$request->input('last_name'),
                    'email' => $email,
                    'phone' => $request->input('phone'),
                ]);

                $user->password = $request->input('password');
                $user->save();

                $existingCustomer->update([
                    'user_id' => $user->getKey(),
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'phone' => $request->input('phone'),
                    'is_active' => true,
                ]);

                Event::dispatch(new Registered($user));

                Auth::guard('web')->login($user);

                return redirect()->route('customer.dashboard')
                    ->with('success', 'Welcome back! We found your previous order history and linked it to your new account.');
            }

            // 'password' is not fillable and users.password is NOT NULL, so the
            // model is built and saved once rather than through create().
            $user = new User([
                'name' => $request->input('first_name').' '.$request->input('last_name'),
                'email' => $email,
                'phone' => $request->input('phone'),
            ]);

            $user->password = $request->input('password');
            $user->save();

            Customer::create([
                'user_id' => $user->getKey(),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'email' => $email,
                'phone' => $request->input('phone'),
                'is_active' => true,
            ]);

            Event::dispatch(new Registered($user));

            Auth::guard('web')->login($user);

            return redirect()->route('customer.dashboard')
                ->with('success', 'Registration successful. Welcome!');
        } catch (\Exception $e) {
            // Never echo $e->getMessage() on a pre-auth endpoint: this is a
            // catch-all over User::create()/Customer::create(), so a
            // QueryException on a unique index renders the failed statement and
            // its bound values into the flash message shown on the public signup
            // form. report() to the log, show static copy.
            report($e);

            return back()->withInput()
                ->with('error', 'Registration failed. Please try again or contact support if the problem persists.');
        }
    }

    public function verify(Request $request, string $id, string $hash)
    {
        if (! URL::hasValidSignature($request)) {
            return redirect()->route('login')
                ->with('error', 'Invalid or expired verification link.');
        }

        $user = User::where('_id', $id)
            ->where('email_verified_at', null)
            ->first();

        if (! $user || ! hash_equals(sha1($user->email), $hash)) {
            return redirect()->route('login')
                ->with('error', 'Invalid verification link.');
        }

        // forceFill, not update(): 'email_verified_at' was removed from
        // User::$fillable so a mass-assignment path cannot forge verification.
        $user->forceFill(['email_verified_at' => now()])->save();

        return redirect()->route('login')
            ->with('success', 'Email verified successfully. You can now login.');
    }
}
