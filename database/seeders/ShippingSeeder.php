<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCity;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        // Create shipping zones
        $domestic = ShippingZone::create([
            'name' => 'Domestic',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $eu = ShippingZone::create([
            'name' => 'European Union',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $international = ShippingZone::create([
            'name' => 'International',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Add cities to Domestic zone
        $ukCities = [
            'London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow',
            'Sheffield', 'Bradford', 'Edinburgh', 'Liverpool', 'Bristol',
        ];

        foreach ($ukCities as $city) {
            ShippingZoneCity::create([
                'shipping_zone_id' => $domestic->_id,
                'city' => $city,
            ]);
        }

        // Add cities to EU zone
        $euCities = [
            'Paris', 'Berlin', 'Madrid', 'Rome', 'Amsterdam',
            'Brussels', 'Vienna', 'Prague', 'Warsaw', 'Budapest',
        ];

        foreach ($euCities as $city) {
            ShippingZoneCity::create([
                'shipping_zone_id' => $eu->_id,
                'city' => $city,
            ]);
        }

        // Create shipping methods
        $standard = ShippingMethod::create([
            'name' => 'Standard Delivery',
            'description' => '3-5 business days delivery',
            'base_cost' => 4.99,
            'per_kg_cost' => 0.50,
            'estimated_days' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $express = ShippingMethod::create([
            'name' => 'Express Delivery',
            'description' => '1-2 business days delivery',
            'base_cost' => 9.99,
            'per_kg_cost' => 1.00,
            'estimated_days' => 1,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $premium = ShippingMethod::create([
            'name' => 'Premium Delivery',
            'description' => 'Next business day delivery with tracking',
            'base_cost' => 14.99,
            'per_kg_cost' => 1.50,
            'estimated_days' => 1,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Create shipping rules for Domestic zone
        // Standard shipping for Domestic
        ShippingRule::create([
            'shipping_zone_id' => $domestic->_id,
            'shipping_method_id' => $standard->_id,
            'price' => 4.99,
            'free_shipping_threshold' => 50.00,
            'is_active' => true,
        ]);

        // Express shipping for Domestic
        ShippingRule::create([
            'shipping_zone_id' => $domestic->_id,
            'shipping_method_id' => $express->_id,
            'price' => 9.99,
            'free_shipping_threshold' => 100.00,
            'is_active' => true,
        ]);

        // Premium shipping for Domestic
        ShippingRule::create([
            'shipping_zone_id' => $domestic->_id,
            'shipping_method_id' => $premium->_id,
            'price' => 14.99,
            'free_shipping_threshold' => 150.00,
            'is_active' => true,
        ]);

        // Create shipping rules for EU zone
        // Standard shipping for EU
        ShippingRule::create([
            'shipping_zone_id' => $eu->_id,
            'shipping_method_id' => $standard->_id,
            'price' => 12.99,
            'free_shipping_threshold' => 100.00,
            'is_active' => true,
        ]);

        // Express shipping for EU
        ShippingRule::create([
            'shipping_zone_id' => $eu->_id,
            'shipping_method_id' => $express->_id,
            'price' => 19.99,
            'free_shipping_threshold' => 150.00,
            'is_active' => true,
        ]);

        // Premium shipping for EU
        ShippingRule::create([
            'shipping_zone_id' => $eu->_id,
            'shipping_method_id' => $premium->_id,
            'price' => 24.99,
            'free_shipping_threshold' => 200.00,
            'is_active' => true,
        ]);

        // Create shipping rules for International zone
        // Standard shipping for International
        ShippingRule::create([
            'shipping_zone_id' => $international->_id,
            'shipping_method_id' => $standard->_id,
            'price' => 24.99,
            'free_shipping_threshold' => 200.00,
            'is_active' => true,
        ]);

        // Express shipping for International
        ShippingRule::create([
            'shipping_zone_id' => $international->_id,
            'shipping_method_id' => $express->_id,
            'price' => 34.99,
            'free_shipping_threshold' => 250.00,
            'is_active' => true,
        ]);

        // Premium shipping for International
        ShippingRule::create([
            'shipping_zone_id' => $international->_id,
            'shipping_method_id' => $premium->_id,
            'price' => 44.99,
            'free_shipping_threshold' => 300.00,
            'is_active' => true,
        ]);
    }
}
