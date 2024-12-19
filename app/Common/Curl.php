<?php
namespace App\Common;

use App\Controller\AbstractController;
use http\Url;

class Curl extends AbstractController {

    //post 请求
    public static function post($url, $data, $headerData,$header = [],$type = 1,$getHerderData = '')
    {

        if($type == 1){
            $data = json_encode($data);
        }else{
            $data = http_build_query($data);
        }

        if (!empty($header)) {
            $headerData = array_merge($headerData, $header);
        }

        $timeout = 60;

        // 启动一个CURL会话
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查。https请求不验证证书和hosts
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_POST, 1); // Post提交的数据包
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerData); //模拟的header头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HEADER, true); // 启用响应头输出
        $output = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE); // 获取响应头大小

        $header = substr($output, 0, $header_size); // 提取响应头内容
        $body = substr($output, $header_size); // 提取响应体内容
        if(!$body){
            return false;
        }

        curl_close($curl);
        if($getHerderData){ //获取响应体的参数
            return self::getHerderValue($header,$getHerderData);
        }else{
            return $body;
        }

    }


    /**
     * @param $url  Url 链接
     * @param $data  数据
     * @param $urlencodeData  需要urldecode 的数据  [appid,money]  传入这种格式表示 appid 和 money 需要urldecode
     * @return bool|string
     */
    //get 请求
    public static function get($url,$data = [],$urlencodeData = [])
    {

        $url = self::getUrl($url,$data,$urlencodeData);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
        // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
        // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;

    }

    public static function getSimple($url, $header = [])
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header); //模拟的header头
        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
        // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
        // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    //post 请求获取响应头和响应体
    public static function postHerderAndBody($url, $data, $headerData,$type = 1,$getHerderData = '')
    {
        if($type == 1){
            $data = json_encode($data);
        }else{
            $data = http_build_query($data);
        }

        $timeout = 60;

        // 启动一个CURL会话
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查。https请求不验证证书和hosts
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_POST, 1); // Post提交的数据包
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerData); //模拟的header头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HEADER, true); // 启用响应头输出
        $output = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE); // 获取响应头大小

        $header = substr($output, 0, $header_size); // 提取响应头内容
        $body = substr($output, $header_size); // 提取响应体内容
        curl_close($curl);
        if(!$body){
            return false;
        }

//        return [$body,$header];

        return [$body,self::getHerderValue($header,$getHerderData)];
    }


    /**
     * @param $url 访问url
     * @param $data 数据包
     * @param  $urlencodeData urlencode 的数据
     * @return void 解析url链接
     */
    public static function getUrl($url,$data = [],$urlencodeData = []){
        if(!$data){
            return $url;
        }
        $string = [];
        foreach ($data as $key => $value) {
            if($urlencodeData && in_array($key,$urlencodeData)){

                $string[] = $key . '=' . urlencode($value);
            }else{
                $string[] = $key . '=' . $value;
            }
        }
        return $url.'?'.(implode('&', $string));
    }


    public static function getHerderValue($header,$field){
        // 解析响应头内容，获取指定字段的值
        $header_lines = explode("\r\n", $header);
        foreach ($header_lines as $line) {
            if (strpos($line, $field.':') !== false) {
                return trim(substr($line, strlen($field.':')));
                break;
            }
        }
        return '';
    }


    /**
     * Content-Type：multipart/form-data 请求POST
     * @param $url 请求的url
     * @param $data 数组
     * @param $header 请求头
     * @return bool|string
     */
    public static function multipartFormData($url,$data,$header){

        // 初始化 cURL
        $ch = curl_init();

        // 设置 cURL 选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // 直接传递数组
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        // 执行请求
        $response = curl_exec($ch);


        // 关闭 cURL
        curl_close($ch);
        return $response;
    }
}


