<?php
use function Hyperf\Support\env;
return [
    'default' => [
        'host' => env('RABBIT_HOST'),//rabbitmq服务的地址
        'port' => (int)env('RABBIT_PORT'),
        'user' => env('RABBIT_USER'),
        'password' => env('RABBIT_PASSWORD'),
        'vhost' => '/',
        'options' => [
            'delivery_mode' => 2,
        ],
        'concurrent' => [
            'limit' => 1,
        ],
        'pool' => [
            'connections' => 1,
        ],
        'params' => [
            'insist' => false,
            'login_method' => 'AMQPLAIN',
            'login_response' => null,
            'locale' => 'en_US',
            'connection_timeout' => 3.0,
            'read_write_timeout' => 6.0,
            'context' => null,
            'keepalive' => false,
            'heartbeat' => 3,
            'close_on_destruct' => false,
        ],
    ],
    'pool2' => [

    ]
];
