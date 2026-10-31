<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default shipping zone
    |--------------------------------------------------------------------------
    |
    | The zone used when a destination city is on no zone's city list. Accepts
    | a zone `_id` or a zone name, matched against active zones. Leave empty to
    | fall back to an active zone that has no cities at all (a catch-all), or
    | to report the destination as unavailable when neither exists.
    |
    */

    'default_zone' => env('SHIPPING_DEFAULT_ZONE'),

];
