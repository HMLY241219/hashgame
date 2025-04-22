<?php
// TdSlots
return [
    'Dc'             => 'BET7',//测试
//    'Dc'             => 'BET7',//正式

    'Iv'            => '154bb501b6babf55',//测试
    //'Iv'            => 'b5c58ab46b6b77ae',//正式

    'Key'             => 'e25b0af075725ca8',//测试
//    'Key'             => '565d2ef6e953f838',//正式


    // 币种
    'currency'         => 'RS',


    'language'         => 'en',  //英语
//    'language'         => 'pt-BR',  //葡萄牙语

    'parent'           => 'bet7inrag',//测试
//    'parent'           => 'bet7inrag',//正式

    //请求头
    'herder' => ["Content-Type: application/x-www-form-urlencoded"],


    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://api.jdb711.com/apiRequest.do', //测试
//    'api_url' => 'https://api.jdb1688.net/apiRequest.do', //正式

    //http://jiligames.com/plusplayer/PlusTrial/{gameId}/{lang}
    'fee_entry_address' => 'http://jiligames.com/plusplayer/PlusTrial/%s/%s?HomeUrl=http://124.221.1.74', //测试试玩链接
//    'fee_entry_address' => 'https://jiligames.com/plusplayer/PlusTrial/%s/%s?HomeUrl=http://124.221.1.74', //正式试玩链接


];





