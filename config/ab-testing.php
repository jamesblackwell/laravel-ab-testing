<?php

return [
    /*
    |--------------------------------------------------------------------------
    | A/B Testing Dashboard Route Middleware
    |--------------------------------------------------------------------------
    |
    | Define the middleware group that will be applied to the A/B testing
    | dashboard route. You can add your own authentication and authorization
    | middleware here, for example: ['web', 'auth', 'can:viewAdminDashboard'].
    |
    */
    'route_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | A/B Testing Dashboard Route Prefix
    |--------------------------------------------------------------------------
    |
    | Define the URL prefix for the A/B testing dashboard route.
    | The default is '/admin/ab'. You can change this if needed.
    |
    */
    'route_prefix' => 'admin/ab',

    /*
    |--------------------------------------------------------------------------
    | Cache Duration
    |--------------------------------------------------------------------------
    |
    | Specifies the duration (in days) for which experiment view and conversion
    | data for a specific user/scope will be cached. This prevents repeated
    | tracking for the same user within this period.
    |
    */
    'cache_duration_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Cache Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be added to all cache keys used by the A/B testing
    | package. Change this if you have potential key collisions.
    |
    */
    'cache_prefix' => '', // Example: 'myapp_ab_' or leave empty

    /*
    |--------------------------------------------------------------------------
    | Automatic ABID Handling
    |--------------------------------------------------------------------------
    |
    | When enabled, this package will automatically register middleware
    | to generate and manage a unique A/B testing identifier ('abid') using cookies.
    | This identifier is used as the default scope for tracking views and
    | conversions when a specific user or scope isn't provided.
    |
    | If you have your own system for managing unique user identifiers that
    | you want to use with Pennant, you can disable this and pass your
    | identifier explicitly to the tracking methods.
    |
    */
    'auto_abid_handling' => true,

    /*
    |--------------------------------------------------------------------------
    | ABID Cookie Configuration (Advanced)
    |--------------------------------------------------------------------------
    |
    | If 'auto_abid_handling' is true, these settings control the abid cookie.
    | Defaults are generally sensible. 'domain' and 'secure' will try to use
    | your application's session configuration by default.
    |
    */
    'abid_cookie' => [
        'minutes' => 525600, // 1 year
        'path' => '/',
        'domain' => null,   // Uses config('session.domain') by default
        'secure' => null,   // Uses config('session.secure') by default
        'httpOnly' => true,
        'sameSite' => 'Lax',  // Recommended: Lax or Strict
    ],

    /*
    |--------------------------------------------------------------------------
    | Require View Before Conversion Tracking
    |--------------------------------------------------------------------------
    |
    | If enabled (true), the `trackConversion` method will only record a
    | conversion if the same scope has previously been tracked for viewing
    | the experiment (via `trackView`). If disabled (false), conversions
    | can be tracked even if a corresponding view wasn't explicitly recorded
    | or found in the cache.
    |
    */
    'require_view_to_convert' => true,
];