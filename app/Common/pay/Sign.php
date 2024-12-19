<?php
declare(strict_types=1);
namespace App\Common\pay;




class Sign{

    /**
     * 进行RSA分段加密方式
     * @param $data_str
     * @param $key
     * @param $type 1 = 直接返回 base64_encode ， 2 = 返回 base64_encode以后还需要urlencode
     * @return string
     */
    public static function JoySign($data_str, $key,$type = 1){
        // 私钥

        $privateKeyBase64 = "-----BEGIN RSA PRIVATE KEY-----\n";
        $privateKeyBase64 .= wordwrap($key, 64, "\n", true);
        $privateKeyBase64 .= "\n-----END RSA PRIVATE KEY-----\n";
        // 签名
        openssl_sign($data_str, $signature, $privateKeyBase64, OPENSSL_ALGO_SHA512);
        return $type == 1 ? base64_encode($signature) : urlencode(base64_encode($signature));
    }


    /**
     * ASCII 码从小到大排序 「&key=」，再拼接您的商户密钥 再md5 转为大写
     * @param array $params 签名数据
     * @param string $paykey 秘钥
     * @param string $keyname  秘钥名称
     * @param int $type 类型:1=直接秘钥名称追加上秘钥 ,2=字符串直接追加秘钥
     * @return string
     */
    public static function asciiKeyStrtoupperSign(array $params,string $paykey,string $keyname = 'key',int $type = 1):string{
        ksort($params);
        $string = [];
        foreach ($params as $key => $value) {
            if ($key == 'sign') continue;
            if(is_array($value)){
                $string[] = $key . '=' . json_encode($value);
            }else{
                $string[] = $key . '=' . $value;
            }

        }
        $sign = $type == 1 ? (implode('&', $string)) . "&$keyname=" . $paykey : (implode('&', $string)) .'&'.$paykey;

        return strtoupper(md5($sign));
    }


    /**
     * ASCII 码从小到大排序 「&key=」，再拼接您的商户密钥 再md5 转为小写
     * @param array $params 签名数据
     * @param string $paykey 秘钥
     * @param string $keyname  秘钥名称
     * @param int $type 类型:1=直接秘钥名称追加上秘钥 ,2=字符串直接追加秘钥
     * @return string
     */
    public static function asciiKeyStrtolowerSign(array $params,string $paykey,string $keyname = 'key',int $type = 1):string{
        ksort($params);
        $string = [];
        foreach ($params as $key => $value) {
            if ($key == 'sign') continue;
            $string[] = $key . '=' . $value;
        }
        $sign = $type == 1 ? (implode('&', $string)) . "&$keyname=" . $paykey : (implode('&', $string)) .'&'.$paykey;
        return strtolower(md5($sign));
    }


    /**
     * 不用排序的签名
     * @param array $data 签名数据
     * @param array $paykey 秘钥
     * @param int $strtoType 类型:1=小写 2=大写
     * @return string
     */
    public static function notKsortSign(array $data,string $paykey,string $keyname = 'key',int $strtoType = 1):string{
        $string = [];
        foreach ($data as $key => $value) {
            if ($key == 'sign') continue;
            $string[] = $key . '=' . $value;
        }
        $sign = $keyname ? (implode('&', $string)) . "&$keyname=".$paykey : (implode('&', $string)) .'&'.$paykey;

        return $strtoType == 1 ? strtolower(md5($sign)) : strtoupper(md5($sign));
//        return $sign;

    }

    /**
     * ASCII 码从小到大排序 将data数据用&拼接直接返回
     * @param $params 数据
     * @param $type 1= 不进行url编码 . 2= 进行url编码
     * @return string
     */
    public static function dataString($params,$type = 1){
        ksort($params);
        $string = [];
        foreach ($params as $key => $value) {
            if ($key == 'sign') continue;
            if($type == 1){
                $string[] = $key . '=' . $value;
            }else{
                $string[] = $key . '=' . rawurlencode($value);
            }

        }

        return implode('&', $string);

    }



    /**
     * 没有拼接等号 直接返回比如json中有a, b, c 三个字段的值分别为1,2,3. 那么拼接后的签名串为"a1b2c3"
     * ASCII 码从小到大排序 将data数据用&拼接直接返回
     * @param $params 数据
     * @return string
     */
    public static function dataNotEqualString($params){
        ksort($params);
        $string = '';
        foreach ($params as $key => $value) {
            if ($key == 'sign') continue;
            if(!$value)continue;
            $string .= $key. $value;

        }
        return $string;

    }



    /**
     * 对签名的数组进行排序，最后追加上key，key直接追加上去进行签名，在md5加密 转为小写
     * @param $params  签名数组
     * @param $serpaykey key
     * @return string
     */
    public static function asciiKeyStrtolowerNotSign(array $params,$key){
        $sign = self::dataString($params).$key;
        return strtolower(md5($sign));
    }


    /**生成指定长度的随机十六进制字符串
     * @param int $length
     * @return string
     */
    public static function createHex(int $length) {
        return bin2hex(random_bytes($length));
    }

    /**
     * EaniShopPay签名
     * @param $appId appId
     * @param $appSecret appSecret
     * @param $method 请求方式
     * @param $url 请求Url
     * @param $timestamp 时间戳(毫秒)
     * @param $nonce 随机字符串
     * @param $body 请求体
     * @return string
     */
    public static function EaniShopSign($appId, $appSecret, $method, $url, $timestamp, $nonce, $body){

        $signContent = "$appId\n$appSecret\n$method\n$url\n$timestamp\n$nonce\n$body\n";

        $content = mb_convert_encoding($signContent, 'UTF-8');

        $hash = hash('sha256', $content, true);
        return bin2hex($hash);


    }

    /**
     * ff_pay签名
     * @param $data
     * @param $keyname
     * @return void
     */
    public static function FfPaySign($data,$keyname){
        ksort($data);
        $string = [];
        foreach ($data as $key => $value) {
            if ($key == 'sign') continue;
            if ($key == 'sign_type') continue;
            $string[] = $key . '=' . $value;
        }
        $signStr = (implode('&', $string)) . "&key=" . $keyname;
        return strtolower(md5($signStr));
    }

    /**
     * newfunpay签名
     * @param $data
     * @param $mch_private_key
     * @return mixed
     */
    public static function newFunPaySing($data,$mch_private_key){
        ksort($data); // 对数据按 key 排序

// 构建签名字符串
        $str = '';
        foreach ($data as $k => $v){
            if(!empty($v)){
                $str .=$k.'='.$v.'&';
            }
        }
        $str = rtrim($str,'&');

        return self::getRsaSign($str,$mch_private_key);
    }


    /**
     * @param $str string 签名字符串
     * @param $mch_private_key string 商户私钥
     * @return array|string|string[]
     */
    public static function getRsaSign(string $str, string $mch_private_key)
    {
        // 处理私钥
        $pem = $mch_private_key;
        $pem = preg_replace('/\\n/', "\n", $pem); // 确保换行符正确
        $pem = "-----BEGIN PRIVATE KEY-----\n" . trim(chunk_split($pem, 64)) . "\n-----END PRIVATE KEY-----";

// 获取私钥资源
        $private_key = openssl_pkey_get_private($pem);

// 加密签名字符串
        $crypto = '';
        foreach (str_split($str, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $private_key);
            $crypto .= $encryptData;
        }

// Base64 编码
        $encrypted = base64_encode($crypto);
        return str_replace(array('+','/','='),array('-','_',''),$encrypted);

   }


    /**
     * MD5withRSA签名 最后返回base64_encode格式
     * @param string $SignStr
     * @param string $mch_private_key
     * @return string
     */
   public static function md5WithRsaSign(string $SignStr, string $mch_private_key){

       $private_key = preg_replace('/\\n/', "\n", $mch_private_key); // 确保换行符正确
       $private_key = "-----BEGIN PRIVATE KEY-----\n" . trim(chunk_split($private_key, 64)) . "\n-----END PRIVATE KEY-----";

       $private_key_resource_id = openssl_get_privatekey($private_key);

       openssl_sign($SignStr, $signature, $private_key_resource_id, 2);

//       openssl_free_key($private_key_resource_id); //php8.0以上函数已经被弃用

       $private_key_resource_id = null;
       return base64_encode($signature);
   }


    /**
     * show_pay代付签名
     * @param $data
     * @param $privateKey
     * @return string|null
     */
   public static function showWithdrawSign($data,$privateKey){
       $data = json_encode($data);
       $privateKey = preg_replace('/\\n/', "\n", $privateKey); // 确保换行符正确
       $pem = chunk_split($privateKey, 64, "\n");
       $pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
       $privKey = openssl_pkey_get_private($pem);

       $result='';
       $data = str_split($data, 117);
       foreach ($data as $block) {
           $isEncrypted=openssl_private_encrypt($block, $dataEncrypt,$privKey);
           if (!$isEncrypted)
           {
               continue ;
           }
           $result .= $dataEncrypt;
       }
       return $result ? base64_encode($result) : null;
   }

    /**
     * bestPaySign
     * @param $dataSign
     * @param $privateKey
     */
   public static function bestPaySign($dataSign,$privateKey){
       $privateKey = preg_replace('/\\n/', "\n", $privateKey); // 确保换行符正确
       $pem = chunk_split($privateKey, 64, "\n");
       $pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
       $privKey = openssl_pkey_get_private($pem);

       $result='';
       $dataSign = str_split($dataSign, 117);
       foreach ($dataSign as $block) {
           $isEncrypted=openssl_private_encrypt($block, $dataEncrypt,$privKey);
           if (!$isEncrypted)
           {
               continue ;
           }
           $result .= $dataEncrypt;
       }
       return $result ? base64_encode($result) : null;
   }

    /**
     * zipPaySign
     * @param $dataSign
     * @param $privateKey
     */
    public static function zipPaySign($dataSign,$privateKey){
        $privateKey = preg_replace('/\\n/', "\n", $privateKey); // 确保换行符正确
        $pem = chunk_split($privateKey, 64, "\n");
        $pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
        $privKey = openssl_pkey_get_private($pem);

        $result='';
        $dataSign = str_split($dataSign, 117);
        foreach ($dataSign as $block) {
            $isEncrypted=openssl_private_encrypt($block, $dataEncrypt,$privKey);
            if (!$isEncrypted)
            {
                continue ;
            }
            $result .= $dataEncrypt;
        }
        return $result ? base64_encode($result) : null;
    }

    /**
     * securityPaySign
     * @param $dataSign
     * @param $privateKey
     */
    public static function securityPaySign($data,$securitypay_Key){
        ksort($data);
        $string = [];
        foreach ($data as $key => $value) {
            if ($key == 'sign') continue;
            if($key == 'responseJsonFormat'){
                $string[] = 'responseJsonFormat=true';
            }else{
                $string[] = $key . '=' . $value;
            }
        }

        $signStr =(implode('&', $string)) . "&securityKey=" . $securitypay_Key;
        return strtoupper(md5($signStr));
    }



    /**
     * SHA512withRSA签名 最后返回base64_encode格式
     * @param string $SignStr
     * @param string $mch_private_key
     * @return string
     */
    public static function Sha512WithRsa(string $SignStr, string $mch_private_key){

        $private_key = preg_replace('/\\n/', "\n", $mch_private_key); // 确保换行符正确
        $private_key = "-----BEGIN PRIVATE KEY-----\n" . trim(chunk_split($private_key, 64)) . "\n-----END PRIVATE KEY-----";

        $private_key_resource_id = openssl_get_privatekey($private_key);

        openssl_sign($SignStr, $signature, $private_key_resource_id, OPENSSL_ALGO_SHA512);

//       openssl_free_key($private_key_resource_id); //php8.0以上函数已经被弃用

        $private_key_resource_id = null;
        return base64_encode($signature);
    }



    /**
     * SHA256withRSA签名 最后返回base64_encode格式
     * @param string $SignStr
     * @param string $mch_private_key
     * @return string
     */
    public static function Sha256WithRsa(string $SignStr, string $mch_private_key): string
    {
        $private_key = preg_replace('/\\n/', "\n", $mch_private_key); // 确保换行符正确
        $private_key = "-----BEGIN PRIVATE KEY-----\n" . trim(chunk_split($private_key, 64)) . "\n-----END PRIVATE KEY-----";
        //转换为openssl密钥，必须是没有经过pkcs8转换的私钥
        $private_key_resource_id = openssl_get_privatekey($private_key);
        //调用openssl内置签名方法，生成签名$sign

        openssl_sign($SignStr, $signature, $private_key_resource_id, OPENSSL_ALGO_SHA256);
        //释放资源
        $private_key_resource_id = null;
        return base64_encode($signature);
    }

}

