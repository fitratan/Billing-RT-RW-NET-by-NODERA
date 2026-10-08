<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If the
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that isn't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required to keep track of your queue workers. That
    | connection should be configured in your "config/database.php" file.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server, so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'nodera-billing'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application.
    | They will be used by the Horizon process to balance the load and
    | process your queued jobs on your various Redis queues cleanly.
    |
    */

    'defaults' => [
        'supervisor-isolation' => [
            'connection' => 'redis',
            'queue' => ['isolation'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 5,
            'maxProcesses' => 20,
            'balanceMaxShift' => 3,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 20,
            'memory' => 128,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'notifications', 'telemetry'],
            'balance' => 'simple',
            'processes' => 10,
            'tries' => 3,
            'timeout' => 30,
            'memory' => 128,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-isolation' => [
                'maxProcesses' => 25,
                'balanceMaxShift' => 5,
                'balanceCooldown' => 2,
            ],
            'supervisor-default' => [
                'processes' => 15,
            ],
        ],

        'local' => [
            'supervisor-isolation' => [
                'minProcesses' => 2,
                'maxProcesses' => 5,
            ],
            'supervisor-default' => [
                'processes' => 3,
            ],
        ],
    ],
];
