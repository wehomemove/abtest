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

    /*
    | Assignment policy (v1.7). Every flag defaults to the v1.6 behaviour so
    | existing installs change nothing until they opt in.
    */
    'assignment' => [
        // Honour ab_experiments.traffic_allocation: users hashed outside the
        // allocation resolve to 'control' and are NOT recorded as participants.
        // false (v1.6): the column is stored and displayed but never enforced.
        'enforce_traffic_allocation' => false,

        // Only create NEW assignments while status === 'running' and now() is
        // inside start_date/end_date (the Experiment::isActive() rule). An
        // existing assignment always wins, so pausing or completing never
        // moves a returning user. false (v1.6): only is_active is checked.
        'enforce_schedule' => false,

        // After 20 assignments, steer new users into the most under-represented
        // arm whenever it sits >= 5pp below target. Balances the split but
        // makes bucketing depend on global state rather than the user id.
        // false: pure deterministic md5 bucketing. true (v1.6): adaptive.
        'adaptive_allocation' => true,
    ],
];
