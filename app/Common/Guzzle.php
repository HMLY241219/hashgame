<?php

declare(strict_types=1);

namespace App\Common;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Hyperf\Guzzle\PoolHandler;
use Hyperf\Guzzle\RetryMiddleware;
use Psr\Http\Message\ResponseInterface;
use Hyperf\Coroutine\Coroutine;
use function Hyperf\Support\make;
use function Symfony\Component\Translation\t;
use  App\Common\Curl;

class Guzzle
{
    /**
     * @var Client
     */

    private array $header = ["Content-Type" => "application/x-www-form-urlencoded"];//默认请求头


    protected $client;

//    public function __construct()
//    {
//
//        $handler = null;
//        if (Coroutine::inCoroutine()) {
//            //创建了一个连接池处理器 PoolHandler，并设置了最大连接数为 50。连接池可以帮助我们管理 HTTP 连接，避免频繁地创建和销毁连接，提高性能和资源利用率
//            $handler = make(PoolHandler::class, [
//                'option' => [
//                    'max_connections' => 50,
//                ],
//            ]);
//        }
//
//        // 默认的重试Middleware 请求失败时自动进行重试，设置了重试次数为 1 次，延迟为 2 秒。
//        $retry = make(RetryMiddleware::class, [
//            'retries' => 1,
//            'delay' => 2,
//        ]);
//
//        //然后，我们创建了一个处理器堆栈，并将连接池处理器加入其中。处理器堆栈是 Guzzle 中用于构建请求处理流程的工具，可以按顺序添加多个中间件来对请求进行处理。
//        $stack = HandlerStack::create($handler);
//        //接着，我们将重试中间件加入到处理器堆栈中，并给中间件起了一个名称为 'retry'。
//        $stack->push($retry->getMiddleware(), 'retry');
//
//        //最后，我们使用处理器堆栈创建了一个 Guzzle HTTP 客户端对象，并将其存储在类的成员变量
//        // $this->client 中。这个客户端对象已经配置了连接池和重试中间件，可以在协程环境中发送 HTTP 请求，并自动进行连接复用和重试。
//        $this->client = make(Client::class, [
//            'config' => [
//                'handler' => $stack,
//            ],
//        ]);
//    }

    /**
     * 发送 GET 请求.
     *
     * @param string $url
     * @param array $query
     * @param array $headers  他这里的请求头是个二维数组，不是索引数组，跟原生的curl有区别
     * @param int $type 1= json_decode返回 ,2=直接返回三方数据
     * @param int|null $timeout
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get(string $url, array $query = [], array $headers = [],int $type = 1,?int $timeout = null)
    {
//        $options = [
//            'form_params' => $query,
//            'headers' => $headers,
//            'timeout' => $timeout ?? 5, // 设置超时时间，默认为 5 秒
//        ];
//        $response =  $this->client->get($url, $options);
//
//
//        return $this->getData($response,$type);


        $response = Curl::get($url);

        return json_decode($response,true);

    }

    /**
     * 发送 POST 请求.
     *
     * @param string $url
     * @param array $data
     * @param array $headers 他这里的请求头是个二维数组，不是索引数组,跟原生的curl有区别
     * @param int $type 1= json_decode返回 ,2=直接返回三方数据
     * @param int|null $timeout
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post(string $url, array $data = [], array $headers = [],int $type = 1 ,?int $timeout = null)
    {
//        $headers = $headers ?:  $this->header;
//        $options = [
//            $this->getField($headers) => $data,
//            'headers' => $headers,
//            'timeout' => $timeout ?? 5, // 设置超时时间，默认为 5 秒
//        ];
//        $response =  $this->client->post($url, $options);
//        return $this->getData($response,$type);
        $headers = $headers ?:  $this->header;
        $aaatype = $this->getField($headers);
        if($aaatype == 1){
            $header = array(
                "Content-Type: application/json",
            );
        }else{
            $header = array(
                "Content-Type: application/x-www-form-urlencoded",
            );
        }
        $response = Curl::post($url,$data,$header,[],$aaatype);
        //$this->logger->error('post data:'.$response);
        return $this->getData($response,$type);
    }

    /**
     * 处理数据
     * @param $response
     * @return void
     */
    private function getData($response,$type = 1){
        // 获取响应的状态码
//        $status = $response->getStatusCode();
        // 获取响应的头部信息
        // $headers = $response->getHeaders();

        // 将原始内容解析为 JSON 格式
        // $body = $response->getBody()->getContents();

        return $type == 1 ? json_decode($response,true) : $response;
    }

    /**
     * 获取参数字段
     * @param array $headers 请求头
     * @return string
     */
//    private function getField(array $headers):string{
//        $contentType = $headers['Content-Type'];
////        if(str_contains($contentType, 'application/json')){
////            return 'json';
////        }else{
////            return 'form_params';
////        }
//        if(str_contains($contentType, 'application/json')){
//            return 1;
//        }else{
//            return 2;
//        }
//    }

    private function getField(array $headers):int{
        $contentType = $headers['Content-Type'];
//        if(str_contains($contentType, 'application/json')){
//            return 'json';
//        }else{
//            return 'form_params';
//        }
        if(str_contains($contentType, 'application/json')){
            return 1;
        }else{
            return 2;
        }
    }
}