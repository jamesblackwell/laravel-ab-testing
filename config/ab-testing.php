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
];