<?php
// sbsslots

return [

    'vendor_id' => 'eks8y7tj3b',//测试环境vendor_id
//    'vendor_id' => 'eks8y7tj3b', //正式环境vendor_id

    'operatorId' => '777betSW',//测试环境operatorId
//    'operatorId' => '777betSW',//正式环境operatorId



    //由于三方测试环境和正式环境UID不能一样,测试环境加一个下缀
    'uid_suffix' => '_test',//测试环境UID
//    'uid_suffix' => '',//正式环境三方UID




    //子账户前缀，子账户才需要填写
    'uid_prefix' => '', //用户前缀

    // 币种
    'currency'         => 20, // 测试环境
//    'currency'         => 61, //印度正式环境


    'oddstype' => 3,//欧洲盘 巴西、印度


    'mintransfer' => 0,//会员单笔最小限制转账金额最小限制转账金额必须大于等于0.

    'maxtransfer' => 99999999,//会员单笔最大限制转账金额.最高可设定 9,999,999,999





    'language'         => 'pt',

    // 交易类型
    'cash_type'        => [
        'in'  => ['value' => 1, 'title' => '充值'],
        'out' => ['value' => 2, 'title' => '转出'],
    ],



    'api_url' => 'https://m2t5tsa.bw6688.com/api', //测试环境请求API
//    'api_url' => 'https://m2t5api.bw6688.com/api',//正式环境请求



];





