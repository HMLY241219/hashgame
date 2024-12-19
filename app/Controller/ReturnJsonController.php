<?php
declare(strict_types=1);

namespace App\Controller;


use function Hyperf\Config\config;
use Psr\Http\Message\ServerRequestInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;
use function Hyperf\Support\env;

/**
 *  返回给客户端的类
 */

class ReturnJsonController
{
    #[Inject]
    private ServerRequestInterface $request;

    #[Inject]
    private ResponseInterface $response;
    /**
     *
     * 成功
     * @param $msg  错误信息
     * @param $data  返回数据
     * @param $type  1 = 加密返回, 2 = 不加密返回
     * @return void
     */
    public function successFul(int $code = 200,mixed $data = null,int $type = 1){
        return ($type == 1 && env('API_DATA_ENCRYPT', true))
            ? self::encryptionData(['code' => $code,'msg' => $this->getMsg($code),'data' => $data])
            : $this->response->json(['code' => $code,'msg' => $this->getMsg($code),'data' => $data]);
    }


    /**
     * 失败
     * @param $code  错误码
     * @param $data  返回数据
     * @param $type  1 = 加密返回, 2 = 不加密返回
     * @param string $msg  错误信息
     * @return void
     */
    public function failFul(int $code = 201,mixed $data = null,int $type = 1, string $msg = ''){
        //str_starts_with('aaa,bbb', 'aaa')用于检查一个字符串是否以指定的子字符串开头。它的作用是检查一个字符串是否以指定的前缀开始，并返回一个布尔值表示结果
        $statusCode = str_starts_with((string)$code, '4') ? 201 : 200; //如果是明文返回,同时又是400状态，响应状态需要返回对应的状态码
        return ($type == 1 && env('API_DATA_ENCRYPT', true))
            ? self::encryptionData(['code' => $code,'msg' => $msg ?: $this->getMsg($code),'data' => $data])
            : $this->response->json(['code' => $code,'msg' => $msg ?: $this->getMsg($code),'data' => $data])->withStatus($statusCode);
    }

    /**
     * @param $code int 错误码
     * @return void 获取错误信息
     */
    private function getMsg(int $code){
        return config('lang.'.$code.'.'.($this->request->getAttribute('lang') ?: 'en'));
    }

    /**
     * 加密数据
     * @param $data 数据
     * @return void
     */
    private function encryptionData($data){
        return base64_encode($this->rc4(json_encode($data)));
    }

    /**
     * rc4加密
     * @param $data  数据
     * @param $pwd  包名
     * @return string
     */
    private function rc4(string $data,$pwd = '') {
        //不传包名直接获取请求头的包名
        $pwd = $pwd ?: $this->request->getAttribute('packname');

        $key[]       = "";
        $box[]       = "";
        $pwd_length  = strlen($pwd);
        $data_length = strlen($data);
        $cipher      = '';
        for ($i = 0; $i < 256; $i++) {
            $key[$i] = ord($pwd[$i % $pwd_length]);
            $box[$i] = $i;
        }
        for ($j = $i = 0; $i < 256; $i++) {
            $j       = ($j + $box[$i] + $key[$i]) % 256;
            $tmp     = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $data_length; $i++) {
            $a       = ($a + 1) % 256;
            $j       = ($j + $box[$a]) % 256;
            $tmp     = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $k = $box[(($box[$a] + $box[$j]) % 256)];
            $cipher .= chr(ord($data[$i]) ^ $k);
        }
        return $cipher;
    }
}

