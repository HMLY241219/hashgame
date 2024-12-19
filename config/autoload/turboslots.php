<?php
// TdSlots
return [
    'CID'             => '3377win',//测试
//    'CID'             => '3377win',//正式


    'TOKEN'             => 'ibgn13osfdwu9jkdzru2xu8f6elel8r2o5vrncnl',//测试
//    'TOKEN'             => 'ibgn13osfdwu9jkdzru2xu8f6elel8r2o5vrncnl',//正式


    // 币种
    'currency'         => 'inr',


    'language'         => 'en',  //英语
//    'language'         => 'pt-BR',  //葡萄牙语


    //请求头
    'herder' => ["Content-Type: application/x-www-form-urlencoded"],


    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://%s-stage.turbogames.io/?locale=%s&cid=%s&token=%s&sub_partner_id=%s&returnUrl=hidden', //测试
//    'api_url' => 'https://%s.turbodiscovery.xyz/?locale=%s&cid=%s&token=%s&sub_partner_id=%s&returnUrl=hidden', //正式

    //http://jiligames.com/plusplayer/PlusTrial/{gameId}/{lang}
    'fee_entry_address' => 'https://%s.turbodiscovery.xyz/?cid =%s&token=%s&demo=true', //测试试玩链接
//    'fee_entry_address' => 'https://demo.spribe.io/launch', //正式试玩链接


];





