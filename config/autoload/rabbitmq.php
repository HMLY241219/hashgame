<?php
// 示例配置文件
return [
    # 玩三方游戏记录队列
    'slots_queue' => [
        'exchange_name' => 'slots_exchange',
        'exchange_type'=>'DIRECT',#直连模式
        'queue_name' => 'slots_queue',
        'route_key' => 'slots_roteking',
        'consumer_tag' => 'slots'
    ],
    'test_queue' => [
        'exchange_name' => 'test_exchange',
        'exchange_type'=>'FANOUT',#直连模式
        'queue_name' => 'test_queue',
        'route_key' => 'test_roteking',
        'consumer_tag' => 'test'
    ],
];