<?php
/**
 * 游戏
 */
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\blockgame;



use App\Controller\AbstractController;
use App\Enum\EnumType;
use App\Exception\ErrMsgException;
use App\Service\BaseService;
use App\Service\BlockApi\BlockApiService;
use App\Service\BlockApi\WebHookService;
use App\Service\BlockGameBetService;
use App\Service\BlockGameService;
use App\Service\WebSocket\WSSocketService;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use WsProto\Block\MessageId;

#[Controller(prefix:"webhook")]
class WebHookController extends AbstractController{

    /**
     * 钱包转账监听
     * @return null
     */
    #[RequestMapping(path: 'hook')]
    public function hook()
    {
        try {
            $params = $this->request->getParsedBody();
            WebHookService::handleData($params);
            return $this->response->write('ok');
        } catch (\Exception|ErrMsgException $e) {
            $this->logger->alert($e->getMessage());
            return $this->response->write($e->getMessage());
        }
    }
}








