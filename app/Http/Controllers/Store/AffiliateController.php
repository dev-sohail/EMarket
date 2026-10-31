<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Affiliate\AffiliateService;

class AffiliateController extends Controller
{
    public function __construct(protected AffiliateService $affiliateService) {}

    /**
     * Public affiliate program landing page with details and terms.
     */
    public function landing()
    {
        $state = 'guest';
        $affiliate = null;

        if (! affiliate_enabled()) {
            $state = 'disabled';
        } elseif ($user = auth()->user()) {
            $customer = $user->customer;
            $affiliate = $customer ? $this->affiliateService->findByCustomer($customer) : null;

            $state = match (true) {
                $affiliate === null => 'eligible',
                $affiliate->status === 'pending' => 'pending',
                $affiliate->status === 'suspended' => 'suspended',
                default => 'active',
            };
        }

        return view('store.affiliate', compact('state', 'affiliate'));
    }
}
