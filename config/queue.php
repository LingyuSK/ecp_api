<?php

return [
    /*
      |--------------------------------------------------------------------------
      | Default Queue Driver
      |--------------------------------------------------------------------------
      |
      | Laravel's queue API supports an assortment of back-ends via a single
      | API, giving you convenient access to each back-end using the same
      | syntax for each one. Here you may set the default queue driver.
      |
      | Supported: "sync", "database", "beanstalkd", "sqs", "redis", "null"
      |
     */

    'default' => env('QUEUE_DRIVER', 'sync'),
    /*
      |--------------------------------------------------------------------------
      | Queue Connections
      |--------------------------------------------------------------------------
      |
      | Here you may configure the connection information for each server that
      | is used by your application. A default configuration has been added
      | for each back-end shipped with Laravel. You are free to add more.
      |
     */
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'queue_ecp',
            'retry_after' => 3600,
            'timeout' => 3600,
            'read_write_timeout' => 0,
            'options' => [
                \Redis::OPT_READ_TIMEOUT => 3600,
            ],
            'tries' => 3,
            'block_for' => 90,
        ]
    ],
    /*
      |--------------------------------------------------------------------------
      | Failed Queue Jobs
      |--------------------------------------------------------------------------
      |
      | These options configure the behavior of failed queue job logging so you
      | can control which database and table are used to store the jobs that
      | have failed. You may change them to any database / table you wish.
      |
     */
    'failed' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
