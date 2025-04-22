<?php
// EgSlots

return [
    'Platform' => '777bet',

    'Agent'             => '777bet_brl',//测试
//    'Agent'             => '777bet_inr',//正式


    'Hash_key'             => '3h4tF5QdYbTypaF4Mgx5G1DjgChY',//测试
//    'Hash_key'             => 'pnus9CeVZEmrY7LrNqY2eB0',//正式


    // 币种
    //'currency'         => 'INR',
    'currency'         => 'BRL',


    'language'         => 'en',  //英语
//    'language'         => 'pt-BR',  //葡萄牙语

    //请求头
    'herder' => ["Content-Type: application/x-www-form-urlencoded"],


    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://open-beta.egslot.cc/tx/api/v1', //测试
//    'api_url' => ' https://tx.api.egslot.cc/api/v1',

    //http://tadagaming.com/plusplayer/PlusTrial/{gameId}/{lang}
    'fee_entry_address1' => 'https://d29juml4m9n88c.cloudfront.net/games/slot/%s/?lang=%s&curr=usd&hidefps=true', //试玩链接  &useIFrame=true&hideTxID=true
    'fee_entry_address2' => 'https://d29juml4m9n88c.cloudfront.net/games/crash/%s/?lang=%s&curr=usd', //试玩链接 &useIFrame=true

];





