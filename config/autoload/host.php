<?php
return [
    'ip' => '172.17.0.4',  //redis地址
    'adminDomain' => \Hyperf\Support\env('URL_DOMAIN_ADMIN'),  // 客户端图片返回的地址
    'apiDomain' => \Hyperf\Support\env('URL_DOMAIN_API'),  // API服务地址
    'gameurl' => \Hyperf\Support\env('URL_DOMAIN_GAME'),  // 三方返回客户端的游戏URL
    'port0' => 6379,
    'port1' => 6501,
    'port2' => 6502,
];
