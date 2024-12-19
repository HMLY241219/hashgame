<?php
// TdSlots
return [
    'AgentId'             => '777BETT_Seamless',//测试
    //'AgentId'             => '777BETT_BRL',//测试
//    'AgentId'             => '777BETT_BRL',//正式


    'AgentKey'             => '0f3f0232fc09117d33da5aea0746ea99a529a72b',//测试
    //'AgentKey'             => '12b74cc057e607e4445761a8dccab4c4598b9bad',//测试
//    'AgentKey'             => 'f6a6590b997399cf432f74709a07190daa9dab79',//正式


    // 币种
    'currency'         => 'BRL',


    'language'         => 'en-US',  //英语
//    'language'         => 'pt-BR',  //葡萄牙语

    //请求头
    'herder' => ["Content-Type: application/x-www-form-urlencoded"],


    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://uat-wb-api.tadagaming.com/api1', //测试
//    'api_url' => 'https://wb-api.tadagaming.com/api1',

    //http://tadagaming.com/plusplayer/PlusTrial/{gameId}/{lang}
    'fee_entry_address' => 'https://tadagaming.com/plusplayer/PlusTrial/%s/%s?HomeUrl=http://124.221.1.74', //测试试玩链接
//    'fee_entry_address' => 'https://tadagaming.com/plusplayer/PlusTrial/%s/%s?HomeUrl=http://124.221.1.74', //正式试玩链接


];





