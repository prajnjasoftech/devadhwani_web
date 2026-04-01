<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Panchang Data Fetch Settings
    |--------------------------------------------------------------------------
    |
    | Configure how many days of Panchang data to fetch and store.
    |
    */

    // Number of days to fetch ahead
    'fetch_days' => (int) env('PANCHANG_FETCH_DAYS', 500),

    // Default location for Panchang calculation (Thrissur, Kerala)
    'default_latitude' => (float) env('PANCHANG_DEFAULT_LATITUDE', 10.5276),
    'default_longitude' => (float) env('PANCHANG_DEFAULT_LONGITUDE', 76.2144),
];
