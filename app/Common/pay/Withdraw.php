<?php
declare(strict_types=1);
namespace App\Common\pay;


use App\Common\Common;
use App\Common\Guzzle;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use  App\Common\Curl;
use function Hyperf\Config\config;

class Withdraw{

    #[Inject]
    protected Common $Common;
    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected LoggerInterface $logger; //存储日志

    //rr_pay
    private string $rrpay_url        = "https://top.adkjk.in/rpay-api/payout/submit";                 //提现地址
    private string $rrpay_merchantNo = "999";//测试
//    private string $rrpay_merchantNo = "1103"; //正式
    private string $rrpay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/rrpayNotify";  //回调地址
    private string $rrpay_Key        = "abc#123!";//测试
//    private string $rrpay_Key        = "7YzlqZ6xhpkhj5B0"; //正式




    //ser_pay
    private string $serpay_url = "https://paypout.metagopayments.com/cashier/TX0001.ac";//下单地址
    private string $serpay_key = "353FD9914A2C8C619FE6F2E1B8CD45E0";//商户密钥的值
    private string $serpay_orgNo = "8210600739";//机构号
    private string $serpay_mchid = "24082600000048";//商户编码
    private string $serpay_backUrl = "http://124.221.1.74:9502/Withdrawlog/serpayNotify";//回调地址
    private string $serpay_account = "2408260000004802138";//代付子账号



    //sertwo_pay 唤醒
    private string $sertwopay_url = "https://paypout.metagopayments.com/cashier/TX0001.ac";//下单地址
    private string $sertwopay_key = "C32FA37CC0421E6231E6E7EDC8C26C5F";//商户密钥的值
    private string $sertwopay_orgNo = "8241100128";//机构号
    private string $sertwopay_mchid = "24110100000126";//商户编码
    private string $sertwopay_backUrl = "http://124.221.1.74:9502/Withdrawlog/serpayNotify";//回调地址

    private string $sertwopay_account = "2411010000012602138";//代付子账号


    //tm_pay
    private string $tmpay_url = "https://beespay.store/gateway/v1/pay/transfer";          //下单地址
    private string $tmpay_merchantNo       = "1M6S80Q0T1";                                   //商户号测试
//    private string $tmpay_merchantNo       = "1HHF923W30";                                   //商户号正式
    private string $tmpay_appid   = "M1E3123051824F36ABFBB75250B0A0DC";  //app_id 测试环境
//    private string $tmpay_appid   = "37B6BD129BE9447E9070CFF6D69E956F";  //app_id 正式环境

    private string $tmpay_Key    = 'KM@8gfWwK0WkZ1#!ux6svzV1ADV:Ftz!*9%A%yW@VZjG=lYMxyIjTB0fw0u#cz0l8RX7ir1#J7fjwop%XJ%f:cZUr$:g041sx^Se3f$X*?tNv3*1DFbT7%R39VTynS8D';//测试
//    private string $tmpay_Key    = 'KZd!m7L?eL:A7lCH#vQHOCGC:AAUMSJ:$zX^gOREKpNuDWyl^h*ZU?LWOrbBYK7n00avVwwA=hAe~ZOCW7ll8gCY!l@5sSwRr9vQH8Af&Xd5NH5J:VNsgqK_bW!k1fdD'; //正式
    private string $tmpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/tmpayNotify";



    //waka_pay
    private string $wakapay_url = "https://wkpluss.com/gateway/";          //下单地址
    private string $wakapay_merchantNo       = "8890964";                        //商户号
    private string $wakapay_Key    = "e10418274f190d3fcfba3d58556978e9";  //商户秘钥
    private string $wakapay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/wakapayNotify";


    //fun_pay
    private string $funpay_url = "https://api.stimulatepay.com/api/payout/create/order";          //下单地址
    private string $funpay_merchantNo       = "pcI98HftC1SLR8qj";                        //商户号
    private string $funpayKey    = "5e48c9b9170f493185f8c9a96c45b487";  //商户秘钥
    private string $funpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/funpayNotify";


    //go_pay
    private string $gopay_url = "https://rummylotus.online/api/deposit/create";          //下单地址
    private string $gopay_merchantNo       = "2023100015";                        //商户号
    private string $gopayKey    = "d65cc4596f20499ea4af91a998321f5c";  //商户秘钥
    private string $gopay_notifyUrl   = "http://124.221.1.74:9502//Withdrawlog/gopayNotify";


    //eanishop_pay
    private string $eanishoppay_url = "https://gateway-sandbox.eanishop.com/pg/v2/payout/create";//测试地址
//    private string $eanishoppay_url = "https://gateway.eanishop.com/pg/v2/payout/create";//正式地址
    private string $eanishoppayAppId    = "170baa8e418a47bf84464c07d1819d73";
    private string $eanishoppayAppSecret    = "e77e54a0ef3642298cad797bc772602c";
    private string $eanishoppay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/eanishoppayNotify";


    //24hrpay
    private string $hr24pay_url = "http://test-pay.24hrpay.vip/api/unified/agentpay/apply";          //测试
//    private string $hr24pay_url = "https://pay.24hrpay.vip/api/unified/agentpay/apply";          //正式
    private string $hr24pay_mchId    = "20000170"; //测试
//    private string $hr24pay_mchId    = "50000567"; //正式
    private string $hr24pay_appKey    = "QG8OZF7SDXVWEEOMQ6R9OPEWMJLLUUXLAA2ZDHHMI3KUBUYYFMGGDRPC4FEXMFX93RIJJYQTPJRTCWNXREB2IU4PCW56S5FW7ZVQ5RHL9J5N407ZG4SIUG22ME5IBITJ"; //测试
//    private string $hr24pay_appKey    = "CABS32R2LZEGAQWRUMM1N7OLPHJ512N1Z9EL7BHYT1ZBQLHYNE1SXW8WF0LBO0XYFQLOT1VLHK0YUWO4T89G8QJC12SL8UCGSOKPAXZZFJTLZWVJ82K73UR6EUKLJSPK"; //正式
    private string $hr24pay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/hr24payNotify";



    //ai_pay
    private string $aipay_url = "https://aipay.cool/gateway/";          //下单地址
    private string $aipay_merchantNo       = "862360";                        //商户号
    private string $aipay_Key    = "517faa1fd5cbd6b13541b90f97fe9625";  //商户秘钥
    private string $aipay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/aipayNotify";



    //x_pay
    private string $xpay_url = "https://pay.xpay.wang/api/transferOrder";   //下单地址
    private string $xpay_mchNo       = "M1669706824";   //商户号
    private string $xpay_appId       = "6385b4487bd27c0dd2ccb6fb";
    private string $xpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/xpayNotify";                 //回调地址
    private string $xpay_Key    = "Tc5YVPZKwck6esXi697jxMDTjvc7ojaWOluTFaiq4kvuO7rmvLIAmSqDQCanXu5NuItfanr4PPEs4bNmkXqTsRMiPiz6vfJI2JNj1AEDU7guTdsQ9IQ4fmFVRfJIoOSz";


    //lets_pay
    private string $letspay_url = "http://api.letspayfast.com/apitrans";                                   //下单地址
    private string $letspay_mchId       = "722931450180";
    private string $letspay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/letspayNotify";               //回调地址
    private string $letspay_Key    = "YFYCHSXAY6DPNS5A0NUYGDD66NSUFXFCUIPCLTXWNQJF0HG5V5IMQKC7AIC071HXZZERDBOBLPIOQUUROFA2AIQKUZDWJTP3N06ZKJPXWJSBJRFORFLGDY5MP3U7VPHE";




    //letstwo_pay  lets_pay原生
    private string $letstwopay_url = "http://api.letspayfast.com/apitrans";                                   //下单地址
    private string $letstwopay_mchId       = "723450269337";
    private string $letstwopay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/letspayNotify";                //回调地址
    private string $letstwopay_Key    = "IZUGUT9LZR6LQIUGV6HMHJZUPREJTNWAZ8R99SJQNFCUBOUYKEK0LZUWWJ6GNIE6ENZNTKX9DKCYBRRJ0VJWXER9S1OVEVGLKWINIMRCL1HEHUJPKJ5IX0ZGX59SE0TL";

    //dragon_pay
    private string $dragonpay_url = "https://dragonpayment.net/api/inr/payment";                                   //下单地址
    private string $dragonpay_appKey       = "53E64C7EFACD3F30D1";
    private string $dragonpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/dragonpayNotify";             //回调地址
    private string $dragonpay_secret    = "81d51489d6bbe4045e3c9b6d6e6067c6068e6e4b";

    //ant_pay
    private string $antpay_url = "https://api.antpay.io/v2/withdraw";                                   //下单地址
    private string $antpay_merchant_code       = "AM1723630965609";
    private string $antpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/antpayNotify";             //回调地址
    private string $antpay_key    = "43e795347e032fa4d439706ac01309f5";

    //ff_pay
    private string $ffpay_url = "https://api.wepayglobal.com/pay/transfer";                                   //下单地址
    private string $ffpay_mchId      = "999100111";//测试
//    private string $ffpay_mchId      = "100777805";//正式
    private string $ffpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/ffpayNotify";             //回调地址
    private string $ffpay_key    = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; //测试
//    private string $ffpay_key    = "UMAB1GFQ1KGBPOANDSLTBMGYCOGFZRBF";//正式



    //cow_pay
    private string $cowpay_url = "https://pay365.cowpay.net/v2/withdraw";                                   //下单地址
    private string $cowpay_mchId      = "1723714607199";

    private string $cowpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/cowpayNotify";             //回调地址
    private string $cowpay_key    = "9c8303152d19b5aac5336bcd5f16fc34";



    //wdd_pay
    private string $wddpay_url = "https://www.wddeasypay.com/out/orders";                                   //下单地址
    private string $wddpay_mchId      = "10238";
    private string $wddpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/wddpayNotify";             //回调地址
    private string $wddpay_key    = "sLwEMOQjDKorywnP";


    //timi_pay
    private string $timipay_url = "https://www.timipay.shop/lh_daifu/agent";                                   //下单地址
    private string $timipay_mchId      = "529098346";
    private string $timipay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/timipayNotify";             //回调地址
    private string $timipay_key    = "56c773f8e761b72c722e1ff1991d2547";


    //newfun_pay
    private string $newfunpay_url = "https://api.funpay.tv/singleOrder";                                   //下单地址
    private string $newfunpay_mchId      = "1009";//测试
//    private string $newfunpay_mchId      = "1059"; //正式
    private string $newfunpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/newfunpayNotify";             //回调地址
    private string $newfunpay_key    = "MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAKZpCRkOMaMccbljkXbV+JK0Hym/k54loJ8SKkDAgWvgHPYKplgCe/GtZrSeZQDnWC5qP233uM3DgL+OItnfM6AEGVI/AVd9fno2Jz11Qzae8xSELjfMs2ZFfXHb3DjuXODU8Zd9hh4HwqgXt+9LTEdyMAf0E41vODQtgI+cv5KJAgMBAAECgYAG6ndB/vtZyl/WPZNuZebGag1K1eG+Vn/+Eb+HILkAO7iDEomRP3aD4m9R8wHtmgUUgL6Rb78oG1zpbnB3r4qx+soc1Fp7ISZJzQVkhJdq7HWnqODNNqs6XxQZGR/tjywCQXrkB02HRTQDEf4eIRm2WFqpCZJTu7bWcVTmOwzL5QJBAOwKfNNi5Yh2z6Pr+5d22+MLlnXxGmJC935f4jImSybzv624+yb4Av9Vy0rhNaRkEdjjfyyFiG9H/vb3M9vSL7MCQQC0e0W6IoUKa61kqLTBk2D68vq7Q5d3PjzfAflRs4vMtvn5x0XBFqbipUtFOTdcKva2b4ZYZFJRsCdpaooIg7bTAkEAytkkdwFZotIAFa5ac8tIorE1p7wA4YsNaIR8Pn7cPOhixKfg5pdi9A3F/F7Ym6MIF21CwH8tRf0IZzMAVRwnswJBAIXT8rw25Jf5iDVfs8jmU79BdRJu6F2PVOu4NvuSO1OtSmcgkGTBOzZMgyftaVN6uD5HLENXAIN6L39HdNsjb+kCQB6895oOZ/yxhPIDMGP2d1lZxILQjS6r6/FWpfRSHLaag0pa6EK8bab/8CxL104qnXqg3KDEf/CqDo/EJpfnf7U=
"; //测试
//    private string $newfunpay_key    = "MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAJMRc60xlWTJmdtm6jFkbk2LNiax2d/bFWw46oXydTuR4HXU2eX0QVpClogKn2CdkA4vwmYt9pc8POwEfwBqq3jMz8Si/5i6YIskdmnktiNa6D/lB0K22CFWQxo+ks/BH7k+pas2+IyL6KO2EHsSwG/67jrjnB3XTLqIndvYNV+zAgMBAAECgYAabL7Tpj6ZEu4tsWKwCEMXdMWAk2E56zQAs4NUGPn+f5oMofea7VXWwXMps3rqkbUCD4vG70hI6T5rC+3D5ea0Mpk5YFd2HW8LEyS2VBAcADkfLYRf6KIChFQ3fvTiqxP1qmVyO7mDlxkuLgMOPSX5kp6JL/hsf3esS+gNcvqFLQJBALvxkkh/JBT0MRMp9zb33ma0Yl/gqqvU9ujqmVzgEknKpNFF2kVpWBdJ56ORTEwgR8BuXisYYfQdsfCGUy/seh8CQQDIUraK4H5HgLYRQoCkBBNFSlMvZV60FicEd3RHjY0QDa4fJrD+LJMFt9loAZXBC226uxYXtjyc1w6EPFT9z4/tAkAzPa2wblmcDOfEXdC0/+d3AP9BPLPLnYikADJIDB9wVvuQwwa7nfkSgGfTRK4Uo0hswqqR/VfXgrEc7sKHcmXpAkBkFi9uI8v0HbLZ3Mg5KnAWZpQ5UgSHJapI6QYH2glow+0DU2mLFOpAKSNOe7w+v18LtP3MyxhtpGV0XFB6n4HhAkEArnUMYgOQHWJHqNrxoDuzYA3alfpHe8/S7VHZ3oPB3FAmpLQDx81C3+7q5MOlHASTU8qvMNEirJeAW3wuyhteaw==";//正式



    //simply_pay
    private string $simplypay_url = "https://api.simplypay.vip/api/v2/payout/order/create";                                   //下单地址
    private string $simplypay_appId      = "3846f553e7a1230c18308af654b688bc";
    private string $simplypay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/simplypayNotify";             //回调地址
    private string $simplypay_key    = "158aa68d9454f18962e14c7a541e73a9";


    //simplytwo_pay
    private string $simplytwopay_url = "https://api.simplypay.vip/api/v2/payout/order/create";                                   //下单地址
    private string $simplytwopay_appId      = "7184076527b71034f6a7bdf437c2acf0";
    private string $simplytwopay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/simplypayNotify";             //回调地址
    private string $simplytwopay_key    = "8fac2bd53e50c19c4ad9e1d7a030a3a6";



    //lq_pay
    private string $lqpay_url = "http://lqpay.txzfpay.top/sys/apple/api/draw";                                   //下单地址
    private string $lqpay_deptId      = "1710169284135714818";  //机构号
    private string $lqpay_mchId      = "22000023";
    private string $lqpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/lqpayNotify";             //回调地址
    private string $lqpay_key    = "MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAN231QFbn8xmA83LecawDmnoHPD4asBG79dJkknggqQgW8yG1dRNnevsJRPZyDMQMRObPnIYIB0KOQ3ewyUjfrEsphJdTeIZUn1zYfuTPAkRsjv8w5xvMwG3WF7RSDh4p7wnXleowmxw2D5U5/k3cahfL5SNqaJXBzLVprHRZK3DAgMBAAECgYAZnJs38XAXqe8ljiwuhfbcSApT0bZvKKKbAW4rJ4qfz/cavLaltCOadahgzyb/sw6gP64qetv2ztABaKqtNxjywmfXZkG5eKk5sHUdHJAPXe5xGvVbXcXtCg9uFLuolu0Qhh6g4MuEXfZ5/HhIMHopJKW1eneYOoJJKr+3miopxQJBAPjk+qZ2QMLLx2E9VCnK7sZCkZA6UntagvaMg7ml9pWjxRrTDjWqBBcl3XyXvsj848W21hdCwWdhUKfnPsoiTq0CQQDkDDy40PpPc77soa8u+BRcP7smKuYf9wluSaCv/4sYKw6yWmPLX8leHXCWf9oZbqSvJwH8bVBaxl/NT198rKwvAkBz4aB1ul8CkwAcXQJ/htVPB5VgUlcuyYBqLBf0arn5B8vwZk2aXLMU1/NcXAZe66dc2XiqUdFcQanc0sSgNgLtAkBipFhvqRVc4LgpKxbXvj8wV/Df5ZZ9JSJTLk3vUx4baiSFSUv5YIl9yEY3Ez6H2bAqgzj8s1wap8wwxrCLATXJAkA7DE4k6B9cSwndrS2djI9JHItrLUkoFNjVq79frH8PcFXVJG7c3mG3nxXxb8n4dl5qt8W9Iy6hE3zCilz7AcRb";




    //threeq_pay
    private string $threeqpay_url = "https://pay.3qpay.org/api/payout/pay";                                   //下单地址
    private string $threeqpay_appId      = "66c81be0e4b00bf3fc383a31";
    private string $threeqpay_mchId      = "M1724390368";
    private string $threeqpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/threeqpayNotify";             //回调地址
    private string $threeqpay_key    = "HsSxrEU6sHIW32udk8xKR055q5lqzqtJzbCoMnBFmrXAGc2TvAFrMRKONSuXfuMaBKh8f2RvEP0o3buoKl7i5WINymzPnkYMgg5BPyE5FDb7E5UjSm9ePjEDCrKDUjsK";



    //show_pay
    private string $showpay_url = "https://api.newbhh.com/v2/test_payout"; //测试
//    private string $showpay_url = "https://api.newbhh.com/v1/payout/withdraw"; //正式
    private string $showpay_mchId      = "100"; //测试
    private string $showpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/showpayNotify";
    private string $showpay_key    = "MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBALSVRoD8z7GGiQkaltBPDRSomwtn8Ponh1m3EwzvBpKvJNxW8FDvg+PQvQnAYD2XWImnTRN81cwM0rRIF8e2U85oybQzLTjrGeHkyu0RQvmvhUJrKh9UxI6Hd3yiEyN0jK7EXhIMouEpbykRcfeBDngk3ZP7U+/nKKd/NoZj3UwlAgMBAAECgYAfG+ArdGHrOTv+P4Bfnl6ogmtDScKvtYORpwI3Ji5Bsr5s3uVDbB+SbXFDbsqlkZ8FB7c1djn2jvb1a738/6HsEEJAQA+UpBz6hignnCifCWA/RYuRSrXy0zmPuiQHtnK01xooN7U6WurV9vWM4ptZCyrV3GnuoNrkoU5uQDE8QQJBAPb5cqFbEw/08rbfDVBmaWwC7nwyL/eu8+o5utOofV9JBlx1p0oSDYN4sx+rEpSygDkG3vLbFtKX6nZPR7tiE7kCQQC7LrVXO5IFvIZCZ3Usj/DEroeAQBzvFTTSkFzo3G9VvgclZwEB12PEUfTtwHVEsgzoE9DCRnDNmouwiacVsgnNAkEAt0O9Cvzg9UtHO+niIFIOUmcOfrxjGcEKIDl8aAk0FxvCC6QGYhFpU7CiApLYM90NBsQRdlaa5eRyyB3mVabeiQJADREaqadH70yU1sfgHydBOImyfdp76pjBYj2frsXMo+CrIQpKwLUnisnp3jsENLJ1QjI37Yf7Ue8K91z0pAgUtQJBAI0lxUtf2Du/ML0to7wO+Ee646ERX1bhrovOt67kdR6wnm7Mas871SSoRRZ4owrKgWkWsISmhG+ze2kkVbyfwDk="; //测试
//    private string $showpay_key    = "MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAM+Iv1PZpepReT7AwWZTeuRSGNEKtrJS/U/DWSg16sjAFeDcoOG7FLeKsNQTeCJm5Vt8MKblpJUF4wRFytrvDF02reCP7ceUkGq3vwClsF7tE2wfcEpThMHFoGVhRsxz6cCFdESL/3C79PkkMVOeKkxYphzcybxeVJmfGsXj8CKdAgMBAAECgYEAkrl9e0y/TqB3dfRVR4Dxu4aHYROQAxdFXCUiXZlw+qlDTozteWoIxZLaXbW+E6Rnt3xf8T8eUvWsgJLPTmX/eIhkZSx+kvKfdJ/xKBEC0kUl6xgnaFWfZ7rzgG2Z3gLiP+6gd/Pfah43XVK3AeFN2KJZP8GNG/EaMjUmYYHozwECQQD6pyhv6c22BRUv5vUBA0LTki5+fcxpRHPmuqran537UQSQ/7prjxWROXhGZjKL45xzkFUF6ksb5pNAkxUs7vI9AkEA0/Yc+a8MG0j1rz01xAVOLecIqikLQ3X9WI2jzPmS73N9klluInnCiFSbQPpuiumlDbTNsQg7RFzg98sGzizX4QJAGdi+3Lt5UPm5M5VXUmFptLNwQ+7o8znx0asSDzVCbzXtiJ42NP0uNil885V6RN6VtXz+p3t/f0MJkDEaj+Wb6QJAIoqg/i+AkZG6N+yJroAO1Xwo9VHq+/tmZd/vKaAiSdNQS2E3iXa+NOlUw6oMCac5tpoYSxlET0ezga4cVc0JAQJARx4/vNVQVt4ukNgYlICLYAwhmnw3Xk1u0/HCymLXGswyQz2Jc3vZo72vkLfoh/lBhx2pRQ04JpRD5RuBGNj5aw=="; //正式



    //g_pay
    private string $gpay_url = "https://api.gpayindia.com/admin/platform/api/out/df";
    private string $gpay_mchId      = "1828416315993178114";
    private string $gpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/gpayNotify";
    private string $gpay_key    = "0356d6cd250eee7ba9ef9f7a1aa10250";



    //tata_pay
    private string $tatapay_url = "http://meapi.kakamesh.com/pay_api/PWithdraw";
    private string $tatapay_mchId      = "20000034";
    private string $tatapay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/tatapayNotify";
    private string $tatapay_key    = "9DC31B9E08D243E297C040D105DD3B1E";

    //pay_pay
    private string $paypay_url = "https://api.paypayonline.vip/api/payOutOrder";
    private string $paypay_mchId      = "3132FB00A9144EABA6E5243DA32FA23F";
    private string $paypay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/paypayNotify";
    private string $paypay_key    = "3CxPH8MjrtWxU3chLfbA3t4cbcH43OFo4gf4U1gOhtEMgTS8grP9iOTelwLoKWGy";



    //yh_pay
    private string $yhpay_url = "http://gateway.yhpay365.com/api/remit";
    private string $yhpay_mchId      = "91000005"; //测试
//    private string $yhpay_mchId      = "91000048";  //正式
    private string $yhpay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/yhpayNotify";
    private string $yhpay_key    = "3jdS5RnwUnSNsOUQvfICZhps";//测试
//    private string $yhpay_key    = "7jqPfsyzqLSfuPlKpxkuEpQk";//正式




    //tmtwo_pay
    private string $tmtwopay_url = "https://beespay.store/gateway/v1/pay/transfer";          //下单地址

    private string $tmtwopay_merchantNo       = "192UNCC334";                                   //商户号正式

    private string $tmtwopay_appid   = "3E2591A316794E53969538D4CFF849D8";  //app_id 正式环境

    private string $tmtwopay_Key    = 'K#wuMZPkbp8HD^TipRxv1R2~_Ux*Ua^JlSxZrLok=7^3urQ%QG_qpxfCQYPS0A7%tcWDSIea%Z7g1k!xOkAuRTpk&sWcWm_k6::41?nB$ewpA$*EBj7db@S~5UPTgefD'; //正式
    private string $tmtwopay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/tmpayNotify";



    //newai_pay
    private string $newaipay_url = "https://gtw.aipay.fit/api/payout/pay";          //下单地址
    private string $newaipay_merchantNo       = "M1727330921";                                   //商户号正式
    private string $newaipay_appid   = "66f4fa69e4b01633f15e34c0";  //app_id 正式环境
    private string $newaipay_Key    = 'KvwcOrtCAbrIqFB0Dsp5QrNGUSYmqsWXGG5owHPjPLit8L7AxkM7YNUSupLnBA1LCLaMl3qgwrRwS4HkRSQJAYYvdIoG3KPxklnHz7G48YaHi2E7dE2g2HXdP8DlceTy'; //正式
    private string $newaipay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/newaipayNotify";


    //allin1_pay
    private string $allin1pay_url = "https://app.allin1pay.com/order/withdrawal/create";          //下单地址
    private string $allin1pay_appid   = "197";  //app_id 正式环境
    private string $allin1pay_Key    = 'f5baf5bbaf9f6d8eb6420684a60a717d'; //正式
    private string $allin1pay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/allin1payNotify";



    //make_pay
    private string $makepay_url = "https://novo.txzfpay.top/sys/zapi/withdraw";          //下单地址
    private string $makepay_appkey   = "1829113188401680385-1840045975921152002";  // 正式环境
    private string $makepay_Key    = '04003728683241822137002479242609'; //正式
    private string $makepay_notifyUrl   = "http://124.221.1.74:9502/Withdrawlog/makepayNotify";



    //rrtwo_pay
    private string $rrtwopay_url        = "https://top.adkjk.in/rpay-api/payout/submit";                 //提现地址
    private string $rrtwopay_merchantNo = "1347";//

    private string $rrtwopay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/rrpayNotify";  //
    private string $rrtwopay_Key        = "A2SzHXv8U8EBPKH6";//



    //best_pay
    private string $bestpay_url  = "https://gateway.bestpay-cus.com/payment/payout";                 //下单地址

    private string $bestpay_merchantNo = "67";

    private string $bestpay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/bestpayNotify";  //回调地址
    private string $bestpay_Key        = "MIICeQIBADANBgkqhkiG9w0BAQEFAASCAmMwggJfAgEAAoGBALB+zfoVSdUZKUladjZZOL68mzCcvCN2TVvIZTd2OlPnSMTYvRKy+WXNI3FyZCTiXxjDgYhNXNRUgrz7eRzKYGUEAAbJW6UIRxQlCvXzInDanoA5xQpGJO1VuyQq2khX2FuZHnjsNhzghSeufEwsXoi+IrpTGB7irhTZMY4kucHRAgMBAAECgYEAqAG9Lw7uvmR6MbJkDu41nxNIoyi/yv4FO5ZyCy6G7XGfiopKyS8HSwnQcGCUxaubHKaWeloyQIjF/wFe07ItuMY6Z/nFrrzBfSzve29UsQx/bRPD8bRwMjscyIVhTfUrj97vODByWbP6MqVByfvRkMRyyQUNz60jc8Ow7BowfgECQQDVotdAkEawM8A1lPxcr0YlgeaN6lIkUf/W9ftDiAgtKPpft+lstCKF4R9zLZl0BD8niFpzCxm9pipXcVa7Ap6hAkEA036GtJGBof3oWdJ5xWTbLxmhjKFf04U4WuFkykbughvtPlhUNAEY3NSVMvaOlfuyveFD+c8+BBZLl5daqXhFMQJBAMHfP1w2EhBBRoLZq5Mo9I2BLwtGxDh1uakIHXeRcWoaL+zBZ7HgXxwDypipnwKr/+wOT5brUfbLXs1v63dWz0ECQQC5yLHIOPGpPYQ4Mz4o+lnYXCmfgbrN8n74xnplfj3SKXoUhD8jl7shcdTGefPzKLFxP0sZTMXrjTJGLfzEVhRhAkEAzNi5Znfp5PoPsmCMcn3rLDFK6lJevroeAOfhgb11bwKjLwhFJtIHG9EWJ+hhMuTu4bLGZRg6aE3Woo0uSWycRw==";//测试

    //zip_pay
    private string $zippay_url  = "https://gateway.zippay-in.com/payment/payout";                 //下单地址

    private string $zippay_merchantNo = "483";

    private string $zippay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/zippayNotify";  //回调地址
    private string $zippay_Key        = "MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAKg/rFB1IrwElT/dv7PdmgzjrN0SaCEivGFcmQccIjpUg1ZY2UgWKm95ZH/uM7obiXxPV5jA0w7Tyk8vooY5+IIUiZhlF7WcyQqV6hjZQxQW99lsQDTas+94xNDjZU4IOfm8wYDV6HfcrdZ//kSJajMNiYct8qj9n2JkPKEly2MlAgMBAAECgYALvB0to23fxUYPpUzIo80p1vtok+8VWJHhDI9T0p+Eh/59GEdXYsxk6AedcKTE90S4meQXMGPIJfd3XHAugn6VnxeZQXpQqoIl0YCzl0Fxlptt70rRG4DJPfqdDArIoU8V0Pi9qVpV4hFTRNvd4KaRAoY7JqGZz04yafWJlNR4oQJBANaXM5tQRiX2+PsLuCdqRLoh4GPx43USkqQZJN34bs1c9ls3UXuzYFOgDM/Yz9UCO8caI9dXpPFeLXOP3BsvzL0CQQDIty06CaAkgNfFZ04ozNVoijksjiQ9JP0+MUyNMQVO7d0gUYc4KAeNIW1E438XvAx+/KbGvfA6LbuRaaUnZDqJAkEApqJ1LZcRUevNfcyk7N6FjfA+ef3cvg11F756tW90Qz58A2saeC9bjrSLHl9jTCpW1w5CZLcnW1LhgopkxivBFQJAGWUzz7gQDw5OPqfHd9oS1ltGyKBjbWkUsZ3DNcoSBd6Kr+Ag37YQ3oZwMNsn5XThj9+fql2122aV6NwZDVbdIQJAcjQW5+Ef7HlROzf4WfUEk6wE3+AR7HMCCXeuX2FTPXUz/e92ZJC00Hqwefpg+ziF0gk65aVOFxad7abJ57pNqQ==";//测试


    //upi_pay
    private string $upipay_url  = "https://api.upi-pays.com/api/payout/createOrder";                 //下单地址

    private string $upipay_merchantNo = "100"; //测试
//    private string $upipay_merchantNo = "258"; //正式

    private string $upipay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/upipayNotify";  //回调地址
    private string $upipay_Key        = "ufoanU37Gdk@d8D3";//测试
//    private string $upipay_Key        = "c5C6XrNbA82a134";//正式



    //q_pay
    private string $securitypay_host  = "po.i-paying.com";                 //下单地址
    private string $securitypay_tokne_url  = "https://po.i-paying.com/v1.0/api/access-token";                 //下单地址
    private string $securitypay_url  = "https://po.i-paying.com/v1.0/api/orders";                 //下单地址
    private string $securitypay_appid = "AKDDIU3FHI39"; //
    private string $securitypay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/securitypayNotify";  //回调地址
    private string $securitypay_Key        = "DUWM1BUYCEX894I6HG0A";//




    //vendoo_pay
    private string $vendoopay_url  = "https://test.api.ips.st/pomfret/v1/payout/order";                 //测试
//    private string $vendoopay_url  = "https://api.seapay.ink/pomfret/v1/payout/order";                 //正式

    private string $vendoopay_merchantNo = "TEST99"; //测试
//    private string $vendoopay_merchantNo = "777bet"; //正式

    private string $vendoopay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/vendoopayNotify";  //回调地址
    private string $vendoopay_Key        = "839656ab9bdae7230bcacc51da685582";//测试
//    private string $vendoopay_Key        = "3e5283781adf6b8ef4f59edea290b3de";//正式


    //rupeelink_pay
    private string $rupeelinkpay_url  = "https://open.rplapi.com/rupeeLink/api/remit";                 //

    private string $rupeelinkpay_merchantNo = "241014137451"; //

    private string $rupeelinkpay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/rupeelinkpayNotify";  //回调地址
    private string $rupeelinkpay_Key        = "eauxMNmns50iG22vqKfXaD0jIhLEy1kW";//



    //unive_pay
    private string $univepay_url  = "https://ydapi.univepay.com/API/GlobalSettlement";                 //下单地址
    private string $univepay_merchantNo = "100008"; //测试
//    private string $univepay_merchantNo = "C24638"; //正式
    private string $univepay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/univepayNotify";  //回调地址
    private string $univepay_Key        = "123456";//测试
//    private string $univepay_Key        = "CJ3DL5SAC57DGPEY";//正式




    //no_pay
    private string $nopay_url  = "https://payforsaas001.qb8.app/order/withdrawOrderCoinCreate";                 //下单地址
    private string $nopay_merchantNo = "TBMFSOZHAJD2FJF9"; //测试
//    private string $nopay_merchantNo = "TBIK6NOXLXUJQ7R1"; //正式
    private string $nopay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/nopayNotify";  //回调地址
    private string $nopay_Key        = "TBKmda6EA7a7LqFAjJPotWXjqF29g7wJ";//测试
//    private string $nopay_Key        = "TBKqifMFIOeud0rSNzIkW6iIFOmrbTVv";//正式





    //ms_pay
    private string $mspay_url  = "https://agent.msigiosdfih.com/api/payout/order";                 //下单地址
    private string $mspay_merchantNo = "777bet"; //

    private string $mspay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/mspayNotify";  //回调地址
    private string $mspay_Key        = "1B002AB86AC1E91BAB92C5E3EB9352E7";//


    //decent_pay
    private string $decentpay_url  = "https://api-in.zupay.top/pay/payment/disbursement/create";                 //下单地址
    private string $decentpay_merchantNo = "1072"; //
    private string $decentpay_appId = "107201"; //
    private string $decentpay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/decentpayNotify";  //回调地址
    private string $decentpay_Key        = "fLgwZ5V7sqavYNSPzq8hStsj6pBA74CJ61l4HH3php7xqqXiNxJzNagxIILvUY6tNUKlU8MR8NDC+SLrBVaoiw==";//



    //fly_pay
    private string $flypay_url  = "http://www.inspay.org/sapi/payment/common/payout";                 //下单地址
    private string $flypay_merchantNo = "999911256"; //

    private string $flypay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/flypayNotify";  //回调地址
    private string $flypay_Key  = "MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCp5ZoYlL/4gGcO1N9PXhvUQ/3uh1SgTkCwjOXKMQYk7JeSSK+32Px3jZt8GoyjC/9J66ybpPdKyJLoaSmD5av/rw3jyUOL6rdfwo0pzdK75kfxsJUOqtVy3+gOTSRQCvVEjqROwZVdzOlgzUTmBLT7mdALRDWh1wSeur887OAJsXKAN8aJ4fakpjX5234kayyLYfYfqEmeUytN//HN/v1T4BcfTzkJLD6vLR3a9vI7noyvaofzy3sSlVpHWoDkA5gAbS9D3uE1YI5PodWHabr1PC4d6mD/Bi/6crCKBCPTstJcFWVfOzgLNxJkuEpi2Buz5fObgNIgGkQQNufsmaq/AgMBAAECggEAFwKNuD6NMW7ShmO2St2ID1uWDLQvdfajNEYg6R1yo5WXgaxugAvXRancIRbHbV22maBdpEbXZz17CBcWFFMK1Ho0+2DK0Sxc4Y9L5xmFLQWnQMiVp4LjncdNeoJgJKcXSM242yHExQt3oDCs4CpLCLhwQNZgHSct7QGF3Q6H2UgCQTNxFOpsYHzI8LBqwROGHv5E6/UN9kGUSpQf22LlzhJLb9oJDyvzk2b0m4fb2/7OBJbsdClD7PwCKn+mamvLTbjDBZpSm13tmTtgCRUV25hDREQzyBNeJwHqclK3gMMz58FF0YZIcNBrbWel5v32cZkugDj10J8Gln/g2b6q4QKBgQDq+aa/KDZ0vq/9VaaKWhZL3eeQe4V6hu0FCkqQw6Z2lzlRGVAzOAoLTnvAW31fR7I6xB1w5QkzL3P6GjaH93Cq7HL1um/0MKFLHPaD9i13V5p7YD7qFIjVPlgv+6vkCjrQvvudNi61R9K0y+X9gyGeIppgRUIVL7IUMoqECeiRgwKBgQC5GUPIQ+tiNCW2Hld2kuO5ue0wn5Rj72E7BqrPdgT3TrQc1UTLLO/SQdEXSG5mp+9C8FmikCbDYXWeSXfu8VsJ4Ep/ujHj+J49VqK+sQthA8kvyKUeMMXbL3gF6emstW6kbU3/dLoU9arZGkpNX+PJ1obBjwV1BQiR/93JPx1pFQKBgEE8bIn3zR6eblfkNqeEmVoY0phvYsCAwz85+zezyfx0wan9YCHINimrcXoXLHiOfDIKjq3wOJyoWQefzXH0Rah+mvAUAc8GzVEASoSajUbr4GzObMkqSE8DzxILSk62dFvOGicsis0zkpE1ZrX6eRPhQYDm2ZDuO/+VhJVh9tqnAoGBAKzeiq6DuFccKsg+6CKmpyYzHfGWaEk5LQ6qeGaPa63pBFAVYk1653Pv4i6jh/A6ETvsK1qm1H0PDYFKTkeLhCHiJtHJfITUEj1pJ09/HAh8N6537rYWiQLe/3JOdt3FCNNp/jmBs7SVh/2BDznaP2ym/W3SfB9BFzL7yxAD8RzNAoGBAM67j156oSVF0VH6d2z4/b/9wTLSjG69gzaQMq0eiZPE2TQwZ5MwnmHeUleELOau98BGweNdKBDjxSEzSo//r1GL46usT8Ovy+wDJo62wkBvsnVC9bFmvVTXxTeEshbEm6ylRM5twOmmkoELsVC36XFZ1LKFu87NXdnLOKnyr7fw";//

    //kk_pay
    private string $kkpay_url  = "http://api.zhkap.com/pay/withdraw";                 //下单地址
    private string $kkpay_merchantNo = "1000100020003416"; //
    private string $kkpay_applicationId = "410"; //

    private string $kkpay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/kkpayNotify";  //回调地址
    private string $kkpay_appKey  = "2FDE621690694CF9B2FC80EBAB9F53D2";//
    private string $kkpay_merchantKey  = "20E87B6B726846BCACAF261AEFF33B6B";//



    //tk_pay
    private string $tkpay_url  = "https://seabird.world/api/order/withdraw/create";                 //下单地址
    private string $tkpay_merchantNo = "202366100"; //
//    private string $tkpay_merchantNo = "202466298"; //正式
    private string $tkpay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/tkpayNotify";  //回调地址
    private string $tkpay_Key        = "c385fe7029344aef826d8112625b2625x";//
//    private string $tkpay_Key        = "fca35368da5d403086dcf248103b432f";//正式

    //kktwo_pay
    private string $kktwopay_url  = "http://api.zhkap.com/pay/withdraw";                 //下单地址
    private string $kktwopay_merchantNo = "1000100020003421"; //
    private string $kktwopay_applicationId = "415"; //

    private string $kktwopay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/kktwopayNotify";  //回调地址
    private string $kktwopay_appKey  = "CCDF005D598D432ABE36A10C621819CA";//
    private string $kktwopay_merchantKey  = "8A76608C2D344C279F67FE507CD34ABE";//





    //one_pay
    private string $onepay_url  = "https://pay-test.uangku.top/cashout/india/order/create";                 //测试
//    private string $onepay_url  = "https://api-in.onepyg.com/cashout/india/order/create";                 //正式
    private string $onepay_merchantNo = "2061545184"; //
//    private string $onepay_merchantNo = "3619769596"; //

    private string $onepay_appId = "R22e9c0f1x3hycRKd9"; //
//    private string $onepay_appId = "OJMXy4x3uNmVaOU8Ya"; //

    private string $onepay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/onepayNotify";  //回调地址
    private string $onepay_Key        = "MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDYkwC5pxuCzCbfj4CIzltDPq0fsipBKuJHCK0e77ro7H1DUrHBSSYyCaa9U5cLQbIsLf5rhMzrGzef2Yl3x2eL8BBx+EGZBC4nj0L8IohqJzTzU6SIcV6hWostj0j8b3wT6hD8WTGWO9nECJmFXWPGHuRHkKf4jXRnvnTUyITQ9KUCST1FaT2Dq6OUz7qDInHNZuCzD5k2IjJRKFn67ouqg/kAW8S8JW03IXHmGu5SotpHMx9550LxECUD3wL1RVWi9I5Y4OgUJEEGYw36i+Ip0gKDUoKcbdpLbzP9C7hPx07+x5IwWSH7N5t8NY0LpsFeSTT8BtY5z8Dx6lQbGHrDAgMBAAECggEBALCEB/hI6hROHnTh+ioGvr1tDR+jT+szb5/jw25Oh/GmJmZDtRtLanUoBp2Szq3fCsAVcKLjZz1MPqbrX66feJbGwDCT3atAi/m0Oy1TXAnhELgt+RE4DZ/HM16bxSnyF5gHk3aJn+/JsjCjrbTULCjjLr3hefYMHM8dnQ18rTu8Wf7/VAnPc6dEdemJyUSW3F9HmprZBMW0E4WzxvG9JhF3jnVxBhG/cbb8ks8a7neCsneA4AJ+DcFeVIG6v/djFBrVMrFiDGs4KCGoZjAqTYngqtyFBi+h2WsPs1yRvBeg/XoQ8QqWdvELdnl8eTolYKxvK9uiStcHSePtz/Yh+yECgYEA8PY7aSdRNfOdVLMDpGmHwiJLeO9UoSWRN8rZ2QszGpz5GCj3W+ZLPiWz6lJaw7yZn/F4VUHhTGKUdujZ4Dz3wKOtNV8T7ei8stSRUh9Bk7pryD9LIaNLQiiiIUQ0QqFiXtW6GtLpgt/FeknecwByOzfTTTUQ4H1RpvOBVgEpb3ECgYEA5hcjTK5a/E+W2aYTyk6cRw2ybqZDF4yNni0IRhDTOAvJDwJTzic/lbNrHXWna0ooaLZmmX12v2QZ6RCIUSfGfSnocNXtyeputFSozOk16QWGDpH1QlhNhtW5lFB2ORc1mvTG7C3ECq0RovQRxvqsIWtCjPUKn6OvZjSlMtIim3MCgYEAzvHacmP3Bkv0mlKoVSLhGmTTxshdOY0HHBCWaiaJPFkGQa6lSoMNqhE9ZIhYNXUbx1beDvLmqPCdK0auIDycVxD7aDQA7LmOnlObfxki+9oGSVO6leglcWtuWv21mGf8ERCjpffv3puKgY1BhCkk8iDu04c4uGRIpQbK1G9pA7ECgYA222NeH9+vciZMA+2J+U4HHrvg56DtV2RYRvJHCjHhleW8v1hNuUvOnDU4k9lzmf2iYYJ6q9AI94u55mgpuSr4omo5pLeJwWvdcKXCHQPuZ5O7m47232i0cfZJ5xkYqXDtXdijbJHl3bdru3cVkqRBX3pBcxayUus5memdAT6hAwKBgQCMNiNuZl6A53hzsbAzyXGWXVuM98jWHjLoyjN6AsvY2ZmLHPNHR1ht0QQ5BssMtCdfawSi6jiIP0HOKl0SudL9F5wWU48UZADF2pA+D8qUA5qHoIW5ebU87jja1LEfOVamwh7FgG223qI+2uSE9W4mqf/rWJSjL3FJEDyLcBTMcA==";//
//    private string $onepay_Key        = "MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDY+5ytnLAybiUT7+Av1+EjXos1CLdfr7L9hKJPdIooaRJusNA1pXG2kbg6BfexnkvwHYwyyTnGl6oDpkzxl4qYg+YcjbTIvouxMsP/A5cP5W4nlTvY6D/lexEjmVWbC5FdnXPun1Gh2WGTEqpXIqtEjuVAorC28NpLhtjcZAzYze+HcnvnZywUHBfRUtoa6pXr2Gx1A+yYkJAdZhuEcMzfcBTEJT9V8Jc4CYvrdshJpVO4jU2BV5LIXXY88rxZD/2qViPs27MrIUfeCbrLHGY3c25n3uvf+4hpjUj/biFMmb2ExBMxytLC9EZuZiOv+PsaBiF+KZWIzg8HzSOomHvTAgMBAAECggEACUTKPxJL5oOU+uKKyZBnshqWSLgsGR7IvxKc2lcIlcxTOL49zqTYFCvqWjQqDgDMjp+8fujgmm6dGRXQAJvwFC7CNCMLf23AStv3ylttZwTubStvSIc3P4a/xy0EHqDiX0TcEGvm0UqXY/BurVUySrXt7hJlCROFx6cleViewd4J/kLGHHgsEKy1ocfFym15auCLKWxQjdi7Nn/3w7uUHdq8XlXb+etC1vjcu5cQuDmSiQRup6RZs640ppn1vusJt8CscB0zokYYhMl1dTI5O7TAy94ilgG8SJEF+qKtsNxw/xUeJ8CC8tggRQUSmt4Z5vOOjAZz84AvwQJAGWcRvQKBgQD3OAZL9JZWqnTJgmBGdUpB73tKbKf2utna5B77Sq9dVHHs0N7f5YTHllIR9oKYTue99LdC+2f+7r92drFEiS5Yh996ACFv5K8dGd7M3DoZzjsRiuH2D3dzJXJ9VJ0GpVuGDiT9jV00TBkZYHUh6M8oVSazX3ckbuWzSzUquBoW7wKBgQDgsKZbowzFyiYFPq3ZcmSVPs1VD9wfXgfQaE9JOGX1eFn9+HdnjWMxC3//DyvZWsbXx64Eqfb0AGX1dXQ74YzRRqPR84cH7f9sNLFUv66MECdx+dhi8OddnfeKFjRWdGM4Ge57kc3M+ioJbF9qThJMHJJ6u1eX7H4fLqu28qxJXQKBgQDrAxJRUHE+cApXqZ4WPNfbuGpPBN3jWhtRz7x4DLaKlYU7qA/Hbmv8RDU+qEXbvl7lIGa6wT5KhfHzDsBTs8kgFgJm+wrOUOn7UyWPP+fnsjpK4ekOvgNCrh2ZcT9ZGwbXeEjH1IP+/Dx7+EtBcgzEfbYtnJopQ1cPS3Z+Zsc+dwKBgBxd8QLMuQYXmWk8GpLDYHN/NEky8WV8Z5wmLyxdVHIDOclYnyqRrR46B3TaI30TetsvOIcaNjVj/3tX0s7kkPSy6GfPSRL1NzQgCutaL907BN/c3TbQl0U4dlIWr5DirMweaf9rzwG766a46erv5Ft7l/qqwEpL7zhcmg1E4f95AoGAJqfsBW6PnVVJ6K4oADF4mokFQwXNoTRkZE4vmlcubdpGKL3hiLQGhaXGtkEChxoIPFeO6KgI7Y4N9ptJdMpDy6cPKMwxVrl1fsyHPUt3srwWOzf1GwQ6TVRXxGbNym6X+lPT4UUL3L8rrhcCazSFB0tOkh6aVn6PGlKcUvENtYQ=";//



    //global_pay
    private string $globalpay_url  = "https://gateway.globalpay2024.com/api/payout/order";                 //下单地址
    private string $globalpay_merchantNo = "180318"; //
    private string $globalpay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/globalpayNotify";  //回调地址
    private string $globalpay_Key        = "6e143bc02801d64e21369524e885dc0d";//



    //a777_pay
    private string $a777pay_url  = "https://www.777-pay.com/open-api/create-payout-order";                 //下单地址
    private string $a777pay_merchantNo = "ca5e5a7bb1d140669f4907c0f08bf723"; //
    private string $a777pay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/a777payNotify";  //回调地址
    private string $a777pay_Key        = "UXCt7sRLGJyFYzQmRLEEMlbeiQygkQ3aFmBmXs";//




    //masat_pay
    private string $masatpay_url  = "https://api.masatpay.vip/api/createTransferOrder";                 //下单地址
    private string $masatpay_merchantNo = "507757308571422720"; //
    private string $masatpay_notifyUrl  = "http://124.221.1.74:9502/Withdrawlog/masatpayNotify";  //回调地址
    private string $masatpay_Key        = "507757308307181568";//




    private array $header = ["Content-Type" => "application/x-www-form-urlencoded"];

    private array $zr_header = ["Content-Type" => "application/json"];

    /**
     * @return void 统一提现接口
     * @param $paytype 提现渠道
     * @param $withdrawdata 创建提现订单信息
     */
    public function withdraw($withdrawdata,$withtype,$type){

        return $this->$withtype($withdrawdata,$type);
    }



    /**
     * rr_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public  function rr_pay($withdrawlog,$type) {
        $data = [
            "merchantId"      => $this->rrpay_merchantNo,
            "merchantOrderId" => $withdrawlog['ordersn'],
            "amount"          => bcdiv((string)$withdrawlog['really_money'],'100',2),
        ];
        $data['sign'] = Sign::notKsortSign($data,$this->rrpay_Key,'');

        $data["timestamp"]   = time() * 1000;
        $data["notifyUrl"]   = $this->rrpay_notifyUrl;
        if($withdrawlog['type'] == 1){ //银行卡
            $data["fundAccount"] = [
                "accountType" => "bank_account",
                "contact"     => [
                    "name"          => $withdrawlog['backname'],
                ],
                "bankAccount" => [
                    "name"          => $withdrawlog['backname'],
                    "ifsc"          => $withdrawlog['ifsccode'],
                    "accountNumber" => $withdrawlog['bankaccount']
                ],
            ];
        }else{ //UIP
            $data["fundAccount"] = [
                "accountType" => "vpa",
                "contact"     => [
                    "name"          => $withdrawlog['backname'],
                ],
                "vpa" => [
                    "address"          => $withdrawlog['bankaccount'],
                ],
            ];
        }

        try {
            $response = $this->guzzle->post($this->rrpay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }


        if (isset($response['code']) && $response['code'] == "0") {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['payoutId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * ser_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function ser_pay($withdrawlog,$type){

        if($withdrawlog['type'] == 1){  //银行卡
            $data=array(
                "version"=>"2.1",
                "orgNo"=>$this->serpay_orgNo,
                "custId"=>$this->serpay_mchid,
                "custOrdNo"=>$withdrawlog['ordersn'],
                "casType"=>"00",
                "country"=>"IN",
                "currency"=>"INR",
                "casAmt"=>(int)$withdrawlog['really_money'],
                "deductWay"=>"02",
                "callBackUrl"=>$this->serpay_backUrl,
                "account"=>$this->serpay_account,
                "payoutType"=>"Card",
                "cardType"=>"IMPS",
                "accountName"=>$withdrawlog['backname'],
                "payeeBankCode"=>substr($withdrawlog['ifsccode'], 0, 4),
                "cnapsCode"=>$withdrawlog['ifsccode'],
                "cardNo"=>$withdrawlog['bankaccount'],
                "phone"=>$withdrawlog['phone'],
                "email"=>$withdrawlog["email"]
            );
        }else{ //UPI
            $data=array(
                "version"=>"2.1",
                "orgNo"=>$this->serpay_orgNo,
                "custId"=>$this->serpay_mchid,
                "custOrdNo"=>$withdrawlog['ordersn'],
                "casType"=>"00",
                "country"=>"IN",
                "currency"=>"INR",
                "casAmt"=>(int)$withdrawlog['really_money'],
                "deductWay"=>"02",
                "callBackUrl"=>$this->serpay_backUrl,
                "account"=>$this->serpay_account,
                "payoutType"=>"UPI",
                "accountName"=>$withdrawlog['backname'],
                "upiId"=>$withdrawlog['bankaccount'],
                "phone"=>$withdrawlog['phone'],
                "email"=>$withdrawlog["email"]
            );
        }

        $data['sign'] = Sign::asciiKeyStrtolowerSign($data, $this->serpay_key);


        try {
            $response = $this->guzzle->post($this->serpay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }


        if (isset($response['code']) && $response['code']=="000000") {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['custId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }





    /**
     * sertwo_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function sertwo_pay($withdrawlog,$type){

        if($withdrawlog['type'] == 1){  //银行卡
            $data=array(
                "version"=>"2.1",
                "orgNo"=>$this->sertwopay_orgNo,
                "custId"=>$this->sertwopay_mchid,
                "custOrdNo"=>$withdrawlog['ordersn'],
                "casType"=>"00",
                "country"=>"IN",
                "currency"=>"INR",
                "casAmt"=>(int)$withdrawlog['really_money'],
                "deductWay"=>"02",
                "callBackUrl"=>$this->sertwopay_backUrl,
                "account"=>$this->sertwopay_account,
                "payoutType"=>"Card",
                "cardType"=>"IMPS",
                "accountName"=>$withdrawlog['backname'],
                "payeeBankCode"=>substr($withdrawlog['ifsccode'], 0, 4),
                "cnapsCode"=>$withdrawlog['ifsccode'],
                "cardNo"=>$withdrawlog['bankaccount'],
                "phone"=>$withdrawlog['phone'],
                "email"=>$withdrawlog["email"]
            );
        }else{ //UPI
            $data=array(
                "version"=>"2.1",
                "orgNo"=>$this->sertwopay_orgNo,
                "custId"=>$this->sertwopay_mchid,
                "custOrdNo"=>$withdrawlog['ordersn'],
                "casType"=>"00",
                "country"=>"IN",
                "currency"=>"INR",
                "casAmt"=>(int)$withdrawlog['really_money'],
                "deductWay"=>"02",
                "callBackUrl"=>$this->sertwopay_backUrl,
                "account"=>$this->sertwopay_account,
                "payoutType"=>"UPI",
                "accountName"=>$withdrawlog['backname'],
                "upiId"=>$withdrawlog['bankaccount'],
                "phone"=>$withdrawlog['phone'],
                "email"=>$withdrawlog["email"]
            );
        }

        $data['sign'] = Sign::asciiKeyStrtolowerSign($data, $this->sertwopay_key);


        try {
            $response = $this->guzzle->post($this->sertwopay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }


        if (isset($response['code']) && $response['code']=="000000") {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['custId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * tm_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function tm_pay($withdrawlog,$type){
        $url    = $this->tmpay_url;
        $header = ["Content-Type" => "application/json;charset='utf-8'"];
        $data = [
            "mch_no"   => $this->tmpay_merchantNo,
            "app_id"   => $this->tmpay_appid,
            "mch_trade_no" => $withdrawlog['ordersn'],
            "type"   => $withdrawlog['type'] == 1 ? 1 : 3,
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "timestamp" => time().'000',
            "notify_url" => $this->tmpay_notifyUrl,
            "cus_name" => $withdrawlog['backname'],
            "cus_email" => $withdrawlog['email'],
            "cus_mobile" => $withdrawlog['phone'],
            "card_holder" => $withdrawlog['backname'],
            "bank_code" =>$withdrawlog['type'] == 1 ? $withdrawlog['ifsccode'] : $withdrawlog['bankaccount'],
            "branch_code" =>$withdrawlog['type'] == 1 ? $withdrawlog['ifsccode'] : $withdrawlog['bankaccount'],
        ];
        if($withdrawlog['type'] == 1){
            $data['ifsc'] = $withdrawlog['ifsccode'];
            $data['card_no'] = $withdrawlog['bankaccount'];
        }else{
            $data['ifsc'] = $withdrawlog['bankaccount'];
            $data['upi_id'] = $withdrawlog['bankaccount'];
            $data['card_no'] = $withdrawlog['bankaccount'];
        }

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->tmpay_Key,'app_key');


        try {
            $response = $this->guzzle->post($url,$data,$header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 0) {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['plat_trade_no']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }





    /**
     * tmtwo_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function tmtwo_pay($withdrawlog,$type){
        $url    = $this->tmtwopay_url;
        $header = ["Content-Type" => "application/json;charset='utf-8'"];
        $data = [
            "mch_no"   => $this->tmtwopay_merchantNo,
            "app_id"   => $this->tmtwopay_appid,
            "mch_trade_no" => $withdrawlog['ordersn'],
            "type"   => $withdrawlog['type'] == 1 ? 1 : 3,
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "timestamp" => time().'000',
            "notify_url" => $this->tmtwopay_notifyUrl,
            "cus_name" => $withdrawlog['backname'],
            "cus_email" => $withdrawlog['email'],
            "cus_mobile" => $withdrawlog['phone'],
            "card_holder" => $withdrawlog['backname'],
            "bank_code" =>$withdrawlog['type'] == 1 ? $withdrawlog['ifsccode'] : $withdrawlog['bankaccount'],
            "branch_code" =>$withdrawlog['type'] == 1 ? $withdrawlog['ifsccode'] : $withdrawlog['bankaccount'],
        ];
        if($withdrawlog['type'] == 1){
            $data['ifsc'] = $withdrawlog['ifsccode'];
            $data['card_no'] = $withdrawlog['bankaccount'];
        }else{
            $data['ifsc'] = $withdrawlog['bankaccount'];
            $data['upi_id'] = $withdrawlog['bankaccount'];
            $data['card_no'] = $withdrawlog['bankaccount'];
        }

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->tmtwopay_Key,'app_key');


        try {
            $response = $this->guzzle->post($url,$data,$header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 0) {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['plat_trade_no']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * waka_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function waka_pay($withdrawlog,$type){



        $url    = $this->wakapay_url;
        $header = $this->zr_header;
        $data = [
            "mer_no"   => $this->wakapay_merchantNo,
            "order_no" => $withdrawlog['ordersn'],
            "method" => 'fund.apply',
            "order_amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "currency" => 'INR',
            "acc_no" => $withdrawlog['bankaccount'],
            "returnurl" => $this->wakapay_notifyUrl,
            "acc_phone" => (string)$withdrawlog['phone'],
            "acc_email" => $withdrawlog['email'],
        ];
        if($withdrawlog['type'] == 1){
            $data['acc_code'] = 'BANK';
            $data['acc_name'] = $withdrawlog['backname'];
            $data['province'] = $withdrawlog['ifsccode'];
        }else{
            $data['acc_code'] = 'UPI';
            $data['acc_name'] = $withdrawlog['backname'];
        }
        $data['sign'] = Sign::asciiKeyStrtolowerNotSign($data,$this->wakapay_Key);



        try {
            $response = $this->guzzle->post($url,$data,$header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }


        if (isset($response['status']) && $response['status'] == 'success') {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['sys_no']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];

    }

    /**
     * fun_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function fun_pay($withdrawlog,$type){

        $url    = $this->funpay_url;
        $header = $this->zr_header;
        $data = [
            "merchantId"   => $this->funpay_merchantNo,
            "orderId" => $withdrawlog['ordersn'],
            "amount" => $withdrawlog['really_money'],
            "address" => $withdrawlog['backname'],
            "email" => $withdrawlog['email'],
            "notifyUrl" => $this->funpay_notifyUrl,
            "phone" => $withdrawlog['phone'],
            "userId" => $withdrawlog['uid'],
        ];

        if($withdrawlog['type'] == 1){
            $data['bankAccount'] = $withdrawlog['bankaccount'];
            $data['ifsc'] = $withdrawlog['ifsccode'];
            $data['userName'] =  $withdrawlog['backname'];
        }else{
            $data['vpa'] = $withdrawlog['bankaccount'];
            $data['userName'] = $withdrawlog['backname'];
        }

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->funpayKey);



        try {
            $response = $this->guzzle->post($url,$data,$header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }


        if (isset($response['code']) && $response['code'] == 200) {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['tranId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];


    }



    /**
     * go_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function go_pay($withdrawlog,$type){

        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $url    = $this->gopay_url;
        $header = $this->zr_header;
        $data = [
            "amount" => $withdrawlog['really_money'],
            "currency" => "INR",
            "merId"   => $this->gopay_merchantNo,
            "notifyUrl" => $this->gopay_notifyUrl,
            "orderId" => $withdrawlog['ordersn'],
            "type" => 1,
            "bankCode" => $withdrawlog['ifsccode'],
            "account" => $withdrawlog['bankaccount'],
            "userName" => $withdrawlog['backname'],
            "email" => $withdrawlog['email'],
            "mobile" => $withdrawlog['phone'],
        ];


        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->gopayKey);




        try {
            $response = $this->guzzle->post($url,$data,$header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 200) {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['id']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * eanishop_pay
     *
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    private function eanishop_pay($withdrawlog,$type){
        $method = 'POST';
        $timestamp = time().'000';
        $nonce = Sign::createHex(16);
        $body = [
            'merchantTradeNo' =>$withdrawlog['ordersn'],
            'amount' => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'currency' => 'INR',
            'description' => '777WinWithDraw',
            'payoutMethod' => $withdrawlog['type'] == 1 ?
                [
                    'type' => 'BANK_ACCOUNT',
                    'mode' => 'IMPS',
                    'bankCode' => $withdrawlog['ifsccode'],
                    'bankName' => $withdrawlog['backname'],
                    'accountNumber' => $withdrawlog['bankaccount'],
                    'payeeName' =>  $withdrawlog['backname'],
                    'payeePhone' =>  $withdrawlog['phone'],
                    'payeeEmail' =>  $withdrawlog['email'],
                ]
                :
                [
                    'type' => 'UPI',
                    'vpa' => $withdrawlog['bankaccount'],
                    'payeeName' =>  $withdrawlog['backname']
                ],
            'notifyUrl' => $this->eanishoppay_notifyUrl,
        ];
        $sign = Sign::EaniShopSign($this->eanishoppayAppId, $this->eanishoppayAppSecret, $method, $this->eanishoppay_url, $timestamp, $nonce, json_encode($body));
        $Authorization  =  "V2_SHA256 appId=$this->eanishoppayAppId,sign=$sign,timestamp=$timestamp,nonce=$nonce";
//        $herder = [
//            'Content-Type' => 'application/json',
//            'Authorization' => $Authorization,
//        ];


//        try {
//            $response = $this->guzzle->post($this->eanishoppay_url,$body,$herder);
//        }catch (\Exception $e){
//            $response = ['Request timed out'];
//        }

        $herder = [
            'Content-Type: application/json',
            'Authorization:'. $Authorization,
        ];
        $response = Curl::post($this->eanishoppay_url,$body,$herder);

        $response = json_decode($response,true);


        if (isset($response['code']) && $response['code'] == 'OK') {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['payoutNo']];
        }
        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }


    /**
     * 24hrpay
     *
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    private function hr24_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $body = [
            'mchId' => $this->hr24pay_mchId,
            'accountName' => $withdrawlog['backname'],
            'accountNo' => $withdrawlog['bankaccount'],
            'cciNumber' => $withdrawlog['ifsccode'],
            'phone' => $withdrawlog['phone'],
            'amount' => $withdrawlog['really_money'],
            'mchOrderNo' => $withdrawlog['ordersn'],
            'notifyUrl' => $this->hr24pay_notifyUrl,
            'nonceStr' => (string)time(),
        ];

        $body['sign'] = Sign::asciiKeyStrtoupperSign($body, $this->hr24pay_appKey);
//        $herder = $this->zr_header;
//        $herder['tmId'] = '24hr_ind_auto';

        $herder = array(
            "Content-Type: application/json",
            "tmId: 24hr_ind_auto",
        );



        try {
//            $response = $this->guzzle->post($this->hr24pay_url,$body,$herder);
            $response = Curl::post($this->hr24pay_url,$body,$herder);

            $response = json_decode($response,true);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }



        if (isset($response['resCode']) && $response['resCode'] == 'SUCCESS') {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['orderId']];
        }
        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * ai_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function ai_pay($withdrawlog,$type){



        $url    = $this->aipay_url;
        $header = $this->zr_header;
        $data = [
            "mer_no"   => $this->aipay_merchantNo,
            "order_no" => $withdrawlog['ordersn'],
            "method" => 'fund.apply',
            "order_amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "currency" => 'INR',
            "acc_no" => $withdrawlog['bankaccount'],
            "returnurl" => $this->aipay_notifyUrl,
            "acc_phone" => (string)$withdrawlog['phone'],
            "acc_email" => $withdrawlog['email'],
        ];
        if($withdrawlog['type'] == 1){
            $data['acc_code'] = 'BANK';
            $data['acc_name'] = $withdrawlog['backname'];
            $data['province'] = $withdrawlog['ifsccode'];
        }else{
            $data['acc_code'] = 'UPI';
            $data['acc_name'] = $withdrawlog['backname'];
        }
        $data['sign'] = Sign::asciiKeyStrtolowerNotSign($data,$this->aipay_Key);



        try {
            $response = $this->guzzle->post($url,$data,$header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }


        if (isset($response['status']) && $response['status'] == 'success') {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['sys_no']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];

    }



    /**
     * x_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function x_pay($withdrawlog,$type){
        $data['mchNo'] = $this->xpay_mchNo;
        $data['appId'] = $this->xpay_appId;
        $data['mchOrderNo'] = $withdrawlog['ordersn'];
        $data['currency'] = 'INR';
        $data['orderAmount'] = bcdiv((string)$withdrawlog['really_money'],'100',2);
        if($withdrawlog['type'] == 1) {  //银行卡
            $data['bankCode'] ='BANK';
            $data['bankName'] = $withdrawlog['backname'];
            $data['accountNo'] = $withdrawlog['bankaccount'];
            $data['ifsc'] = $withdrawlog['ifsccode'];
        }else{
            $data['bankCode'] = 'UPI';
            $data['vpa'] = $withdrawlog['bankaccount'];
        }
        $data['accountName'] =   $withdrawlog['backname'];
        $data['accountEmail'] = $withdrawlog['email'];
        $data['accountMobileNo'] = $withdrawlog['phone'];
        $data['notifyUrl'] = $this->xpay_notifyUrl;
        $data['reqTime'] =  time()."000";
        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->xpay_Key);

        try {
            $response = $this->guzzle->post($this->xpay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['code']=="0" && ($response['data']['state'] == 1 || $response['data']['state'] == 2)){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['transferId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }


    /**
     * lets_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function lets_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $data = [
            'type' => 'api',
            'mchId' => $this->letspay_mchId,
            'mchTransNo' => $withdrawlog['ordersn'],
            'amount' => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'notifyUrl' => $this->letspay_notifyUrl,
            'accountName' => $withdrawlog['backname'],
            'accountNo' => $withdrawlog['bankaccount'],
            'bankCode' => $withdrawlog['ifsccode'],
            'remarkInfo' => 'email:'.$withdrawlog['email'].'/name:'.$withdrawlog['backname'].'/phone:'.$withdrawlog['phone'].'/mode:bank',
        ];


        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->letspay_Key);

        try {
            $response = $this->guzzle->post($this->letspay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['retCode']=="SUCCESS"){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['platOrder']];
        }
        $status = Db::table('withdraw_log')->where('ordersn',$withdrawlog['ordersn'])->value('status');
        if($status && !in_array($status,[0,3]))return ['code' => 201 , 'msg' => '' , 'data' => []];
        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * letstwo_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function letstwo_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $data = [
            'type' => 'api',
            'mchId' => $this->letstwopay_mchId,
            'mchTransNo' => $withdrawlog['ordersn'],
            'amount' => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'notifyUrl' => $this->letstwopay_notifyUrl,
            'accountName' => $withdrawlog['backname'],
            'accountNo' => $withdrawlog['bankaccount'],
            'bankCode' => $withdrawlog['ifsccode'],
            'remarkInfo' => 'email:'.$withdrawlog['email'].'/name:'.$withdrawlog['backname'].'/phone:'.$withdrawlog['phone'].'/mode:bank',
        ];


        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->letstwopay_Key);

        try {
            $response = $this->guzzle->post($this->letstwopay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['retCode']=="SUCCESS"){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['platOrder']];
        }
        $status = Db::table('withdraw_log')->where('ordersn',$withdrawlog['ordersn'])->value('status');
        if($status && !in_array($status,[0,3]))return ['code' => 201 , 'msg' => '' , 'data' => []];

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }

    /**
     * dragon_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function dragon_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $data = [
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "appKey" => $this->dragonpay_appKey,
            "callbackUrl" => $this->dragonpay_notifyUrl,
            "account" => $withdrawlog['bankaccount'],
            "ifsc" => $withdrawlog['ifsccode'],
            "nonce" => $this->generateRandomString(),
            "orderId" => $withdrawlog['ordersn'],
            "personName" => $withdrawlog['backname'],
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->dragonpay_secret,'secret');

        try {
            $response = $this->guzzle->post($this->dragonpay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['code'] == 1){
            $this->logger->error('dragon_pay提现数据:'.json_encode($response));
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }

    /**
     * ant_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function ant_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $data = [
            "merchant_code" => $this->antpay_merchant_code,
            "order_no" => $withdrawlog['ordersn'],
            "order_amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "bank_card" => $withdrawlog['bankaccount'],
            "user_name" => $withdrawlog['backname'],
            "notify_url" => $this->antpay_notifyUrl,
        ];
        if($withdrawlog['type'] == 1){
            $data['pay_type'] = 'india-bank-repay';
            $data['bank_name'] = 'BANK';
            $data['bank_branch'] = $withdrawlog['ifsccode'];
        }else{
            $data['pay_type'] = 'india-upi-repay';
        }
        //$this->logger->error('ant_pay提现数据:'.json_encode($data));

        $sign = Sign::asciiKeyStrtoupperSign($data,$this->antpay_key);

        $new_data = [
            'signtype' => 'MD5',
            'sign' => urlencode($sign),
            'transdata' => urlencode(json_encode($data)),
        ];

        try {
            $response = $this->guzzle->post($this->antpay_url,$new_data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['status']==true){
            return ['code' => 200 , 'msg' => '' , 'data' => 'suc'];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * ff_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function ff_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $data = [
            'sign_type' => 'MD5',
            'mch_id' => $this->ffpay_mchId,
            'mch_transferId' => $withdrawlog['ordersn'],
            'transfer_amount' => bcdiv((string)$withdrawlog['really_money'],'100',0),
            'apply_date' => date('Y-m-d H:i:s'),
            'bank_code' => 'IDPT0001',
            'receive_name' => $withdrawlog['backname'],
            'receive_account' => $withdrawlog['bankaccount'],
            'remark' => $withdrawlog['ifsccode'],
            'back_url' => $this->ffpay_notifyUrl,
        ];


        $data['sign'] = Sign::FfPaySign($data,$this->ffpay_key);

        try {
            $response = $this->guzzle->post($this->ffpay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['respCode']=="SUCCESS"){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['tradeNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * cow_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function cow_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $data = [
            "merchant_code" => $this->cowpay_mchId,
            "order_no" => $withdrawlog['ordersn'],
            "order_amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "bank_card" => $withdrawlog['bankaccount'],
            "bank_branch" => $withdrawlog['ifsccode'],
            "user_name" => $withdrawlog['backname'],
            "notify_url" => $this->cowpay_notifyUrl,
        ];

        if($withdrawlog['type'] == 1){
            $data["pay_type"] = 'india-bank-repay';
            $data["bank_branch"] =  $withdrawlog['ifsccode'];
            $data["bank_name"] =  $withdrawlog['backname'];
        }else{
            $data["pay_type"] = 'india-upi-repay';
        }

        $sign = Sign::asciiKeyStrtoupperSign($data,$this->cowpay_key);

        $body = [
            'signtype' => 'MD5',
            'sign' => urlencode($sign),
            'transdata' => urlencode(json_encode($data)),
        ];

        try {
            $response = $this->guzzle->post($this->cowpay_url,$body,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['status']==true){
            return ['code' => 200 , 'msg' => '' , 'data' => 'suc'];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * wdd_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function wdd_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $data = [
            "userID" => (int)$this->wddpay_mchId,
            "amount" => (int)bcdiv((string)$withdrawlog['really_money'],'100',0),
            "payeeName" => $withdrawlog['backname'],
            "payeePhone" => $withdrawlog['phone'],
            "payType" => 1,
            'bankCardOwnerName' => $withdrawlog['backname'],
            "bankCardName" => $withdrawlog['ifsccode'],
            "accountNo" => $withdrawlog['bankaccount'],
            "stamp" => time(),
            "orderID" => $withdrawlog['ordersn'],
            'channelcode' => 704,
            "notifyUrl" => $this->wddpay_notifyUrl,
        ];

        $signData = [
            "userID" => $data['userID'],
            "amount" => $data['amount'],
            "payeeName" => $data['payeeName'],
            "payeePhone" => $data['payeePhone'],
            "payType" => $data['payType'],
            "stamp" => $data['stamp'],
            "orderID" => $data['orderID'],
            "notifyUrl" => $data['notifyUrl'],
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($signData,$this->wddpay_key);



//        try {
//            $response = $this->guzzle->post($this->wddpay_url,$data,$this->zr_header);
////            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdraw',$data,$this->header);
//        }catch (\Exception $e){
//            $response = ['Request timed out'];
//        }

        $zr_herder = ['Content-Type: application/json'];
        $response = Curl::post($this->wddpay_url,$data,$zr_herder);

        $response = json_decode($response,true);


        if ($response['code']== '0'){
            return ['code' => 200 , 'msg' => '' , 'data' => 'suc'];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * timi_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function timi_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }


        $data = [
            "mch_id"   => $this->timipay_mchId,
            "out_trade_no" => $withdrawlog['ordersn'],
            "account_code" =>  'IFSC',
            "account_name" => $withdrawlog['backname'],
            "agent_amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "currency" => "INR",
            "extra" => json_encode([
                'account_num' => $withdrawlog['bankaccount'],
                'ifsc_num' => $withdrawlog['ifsccode'],
                'email' =>  $withdrawlog['email'],
                'mobile' =>  $withdrawlog['phone'],
            ]),
            "notify_url" => $this->timipay_notifyUrl,
            "nonce_str" => (string)time(),
        ];


        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->timipay_key);


        try {
            $response = $this->guzzle->post($this->timipay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['status']==200){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['order_id']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * newfun_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function newfun_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }


        $data = [
            "merchant"   => $this->newfunpay_mchId,
            "orderNo" => $withdrawlog['ordersn'],
            "businessCode" => '102',
            "accNo" => $withdrawlog['bankaccount'],
            "accName" => $withdrawlog['backname'],
            "orderAmount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "bankCode" => 'BANK',
            'phone' =>  $withdrawlog['phone'],
            'province' =>  $withdrawlog['ifsccode'],
            'notifyUrl' =>  $this->newfunpay_notifyUrl,
            'remake' => 'Withdraw',
        ];


        $data['sign'] = Sign::newFunPaySing($data,$this->newfunpay_key);


        try {
            $response = $this->guzzle->post($this->newfunpay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['code']==0){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * simply_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function simply_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }


        $body = [
            'appId' => $this->simplypay_appId,
            "merOrderNo" => $withdrawlog['ordersn'],
            "currency" => 'INR',
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'notifyUrl' =>  $this->simplypay_notifyUrl,
            "extra" => [
                'payoutType' => 'IFSC',
                'ifsc' => $withdrawlog['ifsccode'],
                'account' => $withdrawlog['bankaccount'],
                'name' => $withdrawlog['backname'],
                'email' => $withdrawlog['email'],
                'mobile' => $withdrawlog['phone'],
            ],
        ];


        $data = $body;
        $body['extra'] = Sign::dataString($body['extra']);
        $data['sign'] = hash('sha256', Sign::dataString($body).'&key='.$this->simplypay_key);


        try {
            $response = $this->guzzle->post($this->simplypay_url,$data,$this->zr_header);

//            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdraw',$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['code']==0){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * simplytwo_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function simplytwo_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }


        $body = [
            'appId' => $this->simplytwopay_appId,
            "merOrderNo" => $withdrawlog['ordersn'],
            "currency" => 'INR',
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'notifyUrl' =>  $this->simplytwopay_notifyUrl,
            "extra" => [
                'payoutType' => 'IFSC',
                'ifsc' => $withdrawlog['ifsccode'],
                'account' => $withdrawlog['bankaccount'],
                'name' => $withdrawlog['backname'],
                'email' => $withdrawlog['email'],
                'mobile' => $withdrawlog['phone'],
            ],
            'attach' => 'Withdraw'
        ];


        $data = $body;
        $body['extra'] = Sign::dataString($body['extra']);
        $data['sign'] = hash('sha256', Sign::dataString($body).'&key='.$this->simplytwopay_key);


        try {
            $response = $this->guzzle->post($this->simplytwopay_url,$data,$this->zr_header);

//            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdraw',$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if ($response['code']==0){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * lq_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function lq_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "amount" => (string)$withdrawlog['really_money'],
            'deptId' => $this->lqpay_deptId,
            "merchantNo" => $this->lqpay_mchId,
            "orderId" => $withdrawlog['ordersn'],
            'toUser' => $withdrawlog['backname'],
            'toNumber' => $withdrawlog['bankaccount'],
            'toBank' =>  $withdrawlog['backname'],
            'toIsfc' =>  $withdrawlog['ifsccode'],
            'notifyUrl' =>  $this->lqpay_notifyUrl,
            'callbackUrl' => config('host.gameurl'),
        ];

        $SignStr = Sign::dataNotEqualString($data);

        $data['signature'] = Sign::md5WithRsaSign($SignStr,$this->lqpay_key);

        try {
            $response = $this->guzzle->post($this->lqpay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code']==0){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['id']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * threeq_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function threeq_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "mchNo"   => $this->threeqpay_mchId,
            "appId"   => $this->threeqpay_appId,
            "mchOrderNo" => $withdrawlog['ordersn'],
            "amount" => $withdrawlog['really_money'],
            "entryType" => 'IMPS',
            'accountNo' => $withdrawlog['bankaccount'],
            'accountCode' =>  $withdrawlog['ifsccode'],
            'bankName' =>  $withdrawlog['backname'],
            'accountName' =>  $withdrawlog['backname'],
            'accountEmail' =>  $withdrawlog['email'],
            'accountPhone' =>  $withdrawlog['phone'],
            'notifyUrl' =>  $this->threeqpay_notifyUrl,
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->threeqpay_key);

        try {
            $response = $this->guzzle->post($this->threeqpay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code']==0){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['transferId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * show_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function show_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "merchant_id"   => $this->showpay_mchId,
            "order_number" => $withdrawlog['ordersn'],
            "order_amount" => bcdiv((string)$withdrawlog['really_money'],'100',0),
            "type" => 'BANK',
            'email' =>  $withdrawlog['email'],
            'account' => $withdrawlog['bankaccount'],
            'name' =>  $withdrawlog['backname'],
            'ifsc' =>  $withdrawlog['ifsccode'],
            'phone' =>  $withdrawlog['phone'],
            'notify_url' =>  $this->showpay_notifyUrl,
        ];

        $data['sign'] = Sign::showWithdrawSign($data,$this->showpay_key);
        try {
            $response = $this->guzzle->post($this->showpay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code']==100){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['plat_number']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * g_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function g_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "merchantNo"   => $this->gpay_mchId,
            "orderNo" => $withdrawlog['ordersn'],
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "type" => '1',
            'notifyUrl' =>  $this->gpay_notifyUrl,
            'ext' =>  'Withdraw',
            'version' =>  '2.0.2',
            'name' =>  $withdrawlog['backname'],
            'account' => $withdrawlog['bankaccount'],
            'ifscCode' =>  $withdrawlog['ifsccode'],
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->gpay_key);
        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdraw',$data,$this->header);
//            $response = $this->guzzle->post($this->gpay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code']=='0'){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['platformOrderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * tata_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function tata_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "merchantCode"   => (int)$this->tatapay_mchId,
            "orderNo" => $withdrawlog['ordersn'],
            "amount" => (int)$withdrawlog['really_money'],
            'notifyUrl' =>  $this->tatapay_notifyUrl,
            'currency' => 'INR',
            "payType" => 1,
            'accNo' => $withdrawlog['bankaccount'],
            'accName' =>  $withdrawlog['backname'],
            'bankCode' =>  $withdrawlog['ifsccode'],
            'phone' =>  $withdrawlog['phone'],
            'email' =>  $withdrawlog['email'],
            'version' =>  '2.0',
            'sign_type' =>  'MD5',
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->tatapay_key);
        try {
//            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdraw',$data,$this->header);
            $response = $this->guzzle->post($this->tatapay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code']=='0'){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }

    /**
     * pay_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function pay_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "payAmount"   => bcdiv((string)$withdrawlog['really_money'],'100',2),
            "merId"   => $this->paypay_mchId,
            "merOrderNo"   => $withdrawlog['ordersn'],
            "payType"   => 'IMPS',
            "userBankAccount"   => $withdrawlog['bankaccount'],
            "ifscCode"   => $withdrawlog['ifsccode'],
            "userName"   => $withdrawlog['backname'],
            "notifyUrl"   => $this->paypay_notifyUrl,
            "createOrderTime"   => (string)time(),
        ];

        $data['sign'] = Sign::asciiKeyStrtolowerSign($data,$this->paypay_key);
        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdraw',$data,$this->header);
            //$response = $this->guzzle->post($this->paypay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code']=='0000'){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * yh_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function yh_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "merchNo"   => $this->yhpay_mchId,
            "orderNum"   => $withdrawlog['ordersn'],
            "bankTypeCode"   => 'IMPS',
            "bankCode"   => $withdrawlog['ifsccode'],
            "bankAccountName"   => $withdrawlog['backname'],
            "bankAccountNo"   => $withdrawlog['bankaccount'],
            "bankName"   => $withdrawlog['backname'],
            'phoneNo' =>  $withdrawlog['phone'],
            'email' =>  $withdrawlog['email'],
            'currencyCode' =>  'INR',
            "amount"   => (string)$withdrawlog['really_money'],
            "callBackUrl"   => $this->yhpay_notifyUrl,
            "charset"   => 'UTF-8',
        ];

        ksort($data);
        //JSON_UNESCAPED_SLASHES使用 JSON_UNESCAPED_SLASHES 标志：
        //在调用 json_encode 时，传递一个选项标志 JSON_UNESCAPED_SLASHES，这样它就不会转义斜杠了。
        $SignStr = json_encode($data,JSON_UNESCAPED_SLASHES).$this->yhpay_key;
        $data['sign'] = strtoupper(md5($SignStr));
        try {
            $response = $this->guzzle->post($this->yhpay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == '200' ) {
            $responseData = json_decode($response['data'],true);
            return ['code' => 200 , 'msg' => '' , 'data' => $responseData['orderNum']];

        }


        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * newai_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function newai_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "mchNo"   => $this->newaipay_merchantNo,
            "appId"   => $this->newaipay_appid,
            "mchOrderNo" => $withdrawlog['ordersn'],
            "amount" => (int)$withdrawlog['really_money'],
            "entryType" => 'IMPS',
            'accountNo' => $withdrawlog['bankaccount'],
            'accountCode' =>  $withdrawlog['ifsccode'],
            'bankName' =>  $withdrawlog['backname'],
            'accountName' =>  $withdrawlog['backname'],
            'accountEmail' =>  $withdrawlog['email'],
            'accountPhone' =>  $withdrawlog['phone'],
            'notifyUrl' =>  $this->newaipay_notifyUrl,
        ];

        $data['sign'] = Sign::asciiKeyStrtoupperSign($data,$this->newaipay_Key);
        try {
//            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdraw',$data,$this->header);
            $response = $this->guzzle->post($this->newaipay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code']=='0'){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['transferId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * allin1_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function allin1_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "app_id"   => $this->allin1pay_appid,
            "app_order_no" => $withdrawlog['ordersn'],
            "amount" => (int)bcdiv((string)$withdrawlog['really_money'],'100',0),
            'ifsc_code' =>  $withdrawlog['ifsccode'],
            'card_name' =>  $withdrawlog['backname'],
            'bank_id' => '1000',
            'card_no' => $withdrawlog['bankaccount'],
            'notify_url' => $this->allin1pay_notifyUrl,
        ];

        $dataString =  Sign::dataString($data);
        $data['sign'] = strtolower(md5($dataString.$this->allin1pay_Key));
        $this->logger->error('data'.json_encode($data));
        try {
            $response = $this->guzzle->post($this->allin1pay_url,$data,$this->header);
            $this->logger->error('$response'.json_encode($response));
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['success']) && $response['success']=='1'){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['order_no']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * make_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function make_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "orderId" => $withdrawlog['ordersn'],
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'accountUser' => $withdrawlog['backname'],
            'accountNo' => $withdrawlog['bankaccount'],
            'accountBank' => 'NA',
            'accountIfsc' =>  $withdrawlog['ifsccode'],
            'notifyUrl' => $this->makepay_notifyUrl,
            'callbackUrl' => $this->makepay_notifyUrl,
            'memo' => 'NA',
        ];


        $SignStr = json_encode($data).$this->makepay_Key;
        $sign = md5($SignStr);

        $herder = array(
            "Content-Type: application/json",
            "x-app-key: ".$this->makepay_appkey,
            "x-sign: ".$sign,
        );

        try {
            $response = Curl::post($this->makepay_url,$data,$herder);
            $response = json_decode($response,true);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 0){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['id']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * rrtwo_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public  function rrtwo_pay($withdrawlog,$type) {
        $data = [
            "merchantId"      => $this->rrtwopay_merchantNo,
            "merchantOrderId" => $withdrawlog['ordersn'],
            "amount"          => bcdiv((string)$withdrawlog['really_money'],'100',2),
        ];
        $data['sign'] = Sign::notKsortSign($data,$this->rrtwopay_Key,'');

        $data["timestamp"]   = time() * 1000;
        $data["notifyUrl"]   = $this->rrtwopay_notifyUrl;
        if($withdrawlog['type'] == 1){ //银行卡
            $data["fundAccount"] = [
                "accountType" => "bank_account",
                "contact"     => [
                    "name"          => $withdrawlog['backname'],
                ],
                "bankAccount" => [
                    "name"          => $withdrawlog['backname'],
                    "ifsc"          => $withdrawlog['ifsccode'],
                    "accountNumber" => $withdrawlog['bankaccount']
                ],
            ];
        }else{ //UIP
            $data["fundAccount"] = [
                "accountType" => "vpa",
                "contact"     => [
                    "name"          => $withdrawlog['backname'],
                ],
                "vpa" => [
                    "address"          => $withdrawlog['bankaccount'],
                ],
            ];
        }

        try {
            $response = $this->guzzle->post($this->rrtwopay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }


        if (isset($response['code']) && $response['code'] == "0") {
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['payoutId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * best_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function best_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "merchantId"      => (int)$this->bestpay_merchantNo,
            "merchantOrderId" => $withdrawlog['ordersn'],
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'phone' =>  $withdrawlog['phone'],
            'email' =>  $withdrawlog['email'],
            'currency' =>  'INR',
            "nonce"    => (string)rand(00000000,9999999999),
            "timestamp"  => time().'000',
            'account' => $withdrawlog['bankaccount'],
            'accountName' =>  $withdrawlog['backname'],
            'address' => $withdrawlog['backname'],
            'subBranch' =>  $withdrawlog['ifsccode'],
            'withdrawType' => 1,
            'bankName' => $withdrawlog['backname'],
            'remark' => 'WithDraw',
            'notifyUrl' => $this->bestpay_notifyUrl,
        ];

        $dataSign = $data['merchantId'] . $data['merchantOrderId'] . $data['amount'] . $data['nonce'] . $data['timestamp'];
        $data['sign'] = Sign::bestPaySign($dataSign,$this->bestpay_Key);

        try {
            $response = $this->guzzle->post($this->bestpay_url,$data,$this->zr_header);

        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 200 && isset($response['data']['platformOrderId'])){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['platformOrderId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }

    /**
     * zip_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function zip_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "merchantId"      => (int)$this->zippay_merchantNo,
            "merchantOrderId" => $withdrawlog['ordersn'],
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'phone' =>  $withdrawlog['phone'],
            'email' =>  $withdrawlog['email'],
            'currency' =>  'INR',
            "nonce"    => (string)rand(00000000,9999999999),
            "timestamp"  => time().'000',
            'account' => $withdrawlog['bankaccount'],
            'accountName' =>  $withdrawlog['backname'],
            'address' => $withdrawlog['backname'],
            'subBranch' =>  $withdrawlog['ifsccode'],
            'withdrawType' => 1,
            'bankName' => $withdrawlog['backname'],
            'remark' => 'WithDraw',
            'notifyUrl' => $this->zippay_notifyUrl,
        ];

        $dataSign = $data['merchantId'] . $data['merchantOrderId'] . $data['amount'] . $data['nonce'] . $data['timestamp'];
        $data['sign'] = Sign::zipPaySign($dataSign,$this->zippay_Key);

        try {
            $response = $this->guzzle->post($this->zippay_url,$data,$this->zr_header);

        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 200 && isset($response['data']['platformOrderId'])){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['platformOrderId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * upi_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function upi_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "amount" => $withdrawlog['really_money'],
            "merchantId" => $this->upipay_merchantNo,
            "orderId" => $withdrawlog['ordersn'],
            'notifyUrl' => $this->upipay_notifyUrl,
            'outType' => 'IMPS',
            'accountNumber' => $withdrawlog['bankaccount'],
            'ifsc' =>  $withdrawlog['ifsccode'],
            'accountHolder' => $withdrawlog['backname'],
        ];

        $timestamp = time().'000';

        $dataSign = $data['amount'] . $data['merchantId'] . $data['orderId'] . $timestamp . $this->upipay_Key;

        $sign = md5($dataSign);

        $herder = array(
            "Content-Type: application/json",
            "X-TIMESTAMP: ".$timestamp,
            "X-SIGN: ".$sign,
        );


        try {
            $response = Curl::post($this->upipay_url,$data,$herder);

            $response = json_decode($response,true);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 100){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['payOrderId']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }


    /**
     * q_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function q_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        //获取accesstoken
        $accessTokenData = [
            "merchant_id"   => $this->securitypay_appid,
            "sec_key"   => md5(md5($this->securitypay_Key)),
        ];

        $tokenNonce = time().rand(111,222);
        $tokenTimeStamp = time().'000';

        $tokenSignStr = 'host='.$this->securitypay_host."&uri=/v1.0/api/access-token&method=POST&nonce=$tokenNonce&timestamp=$tokenTimeStamp&";
        $accessTokenUrl = $this->securitypay_tokne_url.'?nonce='.$tokenNonce.'&timestamp='.$tokenTimeStamp.'&sign='.md5($tokenSignStr);

        try {
//            $responseTokenArray = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/sign',['data' => $accessTokenData,'url' => $accessTokenUrl],$this->header);
//            $responseToken = $responseTokenArray['response'];
//            $Authorization = $responseTokenArray['Authorization'];

            [$responseTokenStr,$Authorization] = Curl::postHerderAndBody($accessTokenUrl,$accessTokenData,["Content-Type: application/json"],1,'authorization');
            $responseToken = json_decode($responseTokenStr,true);

        }catch (\Exception $e){
            $this->logger->error('Error'.$e->getMessage());
            $responseToken = [];
        }

        if(isset($responseToken['expire_at']) && $responseToken['expire_at']){
            //代付下单
            $data = [
                "client_order_no" => $withdrawlog['ordersn'],
                "user_no" => $withdrawlog['uid'],
                "notify_url" => $this->securitypay_notifyUrl,
                "pay_amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
                "pay_currency" => 'INR',
                "receive_currency" => 'INR',
                "receiver_account_no" => $withdrawlog['bankaccount'],
                "name" => $withdrawlog['backname'],
                "ifsc" => $withdrawlog['ifsccode'],
            ];
            $Nonce = time().rand(111,222);
            $SignStr = 'host='.$this->securitypay_host."&uri=/v1.0/api/orders&method=POST&nonce=$Nonce&timestamp=$tokenTimeStamp&xpt=".$Authorization.'&';
            $Url = $this->securitypay_url.'?xpt='.$Authorization.'&nonce='.$Nonce.'&timestamp='.$tokenTimeStamp.'&sign='.md5($SignStr);

            try {
//                $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdrawNew',['data' => $data,'url' => $Url],$this->header);

                $response = $this->guzzle->post($Url,$data,$this->zr_header);

                if (isset($response['order_no']) && $response['order_no']){
                    return ['code' => 200 , 'msg' => '' , 'data' => $response['order_no']];
                }
            }catch (\Exception $e){
                $response = ['Request timed out'];
            }
        }



        $info = $response ?? $responseToken;

        Common::log($withdrawlog['ordersn'],$info,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$info); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * vendoo_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function vendoo_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "mchNo"      => $this->vendoopay_merchantNo,
            "mchOrderNo" => $withdrawlog['ordersn'],
            'currency' =>  'INR',
            "method" => 'IMPS',
            "amount" => bcdiv((string)$withdrawlog['really_money'],'100',2),
            'name' =>  $withdrawlog['backname'],
            'email' =>  $withdrawlog['email'],
            'phone' =>  $withdrawlog['phone'],
            'notifyUrl' => $this->vendoopay_notifyUrl,
            'productNo' => '191001',
            'withdrawParam' => json_encode([
                'bankCode' => $withdrawlog['ifsccode'],
                'accountName' => $withdrawlog['backname'],
                'accountNumber' => $withdrawlog['bankaccount'],
            ]),
        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->vendoopay_Key);



        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/withdrawNewOne',['data' => $data,'url' => $this->vendoopay_url],$this->header);
//            $response = $this->guzzle->post($this->vendoopay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 200){
            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * rupeelink_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function rupeelink_pay($withdrawlog,$type){

        $data = [
            "userCode"      => $this->rupeelinkpay_merchantNo,
            "orderCode" => $withdrawlog['ordersn'],
            "amount"  =>  bcdiv((string)$withdrawlog['really_money'],'100',2),
            "address"  =>  $withdrawlog['bankaccount'],
            "callbackUrl"  => $this->rupeelinkpay_notifyUrl,
        ];

        $data['sign']  = strtoupper(md5($data['orderCode'].'&'.$data['amount'].'&'.$data['address'].'&'.$data['userCode'].'&'.$this->rupeelinkpay_Key));


        try {
            $response = $this->guzzle->post($this->rupeelinkpay_url,$data,$this->header);

        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 200){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }


    /**
     * unive_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function unive_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "Merchno"      => $this->univepay_merchantNo,
            "Amount"  =>  bcdiv((string)$withdrawlog['really_money'],'100',2),
            'BankCode' => $withdrawlog['ifsccode'],
            'BankName' => 'BANK',
            'Account' => $withdrawlog['backname'],
            'CardNo' => $withdrawlog['bankaccount'],
            'PaymentType' => 'BankCard',
            "Traceno" => $withdrawlog['ordersn'],
            'NotifyUrl' => $this->univepay_notifyUrl,
            'Currency' =>  'INR',
        ];



        $signStr = Sign::dataString($data);
        $data['Signature']  = strtoupper(md5($signStr.'&'.$this->univepay_Key));
        $herder = array(
            "Content-Type: application/x-www-form-urlencoded",
        );

        try {
            $response = Curl::post($this->univepay_url,$data,$herder,[],2);
            $response = json_decode(urldecode($response),true);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['SerialNo']) && $response['SerialNo']){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['SerialNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }


    /**
     * no_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function no_pay($withdrawlog,$type){

        $data   = [
            "merchantOrderNo" => $withdrawlog['ordersn'],
            "merchantMemberNo" => $withdrawlog['uid'],
            "amount"  =>  bcdiv((string)$withdrawlog['protocol_money'],'100',2),
            "coin" => 'USDT',
            "language"  =>  'en',
            "rateType" => 1,
            "protocol" => $withdrawlog['protocol_name'],
            "notifyUrl" => $this->nopay_notifyUrl,
            "toAddress"  =>  $withdrawlog['bankaccount'],
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



        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->nopay_url,'header' => $herder],$this->header);
//        $response = Curl::post($this->nopay_url,$data,$herder);
//        $response = json_decode($response,true);


        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 0){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }



    /**
     * ms_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function ms_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "merchantNo"      => $this->mspay_merchantNo,
            "merchantOrderNo" => $withdrawlog['ordersn'],
            'description' =>  '3377WIN',
            "payAmount"  =>  bcdiv((string)$withdrawlog['really_money'],'100',2),
            "mobile"  =>  $withdrawlog['phone'],
            "email"  =>  $withdrawlog['email'],
            'bankNumber' => $withdrawlog['bankaccount'],
            'bankCode' => $withdrawlog['ifsccode'],
            'bankName' => 'BANK',
            'accountHoldName' => $withdrawlog['backname'],
            'notifyUrl' => $this->mspay_notifyUrl,

        ];

        $signStr = Sign::dataString($data);
        $data['sign']  = md5(md5($signStr.'&').$this->mspay_Key);

        try {
            $herder = array(
                "Content-Type: application/json",
            );
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->mspay_url,'header' => $herder],$this->header);
            //        $response = $this->guzzle->post($this->mspay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['status']) && $response['status'] == 200){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['platOrderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }


    /**
     * decent_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function decent_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            'accountNumber' => $withdrawlog['bankaccount'],
            "amount"  =>  bcdiv((string)$withdrawlog['really_money'],'100',2),
            'bankId' => 1,
            'bankCode' => $withdrawlog['ifsccode'],
            'customerName' => $withdrawlog['backname'],
            'description' => '3377WIN',
            "merchantOrderNo" => $withdrawlog['ordersn'],
            'notifyUrl' => $this->decentpay_notifyUrl,
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

        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->decentpay_url,'header' => $herder],$this->header);
//        $response = Curl::post($this->decentpay_url,$data,$herder);
//        $response = json_decode($response,true);

        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['data']['orderNo']) && $response['data']['orderNo']){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * fly_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function fly_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            'merchantId' => $this->flypay_merchantNo,
            "merchantOrderNum" => $withdrawlog['ordersn'],
            "amount"  =>  bcdiv((string)$withdrawlog['really_money'],'100',2),
            'callBackUrl' => $this->flypay_notifyUrl,
            'accountName' => $withdrawlog['backname'],
            'bankName' => $withdrawlog['backname'],
            'accountNumber' => $withdrawlog['bankaccount'],
            'ifscCode' => $withdrawlog['ifsccode'],
            "phone"  =>  $withdrawlog['phone'],
            "signType"  =>  'RSA',
        ];

        $signStr = Sign::dataString($data);
        $data['sign'] = Sign::Sha512WithRsa($signStr,$this->flypay_Key);


        try {
            $herder = array(
                "Content-Type: application/json",
            );
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->flypay_url,'header' => $herder],$this->header);
//        $response = $this->guzzle->post($this->flypay_url,$data,$this->zr_header);

        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 'SUCCESS'){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['platformOrderNum']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }

    /**
     * kk_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function kk_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "partnerId"      => $this->kkpay_merchantNo,
            "partnerWithdrawNo"      => $withdrawlog['ordersn'],
            "amount"  =>  (string)$withdrawlog['really_money'],
            "currency"   => 'INR',
            "notifyUrl"  => urlencode($this->kkpay_notifyUrl),
            "receiptMode"  => '1',
            'accountNumber' => urlencode($withdrawlog['bankaccount']),
            'accountName' => urlencode($withdrawlog['backname']),
            "accountPhone"  =>  urlencode($withdrawlog['phone']),
            "accountEmail"  =>  urlencode($withdrawlog['email']),
            "accountExtra1"  =>  $withdrawlog['ifsccode'],
            "accountExtra2"  =>  $withdrawlog['ifsccode'],
            "version"   => '1.0',
        ];

        $signData = $data;
        $signData['notifyUrl'] = $this->kkpay_notifyUrl;
        $signData['accountNumber'] = $withdrawlog['bankaccount'];
        $signData['accountName'] = $withdrawlog['backname'];
        $signData['accountPhone'] = $withdrawlog['phone'];
        $signData['accountEmail'] = $withdrawlog['email'];
        $signStr = Sign::dataString($signData);

        $data['sign']  = strtoupper(md5($signStr.'&key='.$this->kkpay_merchantKey));
        $herder = array(
            "Content-Type: application/x-www-form-urlencoded",
        );

        try {
            //$response = Curl::get($this->kkpay_url,$data,$herder,[],2);
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testGetCurlUrl',['data' => $data,'url' => $this->kkpay_url],$this->header);
            //$response = $this->guzzle->post($this->kkpay_url,$data,$this->header);
            //$response = json_decode(urldecode($response),true);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == '0000'){

            return ['code' => 200 , 'msg' => '' , 'data' => $withdrawlog['ordersn']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }


    /**
     * tk_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function tk_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            'merchant_id' => $this->tkpay_merchantNo,
            "order_id" => $withdrawlog['ordersn'],
            "amount"  =>  $withdrawlog['really_money'],
            "withdraw_type"  =>  1,
            'notify_url' => $this->tkpay_notifyUrl,
            'name' => $withdrawlog['backname'],
            "phone"  =>  $withdrawlog['phone'],
            "email"  =>  $withdrawlog['email'],
            'bank_code' => $withdrawlog['ifsccode'],
            'currency' => 'INR',
            'account' => $withdrawlog['bankaccount'],
        ];

        $data['sign'] = Sign::asciiKeyStrtolowerSign($data,$this->tkpay_Key);


        try {

            $response = $this->guzzle->post($this->tkpay_url,$data,$this->zr_header);

        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == '200'){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['id']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }

    /**
     * kktwo_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function kktwo_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "partnerId"      => $this->kktwopay_merchantNo,
            "partnerWithdrawNo"      => $withdrawlog['ordersn'],
            "amount"  =>  (string)$withdrawlog['really_money'],
            "currency"   => 'INR',
            "notifyUrl"  => urlencode($this->kktwopay_notifyUrl),
            "receiptMode"  => '1',
            'accountNumber' => urlencode($withdrawlog['bankaccount']),
            'accountName' => urlencode($withdrawlog['backname']),
            "accountPhone"  =>  urlencode($withdrawlog['phone']),
            "accountEmail"  =>  urlencode($withdrawlog['email']),
            "accountExtra1"  =>  $withdrawlog['ifsccode'],
            "accountExtra2"  =>  $withdrawlog['ifsccode'],
            "version"   => '1.0',
        ];

        $signData = $data;
        $signData['notifyUrl'] = $this->kktwopay_notifyUrl;
        $signData['accountNumber'] = $withdrawlog['bankaccount'];
        $signData['accountName'] = $withdrawlog['backname'];
        $signData['accountPhone'] = $withdrawlog['phone'];
        $signData['accountEmail'] = $withdrawlog['email'];
        $signStr = Sign::dataString($signData);

        $data['sign']  = strtoupper(md5($signStr.'&key='.$this->kktwopay_merchantKey));
        $herder = array(
            "Content-Type: application/x-www-form-urlencoded",
        );

        try {
            //$response = Curl::get($this->kktwopay_url,$data);
            //$response = $this->guzzle->get($this->kktwopay_url,$data,$this->header);
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testGetCurlUrl',['data' => $data,'url' => $this->kktwopay_url],$this->header);
            //$response = $this->guzzle->post($this->kktwopay_url,$data,$this->header);
            //$response = json_decode(urldecode($response),true);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == '0000'){

            return ['code' => 200 , 'msg' => '' , 'data' => $withdrawlog['ordersn']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * one_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function one_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "mchId"      => $this->onepay_merchantNo,
            "txChannel"      => 'TX_INDIA_001',
            "appId"      => $this->onepay_appId,
            "timestamp"      => time(),
            "mchOrderNo"      => $withdrawlog['ordersn'],
            "name"  =>  $withdrawlog['backname'],
            "phone"  =>  (string)$withdrawlog['phone'],
            "email"  =>  $withdrawlog['email'],
            "bankCode"  =>  'BANK_IN',
            "account"  => $withdrawlog['bankaccount'],
            "amount"  =>  bcdiv((string)$withdrawlog['really_money'],'100',2),
            "notifyUrl"  => $this->onepay_notifyUrl,
            "ifsc"  =>  $withdrawlog['ifsccode'],
        ];

        $signStr = Sign::dataString($data);
        $data['sign'] = Sign::Sha256WithRsa($signStr,$this->onepay_Key);
        $herder = array(
            "Content-Type: application/json",
            "lang: en",
        );


        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->onepay_url,'header' => $herder],$this->header);
//            $response = Curl::post($this->onepay_url,$data,$herder);
//            $response = json_decode($response,true);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['status']) && $response['status'] == '200'){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['platOrderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * global_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function global_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "mchId"      => $this->globalpay_merchantNo,
            "signType"      => 'md5',
            "mchOrderNo"      => $withdrawlog['ordersn'],
            "amount"  =>  $withdrawlog['really_money'],
            "channelType"      => 'imps',
            "notifyUrl"  => $this->globalpay_notifyUrl,
            "bankName"  =>  $withdrawlog['backname'],
            "bankCode"  =>   $withdrawlog['ifsccode'],
            "cardName"  =>   $withdrawlog['backname'],
            "cardNo"  =>   $withdrawlog['bankaccount'],
            "rand"   => time().rand(1111,9999),
        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->globalpay_Key);

        $herder = array(
            "Content-Type: multipart/form-data",
        );

        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/globalpay',['data' => $data,'url' => $this->globalpay_url,'header' => $herder],$this->header);
//        $response = Curl::multipartFormData($this->globalpay_url,$data,$herder);
//        $response = json_decode($response,true);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == '0'){

            return ['code' => 200 , 'msg' => '' , 'data' => 'suc'];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * a777_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function a777_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "app_id"      => $this->a777pay_merchantNo,
            "merchant_order_id"      => $withdrawlog['ordersn'],
            "amount"  =>  bcdiv((string)$withdrawlog['really_money'],'100',2),
            "customer_name"  =>  $withdrawlog['backname'],
            "customer_phone"  =>  (string)$withdrawlog['phone'],
            "customer_email"  =>  $withdrawlog['email'],
            "payout_mode"  =>  'INDIA_IMPS',
            "customer_account_type"  =>  $withdrawlog['ifsccode'],
            "customer_account_no"  =>  $withdrawlog['bankaccount'],
            "notify_url"  => $this->a777pay_notifyUrl,
        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->a777pay_Key);

        $herder = array(
            "Content-Type: application/x-www-form-urlencoded",
        );

        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->a777pay_url,'header' => $herder],$this->header);
//            $response = $this->guzzle->post($this->a777pay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 200){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['system_order_id']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * masat_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function masat_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }

        $data = [
            "appId"      => $this->masatpay_notifyUrl,
            "orderNumber"      => $withdrawlog['ordersn'],
            "amount"  =>  bcdiv((string)$withdrawlog['really_money'],'100',2),
            "bankName"  =>  $withdrawlog['backname'],
            "receiptAccountName"  =>  $withdrawlog['backname'],
            "cardNumber"  =>  $withdrawlog['bankaccount'],
            "mobile"  =>  (string)$withdrawlog['phone'],
            "ifsc"  =>  $withdrawlog['ifsccode'],
            "notifyCallback"  => $this->masatpay_notifyUrl,
        ];

        $data['sign']  = Sign::asciiKeyStrtolowerSign($data,$this->masatpay_Key);

        $herder = array(
            "Content-Type: application/json"
        );

        try {
            $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->masatpay_url,'header' => $herder],$this->header);
//            $response = $this->guzzle->post($this->masatpay_url,$data,$this->header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 10000){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['platformOrderNumber']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    private function sendWithdrawFail($withdrawlog,$response){
        $msg = '';
//        if(Common::getConfigValue('is_tg_send') == 1) {
//            //发送提现失败消息to Tg
//            $msg = \service\TelegramService::withdrawFail($withdrawlog, $response);
//        }
        return $msg;
    }


    /**
     * 随机生产几位字母
     * @param $length
     * @return string
     */
    private function generateRandomString($uid = '',$length = 6){

        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString.$uid;

    }
}
