<?php
// TdSlots
return [
    'AgentId'             => '777BETBET_Seamless',//测试
//    'AgentId'             => '777BETBET_Seamless',//正式


    'AgentKey'             => 'dc6651e4ae08a64366651a71b7346ea0287e4d3f',//测试
//    'AgentKey'             => 'd5c27b878edd62bccbd5b17dfe5c89ae0980e34c',//正式


    // 币种
    'currency'         => 'INR',


    'language'         => 'en-US',  //英语
//    'language'         => 'pt-BR',  //葡萄牙语

    //请求头
    'herder' => ["Content-Type: application/x-www-form-urlencoded"],


    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://uat-wb-api-2.kijl788du.com/api1', //测试
//    'api_url' => 'https://wb-api-2.zcchb1ap.com/api1', //正式

    //http://jiligames.com/plusplayer/PlusTrial/{gameId}/{lang}
    'fee_entry_address' => 'http://jiligames.com/plusplayer/PlusTrial/%s/%s?HomeUrl=http://124.221.1.74', //测试试玩链接
//    'fee_entry_address' => 'https://jiligames.com/plusplayer/PlusTrial/%s/%s?HomeUrl=http://124.221.1.74', //正式试玩链接


];





