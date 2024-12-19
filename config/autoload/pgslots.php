<?php
// PGSlots

return [
    'Name'             => 'A_33777WIN',//测试
//    'Name'             => 'A_33777WIN',//正式
    'OperatorToken'    => 'I-41a7dac0088a4454978a574b4ef72bea', // 运营商独有的身份识别
//    'OperatorToken'    => 'A-48dc679d-6144-4770-8142-787d87965e0e', // 运营商独有的身份识别
    'SecretKey'        => '5407388aab1245af83b11932634b5ef9', // 密码
//    'SecretKey'        => 'b07d8f92a6224f90a93ffa3c2b79b247', // 密码
    'Salt'             => '4251567cf5e04351b0d8694b19937510',//测试
//    'Salt'             => 'eb62dd5728854fbfb71bb4b5fe74a760',//正式
    'BackOfficeURL'    => 'https://www.pg-bo.me/#/login?code=A_33777WIN',//测试
//    'BackOfficeURL'    => 'https://www.pg-bo.net/#/login?code=A_33777WIN',//正式

    'Username'         => 'a33777',//测试
    'Password'         => 'VNPJNO&!',

//    'Username'         => 'a33777win',//正式
//    'Password'         => '%D!JZSPC',
    // 币种
    'currency'         => 'INR',

    'language'         => 'en',

    // 交易类型
    'cash_type'        => [
        'in'  => ['value' => 1, 'title' => '充值'],
        'out' => ['value' => 2, 'title' => '转出'],
    ],

    //请求头
    'herder' => ["Content-Type: application/x-www-form-urlencoded"],

    // 验证ip地址
    'check_ip'         => false,
    'check_ip_address' => '127.0.0.1', // TODO::正式环境需要更换地址

    // 进入PG游戏地址 TODO::正式环境需要更换地址 第一个%s=game_id ot = OperatorToken; ops = 用户标识;
    'entry_address' =>'https://m.pg-redirect.net/%s/index.html?ot=%s&ops=%s&btt=1&l=pt&f=closewebview' ,
//    'entry_address' => 'https://m.pgr-nmga.com/%s/index.html?ot=%s&ops=%s&btt=1&l=pt&f=closewebview',
    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://api.pg-bo.me/external',
//    'api_url' => 'https://api.pg-bo.co/external',



    'history_url' => "https://api.pg-bo.me/external-datagrabber",  //测试
//    'history_url' => "https://api.pg-bo.co/external-datagrabber",  //正式
];




