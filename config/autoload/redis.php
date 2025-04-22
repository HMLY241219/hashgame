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
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => 6379,
        'db' => (int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'Redis6501' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', ''),
//        'port' => 6501, //正式环境用户这个
        'port' => 6379, //测试docker环境
        'db' => 0,
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'Redis6502' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
//        'port' => 6502, //正式环境用户这个
        'port' => 6379, //测试docker环境
        'db' => (int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'Redis5501' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => 6379, //正式环境用户这个
//        'port' => 5501, //测试docker环境
        'db' => 0,//(int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'Redis5502' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => 6379, //正式环境用户这个
//        'port' => 5502, //测试docker环境
        'db' => 0,//(int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'RedisMy6379' => [ //注意这里链接的
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => 6379, //正式环境
//        'port' => 5501, //测试docker环境
        'db' => 0,//(int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 200,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'RedisMy6379_1' => [ //注意这里链接的
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => 6379, //正式环境
//        'port' => 5501, //测试docker环境
        'db' => 1,//(int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 200,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'RedisMy6379_2' => [ //注意这里链接的
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => 6379, //正式环境
//        'port' => 5501, //测试docker环境
        'db' => 2,//(int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 200,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
    'RedisMy6379_3' => [ //注意这里链接的
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => 6379, //正式环境
//        'port' => 5501, //测试docker环境
        'db' => 3,//(int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 200,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
];
