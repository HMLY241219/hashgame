<?php

namespace App\Controller\websocket;

use App\Enum\EnumType;
use App\Service\BaseService;
use App\Service\BlockGameBetService;
use App\Service\BlockGamePeriodsService;
use App\Service\BlockGameService;
use App\Service\WebSocket\WSSocketService;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Engine\WebSocket\Frame;
use Hyperf\Engine\WebSocket\Response;
use Hyperf\WebSocketServer\Constant\Opcode;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WsProto\Block\HandShake;
use WsProto\Block\MessageId;

class WebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    #[Inject]
    protected LoggerInterface $logger;

    public function onMessage($server, $frame): void
    {
        $response = (new Response($server))->init($frame);
//        if($frame->opcode == Opcode::PING) {
//            // 如果使用协程 Server，在判断是 PING 帧后，需要手动处理，返回 PONG 帧。
//            // 异步风格 Server，可以直接通过 Swoole 配置处理，详情请见 https://wiki.swoole.com/#/websocket_server?id=open_websocket_ping_frame
//            $response->push(new Frame(opcode: Opcode::PONG, payloadData: 'PONG'));
//            return;
//        }
        // 检测心跳
        if (strtolower($frame->data) == 'ping') {
            $response->push(new Frame(payloadData: 'pong'));
            return;
        }

        $data = json_decode($frame->data, true);
        // 检测消息ID
        if (!is_array($data) || !isset($data['msg_id']) || empty($data['data'])) {
            $buf = new \WsProto\Block\ExceptionMsg();
            $buf->setCode(500);
            $buf->setMsg('The data format is invalid');
            $resData = WSSocketService::sendDataFormat(MessageId::MSG_LATEST_BLOCK, $buf->serializeToJsonString());
            $response->push(new Frame(payloadData: $resData));
            return;
        }

        // 消息分发
        switch ($data['msg_id']) {
            // 广播最近区块
            case MessageId::MSG_LATEST_BLOCK:
                self::broadcastLatestBlock($server, $data);
                break;

            // 广播当期开奖数据
            case MessageId::MSG_OPEN_RES:
                self::broadcastOpenResult($server, $data);
                break;

            // 广播房间指定场次下注数据（1分、3分）
            case MessageId::MSG_ROOM_USER_BET_DATA:
                self::broadcastRoomUserBetData($server, $data);
                break;

            default:
                $buf = new \WsProto\Block\ExceptionMsg();
                $buf->setCode(404);
                $buf->setMsg('Msg id not found');
                $resData = WSSocketService::sendDataFormat(MessageId::MSG_EXCEPTION_MSG, $buf->serializeToJsonString());
                $response->push(new Frame(payloadData: $resData));
        }

    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        echo("FD {$fd} closed" . PHP_EOL);
        // 清除客户端ID缓存
//        WSSocketService::delClientFdCache($fd);
    }

    public function onOpen($server, $request): void
    {
        $response = (new Response($server))->init($request);
        // 客户端ID缓存
//        WSSocketService::cacheClientFd($request->fd);
        // 发送链接成功消息
        $buffer = new HandShake();
        $buffer->setOpenData('HandShake Success');
        $data = $buffer->serializeToJsonString();
        $response->push(new Frame(payloadData: $data));
    }

    /**
     * 广播最近区块
     * @param $server
     * @param array $data
     * @return void
     */
    public static function broadcastLatestBlock($server, array $data): void
    {
        if (!isset($data['data']['network'])) {
            return;
        }
        // 组装消息
        $buf = new \WsProto\Block\LatestBlock();
        $buf->setBlockNumber((int)$data['data']['block_number']);
        $buf->setBlockHash($data['data']['block_hash']);
        $buf->setNetwork($data['data']['network']);
        $resData = WSSocketService::sendDataFormat(MessageId::MSG_LATEST_BLOCK, $buf->serializeToJsonString());

        // 广播消息
        self::broadcastMsg($server, $resData);
    }

    /**
     * 广播当期开奖数据
     * @param $server
     * @param array $data
     * @return void
     */
    public static function broadcastOpenResult($server, array $data): void
    {
        $msg = json_decode($data['data'], true);
        $openRes = [];
        foreach ($msg['results'] as $v) {
            $bufTmp = new \WsProto\Block\Result();
            $bufTmp->setGameId($v['game_id']);
            $bufTmp->setAnnounceArea($v['announce_area']);
            $openRes[] = $bufTmp;
        }
        // 组装消息
        $buf = new \WsProto\Block\OpenRes();
        $buf->setBlockNum((int)$msg['block_number']);
        $buf->setBlockHash($msg['block_hash']);
        $buf->setTransactionHash($msg['transaction_hash']);
        $buf->setTimestamp($msg['timestamp']);
        $buf->setResults($openRes);
        $resData = WSSocketService::sendDataFormat(MessageId::MSG_OPEN_RES, $buf->serializeToJsonString());

        // 广播消息
        self::broadcastMsg($server, $resData);
    }

    /**
     * 广播房间指定场次下注数据（1分、3分）
     * @param $server
     * @param array $data
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function broadcastRoomUserBetData($server, array $data): void
    {
        // 获取游戏信息
        $game = BlockGameService::getGameInfo($data['data']['gameId']);
        // 从缓存获取指定区块下注数据
        $cacheKeyPrefix = BlockGamePeriodsService::getBetDataCachePrefix([
            'network' => EnumType::NETWORK_TRX,
            'block_number' => $data['data']['blockNumber'] ?? 0,
        ]);
        $cacheKeys = BaseService::getCacheKeys($cacheKeyPrefix);
        // 获取游戏房间指定下注区域统计数据
        $roomBetLevelData = BlockGameBetService::getGameRoomBetAreaStatisticsData($game, $data['data']['betLevel'], $cacheKeys);

        // 组装消息
        $buf = new \WsProto\Block\RoomBetData();
        $buf->setGameId($game['game_id']);
        $buf->setBetLevel((int)$data['data']['betLevel']);
        $bufData = new \WsProto\Block\BetData();
        $bufData->setUserNum($roomBetLevelData['user_num']);
        $bufData->setBetAmount($roomBetLevelData['bet_amount']);
        $buf->setData($bufData);
        $resData = WSSocketService::sendDataFormat(MessageId::MSG_ROOM_BET_DATA, $buf->serializeToJsonString());

        // 广播消息
        self::broadcastMsg($server, $resData);
    }

    /**
     * 广播消息
     * @param $server
     * @param $data
     * @return void
     */
    public static function broadcastMsg($server, $data): void
    {
        // 所有链接客户端
        $clients = $server->getClientList();
        // 广播消息
        foreach ($clients as $fd) {
            \Hyperf\Coroutine\go(function () use ($server, $fd, $data) {
                $server->push($fd, $data);
            });
        }
    }
}
