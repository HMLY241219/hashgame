<?php
return [
    'operatorID'             => '10927001',//测试
//    'operatorID'             => '',//正式

    'appSecret'        => '6b53381a-6250-4987-9705-b852d6f402ec', // 测试密码
//    'appSecret'        => '', // 密码


    // 币种
    'currency'         => 'INR', //印度
//    'currency'         => 'BRL', //巴西


    'language'         => 'en',  //英语
//    'language'         => 'pt',  //葡萄牙语
//    'language'         => 'in',  //印地文


    //请求头
    'herder' => ["Content-Type: application/x-www-form-urlencoded"],


    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://uat-nc-ugs-weop.ufweg.com', //测试
//    'api_url' => '',


    // 游戏启动API
    'game_url' => 'https://playint.tableslive.com/auth', //测试
//    'game_url' => 'https://play.livetables.io/auth', //正式
];


