<?php
use function Hyperf\Support\env;
return [
    'default' => [
        'host' => env('RABBIT_HOST'),//rabbitmq服务的地址
        'port' => (int)env('RABBIT_PORT'),
        'user' => env('RABBIT_USER'),
        'password' => env('RABBIT_PASSWORD'),
        'vhost' => '/',
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
            // 尽量保持是 heartbeat 数值的两倍
            'read_write_timeout' => 6.0,
            'context' => null,
            'keepalive' => false,
            // 尽量保证每个消息的消费时间小于心跳时间
            'heartbeat' => 3,
            'close_on_destruct' => false,
        ],
    ],
    'pool2' => [

    ]
];
