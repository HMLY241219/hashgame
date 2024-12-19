<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use function Hyperf\Support\env;

return [
    'default' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', '127.0.0.1'),

        /*'read' => [
            'host' => [env('DB_SLAVE_HOST','127.0.0.1')],
            'password' => env('DB_SLAVE_PASSWORD','35b07184f713f185'),
        ],
        'write' => [
            'host' => [env('DB_HOST', '127.0.0.1')],
        ],
        'sticky'    => true,*/

        'database' => env('DB_DATABASE', 'mqtest'),
        'port' => env('DB_PORT', 3306),
        'username' => env('DB_USERNAME', 'mqtest'),
        'password' => env('DB_PASSWORD', 'mqtest123'),
        'charset' => env('DB_CHARSET', 'utf8'),
        'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
        'prefix' => env('DB_PREFIX', 'br_'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Model',
                'force_casts' => true,
                'inheritance' => 'Model',
            ],
        ],
    ],
    'lzmj' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        //'host' => '1.13.81.132',
        'host' => env('DB_HOST', '127.0.0.1'),
        //'database' => 'csf2data',
        'database' => env('DB_DATABASE', 'mqtest'),
        'port' => env('DB_PORT', 3306),
        'username' => env('DB_USERNAME', 'mqtest'),
        //'password' => 'b079f8525b62cc60',
        'password' => env('DB_PASSWORD', 'mqtest123'),
        'charset' => env('DB_CHARSET', 'utf8'),
        'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
        'prefix' => 'lzmj_',
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Model',
                'force_casts' => true,
                'inheritance' => 'Model',
            ],
        ],
    ],
    'readConfig' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        //'host' => env('DB_SLAVE_HOST','172.31.35.150'),

        'read' => [
            'host' => [env('DB_SLAVE_HOST','127.0.0.1')],
            'password' => env('DB_SLAVE_PASSWORD','35b07184f713f185'),
        ],
        'write' => [
            'host' => [env('DB_HOST', '1.13.81.132')],
        ],
        'sticky'    => true,

        'database' => env('DB_DATABASE', 'mqtest'),
        'port' => env('DB_PORT', 3306),
        'username' => env('DB_USERNAME', 'mqtest'),
        'password' => env('DB_SLAVE_PASSWORD','HUEB@WBasdf@3od@'),
        'charset' => env('DB_CHARSET', 'utf8'),
        'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
        'prefix' => env('DB_PREFIX', 'br_'),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Model',
                'force_casts' => true,
                'inheritance' => 'Model',
            ],
        ],
    ],
];
