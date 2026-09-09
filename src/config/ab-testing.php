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

    'routes' => [
        // Set false to register no package routes (host app takes over).
        'enabled' => true,

        // Dashboard pages + dashboard-owned management actions (create,
        // edit, delete, toggle, primary metric).
        // SECURITY: the default is UNAUTHENTICATED (kept for backwards
        // compatibility). Production apps must add their own auth here,
        // e.g. ['web', 'auth', 'can:manage-experiments'], or gate the
        // /ab-testing/dashboard* paths in host middleware.
        'dashboard_middleware' => ['web'],

        // Open tracking endpoints: POST /track, POST /variant,
        // GET /variant/{experiment}, POST /register-debug.
        'api_middleware' => ['api'],

        // Read-only dashboard-polling endpoints (results, stats,
        // recent-activity, chart-data). null => inherit dashboard_middleware.
        'stats_middleware' => null,
    ],

    // Opt-in: also requires app.debug. Injects the floating debug panel
    // middleware into the host's web group.
    'debug_middleware' => env('AB_TESTING_DEBUG_MIDDLEWARE', false),

    'cookie' => [
        // null = mirror request()->isSecure().
        'secure' => null,
        'same_site' => 'Lax',
    ],

    // Reserved: table names are not yet configurable (models hardcode them).
    'database' => [
        'experiments_table' => 'ab_experiments',
        'assignments_table' => 'ab_user_assignments',
        'events_table' => 'ab_events',
    ],

    // Refuse assignments and events for crawler user agents (see
    // Support\BotDetector). On by default: bots inflate every arm of every
    // experiment with cookie-less phantom participants.
    'bot_filtering' => env('AB_TESTING_BOT_FILTERING', true),

    'session_key' => 'ab_user_id',

    'tracking' => [
        'enabled' => true,
        'queue' => false, // Reserved: not yet implemented
    ],
];
