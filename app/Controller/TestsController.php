<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller;
use App\Amqp\Producer\SlotsProducer;
use App\Exception\ErrMsgException;
use App\Service\BlockGameService;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\parallel;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Amqp\Producer;
use Hyperf\Amqp\Builder\QueueBuilder;
use App\Common\QrcodeCommon;
use function Hyperf\Support\env;

#[AutoController]
class TestsController extends AbstractController
{

    #[Inject]
    protected ReturnJsonController $ReturnJsonController;
    #[Inject]
    protected ClientFactory $clientFactory;

    #[Inject]
    protected Producer $product;

    #[Inject]
    protected QueueBuilder $queueBuilder;


    public function index()
    {
        return config('withdrawbankcode.vnd.qf888_pay')['VietinBank'][4];
        return QrcodeCommon::generateQrCodeBase64('1111');
        return 555;

    }

    public function sleep($seconds)//RequestInterface $request
    {
        env();
        //$seconds = $request->query('seconds', 1);
        sleep((int)$seconds);
        var_dump('sleep hello ====> ' . $seconds);
        return $seconds;
    }

    public function test()
    {
        $rabbitmq = config('rabbitmq.slots_queue');

        $data = [
            'code' => 200,
            'data' => [
                'rabbit_msg' => 'From rabbit message',
                'time'       => date('Y-m-d H:i:s', time())
            ]
        ];

        $data = json_encode($data);
        //  将消息推送给生产者
        $message = new SlotsProducer($data);
        $message->setExchange('eesd');
        $message->setRoutingKey('sssss');

        $this->queueBuilder->setQueue('qqqs');
        //  传递消息
        try {
            $this->product->produce($message);
        } catch (\Exception $exception) {
            throw new \Swoole\Exception($exception->getMessage());
        }

        return [
            'msg' => 'rabbit测试方法1',
            'time' => date('Y-m-d H:i:s', time()),
        ];
    }

    public function test2()
    {
        try {
            return $this->ReturnJson->failFul(3002);
        } catch (ErrMsgException $e) {
            return $this->ReturnJson->failFul($e->getCode());
        }
    }
}

