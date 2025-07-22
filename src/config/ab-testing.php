<?php

return [
    /*
    |--------------------------------------------------------------------------
    | A/B Testing Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'prefix' => 'ab_test:',
        'ttl' => 3600, // 1 hour
    ],

    'database' => [
        'experiments_table' => 'ab_experiments',
        'assignments_table' => 'ab_user_assignments',
        'events_table' => 'ab_events',
    ],

    'session_key' => 'ab_user_id',

    'tracking' => [
        'enabled' => true,
        'queue' => false, // Set to true to queue tracking events
    ],
];
