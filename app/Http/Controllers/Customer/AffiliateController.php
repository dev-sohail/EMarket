<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Affiliate\AffiliateService;
use Illuminate\Http\RedirectResponse;

class AffiliateController extends Controller
{
    public function __construct(protected AffiliateService $affiliateService) {}

    public function index()
    {
        $customer = auth()->user()->customer;
        $programEnabled = affiliate_enabled();
        $affiliate = $customer ? $this->affiliateService->findByCustomer($customer) : null;
        $commissions = null;
        $stats = null;

        if ($affiliate && $affiliate->status === 'active') {
            $commissions = $this->affiliateService->getCustomerCommissions($affiliate);
            $stats = $this->affiliateService->customerStats($affiliate);
        }

        return view('customer.affiliate.index', compact(
            'customer',
            'programEnabled',
            'affiliate',
            'commissions',
            'stats'
        ));
    }

    public function apply(): RedirectResponse
    {
        if (! affiliate_enabled()) {
            return back()->with('error', 'The affiliate program is currently unavailable.');
        }

        $customer = auth()->user()->customer;

        if (! $customer) {
            return back()->with('error', 'Only customers can join the affiliate program.');
        }

        if ($this->affiliateService->findByCustomer($customer)) {
            return back()->with('error', 'You have already applied to the affiliate program.');
        }

        $this->affiliateService->enroll($customer, null, 'pending');

        return redirect()->route('customer.affiliate')
            ->with('success', 'Application received! We will review and approve your account soon.');
    }
}
