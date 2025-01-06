<?php
/**
 * 游戏
 */
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\blockgame;



use App\Amqp\Consumer\BlockTransferBetConsumer;
use App\Controller\AbstractController;
use App\Exception\ErrMsgException;
use Hyperf\Amqp\Producer;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Di\Annotation\Inject;

#[Controller(prefix:"webhook")]
class WebHookController extends AbstractController{
    #[Inject]
    public Producer $producer;

    /**
     * 钱包转账监听
     * @return null
     */
    #[RequestMapping(path: 'hook')]
    public function hook()
    {
        try {
            $params = $this->request->getParsedBody();
            // MQ生产消息
            $this->producer->produce(new BlockTransferBetConsumer($params));
            return $this->response->write('ok');
        } catch (\Exception|ErrMsgException $e) {
            $this->logger->alert($e->getMessage());
            return $this->response->write($e->getMessage());
        }
    }
}








