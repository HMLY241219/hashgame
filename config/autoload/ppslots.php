<?php
// PpSlots

return [
    'Name'             => '9fVIP.com',//测试
//    'Name'             => '',//正式


    'secureLogin'             => 'pp_9fvipcom',//测试
//    'secureLogin'             => '',//正式


    'ProviderId'             => 'PragmaticPlay',//测试
//    'ProviderId'             => '',//正式

    'SecretKey'        => 'D2Aa3eF30bD1491a', // 测试密码
//    'SecretKey'        => '', // 密码


    // 币种
    'currency'         => 'BRL',


    'language'         => 'en',  //英语
//    'language'         => 'pt',  //葡萄牙语


    //请求头
    'herder' => ["Content-Type: application/x-www-form-urlencoded"],


    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://api.prerelease-env.biz', //测试
//    'api_url' => 'https://api-br1.pragmaticplay.net',


    'fee_entry_address' => 'https://demogamesfree.pragmaticplay.net/gs2c/openGame.do?lang=pt&cur=BRL&gameSymbol=%s&lobbyURL=http://124.221.1.74', //测试试玩链接
//    'fee_entry_address' => 'https://demogamesfree.pragmaticplay.net/gs2c/openGame.do?lang=pt&cur=BRL&gameSymbol=%s&lobbyURL=http://124.221.1.74', //正式试玩链接

    'history_url' => "https://api.pg-bo.me/external-datagrabber",  //测试
//    'history_url' => "",  //正式
];





