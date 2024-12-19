<?php
declare(strict_types=1);
namespace App\Common;



/**
 *  签名公共方法
 */
class Sign
{

    /**
     * 对签名的数组进行排序，最后追加上key，不需要&key，在md5加密
     * @param $params array  签名数组
     * @param $secretKey string 私钥
     * @return string
     */
    public static function asciiSignNotKey(array $params,string $secretKey):string{
        ksort($params);
        $string = [];
        foreach ($params as $key => $value) {
            if ($key == 'sign') continue;
            $string[] = $key . '=' . $value;
        }
        $sign = (implode('&', $string)) . $secretKey;

        return md5($sign);
    }


    /**
     * ASCII 码从小到大排序 「&key=」，再拼接您的商户密钥 再md5 转为大写
     * @param $params
     * @param $paykey
     * @return void
     */
    public static function asciiKeyStrtoupperSign(array $params,string $paykey,string $keyname = 'key'):string{
        ksort($params);
        $string = [];
        foreach ($params as $key => $value) {
            if ($key == 'sign') continue;
            $string[] = $key . '=' . $value;
        }
        $sign = (implode('&', $string)) . "&$keyname=" . $paykey;

        return strtoupper(md5($sign));
    }

}

