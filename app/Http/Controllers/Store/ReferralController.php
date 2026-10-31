<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Affiliate\AffiliateService;
use Illuminate\Http\RedirectResponse;

class ReferralController extends Controller
{
    public function __construct(protected AffiliateService $affiliateService) {}

    /**
     * Record a referral click, store the attribution cookie and send the
     * visitor to the storefront.
     */
    public function go(string $code): RedirectResponse
    {
        $redirect = redirect()->route('home');

        if (! affiliate_enabled()) {
            return $redirect;
        }

        $affiliate = $this->affiliateService->findActiveByCode($code);

        if (! $affiliate) {
            return $redirect;
        }

        $this->affiliateService->recordClick($affiliate);

        $days = max(1, affiliate_cookie_days());

        return $redirect->withCookie(cookie('ref_code', $affiliate->code, $days * 24 * 60));
    }
}
