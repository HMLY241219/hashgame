<?php
declare(strict_types=1);
namespace App\Common\pay;

use Hyperf\Di\Annotation\Inject;
use App\Common\Common;
use App\Common\Guzzle;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;
use  App\Common\Curl;
use function Swoole\Coroutine\Http\get;
use function Symfony\Component\Translation\t;

class Pay{

    #[Inject]
    protected LoggerInterface $logger; //存储日志
    #[Inject]
    protected Common $Common;
    #[Inject]
    protected Guzzle $guzzle;

    //rr_pay
    private string $rrpay_url        = "https://top.adkjk.in/rpay-api/order/submit";                 //下单地址

    private string $rrpay_merchantNo = "999"; //测试
//    private string $rrpay_merchantNo = "1103"; //正式
    private string $rrpay_notifyUrl  = "http://124.221.1.74:9502/Order/rrpayNotify";  //回调地址
    private string $rrpay_Key        = "abc#123!";//测试
//    private string $rrpay_Key        = "7YzlqZ6xhpkhj5B0";//正式



    //ser_pay
    private string $serpay_url = "https://api.metagopayments.com/cashier/pay.ac";//下单地址
    private string $serpay_key = "353FD9914A2C8C619FE6F2E1B8CD45E0";//商户密钥的值
    private string $serpay_orgNo = "8240800048";//机构号
    private string $serpay_mchid = "24082600000048";//商户编码
    private string $serpay_backUrl = "http://124.221.1.74:9502/Order/serpayNotify";//回调地址




    //sertwo_pay 唤醒
    private string $sertwopay_url = "https://api.metagopayments.com/cashier/pay.ac";//下单地址
    private string $sertwopay_key = "C32FA37CC0421E6231E6E7EDC8C26C5F";//商户密钥的值
    private string $sertwopay_orgNo = "8241100128";//机构号
    private string $sertwopay_mchid = "24110100000126";//商户编码
    private string $sertwopay_backUrl = "http://124.221.1.74:9502/Order/serpayNotify";//回调地址



    //tm_pay
    private string $tmpay_url = "https://beespay.store/gateway/v1/pay/create";          //下单地址

    private string $tmpay_merchantNo       = "1M6S80Q0T1";                                   //商户号测试
//    private string $tmpay_merchantNo       = "1HHF923W30";                                   //商户号正式
    private string $tmpay_appid   = "M1E3123051824F36ABFBB75250B0A0DC";  //app_id 测试环境
//    private string $tmpay_appid   = "37B6BD129BE9447E9070CFF6D69E956F";  //app_id 正式环境

    private string $tmpay_Key    = 'KM@8gfWwK0WkZ1#!ux6svzV1ADV:Ftz!*9%A%yW@VZjG=lYMxyIjTB0fw0u#cz0l8RX7ir1#J7fjwop%XJ%f:cZUr$:g041sx^Se3f$X*?tNv3*1DFbT7%R39VTynS8D';//测试
//    private string $tmpay_Key    = 'KZd!m7L?eL:A7lCH#vQHOCGC:AAUMSJ:$zX^gOREKpNuDWyl^h*ZU?LWOrbBYK7n00avVwwA=hAe~ZOCW7ll8gCY!l@5sSwRr9vQH8Af&Xd5NH5J:VNsgqK_bW!k1fdD'; //正式
    private string $tmpay_notifyUrl   = "http://124.221.1.74:9502/Order/tmpayNotify";




    //waka_pay
    private string $wakapay_url = "https://wkpluss.com/gateway/";          //下单地址
    private string $wakapay_merchantNo       = "8890964";                        //商户号
    private string $wakapay_Key    = "e10418274f190d3fcfba3d58556978e9";  //商户秘钥
    private string $wakapay_notifyUrl   = "http://124.221.1.74:9502/Order/wakapayNotify";


    //fun_pay
    private string $funpay_url = "https://api.stimulatepay.com/api/payment/v1/create";          //下单地址
    private string $funpay_merchantNo       = "pcI98HftC1SLR8qj";                        //商户号
    private string $funpayKey    = "5e48c9b9170f493185f8c9a96c45b487";  //商户秘钥
    private string $funpay_notifyUrl   = "http://124.221.1.74:9502/Order/funpayNotify";



    //go_pay
    private string $gopay_url = "https://rummylotus.online/api/recharge/create";          //下单地址
    private string $gopay_merchantNo       = "2023100015";                        //商户号
    private string $gopayKey    = "d65cc4596f20499ea4af91a998321f5c";  //商户秘钥
    private string $gopay_notifyUrl   = "http://124.221.1.74:9502/Order/gopayNotify";



    //eanishop_pay
    private string $eanishoppay_url = "https://gateway-sandbox.eanishop.com/pg/v2/payment/create";          //测试
//    private string $eanishoppay_url = "https://gateway.eanishop.com/pg/v2/payment/create";          //正式
    private string $eanishoppayAppId    = "170baa8e418a47bf84464c07d1819d73";
    private string $eanishoppayAppSecret    = "e77e54a0ef3642298cad797bc772602c";
    private string $eanishoppay_notifyUrl   = "http://124.221.1.74:9502/Order/eanishoppayNotify";


    //24hrpay
    private string $hr24pay_url = "http://test-pay.24hrpay.vip/api/unified/collection/create";          //测试
//    private string $hr24pay_url = "https://pay.24hrpay.vip/api/unified/collection/create";          //正式
    private string $hr24pay_mchId    = "20000170"; //测试
//    private string $hr24pay_mchId    = "50000567"; //正式
    private string $hr24pay_appKey    = "QG8OZF7SDXVWEEOMQ6R9OPEWMJLLUUXLAA2ZDHHMI3KUBUYYFMGGDRPC4FEXMFX93RIJJYQTPJRTCWNXREB2IU4PCW56S5FW7ZVQ5RHL9J5N407ZG4SIUG22ME5IBITJ"; //测试
//    private string $hr24pay_appKey    = "CABS32R2LZEGAQWRUMM1N7OLPHJ512N1Z9EL7BHYT1ZBQLHYNE1SXW8WF0LBO0XYFQLOT1VLHK0YUWO4T89G8QJC12SL8UCGSOKPAXZZFJTLZWVJ82K73UR6EUKLJSPK"; //正式
    private string $hr24pay_notifyUrl   = "http://124.221.1.74:9502/Order/hr24payNotify";




    //ai_pay
    private string $aipay_url = "https://aipay.cool/gateway/";          //下单地址
    private string $aipay_merchantNo       = "862360";                        //商户号
    private string $aipay_Key    = "517faa1fd5cbd6b13541b90f97fe9625";  //商户秘钥
    private string $aipay_notifyUrl   = "http://124.221.1.74:9502/Order/aipayNotify";



    //x_pay
    private string $xpay_url = "https://pay.xpay.wang/api/pay/unifiedOrder";                                   //下单地址
    private string $xpay_merchantNo       = "M1669706824";                                   //下单地址-gesang
    private string $xpay_appId       = "6385b4487bd27c0dd2ccb6fb";                                   //下单地址-gesang
    private string $xpay_notifyUrl   = "http://124.221.1.74:9502/Order/xpayNotify";               //回调地址
    private string $xpay_Key    = "Tc5YVPZKwck6esXi697jxMDTjvc7ojaWOluTFaiq4kvuO7rmvLIAmSqDQCanXu5NuItfanr4PPEs4bNmkXqTsRMiPiz6vfJI2JNj1AEDU7guTdsQ9IQ4fmFVRfJIoOSz";


    //lets_pay
    private string $letspay_url = "http://api.letspayfast.com/apipay";                                   //下单地址
    private string $letspay_mchId       = "722931450180";
    private string $letspay_notifyUrl   = "http://124.221.1.74:9502/Order/letspayNotify";               //回调地址
    private string $letspay_Key    = "YFYCHSXAY6DPNS5A0NUYGDD66NSUFXFCUIPCLTXWNQJF0HG5V5IMQKC7AIC071HXZZERDBOBLPIOQUUROFA2AIQKUZDWJTP3N06ZKJPXWJSBJRFORFLGDY5MP3U7VPHE";



    //letstwo_pay  lets_pay原生
    private string $letstwopay_url = "http://api.letspayfast.com/apipay";                                   //下单地址
    private string $letstwopay_mchId       = "723450269337";
    private string $letstwopay_notifyUrl   = "http://124.221.1.74:9502/Order/letspayNotify";             //回调地址
    private string $letstwopay_Key    = "IZUGUT9LZR6LQIUGV6HMHJZUPREJTNWAZ8R99SJQNFCUBOUYKEK0LZUWWJ6GNIE6ENZNTKX9DKCYBRRJ0VJWXER9S1OVEVGLKWINIMRCL1HEHUJPKJ5IX0ZGX59SE0TL";

    //dragon_pay
    private string $dragonpay_url = "https://dragonpayment.net/api/inr/recharge";                                   //下单地址
    private string $dragonpay_appKey       = "53E64C7EFACD3F30D1";
    private string $dragonpay_notifyUrl   = "http://124.221.1.74:9502/Order/dragonpayNotify";             //回调地址
    private string $dragonpay_secret    = "81d51489d6bbe4045e3c9b6d6e6067c6068e6e4b";

    //ant_pay
    private string $antpay_url = "https://api.antpay.io/pay";                                   //下单地址
    private string $antpay_merchant_code       = "AM1723630965609";
    private string $antpay_notifyUrl   = "http://124.221.1.74:9502/Order/antpayNotify";             //回调地址
    private string $antpay_key    = "43e795347e032fa4d439706ac01309f5";

    //ff_pay
    private string $ffpay_url = "https://api.wepayglobal.com/pay/web";                                   //下单地址
    private string $ffpay_mchId      = "999100111";//测试
//    private string $ffpay_mchId      = "100777805";//正式
    private string $ffpay_notifyUrl   = "http://124.221.1.74:9502/Order/ffpayNotify";             //回调地址
    private string $ffpay_key    = "741dc77231d8466aaa352c5ddb52bd07"; //测试
//    private string $ffpay_key    = "874a2e43f7e54d799846667d6abe2666";//正式




    //cow_pay
    private string $cowpay_url = "https://pay365.cowpay.net/pay";                                   //下单地址
    private string $cowpay_mchId      = "1723714607199";

    private string $cowpay_notifyUrl   = "http://124.221.1.74:9502/Order/cowpayNotify";             //回调地址
    private string $cowpay_key    = "9c8303152d19b5aac5336bcd5f16fc34";



    //wdd_pay
    private string $wddpay_url = "https://www.wddeasypay.com/orders/orders";                                   //下单地址
    private string $wddpay_mchId      = "10238";
    private string $wddpay_notifyUrl   = "http://124.221.1.74:9502/Order/wddpayNotify";             //回调地址
    private string $wddpay_key    = "sLwEMOQjDKorywnP";



    //timi_pay
    private string $timipay_url = "https://www.timipay.shop/lh_pay/pay";                                   //下单地址
    private string $timipay_mchId      = "529098346";
    private string $timipay_notifyUrl   = "http://124.221.1.74:9502/Order/timipayNotify";             //回调地址
    private string $timipay_key    = "56c773f8e761b72c722e1ff1991d2547";



    //newfun_pay
    private string $newfunpay_url = "https://api.funpay.tv/orderPay";                                   //下单地址
    private string $newfunpay_mchId      = "1009";//测试
//    private string $newfunpay_mchId      = "1059"; //正式
    private string $newfunpay_notifyUrl   = "http://124.221.1.74:9502/Order/newfunpayNotify";             //回调地址
    private string $newfunpay_key    = "MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAKZpCRkOMaMccbljkXbV+JK0Hym/k54loJ8SKkDAgWvgHPYKplgCe/GtZrSeZQDnWC5qP233uM3DgL+OItnfM6AEGVI/AVd9fno2Jz11Qzae8xSELjfMs2ZFfXHb3DjuXODU8Zd9hh4HwqgXt+9LTEdyMAf0E41vODQtgI+cv5KJAgMBAAECgYAG6ndB/vtZyl/WPZNuZebGag1K1eG+Vn/+Eb+HILkAO7iDEomRP3aD4m9R8wHtmgUUgL6Rb78oG1zpbnB3r4qx+soc1Fp7ISZJzQVkhJdq7HWnqODNNqs6XxQZGR/tjywCQXrkB02HRTQDEf4eIRm2WFqpCZJTu7bWcVTmOwzL5QJBAOwKfNNi5Yh2z6Pr+5d22+MLlnXxGmJC935f4jImSybzv624+yb4Av9Vy0rhNaRkEdjjfyyFiG9H/vb3M9vSL7MCQQC0e0W6IoUKa61kqLTBk2D68vq7Q5d3PjzfAflRs4vMtvn5x0XBFqbipUtFOTdcKva2b4ZYZFJRsCdpaooIg7bTAkEAytkkdwFZotIAFa5ac8tIorE1p7wA4YsNaIR8Pn7cPOhixKfg5pdi9A3F/F7Ym6MIF21CwH8tRf0IZzMAVRwnswJBAIXT8rw25Jf5iDVfs8jmU79BdRJu6F2PVOu4NvuSO1OtSmcgkGTBOzZMgyftaVN6uD5HLENXAIN6L39HdNsjb+kCQB6895oOZ/yxhPIDMGP2d1lZxILQjS6r6/FWpfRSHLaag0pa6EK8bab/8CxL104qnXqg3KDEf/CqDo/EJpfnf7U=
"; //测试
//    private string $newfunpay_key    = "MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAJMRc60xlWTJmdtm6jFkbk2LNiax2d/bFWw46oXydTuR4HXU2eX0QVpClogKn2CdkA4vwmYt9pc8POwEfwBqq3jMz8Si/5i6YIskdmnktiNa6D/lB0K22CFWQxo+ks/BH7k+pas2+IyL6KO2EHsSwG/67jrjnB3XTLqIndvYNV+zAgMBAAECgYAabL7Tpj6ZEu4tsWKwCEMXdMWAk2E56zQAs4NUGPn+f5oMofea7VXWwXMps3rqkbUCD4vG70hI6T5rC+3D5ea0Mpk5YFd2HW8LEyS2VBAcADkfLYRf6KIChFQ3fvTiqxP1qmVyO7mDlxkuLgMOPSX5kp6JL/hsf3esS+gNcvqFLQJBALvxkkh/JBT0MRMp9zb33ma0Yl/gqqvU9ujqmVzgEknKpNFF2kVpWBdJ56ORTEwgR8BuXisYYfQdsfCGUy/seh8CQQDIUraK4H5HgLYRQoCkBBNFSlMvZV60FicEd3RHjY0QDa4fJrD+LJMFt9loAZXBC226uxYXtjyc1w6EPFT9z4/tAkAzPa2wblmcDOfEXdC0/+d3AP9BPLPLnYikADJIDB9wVvuQwwa7nfkSgGfTRK4Uo0hswqqR/VfXgrEc7sKHcmXpAkBkFi9uI8v0HbLZ3Mg5KnAWZpQ5UgSHJapI6QYH2glow+0DU2mLFOpAKSNOe7w+v18LtP3MyxhtpGV0XFB6n4HhAkEArnUMYgOQHWJHqNrxoDuzYA3alfpHe8/S7VHZ3oPB3FAmpLQDx81C3+7q5MOlHASTU8qvMNEirJeAW3wuyhteaw==";//正式



    //simply_pay
    private string $simplypay_url = "https://api.simplypay.vip/api/v2/payment/order/create";                                   //下单地址
    private string $simplypay_appId      = "3846f553e7a1230c18308af654b688bc";
    private string $simplypay_notifyUrl   = "http://124.221.1.74:9502/Order/simplypayNotify";             //回调地址
    private string $simplypay_key    = "158aa68d9454f18962e14c7a541e73a9";



    //simplytwo_pay
    private string $simplytwopay_url = "https://api.simplypay.vip/api/v2/payment/order/create";                                   //下单地址
    private string $simplytwopay_appId      = "7184076527b71034f6a7bdf437c2acf0";
    private string $simplytwopay_notifyUrl   = "http://124.221.1.74:9502/Order/simplypayNotify";             //回调地址
    private string $simplytwopay_key    = "8fac2bd53e50c19c4ad9e1d7a030a3a6";



    //lq_pay
    private string $lqpay_url = "http://lqpay.txzfpay.top/sys/apple/api/collect";                                   //下单地址
    private string $lqpay_deptId      = "1710169284135714818";  //机构号
    private string $lqpay_mchId      = "22000023";
    private string $lqpay_notifyUrl   = "http://124.221.1.74:9502/Order/lqpayNotify";             //回调地址
    private string $lqpay_key    = "MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAN231QFbn8xmA83LecawDmnoHPD4asBG79dJkknggqQgW8yG1dRNnevsJRPZyDMQMRObPnIYIB0KOQ3ewyUjfrEsphJdTeIZUn1zYfuTPAkRsjv8w5xvMwG3WF7RSDh4p7wnXleowmxw2D5U5/k3cahfL5SNqaJXBzLVprHRZK3DAgMBAAECgYAZnJs38XAXqe8ljiwuhfbcSApT0bZvKKKbAW4rJ4qfz/cavLaltCOadahgzyb/sw6gP64qetv2ztABaKqtNxjywmfXZkG5eKk5sHUdHJAPXe5xGvVbXcXtCg9uFLuolu0Qhh6g4MuEXfZ5/HhIMHopJKW1eneYOoJJKr+3miopxQJBAPjk+qZ2QMLLx2E9VCnK7sZCkZA6UntagvaMg7ml9pWjxRrTDjWqBBcl3XyXvsj848W21hdCwWdhUKfnPsoiTq0CQQDkDDy40PpPc77soa8u+BRcP7smKuYf9wluSaCv/4sYKw6yWmPLX8leHXCWf9oZbqSvJwH8bVBaxl/NT198rKwvAkBz4aB1ul8CkwAcXQJ/htVPB5VgUlcuyYBqLBf0arn5B8vwZk2aXLMU1/NcXAZe66dc2XiqUdFcQanc0sSgNgLtAkBipFhvqRVc4LgpKxbXvj8wV/Df5ZZ9JSJTLk3vUx4baiSFSUv5YIl9yEY3Ez6H2bAqgzj8s1wap8wwxrCLATXJAkA7DE4k6B9cSwndrS2djI9JHItrLUkoFNjVq79frH8PcFXVJG7c3mG3nxXxb8n4dl5qt8W9Iy6hE3zCilz7AcRb";



    //threeq_pay
    private string $threeqpay_url = "https://pay.3qpay.org/api/pay/pay";                                   //下单地址
    private string $threeqpay_appId      = "66c81be0e4b00bf3fc383a31";
    private string $threeqpay_mchId      = "M1724390368";
    private string $threeqpay_notifyUrl   = "http://124.221.1.74:9502/Order/threeqpayNotify";             //回调地址
    private string $threeqpay_key    = "HsSxrEU6sHIW32udk8xKR055q5lqzqtJzbCoMnBFmrXAGc2TvAFrMRKONSuXfuMaBKh8f2RvEP0o3buoKl7i5WINymzPnkYMgg5BPyE5FDb7E5UjSm9ePjEDCrKDUjsK";


    //show_pay
    private string $showpay_url = "https://api.newbhh.com/v2/test_payin"; //测试
//    private string $showpay_url = "https://api.newbhh.com/v1/pay/payin"; //正式
    private string $showpay_mchId      = "100"; //测试
    private string $showpay_notifyUrl   = "http://124.221.1.74:9502/Order/showpayNotify";
    private string $showpay_key    = "test_sign_key"; //测试



    //g_pay
    private string $gpay_url = "https://api.gpayindia.com/admin/platform/api/out/pay";
    private string $gpay_mchId      = "1828416315993178114";
    private string $gpay_notifyUrl   = "http://124.221.1.74:9502/Order/gpayNotify";
    private string $gpay_key    = "0356d6cd250eee7ba9ef9f7a1aa10250";


    //tata_pay
    private string $tatapay_url = "http://meapi.kakamesh.com/pay_api/POrder";
    private string $tatapay_mchId      = "20000034";
    private string $tatapay_notifyUrl   = "http://124.221.1.74:9502/Order/tatapayNotify";
    private string $tatapay_key    = "F8F1077E12E6430E8FB81CDAA5FC03F0";

    //pay_pay
    private string $paypay_url = "https://api.paypayonline.vip/api/payInOrder";
    private string $paypay_mchId      = "3132FB00A9144EABA6E5243DA32FA23F";
    private string $paypay_notifyUrl   = "http://124.221.1.74:9502/Order/paypayNotify";
    private string $paypay_key    = "3CxPH8MjrtWxU3chLfbA3t4cbcH43OFo4gf4U1gOhtEMgTS8grP9iOTelwLoKWGy";



    //yh_pay
    private string $yhpay_url = "http://gateway.yhpay365.com/api/pay";
    private string $yhpay_mchId      = "91000005"; //测试
//    private string $yhpay_mchId      = "91000048";  //正式
    private string $yhpay_notifyUrl   = "http://124.221.1.74:9502/Order/yhpayNotify";
    private string $yhpay_key    = "3jdS5RnwUnSNsOUQvfICZhps";//测试
//    private string $yhpay_key    = "7jqPfsyzqLSfuPlKpxkuEpQk";//正式





    //tmtwo_pay
    private string $tmtwopay_url = "https://beespay.store/gateway/v1/pay/create";          //下单地址

    private string $tmtwopay_merchantNo       = "192UNCC334";                                   //商户号正式

    private string $tmtwopay_appid   = "3E2591A316794E53969538D4CFF849D8";  //app_id 正式环境

    private string $tmtwopay_Key    = 'K#wuMZPkbp8HD^TipRxv1R2~_Ux*Ua^JlSxZrLok=7^3urQ%QG_qpxfCQYPS0A7%tcWDSIea%Z7g1k!xOkAuRTpk&sWcWm_k6::41?nB$ewpA$*EBj7db@S~5UPTgefD'; //正式
    private string $tmtwopay_notifyUrl   = "http://124.221.1.74:9502/Order/tmpayNotify";




    //newai_pay
    private string $newaipay_url = "https://gtw.aipay.fit/api/pay/pay";          //下单地址
    private string $newaipay_merchantNo       = "M1727330921";                                   //商户号正式
    private string $newaipay_appid   = "66f4fa69e4b01633f15e34c0";  //app_id 正式环境
    private string $newaipay_Key    = 'KvwcOrtCAbrIqFB0Dsp5QrNGUSYmqsWXGG5owHPjPLit8L7AxkM7YNUSupLnBA1LCLaMl3qgwrRwS4HkRSQJAYYvdIoG3KPxklnHz7G48YaHi2E7dE2g2HXdP8DlceTy'; //正式
    private string $newaipay_notifyUrl   = "http://124.221.1.74:9502/Order/newaipayNotify";



    //allin1_pay
    private string $allin1pay_url = "https://app.allin1pay.com/order/deposit/create";          //下单地址
    private string $allin1pay_appid   = "197";  //app_id 正式环境
    private string $allin1pay_Key    = 'f5baf5bbaf9f6d8eb6420684a60a717d'; //正式
    private string $allin1pay_notifyUrl   = "http://124.221.1.74:9502/Order/allin1payNotify";




    //make_pay
    private string $makepay_url = "https://novo.txzfpay.top/sys/zapi/charge";          //下单地址
    private string $makepay_appkey   = "1829113188401680385-1840045975921152002";  // 正式环境
    private string $makepay_Key    = '04003728683241822137002479242609'; //正式
    private string $makepay_notifyUrl   = "http://124.221.1.74:9502/Order/makepayNotify";

    //newai2_pay
    private string $newai2pay_url = "https://top.adkjk.in/aipay-api/order/submit";          //下单地址
    private string $newai2pay_merchantId       = "999";                                   //商户号正式
    //private string $newai2pay_appid   = "66f4fa69e4b01633f15e34c0";  //app_id 正式环境
    private string $newai2pay_Key    = 'abc#123!'; //正式
    private string $newai2pay_notifyUrl   = "http://124.221.1.74:9502/Order/newai2payNotify";



    //rrtwo_pay唤醒
    private string $rrtwopay_url  = "https://top.adkjk.in/rpay-api/order/submit";                 //下单地址

    private string $rrtwopay_merchantNo = "1347"; //测试

    private string $rrtwopay_notifyUrl  = "http://124.221.1.74:9502/Order/rrpayNotify";  //回调地址
    private string $rrtwopay_Key        = "A2SzHXv8U8EBPKH6";//测试



    //best_pay
    private string $bestpay_url  = "https://gateway.bestpay-cus.com/payment/payin";                 //下单地址

    private string $bestpay_merchantNo = "67";

    private string $bestpay_notifyUrl  = "http://124.221.1.74:9502/Order/bestpayNotify";  //回调地址
    private string $bestpay_Key        = "MIICeQIBADANBgkqhkiG9w0BAQEFAASCAmMwggJfAgEAAoGBALB+zfoVSdUZKUladjZZOL68mzCcvCN2TVvIZTd2OlPnSMTYvRKy+WXNI3FyZCTiXxjDgYhNXNRUgrz7eRzKYGUEAAbJW6UIRxQlCvXzInDanoA5xQpGJO1VuyQq2khX2FuZHnjsNhzghSeufEwsXoi+IrpTGB7irhTZMY4kucHRAgMBAAECgYEAqAG9Lw7uvmR6MbJkDu41nxNIoyi/yv4FO5ZyCy6G7XGfiopKyS8HSwnQcGCUxaubHKaWeloyQIjF/wFe07ItuMY6Z/nFrrzBfSzve29UsQx/bRPD8bRwMjscyIVhTfUrj97vODByWbP6MqVByfvRkMRyyQUNz60jc8Ow7BowfgECQQDVotdAkEawM8A1lPxcr0YlgeaN6lIkUf/W9ftDiAgtKPpft+lstCKF4R9zLZl0BD8niFpzCxm9pipXcVa7Ap6hAkEA036GtJGBof3oWdJ5xWTbLxmhjKFf04U4WuFkykbughvtPlhUNAEY3NSVMvaOlfuyveFD+c8+BBZLl5daqXhFMQJBAMHfP1w2EhBBRoLZq5Mo9I2BLwtGxDh1uakIHXeRcWoaL+zBZ7HgXxwDypipnwKr/+wOT5brUfbLXs1v63dWz0ECQQC5yLHIOPGpPYQ4Mz4o+lnYXCmfgbrN8n74xnplfj3SKXoUhD8jl7shcdTGefPzKLFxP0sZTMXrjTJGLfzEVhRhAkEAzNi5Znfp5PoPsmCMcn3rLDFK6lJevroeAOfhgb11bwKjLwhFJtIHG9EWJ+hhMuTu4bLGZRg6aE3Woo0uSWycRw==";//测试

    //zip_pay
    private string $zippay_url  = "https://gateway.zippay-in.com/payment/payin";                 //下单地址

    private string $zippay_merchantNo = "483";

    private string $zippay_notifyUrl  = "http://124.221.1.74:9502/Order/zippayNotify";  //回调地址
    private string $zippay_Key        = "MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAKg/rFB1IrwElT/dv7PdmgzjrN0SaCEivGFcmQccIjpUg1ZY2UgWKm95ZH/uM7obiXxPV5jA0w7Tyk8vooY5+IIUiZhlF7WcyQqV6hjZQxQW99lsQDTas+94xNDjZU4IOfm8wYDV6HfcrdZ//kSJajMNiYct8qj9n2JkPKEly2MlAgMBAAECgYALvB0to23fxUYPpUzIo80p1vtok+8VWJHhDI9T0p+Eh/59GEdXYsxk6AedcKTE90S4meQXMGPIJfd3XHAugn6VnxeZQXpQqoIl0YCzl0Fxlptt70rRG4DJPfqdDArIoU8V0Pi9qVpV4hFTRNvd4KaRAoY7JqGZz04yafWJlNR4oQJBANaXM5tQRiX2+PsLuCdqRLoh4GPx43USkqQZJN34bs1c9ls3UXuzYFOgDM/Yz9UCO8caI9dXpPFeLXOP3BsvzL0CQQDIty06CaAkgNfFZ04ozNVoijksjiQ9JP0+MUyNMQVO7d0gUYc4KAeNIW1E438XvAx+/KbGvfA6LbuRaaUnZDqJAkEApqJ1LZcRUevNfcyk7N6FjfA+ef3cvg11F756tW90Qz58A2saeC9bjrSLHl9jTCpW1w5CZLcnW1LhgopkxivBFQJAGWUzz7gQDw5OPqfHd9oS1ltGyKBjbWkUsZ3DNcoSBd6Kr+Ag37YQ3oZwMNsn5XThj9+fql2122aV6NwZDVbdIQJAcjQW5+Ef7HlROzf4WfUEk6wE3+AR7HMCCXeuX2FTPXUz/e92ZJC00Hqwefpg+ziF0gk65aVOFxad7abJ57pNqQ==";//测试




    //upi_pay
    private string $upipay_url  = "https://api.upi-pays.com/api/payment/createOrder";                 //下单地址

    private string $upipay_merchantNo = "100"; //测试
//    private string $upipay_merchantNo = "258"; //正式

    private string $upipay_notifyUrl  = "http://124.221.1.74:9502/Order/upipayNotify";  //回调地址
    private string $upipay_Key        = "ufoanU37Gdk@d8D3";//测试
//    private string $upipay_Key        = "c5C6XrNbA82a134";//正式






    //q_pay
    private string $securitypay_url  = "https://open.i-paying.com/v2/payment/gateway";                 //下单地址
    private string $securitypay_appid = "AKDDIU3FHI39"; //
    private string $securitypay_notifyUrl  = "http://124.221.1.74:9502/Order/securitypayNotify";  //回调地址
    private string $securitypay_Key        = "DUWM1BUYCEX894I6HG0A";//



    //allin1two_pay
    private string $allin1twopay_url  = "https://app.allin1pay.com/order/deposit/through";                 //下单地址
    private string $allin1twopay_appid = "197"; //
    private string $allin1twopay_notifyUrl  = "http://124.221.1.74:9502/Order/allin1twopayNotify";  //回调地址
    private string $allin1twopay_Key        = "f5baf5bbaf9f6d8eb6420684a60a717d";//


    //vendoo_pay
    private string $vendoopay_url  = "https://test.api.ips.st/pomfret/v1/lc/pay/order";                 //测试
//    private string $vendoopay_url  = "https://api.seapay.ink/pomfret/v1/lc/pay/cashier/order";                 //正式

    private string $vendoopay_merchantNo = "TEST99"; //测试
//    private string $vendoopay_merchantNo = "777bet"; //正式

    private string $vendoopay_notifyUrl  = "http://124.221.1.74:9502/Order/vendoopayNotify";  //回调地址
    private string $vendoopay_Key        = "839656ab9bdae7230bcacc51da685582";//测试
//    private string $vendoopay_Key        = "3e5283781adf6b8ef4f59edea290b3de";//正式



    //rupeelink_pay
    private string $rupeelinkpay_url  = "https://open.rplapi.com/rupeeLink/api/pay";                 //下单地址
    private string $rupeelinkpay_merchantNo = "241014137451"; //
    private string $rupeelinkpay_notifyUrl  = "http://124.221.1.74:9502/Order/rupeelinkpayNotify";  //回调地址
    private string $rupeelinkpay_Key        = "eauxMNmns50iG22vqKfXaD0jIhLEy1kW";//


    //unive_pay
    private string $univepay_url  = "https://ydpay.univepay.com/Payment/GlobalPay";                 //下单地址
    private string $univepay_merchantNo = "100008"; //测试
//    private string $univepay_merchantNo = "C24638"; //正式
    private string $univepay_notifyUrl  = "http://124.221.1.74:9502/Order/univepayNotify";  //回调地址
    private string $univepay_Key        = "123456";//测试
//    private string $univepay_Key        = "CJ3DL5SAC57DGPEY";//正式


    //no_pay
    private string $nopay_url  = "https://payforsaas001.qb8.app/order/depositOrderCoinCreate";                 //下单地址
    private string $nopay_merchantNo = "CBY7TB1K77UYS99Z"; //测试
//    private string $nopay_merchantNo = "CBQ3DK9CN67LEKPX"; //正式
    private string $nopay_notifyUrl  = "http://124.221.1.74:9502/Order/nopayNotify";  //回调地址
    private string $nopay_Key        = "CBKvxKo5BzcBPQPKiW2VTU6NdWKeI6AE";//测试
//    private string $nopay_Key        = "CBKX2EpjQikiiqK6Bd9zIhkXoOVK7aOB";//正式



    //ms_pay
    private string $mspay_url  = "https://agent.msigiosdfih.com/api/payin/order";                 //下单地址
    private string $mspay_merchantNo = "777bet"; //

    private string $mspay_notifyUrl  = "http://124.221.1.74:9502/Order/mspayNotify";  //回调地址
    private string $mspay_Key        = "1B002AB86AC1E91BAB92C5E3EB9352E7";//



    //decent_pay
    private string $decentpay_url  = "https://api-in.zupay.top/pay/collection/direct-payment/create";                 //下单地址
    private string $decentpay_merchantNo = "1072"; //
    private string $decentpay_appId = "107201"; //
    private string $decentpay_notifyUrl  = "http://124.221.1.74:9502/Order/decentpayNotify";  //回调地址
    private string $decentpay_Key        = "fLgwZ5V7sqavYNSPzq8hStsj6pBA74CJ61l4HH3php7xqqXiNxJzNagxIILvUY6tNUKlU8MR8NDC+SLrBVaoiw==";//



    //fly_pay
    private string $flypay_url  = "http://www.inspay.org/sapi/payment/common/payin";                 //下单地址
    private string $flypay_merchantNo = "999911256"; //

    private string $flypay_notifyUrl  = "http://124.221.1.74:9502/Order/flypayNotify";  //回调地址
    private string $flypay_Key  = "MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCp5ZoYlL/4gGcO1N9PXhvUQ/3uh1SgTkCwjOXKMQYk7JeSSK+32Px3jZt8GoyjC/9J66ybpPdKyJLoaSmD5av/rw3jyUOL6rdfwo0pzdK75kfxsJUOqtVy3+gOTSRQCvVEjqROwZVdzOlgzUTmBLT7mdALRDWh1wSeur887OAJsXKAN8aJ4fakpjX5234kayyLYfYfqEmeUytN//HN/v1T4BcfTzkJLD6vLR3a9vI7noyvaofzy3sSlVpHWoDkA5gAbS9D3uE1YI5PodWHabr1PC4d6mD/Bi/6crCKBCPTstJcFWVfOzgLNxJkuEpi2Buz5fObgNIgGkQQNufsmaq/AgMBAAECggEAFwKNuD6NMW7ShmO2St2ID1uWDLQvdfajNEYg6R1yo5WXgaxugAvXRancIRbHbV22maBdpEbXZz17CBcWFFMK1Ho0+2DK0Sxc4Y9L5xmFLQWnQMiVp4LjncdNeoJgJKcXSM242yHExQt3oDCs4CpLCLhwQNZgHSct7QGF3Q6H2UgCQTNxFOpsYHzI8LBqwROGHv5E6/UN9kGUSpQf22LlzhJLb9oJDyvzk2b0m4fb2/7OBJbsdClD7PwCKn+mamvLTbjDBZpSm13tmTtgCRUV25hDREQzyBNeJwHqclK3gMMz58FF0YZIcNBrbWel5v32cZkugDj10J8Gln/g2b6q4QKBgQDq+aa/KDZ0vq/9VaaKWhZL3eeQe4V6hu0FCkqQw6Z2lzlRGVAzOAoLTnvAW31fR7I6xB1w5QkzL3P6GjaH93Cq7HL1um/0MKFLHPaD9i13V5p7YD7qFIjVPlgv+6vkCjrQvvudNi61R9K0y+X9gyGeIppgRUIVL7IUMoqECeiRgwKBgQC5GUPIQ+tiNCW2Hld2kuO5ue0wn5Rj72E7BqrPdgT3TrQc1UTLLO/SQdEXSG5mp+9C8FmikCbDYXWeSXfu8VsJ4Ep/ujHj+J49VqK+sQthA8kvyKUeMMXbL3gF6emstW6kbU3/dLoU9arZGkpNX+PJ1obBjwV1BQiR/93JPx1pFQKBgEE8bIn3zR6eblfkNqeEmVoY0phvYsCAwz85+zezyfx0wan9YCHINimrcXoXLHiOfDIKjq3wOJyoWQefzXH0Rah+mvAUAc8GzVEASoSajUbr4GzObMkqSE8DzxILSk62dFvOGicsis0zkpE1ZrX6eRPhQYDm2ZDuO/+VhJVh9tqnAoGBAKzeiq6DuFccKsg+6CKmpyYzHfGWaEk5LQ6qeGaPa63pBFAVYk1653Pv4i6jh/A6ETvsK1qm1H0PDYFKTkeLhCHiJtHJfITUEj1pJ09/HAh8N6537rYWiQLe/3JOdt3FCNNp/jmBs7SVh/2BDznaP2ym/W3SfB9BFzL7yxAD8RzNAoGBAM67j156oSVF0VH6d2z4/b/9wTLSjG69gzaQMq0eiZPE2TQwZ5MwnmHeUleELOau98BGweNdKBDjxSEzSo//r1GL46usT8Ovy+wDJo62wkBvsnVC9bFmvVTXxTeEshbEm6ylRM5twOmmkoELsVC36XFZ1LKFu87NXdnLOKnyr7fw";//

    //kk_pay
    private string $kkpay_url  = "http://api.zhkap.com/pay/order";                 //下单地址
    private string $kkpay_merchantNo = "1000100020003416"; //
    private string $kkpay_applicationId = "410"; //

    private string $kkpay_notifyUrl  = "http://124.221.1.74:9502/Order/kkpayNotify";  //回调地址
    private string $kkpay_appKey  = "2FDE621690694CF9B2FC80EBAB9F53D2";//
    private string $kkpay_merchantKey  = "20E87B6B726846BCACAF261AEFF33B6B";//



    //tk_pay
    private string $tkpay_url  = "https://seabird.world/api/order/pay/create";                 //下单地址
    private string $tkpay_merchantNo = "202366100"; //
//    private string $tkpay_merchantNo = "202466298"; //正式

    private string $tkpay_notifyUrl  = "http://124.221.1.74:9502/Order/tkpayNotify";  //回调地址
    private string $tkpay_Key        = "c385fe7029344aef826d8112625b2625x";//
//    private string $tkpay_Key        = "fca35368da5d403086dcf248103b432f";//正式

    //kktwo_pay
    private string $kktwopay_url  = "http://api.zhkap.com/pay/order";                 //下单地址
    private string $kktwopay_merchantNo = "1000100020003421"; //
    private string $kktwopay_applicationId = "415"; //

    private string $kktwopay_notifyUrl  = "http://124.221.1.74:9502/Order/kktwopayNotify";  //回调地址
    private string $kktwopay_appKey  = "CCDF005D598D432ABE36A10C621819CA";//
    private string $kktwopay_merchantKey  = "8A76608C2D344C279F67FE507CD34ABE";//


    //one_pay
    private string $onepay_url  = "https://pay-test.uangku.top/cashin/india/order/create";                 //测试
//    private string $onepay_url  = "https://api-in.onepyg.com/cashin/india/order/create";                 //正式
    private string $onepay_merchantNo = "2061545184"; //
//    private string $onepay_merchantNo = "3619769596"; //

    private string $onepay_appId = "R22e9c0f1x3hycRKd9"; //
//    private string $onepay_appId = "OJMXy4x3uNmVaOU8Ya"; //

    private string $onepay_notifyUrl  = "http://124.221.1.74:9502/Order/onepayNotify";  //回调地址
    private string $onepay_Key        = "MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDYkwC5pxuCzCbfj4CIzltDPq0fsipBKuJHCK0e77ro7H1DUrHBSSYyCaa9U5cLQbIsLf5rhMzrGzef2Yl3x2eL8BBx+EGZBC4nj0L8IohqJzTzU6SIcV6hWostj0j8b3wT6hD8WTGWO9nECJmFXWPGHuRHkKf4jXRnvnTUyITQ9KUCST1FaT2Dq6OUz7qDInHNZuCzD5k2IjJRKFn67ouqg/kAW8S8JW03IXHmGu5SotpHMx9550LxECUD3wL1RVWi9I5Y4OgUJEEGYw36i+Ip0gKDUoKcbdpLbzP9C7hPx07+x5IwWSH7N5t8NY0LpsFeSTT8BtY5z8Dx6lQbGHrDAgMBAAECggEBALCEB/hI6hROHnTh+ioGvr1tDR+jT+szb5/jw25Oh/GmJmZDtRtLanUoBp2Szq3fCsAVcKLjZz1MPqbrX66feJbGwDCT3atAi/m0Oy1TXAnhELgt+RE4DZ/HM16bxSnyF5gHk3aJn+/JsjCjrbTULCjjLr3hefYMHM8dnQ18rTu8Wf7/VAnPc6dEdemJyUSW3F9HmprZBMW0E4WzxvG9JhF3jnVxBhG/cbb8ks8a7neCsneA4AJ+DcFeVIG6v/djFBrVMrFiDGs4KCGoZjAqTYngqtyFBi+h2WsPs1yRvBeg/XoQ8QqWdvELdnl8eTolYKxvK9uiStcHSePtz/Yh+yECgYEA8PY7aSdRNfOdVLMDpGmHwiJLeO9UoSWRN8rZ2QszGpz5GCj3W+ZLPiWz6lJaw7yZn/F4VUHhTGKUdujZ4Dz3wKOtNV8T7ei8stSRUh9Bk7pryD9LIaNLQiiiIUQ0QqFiXtW6GtLpgt/FeknecwByOzfTTTUQ4H1RpvOBVgEpb3ECgYEA5hcjTK5a/E+W2aYTyk6cRw2ybqZDF4yNni0IRhDTOAvJDwJTzic/lbNrHXWna0ooaLZmmX12v2QZ6RCIUSfGfSnocNXtyeputFSozOk16QWGDpH1QlhNhtW5lFB2ORc1mvTG7C3ECq0RovQRxvqsIWtCjPUKn6OvZjSlMtIim3MCgYEAzvHacmP3Bkv0mlKoVSLhGmTTxshdOY0HHBCWaiaJPFkGQa6lSoMNqhE9ZIhYNXUbx1beDvLmqPCdK0auIDycVxD7aDQA7LmOnlObfxki+9oGSVO6leglcWtuWv21mGf8ERCjpffv3puKgY1BhCkk8iDu04c4uGRIpQbK1G9pA7ECgYA222NeH9+vciZMA+2J+U4HHrvg56DtV2RYRvJHCjHhleW8v1hNuUvOnDU4k9lzmf2iYYJ6q9AI94u55mgpuSr4omo5pLeJwWvdcKXCHQPuZ5O7m47232i0cfZJ5xkYqXDtXdijbJHl3bdru3cVkqRBX3pBcxayUus5memdAT6hAwKBgQCMNiNuZl6A53hzsbAzyXGWXVuM98jWHjLoyjN6AsvY2ZmLHPNHR1ht0QQ5BssMtCdfawSi6jiIP0HOKl0SudL9F5wWU48UZADF2pA+D8qUA5qHoIW5ebU87jja1LEfOVamwh7FgG223qI+2uSE9W4mqf/rWJSjL3FJEDyLcBTMcA==";//
//    private string $onepay_Key        = "MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDY+5ytnLAybiUT7+Av1+EjXos1CLdfr7L9hKJPdIooaRJusNA1pXG2kbg6BfexnkvwHYwyyTnGl6oDpkzxl4qYg+YcjbTIvouxMsP/A5cP5W4nlTvY6D/lexEjmVWbC5FdnXPun1Gh2WGTEqpXIqtEjuVAorC28NpLhtjcZAzYze+HcnvnZywUHBfRUtoa6pXr2Gx1A+yYkJAdZhuEcMzfcBTEJT9V8Jc4CYvrdshJpVO4jU2BV5LIXXY88rxZD/2qViPs27MrIUfeCbrLHGY3c25n3uvf+4hpjUj/biFMmb2ExBMxytLC9EZuZiOv+PsaBiF+KZWIzg8HzSOomHvTAgMBAAECggEACUTKPxJL5oOU+uKKyZBnshqWSLgsGR7IvxKc2lcIlcxTOL49zqTYFCvqWjQqDgDMjp+8fujgmm6dGRXQAJvwFC7CNCMLf23AStv3ylttZwTubStvSIc3P4a/xy0EHqDiX0TcEGvm0UqXY/BurVUySrXt7hJlCROFx6cleViewd4J/kLGHHgsEKy1ocfFym15auCLKWxQjdi7Nn/3w7uUHdq8XlXb+etC1vjcu5cQuDmSiQRup6RZs640ppn1vusJt8CscB0zokYYhMl1dTI5O7TAy94ilgG8SJEF+qKtsNxw/xUeJ8CC8tggRQUSmt4Z5vOOjAZz84AvwQJAGWcRvQKBgQD3OAZL9JZWqnTJgmBGdUpB73tKbKf2utna5B77Sq9dVHHs0N7f5YTHllIR9oKYTue99LdC+2f+7r92drFEiS5Yh996ACFv5K8dGd7M3DoZzjsRiuH2D3dzJXJ9VJ0GpVuGDiT9jV00TBkZYHUh6M8oVSazX3ckbuWzSzUquBoW7wKBgQDgsKZbowzFyiYFPq3ZcmSVPs1VD9wfXgfQaE9JOGX1eFn9+HdnjWMxC3//DyvZWsbXx64Eqfb0AGX1dXQ74YzRRqPR84cH7f9sNLFUv66MECdx+dhi8OddnfeKFjRWdGM4Ge57kc3M+ioJbF9qThJMHJJ6u1eX7H4fLqu28qxJXQKBgQDrAxJRUHE+cApXqZ4WPNfbuGpPBN3jWhtRz7x4DLaKlYU7qA/Hbmv8RDU+qEXbvl7lIGa6wT5KhfHzDsBTs8kgFgJm+wrOUOn7UyWPP+fnsjpK4ekOvgNCrh2ZcT9ZGwbXeEjH1IP+/Dx7+EtBcgzEfbYtnJopQ1cPS3Z+Zsc+dwKBgBxd8QLMuQYXmWk8GpLDYHN/NEky8WV8Z5wmLyxdVHIDOclYnyqRrR46B3TaI30TetsvOIcaNjVj/3tX0s7kkPSy6GfPSRL1NzQgCutaL907BN/c3TbQl0U4dlIWr5DirMweaf9rzwG766a46erv5Ft7l/qqwEpL7zhcmg1E4f95AoGAJqfsBW6PnVVJ6K4oADF4mokFQwXNoTRkZE4vmlcubdpGKL3hiLQGhaXGtkEChxoIPFeO6KgI7Y4N9ptJdMpDy6cPKMwxVrl1fsyHPUt3srwWOzf1GwQ6TVRXxGbNym6X+lPT4UUL3L8rrhcCazSFB0tOkh6aVn6PGlKcUvENtYQ=";//


    //global_pay
    private string $globalpay_url  = "https://gateway.globalpay2024.com/api/deposit/order";                 //下单地址
    private string $globalpay_merchantNo = "180318"; //
    private string $globalpay_notifyUrl  = "http://124.221.1.74:9502/Order/globalpayNotify";  //回调地址
    private string $globalpay_Key        = "6e143bc02801d64e21369524e885dc0d";//



    //a777_pay
    private string $a777pay_url  = "https://www.777-pay.com/open-api/create-pay-order";                 //下单地址
    private string $a777pay_merchantNo = "ca5e5a7bb1d140669f4907c0f08bf723"; //
    private string $a777pay_notifyUrl  = "http://124.221.1.74:9502/Order/a777payNotify";  //回调地址
    private string $a777pay_Key        = "UXCt7sRLGJyFYzQmRLEEMlbeiQygkQ3aFmBmXs";//




    //masat_pay
    private string $masatpay_url  = "https://api.masatpay.vip/api/createPayOrder";                 //下单地址
    private string $masatpay_merchantNo = "507757308571422720"; //
    private string $masatpay_notifyUrl  = "http://124.221.1.74:9502/Order/masatpayNotify";  //回调地址
    private string $masatpay_Key        = "507757308307181568";//




    //ok_pay
    private string $okpay_url  = "https://api.okpayfast.com/pomfret/v1/lc/pay/cashier/order";                 //下单地址
    private string $okpay_merchantNo = "TEST03"; //
    private string $okpay_notifyUrl  = "http://124.221.1.74:9502/Order/okpayNotify";  //回调地址
    private string $okpay_Key        = "1a32560d25440d541fab195012d3d537";//


    private array $header = ["Content-Type" => "application/x-www-form-urlencoded"];


    private array $zr_header = ["Content-Type" => "application/json"];

    /**
     * @return void 统一支付渠道
     * @param $paytype 支付渠道
     * @param $createData 创建订单信息
     */
    public function pay($paytype,$createData,$baseUserInfo){

        return $this->$paytype($createData,$baseUserInfo);
    }



    /**
     * rr_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function rr_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchantId"      => (int)$this->rrpay_merchantNo,
            "merchantOrderId" => $createinfo['ordersn'],
            "amount"          => bcdiv((string)$createinfo['price'],'100',2)
        ];

        $data['sign'] = Sign::notKsortSign($data,$this->rrpay_Key,'');

        $data['timestamp'] = time() * 1000;
        $data['payType']   = 1;
        $data['notifyUrl'] = $this->rrpay_notifyUrl;
        $data['remark']    = 'PAY FOR GAME';

        $response = $this->guzzle->post($this->rrpay_url,$data,$this->zr_header);

        if (isset($response['code'])&&$response['code'] == 0) {
            $paydata = $this->getPayData($response['data']['orderId'],$response['data']['h5Url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * ser_pay
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     * @return array
     */
    public function ser_pay($createinfo, $baseUserInfo) {
        $email  = $baseUserInfo['email'];
        $mobile = $baseUserInfo['mobile'];
        $name   = $this->generateRandomString($baseUserInfo['uid']);  //username 只能是英文或英文加数字

        $data  = [
            "version"     => "2.1",
            "orgNo"       => $this->serpay_orgNo,
            "custId"      => $this->serpay_mchid,
            "custOrderNo" => $createinfo['ordersn'],
            "tranType"    => '0412',
            "clearType"   => "01",
            "payAmt"      => (int)$createinfo['price'],
            "backUrl"     => $this->serpay_backUrl,
            "frontUrl"    => config('host.gameurl'),
            "goodsName"   => "pay",
            "orderDesc"   => "pay" . $createinfo['price'] . "INR",
            "buyIp"       => $baseUserInfo['ip'],
            "userName"    => $name,
            "userPhone"   => $mobile,
            "userEmail"   => $email,
            "countryCode" => "IN",
            "currency"    => "INR",
        ];

        $data['sign'] = Sign::asciiKeyStrtolowerSign($data, $this->serpay_key);


        $response = $this->guzzle->post($this->serpay_url,$data,$this->header);

        if (isset($response['code']) && $response['code'] == "000000") {
            $paydata = $this->getPayData($response['prdOrdNo'] ?? 0,$response['busContent']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * sertwo_pay
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     * @return array
     */
    public function sertwo_pay($createinfo, $baseUserInfo) {
        $email  = $baseUserInfo['email'];
        $mobile = $baseUserInfo['mobile'];
        $name   = $this->generateRandomString($baseUserInfo['uid']);  //username 只能是英文或英文加数字

        $data  = [
            "version"     => "2.1",
            "orgNo"       => $this->sertwopay_orgNo,
            "custId"      => $this->sertwopay_mchid,
            "custOrderNo" => $createinfo['ordersn'],
            "tranType"    => '0412',
            "clearType"   => "01",
            "payAmt"      => (int)$createinfo['price'],
            "backUrl"     => $this->sertwopay_backUrl,
            "frontUrl"    => config('host.gameurl'),
            "goodsName"   => "pay",
            "orderDesc"   => "pay" . $createinfo['price'] . "INR",
            "buyIp"       => $baseUserInfo['ip'],
            "userName"    => $name,
            "userPhone"   => $mobile,
            "userEmail"   => $email,
            "countryCode" => "IN",
            "currency"    => "INR",
        ];

        $data['sign'] = Sign::asciiKeyStrtolowerSign($data, $this->sertwopay_key);


        $response = $this->guzzle->post($this->sertwopay_url,$data,$this->header);

        if (isset($response['code']) && $response['code'] == "000000") {
            $paydata = $this->getPayData($response['prdOrdNo'] ?? 0,$response['busContent']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * tm_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function tm_pay($createinfo, $baseUserInfo) {
        $data = [
            "mch_no"   => $this->tmpay_merchantNo,
            "app_id"   => $this->tmpay_appid,
            "mch_trade_no" => $createinfo['ordersn'],
            "type"   => 1,
            "amount" => bcdiv((string)$createinfo['price'],'100',2),
            "timestamp" => time().'000',
            "redirect_url" => 'http://3377bigwin.com',
            "notify_url" => $this->tmpay_notifyUrl,
            "cus_name"    => $baseUserInfo['jianame'],
            "cus_email"    => $baseUserInfo['email'],
            "cus_mobile"    => $baseUserInfo['mobile'],
        ];
        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->tmpay_Key,'app_key');

        $herder = ["Content-Type" => "application/json;charset='utf-8'"];
        $response = $this->guzzle->post($this->tmpay_url,$data,$herder);
        if (isset($response['code']) && $response['code'] == 0) {
            $paydata = $this->getPayData($response['data']['plat_trade_no'] ?? 0,$response['data']['pay_url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }





    /**
     * tmtwo_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function tmtwo_pay($createinfo, $baseUserInfo) {
        $data = [
            "mch_no"   => $this->tmtwopay_merchantNo,
            "app_id"   => $this->tmtwopay_appid,
            "mch_trade_no" => $createinfo['ordersn'],
            "type"   => 1,
            "amount" => bcdiv((string)$createinfo['price'],'100',2),
            "timestamp" => time().'000',
            "redirect_url" => 'http://3377bigwin.com',
            "notify_url" => $this->tmtwopay_notifyUrl,
            "cus_name"    => $baseUserInfo['jianame'],
            "cus_email"    => $baseUserInfo['email'],
            "cus_mobile"    => $baseUserInfo['mobile'],
        ];
        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->tmtwopay_Key,'app_key');

        $herder = ["Content-Type" => "application/json;charset='utf-8'"];
        $response = $this->guzzle->post($this->tmtwopay_url,$data,$herder);
        if (isset($response['code']) && $response['code'] == 0) {
            $paydata = $this->getPayData($response['data']['plat_trade_no'] ?? 0,$response['data']['pay_url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * waka_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function waka_pay($createinfo, $baseUserInfo) {
        $url    = $this->wakapay_url;
        $header = $this->zr_header;
        $data = [
            "mer_no"   => $this->wakapay_merchantNo,
            "order_no" => $createinfo['ordersn'],
            "order_amount"   => bcdiv((string)$createinfo['price'],'100',2),
            'payname' => self::generateRandomString(),
            'payemail' => $baseUserInfo['email'],
            'payphone' => $baseUserInfo['mobile'],
            'currency' => 'INR',
            'paytypecode' => '11003',
            'method' => 'trade.create',
            'returnurl' => $this->wakapay_notifyUrl,
        ];

        $data['sign'] = Sign::asciiKeyStrtolowerNotSign($data,$this->wakapay_Key);


        $response = $this->guzzle->post($url,$data,$header);

        if (isset($response['status']) && $response['status'] == 'success') {
            $paydata = $this->getPayData($response['sys_no'] ?? 0,$response['order_data']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * fun_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function fun_pay($createinfo, $baseUserInfo){
        $url    = $this->funpay_url;
        $header = $this->zr_header;
        $data = [
            "merchantId"   => $this->funpay_merchantNo,
            "orderId" => $createinfo['ordersn'],
            "amount"   => $createinfo['price'],
            'userName' => $this->generateRandomString(),
            'email' => $baseUserInfo['email'],
            'phone' => $baseUserInfo['mobile'],
            'userId' => $baseUserInfo['uid'],
            'currency' => 'INR',
            'callbackUrl' => config('host.gameurl'),
            'notifyUrl' => $this->funpay_notifyUrl,
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->funpayKey);


        $response = $this->guzzle->post($url,$data,$header);

        if (isset($response['code']) && $response['code'] == 200) {
            $paydata = $this->getPayData($response['data']['platformOrderId'] ?? 0,$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];
    }


    /**
     * go_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function go_pay($createinfo, $baseUserInfo){
        $url    = $this->gopay_url;
        $header = $this->zr_header;
        $data = [
            "amount"   => $createinfo['price'],
            'currency' => 'INR',
            "merId"   => $this->gopay_merchantNo,
            'notifyUrl' => $this->gopay_notifyUrl,
            "orderId" => $createinfo['ordersn'],
            "type" => 1,
            'returnUrl' => config('host.gameurl'),
        ];
        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->gopayKey);

        $response = $this->guzzle->post($url,$data,$header);

        if (isset($response['code']) && $response['code'] == 200) {
            $paydata = $this->getPayData($response['data']['id'] ?? 0,$response['data']['payLink']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * eanishop_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    private function eanishop_pay($createinfo, $baseUserInfo){
        $method = 'POST';
        $timestamp = time().'000';
        $nonce = Sign::createHex(16);
        $body = [
            'merchantTradeNo' =>$createinfo['ordersn'],
            'amount' => bcdiv((string)$createinfo['price'],'100',2),
            'currency' => 'INR',
            'description' => '777WinPay',
            'payer' => [
                'userId' => $baseUserInfo['uid'],
                'name' => $this->generateRandomString(),
                'email' => $baseUserInfo['email'],
                'phone' => $baseUserInfo['mobile'],
            ],
            'payMethod' => [
                'type' => 'UPI',
            ],
            'notifyUrl' => $this->eanishoppay_notifyUrl,
            'returnUrl' => config('host.gameurl'),
        ];
        $bodyString = json_encode($body);
        $sign = Sign::EaniShopSign($this->eanishoppayAppId, $this->eanishoppayAppSecret, $method, $this->eanishoppay_url, $timestamp, $nonce, $bodyString);


        $Authorization  =  "V2_SHA256 appId=$this->eanishoppayAppId,sign=$sign,timestamp=$timestamp,nonce=$nonce";
//        $herder = [
//            'Content-Type' => 'application/json',
//            'Authorization' => $Authorization,
//            'Accept' => 'application/json',
//        ];
//
//        $response = $this->guzzle->post($this->eanishoppay_url,$body,$herder);


        $herder = [
            'Content-Type: application/json',
            'Authorization: '.$Authorization,
            'Accept: application/json',
        ];


        $response = Curl::post($this->eanishoppay_url,$body,$herder);

        $response = json_decode($response,true);

        if (isset($response['code']) && $response['code'] == 'OK') {
            $paydata = $this->getPayData($response['data']['paymentNo'] ?? 0,$response['data']['action']['url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];
    }


    /**
     * 24hrpay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    private function hr24_pay($createinfo, $baseUserInfo){

        $body = [
            'amount' => $createinfo['price'],
            'name' => $this->generateRandomString(),
            'phone' =>  $baseUserInfo['mobile'],
            'mchId' => $this->hr24pay_mchId,
            'mchOrderNo' => $createinfo['ordersn'],
            'nonceStr' => (string)time(),
            'notifyUrl' => $this->hr24pay_notifyUrl,
        ];


        $body['sign'] = Sign::asciiKeyStrtoupperSign($body, $this->hr24pay_appKey);
//        $herder = $this->zr_header;
//        $herder['tmId'] = '24hr_ind_auto';
//        $response = $this->guzzle->post($this->hr24pay_url,$body,$herder);

        $herder = array(
            "Content-Type: application/json",
            "tmId: 24hr_ind_auto",
        );
        $response = Curl::post($this->hr24pay_url,$body,$herder);
        $response = json_decode($response,true);

        if (isset($response['resCode']) && $response['resCode'] == 'SUCCESS') {
            $paydata = $this->getPayData($response['orderId'],$response['url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];
    }



    /**
     * ai_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function ai_pay($createinfo, $baseUserInfo) {
        $url    = $this->aipay_url;
        $header = $this->zr_header;
        $data = [
            "mer_no"   => $this->aipay_merchantNo,
            "order_no" => $createinfo['ordersn'],
            "order_amount"   => bcdiv((string)$createinfo['price'],'100',2),
            'payname' => self::generateRandomString(),
            'payemail' => $baseUserInfo['email'],
            'payphone' => $baseUserInfo['mobile'],
            'currency' => 'INR',
            'paytypecode' => '11004',
            'method' => 'trade.create',
            'returnurl' => $this->aipay_notifyUrl,
        ];

        $data['sign'] = Sign::asciiKeyStrtolowerNotSign($data,$this->aipay_Key);


        $response = $this->guzzle->post($url,$data,$header);

        if (isset($response['status']) && $response['status'] == 'success') {
            $paydata = $this->getPayData($response['sys_no'] ?? 0,$response['order_data']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * x_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function x_pay($createinfo, $baseUserInfo) {
        $data = [
            "mchNo" => $this->xpay_merchantNo,
            "mchOrderNo" => $createinfo['ordersn'],
            "appId" => $this->xpay_appId,
            "currency" => "INR",
            "notifyUrl" => $this->xpay_notifyUrl,
            "orderAmount" => bcdiv((string)$createinfo['price'],'100',2),
            "email" => $baseUserInfo['email'],
            "name"  => self::generateRandomString(),
            "phone" => $baseUserInfo['mobile'],
            "reqTime" => time()."000",
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->xpay_Key);
        $response = $this->guzzle->post($this->xpay_url,$data,$this->zr_header);

        if (isset($response['data']['payData']) && $response['data']['payData']) {
            $paydata = $this->getPayData( $response['data']['payOrderId'],$response['data']['payData']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * lets_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function lets_pay($createinfo, $baseUserInfo) {
        $data = [
            "mchId" => $this->letspay_mchId,
            "orderNo" => $createinfo['ordersn'],
            "amount" => bcdiv((string)$createinfo['price'],'100',2),
            "product" => 'indiaupi',
            "bankcode" => 'all',
            "goods" => 'email:'.$baseUserInfo['email'].'/name:'.$baseUserInfo['jianame'].'/phone:'.$baseUserInfo['mobile'],
            "notifyUrl" => $this->letspay_notifyUrl,
            'returnUrl' => config('host.gameurl'),
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->letspay_Key);
        $response = $this->guzzle->post($this->letspay_url,$data,$this->header);

        if (isset($response['payUrl']) && $response['payUrl']) {
            $paydata = $this->getPayData( $response['platOrder'],$response['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }

    /**
     * letstwo_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function letstwo_pay($createinfo, $baseUserInfo) {
        $data = [
            "mchId" => $this->letstwopay_mchId,
            "orderNo" => $createinfo['ordersn'],
            "amount" => bcdiv((string)$createinfo['price'],'100',2),
            "product" => 'indiaupi',
            "bankcode" => 'all',
            "goods" => 'email:'.$baseUserInfo['email'].'/name:'.$baseUserInfo['jianame'].'/phone:'.$baseUserInfo['mobile'],
            "notifyUrl" => $this->letstwopay_notifyUrl,
            'returnUrl' => config('host.gameurl'),
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->letstwopay_Key);
        $response = $this->guzzle->post($this->letstwopay_url,$data,$this->header);

        if (isset($response['payUrl']) && $response['payUrl']) {
            $paydata = $this->getPayData( $response['platOrder'],$response['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }

    /**
     * dragon_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function dragon_pay($createinfo, $baseUserInfo) {
        $data = [
            "appKey" => $this->dragonpay_appKey,
            "callbackUrl" => $this->dragonpay_notifyUrl,
            "nonce" => $this->generateRandomString(),
            "orderAmount" => (string)bcdiv((string)$createinfo['price'],'100',2),
            "orderId" => $createinfo['ordersn'],
            "skipUrl" => config('host.gameurl'),
            "timestamp" => time()."000",
            "payMode" => 'launch',
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->dragonpay_secret,'secret');
        $response = $this->guzzle->post($this->dragonpay_url,$data,$this->zr_header);

        if (isset($response['data']['rechargeUrl']) && $response['data']['rechargeUrl']) {
            $paydata = $this->getPayData( $response['data']['orderId'],$response['data']['rechargeUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }

    /**
     * ant_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function ant_pay($createinfo, $baseUserInfo) {
        $data = [
            "merchant_code" => $this->antpay_merchant_code,
            "order_no" => $createinfo['ordersn'],
            "order_amount" => (string)bcdiv((string)$createinfo['price'],'100',2),
            "order_time" => time()."000",
            "product_name" => 'email:'.$baseUserInfo['email'].'/name:'.$baseUserInfo['jianame'].'/phone:'.$baseUserInfo['mobile'],
            "notify_url" => $this->antpay_notifyUrl,
            "pay_type" => 'india-upi-h5',
            "return_url" => config('host.gameurl'),
        ];
        $sign = Sign::asciiKeyStrtoupperSign($data,$this->antpay_key);

        $new_data = [
            'signtype' => 'MD5',
            'sign' => urlencode($sign),
            'transdata' => urlencode(json_encode($data)),
        ];

        $response = $this->guzzle->post($this->antpay_url,$new_data,$this->zr_header);
        //$this->logger->error('ant_pay充值数据:'.json_encode($response));

        if (isset($response['payUrl']) && $response['payUrl']) {
            $paydata = $this->getPayData( $response['orderNo'],$response['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * ff_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function ff_pay($createinfo, $baseUserInfo) {

        $data = [
            "version" => '1.0',
            "mch_id" => $this->ffpay_mchId,
            "notify_url" => $this->ffpay_notifyUrl,
            "page_url" =>  config('host.gameurl'),
            "mch_order_no" => $createinfo['ordersn'],
            "pay_type" =>  '151',//测试
//            "pay_type" =>  '104',//正式
            "trade_amount" => bcdiv((string)$createinfo['price'],'100',2),
            "order_date" => date('Y-m-d H:i:s'),
            "goods_name" => 'pay',
            "sign_type" => 'MD5',
        ];

        $data['sign'] = Sign::FfPaySign($data,$this->ffpay_key);

        $response = $this->guzzle->post($this->ffpay_url,$data,$this->header);
//        $herder = array(
//            "Content-Type: application/x-www-form-urlencoded",
//        );
//        $response = Curl::post($this->ffpay_url,$data,$herder,[],2);
//
//        $response = json_decode($response,true);

        if (isset($response['respCode']) && $response['respCode'] == 'SUCCESS') {
            $paydata = $this->getPayData( $response['orderNo'],$response['payInfo']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }





    /**
     * cow_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function cow_pay($createinfo, $baseUserInfo) {

        $data = [
            "merchant_code" => $this->cowpay_mchId,
            "order_no" => $createinfo['ordersn'],
            "order_amount" => bcdiv((string)$createinfo['price'],'100',2),
            "order_time" => time()."000",
            "product_name" => 'pay',
            "notify_url" => $this->cowpay_notifyUrl,
            "pay_type" =>  'india-upi-h5',//测试
        ];

        $sign = Sign::asciiKeyStrtoupperSign($data,$this->cowpay_key);

        $body = [
            'signtype' => 'MD5',
            'sign' => urlencode($sign),
            'transdata' => urlencode(json_encode($data)),
        ];

        $response = $this->guzzle->post($this->cowpay_url,$body,$this->zr_header);

        if (isset($response['code']) && $response['code'] == '0') {
            $paydata = $this->getPayData( $response['orderNo'],$response['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * wdd_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function wdd_pay($createinfo, $baseUserInfo) {

        $data = [
            "userid" => (int)$this->wddpay_mchId,
            "orderid" => $createinfo['ordersn'],
            "stamp" => time(),
            "channelcode" => 611, // 611 & 612 & 689
            "notifyurl" => $this->wddpay_notifyUrl,
            "createnotifyurl" =>config('host.gameurl'),
            "amount" => (int)bcdiv((string)$createinfo['price'],'100',0),
            "email" =>$baseUserInfo['email'],
            "firstname" => $baseUserInfo['jianame'],
            "lastname" => $baseUserInfo['jianame'],
            "phone" => $baseUserInfo['mobile'],
        ];

        $signData = [
            "amount" => $data['amount'],
            "channelcode" => $data['channelcode'],
            "notifyurl" => $data['notifyurl'],
            "orderid" => $data['orderid'],
            "stamp" => $data['stamp'],
            "userid" => $data['userid'],
            "createnotifyurl" => $data['createnotifyurl'],
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($signData,$this->wddpay_key);

//        $response = $this->guzzle->post($this->wddpay_url,$data,$this->zr_header);

        $zr_herder = ['Content-Type: application/json'];


        $response = Curl::post($this->wddpay_url,$data,$zr_herder);

        $response = json_decode($response,true);


//        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/pay',$data,$this->header);

        if (isset($response['code']) && $response['code'] == '0') {
            $paydata = $this->getPayData( $response['platformOrderid'],$response['backUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * timi_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function timi_pay($createinfo, $baseUserInfo) {
        $data = [
            "mch_id"   => $this->timipay_mchId,
            "pay_type"   => 'INR_NATIVE_PAY',
            "out_trade_no" => $createinfo['ordersn'],
            "total_fee"   => bcdiv((string)$createinfo['price'],'100',2),
            'notify_url' => $this->timipay_notifyUrl,
            'ip' => $baseUserInfo['ip'],
            "extra" => json_encode([
                'account_name' => $baseUserInfo['jianame'],
                'email' => $baseUserInfo['email'],
                'mobile' =>  $baseUserInfo['mobile'],
            ]),
            'nonce_str' => (string)time(),
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->timipay_key);

        $response = $this->guzzle->post($this->timipay_url,$data,$this->zr_header);

        if (isset($response['status']) && $response['status'] == 200) {
            $paydata = $this->getPayData( $response['order_id'],$response['pay_url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }




    /**
     * newfun_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function newfun_pay($createinfo, $baseUserInfo) {
        $data = [
            "merchant"   => $this->newfunpay_mchId,
            "orderNo" => $createinfo['ordersn'],
            "businessCode"   => '101',
            'name' => $baseUserInfo['jianame'],
            'phone' =>  $baseUserInfo['mobile'],
            'email' =>  $baseUserInfo['email'],
            "amount"   => bcdiv((string)$createinfo['price'],'100',2),
            'notifyUrl' => $this->newfunpay_notifyUrl,
            "pageUrl" =>config('host.gameurl'),
            'bankCode' => 'BANK',
            'subject' => 'PAY',
        ];

        $data['sign'] = Sign::newFunPaySing($data,$this->newfunpay_key);

        $response = $this->guzzle->post($this->newfunpay_url,$data,$this->zr_header);

        if (isset($response['code']) && $response['code'] == 0) {
            $paydata = $this->getPayData( $response['data']['orderNo'],$response['data']['orderData']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * simply_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function simply_pay($createinfo, $baseUserInfo) {
        $body = [
            'appId' => $this->simplypay_appId,
            "merOrderNo" => $createinfo['ordersn'],
            "currency" => 'INR',
            "amount"   => bcdiv((string)$createinfo['price'],'100',2),
            'notifyUrl' => $this->simplypay_notifyUrl,
            'extra' => [
                'name' => $baseUserInfo['jianame'],
                'email' => $baseUserInfo['email'],
                'mobile' =>  $baseUserInfo['mobile'],
            ],

        ];
        $data = $body;
        $body['extra'] = Sign::dataString($body['extra']);
        $data['sign'] = hash('sha256', Sign::dataString($body).'&key='.$this->simplypay_key);


        $response = $this->guzzle->post($this->simplypay_url,$data,$this->zr_header);

//       $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/pay',$data,$this->header);

        if (isset($response['code']) && $response['code'] == 0 && isset($response['data']['params']['paymentLink'])) {
            $paydata = $this->getPayData( $response['data']['orderNo'],$response['data']['params']['paymentLink']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * simplytwo_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function simplytwo_pay($createinfo, $baseUserInfo) {
        $body = [
            'appId' => $this->simplytwopay_appId,
            "merOrderNo" => $createinfo['ordersn'],
            "currency" => 'INR',
            "amount"   => bcdiv((string)$createinfo['price'],'100',2),
            'notifyUrl' => $this->simplytwopay_notifyUrl,
            'extra' => [
                'name' => $baseUserInfo['jianame'],
                'email' => $baseUserInfo['email'],
                'mobile' =>  $baseUserInfo['mobile'],
            ],

        ];
        $data = $body;
        $body['extra'] = Sign::dataString($body['extra']);
        $data['sign'] = hash('sha256', Sign::dataString($body).'&key='.$this->simplytwopay_key);


        $response = $this->guzzle->post($this->simplytwopay_url,$data,$this->zr_header);

//        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/pay',$data,$this->header);

        if (isset($response['code']) && $response['code'] == 0 && isset($response['data']['params']['paymentLink'])) {
            $paydata = $this->getPayData( $response['data']['orderNo'],$response['data']['params']['paymentLink']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * lq_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function lq_pay($createinfo, $baseUserInfo) {
        $data = [
            "amount"   => (string)$createinfo['price'],
            'deptId' => $this->lqpay_deptId,
            "merchantNo" => $this->lqpay_mchId,
            "orderId" => $createinfo['ordersn'],
            "productType" => 4,
            "payerInfo" =>  $createinfo['ordersn'],
            'notifyUrl' => $this->lqpay_notifyUrl,
            'callbackUrl' => config('host.gameurl'),

        ];
        $SignStr = Sign::dataNotEqualString($data);

        $data['signature'] = Sign::md5WithRsaSign($SignStr,$this->lqpay_key);

        $response = $this->guzzle->post($this->lqpay_url,$data,$this->zr_header);

        if (isset($response['code']) && $response['code'] == 0 ) {
            $paydata = $this->getPayData( $response['data']['id'],$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }




    /**
     * threeq_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function threeq_pay($createinfo, $baseUserInfo) {
        $data = [
            "mchNo"   => $this->threeqpay_mchId,
            "appId"   => $this->threeqpay_appId,
            "mchOrderNo" => $createinfo['ordersn'],
            "amount"   => $createinfo['price'],
            "wayCode"   => 801,
            "customerName"   => $baseUserInfo['jianame'],
            "customerEmail"   => $baseUserInfo['email'],
            "customerPhone"   => $baseUserInfo['mobile'],
            'notifyUrl' => $this->threeqpay_notifyUrl,
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->threeqpay_key);

        $response = $this->guzzle->post($this->threeqpay_url,$data,$this->zr_header);

        if (isset($response['code']) && $response['code'] == 0 ) {
            $paydata = $this->getPayData( $response['data']['payOrderId'],$response['data']['payData']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }




    /**
     * show_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function show_pay($createinfo, $baseUserInfo) {
        $data = [
            "merchant_id"   => $this->showpay_mchId,
            "order_number" => $createinfo['ordersn'],
            "order_amount"   => bcdiv((string)$createinfo['price'],'100',0),
            "name"   => $baseUserInfo['jianame'],
            "email"   => $baseUserInfo['email'],
            "phone"   => $baseUserInfo['mobile'],
            "deeplink" => '0',
            'notify_url' => $this->showpay_notifyUrl,
        ];
        $private_key = preg_replace('/\\n/', "\n", $this->showpay_key); // 确保换行符正确
        $data['sign'] = strtoupper(md5($data['order_number'].$data['merchant_id'].$private_key));
        $response = $this->guzzle->post($this->showpay_url,$data,$this->header);

        if (isset($response['code']) && $response['code'] == 100 ) {
            $paydata = $this->getPayData( $response['data']['plat_number'],$response['data']['pay_url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * g_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function g_pay($createinfo, $baseUserInfo) {
        $data = [
            "merchantNo"   => $this->gpay_mchId,
            "orderNo" => $createinfo['ordersn'],
            "amount"   => bcdiv((string)$createinfo['price'],'100',2),
            "type"   => '8',
            "notifyUrl"   => $this->gpay_notifyUrl,
            "userName"   => $baseUserInfo['jianame'],
            "ext" => 'Pay',
            "version" => '2.0.2',
        ];

        $data['sign'] =  Sign::asciiKeyStrtoupperSign($data,$this->gpay_key);

//        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/pay',$data,$this->header);
        $response = $this->guzzle->post($this->gpay_url,$data,$this->zr_header);

        if (isset($response['code']) && $response['code'] == '0' ) {
            $paydata = $this->getPayData( $response['platformOrderNo'],$response['url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * tata_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function tata_pay($createinfo, $baseUserInfo) {
        $data = [
            "merchantCode"   => (int)$this->tatapay_mchId,
            "channelPayType"   => 2,
            "orderNo" => $createinfo['ordersn'],
            "currency" => 'INR',
            "amount"   => $createinfo['price'],
            "notifyUrl"   => $this->tatapay_notifyUrl,
            "clientIp"       => $baseUserInfo['ip'],
            "cname"    => $baseUserInfo['jianame'],
            "cmobile"   => $baseUserInfo['mobile'],
            "cemail"   => $baseUserInfo['email'],
            "version"   => '2.0',
            "sign_type"   => 'MD5',
        ];

        $data['sign'] =  Sign::asciiKeyStrtoupperSign($data,$this->tatapay_key);

//        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/pay',$data,$this->header);
        $response = $this->guzzle->post($this->tatapay_url,$data,$this->zr_header);

        if (isset($response['code']) && $response['code'] == '0' ) {
            $paydata = $this->getPayData( $response['data']['orderNo'],$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }

    /**
     * pay_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function pay_pay($createinfo, $baseUserInfo) {
        $data = [
            "payAmount"   => bcdiv((string)$createinfo['price'],'100',2),
            "merOrderNo"   => $createinfo['ordersn'],
            "notifyUrl"   => $this->paypay_notifyUrl,
            "createOrderTime"   => time(),
            //"returnUrl"   => config('host.gameurl'),
            "merId"   => $this->paypay_mchId,
            "userIp"   => $baseUserInfo['ip'],
        ];

        $data['sign'] =  Sign::asciiKeyStrtolowerSign($data,$this->paypay_key);

        //$response = $this->guzzle->post($this->paypay_url,$data,$this->header);
        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/pay',$data,$this->header);
        $this->logger->error('pay_pay:'.json_encode($response));

        if (isset($response['code']) && $response['code'] == '0000' ) {
            $paydata = $this->getPayData( $response['orderNo'],$response['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }




    /**
     * yh_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function yh_pay($createinfo, $baseUserInfo) {
        $data = [
            "currencyCode"   => 'INR',
            "gatewayCode"   => 'YH_PAY',
            "goodsName"   => 'Pay',
            "merchNo"   => $this->yhpay_mchId,
            "amount"   => (string)$createinfo['price'],
            "callBackUrl" =>  $this->yhpay_notifyUrl,
            "charset" =>  'UTF-8',
            "orderNum" =>  $createinfo['ordersn'],
            "callBackViewUrl"   => config('host.gameurl'),
            "random"   => (string)rand(0000,9999),
        ];

        ksort($data);
        //JSON_UNESCAPED_SLASHES使用 JSON_UNESCAPED_SLASHES 标志：
        //在调用 json_encode 时，传递一个选项标志 JSON_UNESCAPED_SLASHES，这样它就不会转义斜杠了。
        $SignStr = json_encode($data,JSON_UNESCAPED_SLASHES).$this->yhpay_key;
        $data['sign'] = strtoupper(md5($SignStr));
        $response = $this->guzzle->post($this->yhpay_url,$data,$this->zr_header);
        if (isset($response['code']) && $response['code'] == '200' ) {
            $responseData = json_decode($response['data'],true);
            $paydata = $this->getPayData( $responseData['orderNum'],$responseData['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * newai_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function newai_pay($createinfo, $baseUserInfo) {
        $data = [
            "mchNo"   => $this->newaipay_merchantNo,
            "appId"   => $this->newaipay_appid,
            "mchOrderNo" => $createinfo['ordersn'],
            "amount"   => (int)$createinfo['price'],
            "customerName"    => $baseUserInfo['jianame'],
            "customerEmail"   => $baseUserInfo['email'],
            "customerPhone"   => $baseUserInfo['mobile'],
            "notifyUrl"   => $this->newaipay_notifyUrl,
        ];


        $data['sign'] =  Sign::asciiKeyStrtoupperSign($data,$this->newaipay_Key);

        $response = $this->guzzle->post($this->newaipay_url,$data,$this->zr_header);

        if (isset($response['code']) && $response['code'] == '0' && isset($response['data']['payData'])) {

            $paydata = $this->getPayData( $response['data']['payOrderId'],$response['data']['payData']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * allin1_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function allin1_pay($createinfo, $baseUserInfo) {
        $data = [
            "app_id"   => $this->allin1pay_appid,
            "app_order_no" => $createinfo['ordersn'],
            "type_id" => 53, //原生
            // "type_id" => 47, //唤醒
            "app_user"    => $baseUserInfo['jianame'],
            "app_order_time"    => date('Y-m-d H:i:s'),
            "amount"   => (int)bcdiv((string)$createinfo['price'],'100',0),
            "notify_url"   => $this->allin1pay_notifyUrl,
            "client_ip" => $baseUserInfo['ip'],
            "note" => 'Pay',
        ];


        $dataString =  Sign::dataString($data);
        $data['sign'] = strtolower(md5($dataString.$this->allin1pay_Key));
//        $this->logger->error(json_encode($data));
        $response = $this->guzzle->post($this->allin1pay_url,$data,$this->header);
        if (isset($response['success']) && $response['success'] == '1') {
            $paydata = $this->getPayData( $response['order_no'],$response['pay_url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * make_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function make_pay($createinfo, $baseUserInfo) {

        $data = [
            "amount" => bcdiv((string)$createinfo['price'],'100',2),
            "orderId" => $createinfo['ordersn'],
            "notifyUrl" => $this->makepay_notifyUrl,
            "callbackUrl" =>  config('host.gameurl'),
            "payCode" => 'upi',
        ];

        $SignStr = json_encode($data).$this->makepay_Key;
        $sign = md5($SignStr);
        $herder = array(
            "Content-Type: application/json",
            "x-app-key: ".$this->makepay_appkey,
            "x-sign: ".$sign,
        );
        $response = Curl::post($this->makepay_url,$data,$herder);
        $response = json_decode($response,true);

        if (isset($response['code']) && $response['code'] == 0) {
            $paydata = $this->getPayData( $response['data']['id'],$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }

    /**
     * newai2_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function newai2_pay($createinfo, $baseUserInfo) {
        $data = [
            "merchantId"   => $this->newai2pay_merchantId,
            "merchantOrderId" => $createinfo['ordersn'],
            "amount"   => bcdiv((string)$createinfo['price'],'100',2),
            "timestamp"    => time().'000',
            "payType"   => 1,
            "notifyUrl"   => $this->newai2pay_notifyUrl,
            "remark"   => 'GAME',
        ];

        $sign_data = [
            "merchantId" => $this->newai2pay_merchantId,
            "merchantOrderId" => $createinfo['ordersn'],
            "amount"   => bcdiv((string)$createinfo['price'],'100',2),
        ];


        $data['sign'] =  Sign::notKsortSign($sign_data,$this->newai2pay_Key,'');

//        $response = $this->guzzle->post($this->newai2pay_url,$data,$this->zr_header);
        $response = $this->guzzle->post($this->newai2pay_url,$data,$this->zr_header);
        $this->logger->error('$response1111:'.json_encode($data));
        $this->logger->error('$response:'.json_encode($response));
        if (isset($response['code']) && $response['code'] == '0' && isset($response['data']['h5Url'])) {
            $this->logger->error('data:'.json_encode($response['data']));
            $paydata = $this->getPayData( $response['data']['orderId'],$response['data']['h5Url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * rrtwo_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function rrtwo_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchantId"      => (int)$this->rrtwopay_merchantNo,
            "merchantOrderId" => $createinfo['ordersn'],
            "amount"          => bcdiv((string)$createinfo['price'],'100',2)
        ];

        $data['sign'] = Sign::notKsortSign($data,$this->rrtwopay_Key,'');

        $data['timestamp'] = time() * 1000;
        $data['payType']   = 1;
        $data['notifyUrl'] = $this->rrtwopay_notifyUrl;
        $data['remark']    = 'PAY FOR GAME';

        $response = $this->guzzle->post($this->rrtwopay_url,$data,$this->zr_header);

        if (isset($response['code'])&&$response['code'] == 0) {
            $paydata = $this->getPayData($response['data']['orderId'],$response['data']['h5Url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }




    /**
     * best_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function best_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchantId"      => (int)$this->bestpay_merchantNo,
            "merchantOrderId" => $createinfo['ordersn'],
            "amount"          => bcdiv((string)$createinfo['price'],'100',2),
            "phone"   => (string)$baseUserInfo['mobile'],
            "name"    => $baseUserInfo['jianame'],
            "email"   => $baseUserInfo['email'],
            "currency"    => 'INR',
            "nonce"    => (string)rand(00000000,9999999999),
            "timestamp"  => time().'000',
            "notifyUrl"  => $this->bestpay_notifyUrl,
        ];
        $dataSign = $data['merchantId'] . $data['merchantOrderId'] . $data['amount'] . $data['nonce'] . $data['timestamp'];
        $data['sign'] = Sign::bestPaySign($dataSign,$this->bestpay_Key);

        $response = $this->guzzle->post($this->bestpay_url,$data,$this->zr_header);

        if (isset($response['code'])&&$response['code'] == 200) {
            $paydata = $this->getPayData($response['data']['platformOrderId'],$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }

    /**
     * zip_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function zip_pay($createinfo, $baseUserInfo) {
        $data = [
            "merchantId"   => $this->zippay_merchantNo,
            "merchantOrderId" => $createinfo['ordersn'],
            "amount"   => bcdiv((string)$createinfo['price'],'100',2),
            'phone' =>  $baseUserInfo['mobile'],
            'email' =>  $baseUserInfo['email'],
            'name' => $baseUserInfo['jianame'],
            "currency"    => 'INR',
            "nonce"    => (string)rand(00000000,9999999999),
            'timestamp' => time().'000',
            'notifyUrl' => $this->zippay_notifyUrl,
        ];

        $dataSign = $data['merchantId'] . $data['merchantOrderId'] . $data['amount'] . $data['nonce'] . $data['timestamp'];
        $data['sign'] = Sign::zipPaySign($dataSign,$this->zippay_Key);

        $response = $this->guzzle->post($this->zippay_url,$data,$this->zr_header);
        $this->logger->error('zip_pay充值数据:'.json_encode($data));
        if (isset($response['code'])&&$response['code'] == 200) {
            $paydata = $this->getPayData($response['data']['platformOrderId'],$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * upi_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function upi_pay($createinfo, $baseUserInfo) {
        $data = [
            "amount"   => $createinfo['price'],
            "merchantId"   => $this->upipay_merchantNo,
            "orderId" => $createinfo['ordersn'],
            'notifyUrl' => $this->upipay_notifyUrl,
        ];
        $timestamp = time().'000';

        $dataSign = $data['amount'] . $data['merchantId'] . $data['orderId'] . $timestamp . $this->upipay_Key;

        $sign = md5($dataSign);

        $herder = array(
            "Content-Type: application/json",
            "X-TIMESTAMP: ".$timestamp,
            "X-SIGN: ".$sign,
        );

        $response = Curl::post($this->upipay_url,$data,$herder);

        $response = json_decode($response,true);

        if (isset($response['code'])&&$response['code'] == 100) {
            $paydata = $this->getPayData($response['payOrderId'],$response['paymentUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * q_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function q_pay($createinfo, $baseUserInfo) {
        $data = [
            "merchantId"   => $this->securitypay_appid,
            "amount"   => bcdiv((string)$createinfo['price'],'100',2),
            "currency" => 'INR',
            "orderNum" => $createinfo['ordersn'],
            "successUrl" => config('host.gameurl'),
            "failureUrl" => config('host.gameurl'),
            "noticeUrl"   => $this->securitypay_notifyUrl,
            "userId"   => $baseUserInfo['uid'],
            "firstName"   => $baseUserInfo['jianame'],
            "lastName"   => $baseUserInfo['jianame'],
            "email"   => $baseUserInfo['email'],
            "phoneNum"   => '91'.$baseUserInfo['mobile'],
            "responseJsonFormat"   => true,
            "timestamp" => time().'000',
            "nonce" => time().rand(111,222),
        ];

        $data['sign'] =  Sign::securityPaySign($data,$this->securitypay_Key);
        $response = $this->guzzle->post($this->securitypay_url,$data,$this->zr_header);

        if (isset($response['paymentRedirectUrl']) && $response['paymentRedirectUrl']) {
            $paydata = $this->getPayData($response['orderNum'],$response['paymentRedirectUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * allin1two_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function allin1two_pay($createinfo, $baseUserInfo) {
        $data   = [
            "app_id"      => (int)$this->allin1twopay_appid,
            "app_order_no" => $createinfo['ordersn'],
            "type_id" => 47,
            "amount"  =>  (int)bcdiv((string)$createinfo['price'],'100',0),
            "notify_url"  => $this->allin1twopay_notifyUrl,
        ];

        $signStr = Sign::dataString($data);
        $data['sign'] = md5($signStr.$this->allin1twopay_Key);

        $response = $this->guzzle->post($this->allin1twopay_url,$data,$this->header);
        $this->logger->error('allin1two_pay:'.json_encode($response));
        if (isset($response['success'])&&$response['success'] == 1) {
            $paydata = $this->getPayData($response['data']['order_no'],$response['data']['pay_url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * vendoo_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function vendoo_pay($createinfo, $baseUserInfo) {
        $data   = [
            "mchNo"      => $this->vendoopay_merchantNo,
            "mchOrderNo" => $createinfo['ordersn'],
            "amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            "currency" => 'INR',
            "productNo" => 'LC',
            "email" =>  $baseUserInfo['email'],
            "attach" => (string)$baseUserInfo['uid'],
            "notifyUrl"  => $this->vendoopay_notifyUrl,
            "firstName"   => $baseUserInfo['jianame'],
            "lastName"   => $baseUserInfo['jianame'],
            "phone"   => (string)$baseUserInfo['mobile'],
            "accountType"   => '1',
            "reqTime" => date('Y-m-d H:i:s'),
            "productInfo" => 'Pay',
            "returnUrl" => config('host.gameurl'),
        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->vendoopay_Key);

        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/payNewOne',['data' => $data,'url' => $this->vendoopay_url],$this->header);
//        $response = $this->guzzle->post($this->vendoopay_url,$data,$this->header);

        if (isset($response['code'])&&$response['code'] == 200) {
            $paydata = $this->getPayData($response['data']['orderNo'],$response['data']['cashierUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * rupeelink_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function rupeelink_pay($createinfo, $baseUserInfo) {
        $data   = [
            "userCode"      => $this->rupeelinkpay_merchantNo,
            "orderCode" => $createinfo['ordersn'],
            "amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            "callbackUrl"  => $this->rupeelinkpay_notifyUrl,
        ];
        $data['sign']  = strtoupper(md5($data['orderCode'].'&'.$data['amount'].'&'.$data['userCode'].'&'.$this->rupeelinkpay_Key));

        $response = $this->guzzle->post($this->rupeelinkpay_url,$data,$this->header);

        if (isset($response['code'])&&$response['code'] == 200) {
            $paydata = $this->getPayData($response['data']['orderNo'],$response['data']['url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * unive_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function unive_pay($createinfo, $baseUserInfo) {
        $data   = [
            "Merchno"      => $this->univepay_merchantNo,
            "Amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            "Traceno" => $createinfo['ordersn'],
            "Pname"   => $baseUserInfo['jianame'],
            "Pemail" =>  $baseUserInfo['email'],
            "Phone"   => (string)$baseUserInfo['mobile'],
            "CountryCode"   => 'IN',
            "Currency"   => 'INR',
            "PayCode"   => 'UPI',
            "GoodsName"   => '3377WIN',
            "NotifyUrl"  => $this->univepay_notifyUrl,
        ];
        $signStr = Sign::dataString($data);
        $data['Signature']  = strtoupper(md5($signStr.'&'.$this->univepay_Key));
        $response = $this->guzzle->post($this->univepay_url,$data,$this->header);
        if (isset($response['status'])&&$response['status'] == '00') {
            $paydata = $this->getPayData($response['payOrderid'],$response['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * no_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function no_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchantOrderNo" => $createinfo['ordersn'],
            "merchantMemberNo" => $baseUserInfo['uid'],
            "amount"  =>  bcdiv((string)$createinfo['protocol_money'],'100',2),
            "language"  =>  'en',
            "coin" => 'USDT',
            "rateType" => 1,
            "protocol" => $createinfo['protocol_name'],
            "notifyUrl"  => $this->nopay_notifyUrl,
            "timestamp" => time(),
            "appId" => $this->nopay_merchantNo,
        ];

        $signStr = Sign::dataString($data);
        $data['sign'] =  hash('sha256', $signStr.'&key='.$this->nopay_Key);

        $herder = array(
            "Content-Type: application/json",
            "version: v1",
            "appId: ".$this->nopay_merchantNo,
        );

        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->nopay_url,'header' => $herder],$this->header);
//        $response = Curl::post($this->nopay_url,$data,$herder);
//        $response = json_decode($response,true);
        if (isset($response['code'])&&$response['code'] == 0) {
            $paydata = $this->getPayData($response['data']['orderNo'],$response['data']['url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }





    /**
     * ms_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function ms_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchantNo" => $this->mspay_merchantNo,
            "method" => 'YD11',
            "merchantOrderNo" => $createinfo['ordersn'],
            "payAmount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            "mobile"   => (string)$baseUserInfo['mobile'],
            "name"   => $baseUserInfo['jianame'],
            "email" =>  $baseUserInfo['email'],
            "notifyUrl"  => $this->mspay_notifyUrl,
            "description" => '3377WIN',
        ];

        $signStr = Sign::dataString($data);
        $data['sign']  = md5(md5($signStr.'&').$this->mspay_Key);

        $herder = array(
            "Content-Type: application/json",
        );
        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->mspay_url,'header' => $herder],$this->header);

        //        $response = $this->guzzle->post($this->mspay_url,$data,$this->zr_header);

        if (isset($response['status']) && $response['status'] == '200') {
            $paydata = $this->getPayData($response['data']['platOrderNo'],$response['data']['paymentInfo']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * decent_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function decent_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchantOrderNo" => $createinfo['ordersn'],
            "amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            "notifyUrl"  =>  $this->decentpay_notifyUrl,
            "payProduct"  =>  'india-upi-h5',
        ];

        $time = time();
        $signStr = Sign::dataString($data);
        $sign =  strtoupper(md5("merchantNo=".$this->decentpay_merchantNo."&appId=".$this->decentpay_appId."&timestamp=$time&$signStr".$this->decentpay_Key));

        $herder = array(
            "Content-Type: application/json",
            "merchantNo: ".$this->decentpay_merchantNo,
            "appId: ".$this->decentpay_appId,
            "timestamp: ".$time,
            "sign: ".$sign,
        );

        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->decentpay_url,'header' => $herder],$this->header);
//        $response = Curl::post($this->decentpay_url,$data,$herder);
//        $response = json_decode($response,true);

        if (isset($response['data']['target']) && $response['data']['target']) {
            $paydata = $this->getPayData($response['data']['orderNo'],$response['data']['target']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * fly_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function fly_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchantId" => $this->flypay_merchantNo,
            "merchantOrderNum" => $createinfo['ordersn'],
            "amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            "callBackUrl"  =>  $this->flypay_notifyUrl,
            "accountName"   => $baseUserInfo['jianame'],
            "phone"   => (string)$baseUserInfo['mobile'],
            "productDesc" =>  '3377WIN',
            "signType"  =>  'RSA',
        ];

        $signStr = Sign::dataString($data);
        $data['sign'] = Sign::Sha512WithRsa($signStr,$this->flypay_Key);

        $herder = array(
            "Content-Type: application/json",
        );
        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->flypay_url,'header' => $herder],$this->header);
//        $response = $this->guzzle->post($this->flypay_url,$data,$this->zr_header);


        if (isset($response['code']) && $response['code'] == 'SUCCESS') {
            $paydata = $this->getPayData($response['platformOrderNum'],$response['paymentLink']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }


    /**
     * kk_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function kk_pay($createinfo, $baseUserInfo) {
        $extra = [
            'userName' => $baseUserInfo['jianame'],
            'userEmail' => $baseUserInfo['email'],
            'userPhone' => (string)$baseUserInfo['mobile'],
        ];
        $data   = [
            "partnerId"      => $this->kkpay_merchantNo,
            "applicationId"      => $this->kkpay_applicationId,
            "payWay"      => '2',
            "partnerOrderNo"      => $createinfo['ordersn'],
            "amount"  =>  (string)$createinfo['price'],
            "currency"   => 'INR',
            "clientIp"   => $baseUserInfo['ip'],
            "notifyUrl"  => $this->kkpay_notifyUrl,
            "subject"   => urlencode('3377WIN'),
            "body"   => urlencode('3377WIN'),
            "extra"   => urlencode(json_encode($extra)),
            "version"   => '1.0',
        ];

        $signData = $data;
        $signData['subject'] = '3377WIN';
        $signData['body'] = '3377WIN';
        $signData['extra'] = json_encode($extra);
        $signStr = Sign::dataString($signData);

        $data['sign']  = strtoupper(md5($signStr.'&key='.$this->kkpay_appKey));
        //$response = $this->guzzle->get($this->kkpay_url,$data,$this->header);
        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testGetCurlUrl',['data' => $data,'url' => $this->kkpay_url],$this->header);

        if (isset($response['code']) && $response['code'] == '0000') {
            $paydata = $this->getPayData($createinfo['ordersn'], $response['data']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }




    /**
     * tk_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function tk_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchant_id" => $this->tkpay_merchantNo,
            "order_id" => $createinfo['ordersn'],
            "pay_type" => 1, //
            "notify_url"  =>  $this->tkpay_notifyUrl,
            "return_url"  =>  config('host.gameurl'),
            "amount"  =>  $createinfo['price'],
            "currency"  =>  'INR',
            "name"   => $baseUserInfo['jianame'],
            "phone"   => (string)$baseUserInfo['mobile'],
            "email" =>   $baseUserInfo['email'],
        ];


        $data['sign'] = Sign::asciiKeyStrtolowerSign($data,$this->tkpay_Key);

        $response = $this->guzzle->post($this->tkpay_url,$data,$this->zr_header);

        if (isset($response['code']) && $response['code'] == '200') {
            $paydata = $this->getPayData($response['data']['id'],$response['data']['pay_url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }

    /**
     * kktwo_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function kktwo_pay($createinfo, $baseUserInfo) {
        $extra = [
            'userName' => $baseUserInfo['jianame'],
            'userEmail' => $baseUserInfo['email'],
            'userPhone' => (string)$baseUserInfo['mobile'],
        ];
        $data   = [
            "partnerId"      => $this->kktwopay_merchantNo,
            "applicationId"      => $this->kktwopay_applicationId,
            "payWay"      => '2',
            "partnerOrderNo"      => $createinfo['ordersn'],
            "amount"  =>  (string)$createinfo['price'],
            "currency"   => 'INR',
            "clientIp"   => $baseUserInfo['ip'],
            "notifyUrl"  => $this->kktwopay_notifyUrl,
            "subject"   => urlencode('3377WIN'),
            "body"   => urlencode('3377WIN'),
            "extra"   => urlencode(json_encode($extra)),
            "version"   => '1.0',
        ];

        $signData = $data;
        $signData['subject'] = '3377WIN';
        $signData['body'] = '3377WIN';
        $signData['extra'] = json_encode($extra);
        $signStr = Sign::dataString($signData);

        $data['sign']  = strtoupper(md5($signStr.'&key='.$this->kktwopay_appKey));
        //$response = $this->guzzle->get($this->kktwopay_url,$data,$this->header);
        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testGetCurlUrl',['data' => $data,'url' => $this->kktwopay_url],$this->header);

        if (isset($response['code']) && $response['code'] == '0000') {
            $paydata = $this->getPayData($createinfo['ordersn'], $response['data']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * one_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function one_pay($createinfo, $baseUserInfo) {

        $data   = [
            "mchId"      => $this->onepay_merchantNo,
            "txChannel"      => 'TX_INDIA_001',
            "appId"      => $this->onepay_appId,
            "timestamp"      => time(),
            "mchOrderNo"      => $createinfo['ordersn'],
            "bankCode"   => 'UPI',
            "amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            'name' => $baseUserInfo['jianame'],
            'phone' => (string)$baseUserInfo['mobile'],
            'email' => $baseUserInfo['email'],
            'productInfo' => '3377WIN',
            'notifyUrl' => $this->onepay_notifyUrl,
        ];

        $signStr = Sign::dataString($data);
        $data['sign'] = Sign::Sha256WithRsa($signStr,$this->onepay_Key);


        $herder = array(
            "Content-Type: application/json",
            "lang: en",
        );
//        $response = Curl::post($this->onepay_url,$data,$herder);
//        $response = json_decode($response,true);

        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->onepay_url,'header' => $herder],$this->header);

        if (isset($response['status']) && $response['status'] == '200') {
            $paydata = $this->getPayData($response['data']['platOrderNo'], $response['data']['link']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }




    /**
     * global_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function global_pay($createinfo, $baseUserInfo) {
        $data   = [
            "mchId"      => $this->globalpay_merchantNo,
            "signType"      => 'md5',
            "mchOrderNo" => $createinfo['ordersn'],
            "amount"  =>  $createinfo['price'],
            "channelType"  =>  'wakeup',
            "terminalType"  =>  'MOBILE',
            "notifyUrl"  =>  $this->globalpay_notifyUrl,
            "rand"   => time().rand(1111,9999),

        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->globalpay_Key);

        $herder = array(
            "Content-Type: multipart/form-data",
        );

        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/globalpay',['data' => $data,'url' => $this->globalpay_url,'header' => $herder],$this->header);
//        $response = Curl::multipartFormData($this->globalpay_url,$data,$herder);
//        $response = json_decode($response,true);

        if (isset($response['code'])&&$response['code'] == 0) {
            $paydata = $this->getPayData($createinfo['ordersn'],$response['data']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * a777_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function a777_pay($createinfo, $baseUserInfo) {
        $data   = [
            "app_id"      => $this->a777pay_merchantNo,
            "merchant_order_id" => $createinfo['ordersn'],
            "amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            'customer_name' => $baseUserInfo['jianame'],
            'customer_phone' => (string)$baseUserInfo['mobile'],
            'customer_email' => $baseUserInfo['email'],
            "pay_channel"      => 'INDIA_NATIVE',
            "notify_url"  =>  $this->a777pay_notifyUrl,
            "page_return_url"  => config('host.gameurl'),
        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->a777pay_Key);


        $herder = array(
            "Content-Type: application/x-www-form-urlencoded"
        );
        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->a777pay_url,'header' => $herder],$this->header);
//        $response = $this->guzzle->post($this->a777pay_url,$data,$this->header);

        if (isset($response['code'])&&$response['code'] == 200) {
            $paydata = $this->getPayData($response['data']['merchant_order_id'],$response['data']['pay_url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * masat_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function masat_pay($createinfo, $baseUserInfo) {
        $data   = [
            "appId"      => $this->masatpay_merchantNo,
            "orderNumber" => $createinfo['ordersn'],
            "amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            'nameSurname' => $baseUserInfo['jianame'],
            'phone' => (string)$baseUserInfo['mobile'],
            'userId' => $baseUserInfo['uid'],
            "notifyCallback"  =>  $this->masatpay_notifyUrl,
        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->masatpay_Key);


        $herder = array(
            "Content-Type: application/json"
        );
        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->masatpay_url,'header' => $herder],$this->header);
//        $response = $this->guzzle->post($this->masatpay_url,$data,$this->zr_header);

        if (isset($response['code'])&&$response['code'] == 10000) {
            $paydata = $this->getPayData($response['data']['platformOrderNumber'],$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }





    /**
     * ok_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function ok_pay($createinfo, $baseUserInfo) {
        $data   = [
            "appId"      => $this->masatpay_merchantNo,
            "orderNumber" => $createinfo['ordersn'],
            "amount"  =>  bcdiv((string)$createinfo['price'],'100',2),
            'nameSurname' => $baseUserInfo['jianame'],
            'phone' => (string)$baseUserInfo['mobile'],
            'userId' => $baseUserInfo['uid'],
            "notifyCallback"  =>  $this->masatpay_notifyUrl,
        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->masatpay_Key);


        $herder = array(
            "Content-Type: application/json"
        );
        $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->masatpay_url,'header' => $herder],$this->header);
//        $response = $this->guzzle->post($this->masatpay_url,$data,$this->zr_header);

        if (isset($response['code'])&&$response['code'] == 10000) {
            $paydata = $this->getPayData($response['data']['platformOrderNumber'],$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * 随机生产几位字母
     * @param $length
     * @return string
     */
    private function generateRandomString($uid= '',$length = 6){

        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString.$uid;

    }

    /**
     * 给充值用户随机生产email
     * @param $length
     * @return string
     */
    private function getEmail(){
        $shuff = '@gmail.com';
        $RandomString = self::generateRandomString(rand(1000,9999),6);
        return $RandomString.$shuff;
    }

    /**
     * 得到支付数据
     * @param $tradeodersn 三方订单号
     * @param $payurl 三方h5支付链接
     * @param $appPayUrl 三方APP支付链接
     * @return void
     */
    private function getPayData($tradeodersn,$payurl,$appPayUrl = ''){
        return ['tradeodersn' => $tradeodersn,'payurl'=>$payurl,'appPayUrl' => $appPayUrl];
    }

}