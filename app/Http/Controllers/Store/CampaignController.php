<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\DiscountCampaign;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = DiscountCampaign::active()
            ->with(['products' => function ($q) {
                $q->where('is_active', true)->with('images');
            }])
            ->orderBy('starts_at', 'desc')
            ->get();

        return view('store.campaigns.index', compact('campaigns'));
    }

    public function show(DiscountCampaign $campaign)
    {
        $campaign->load(['products' => function ($q) {
            $q->where('is_active', true)->with('images');
        }]);

        return view('store.campaigns.show', compact('campaign'));
    }
}
