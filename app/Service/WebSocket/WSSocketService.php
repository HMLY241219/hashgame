<?php

namespace App\Service\WebSocket;

use App\Enum\EnumType;
use App\Service\BaseService;
use Hyperf\Context\ApplicationContext;
use Hyperf\WebSocketServer\Sender;

class WSSocketService
{
    /**
     * 处理消息
     * @param string $msg
     * @return void
     */
    public static function handlingMsg(int $fd, string $msg): void
    {
        $data = json_decode($msg, true);
        if (is_array($data) && isset($data['msg_id'])) {
            var_dump($data);
        }
    }

    /**
     * 广播消息
     * @param string $data
     * @param array $clientFds
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function broadcast(string $data, array $clientFds): void
    {
        $sender = ApplicationContext::getContainer()->get(Sender::class);
//        $clientFds = BaseService::getCache(EnumType::WS_CLIENT_FD_CACHE_KEY);
        foreach ($clientFds as $fd) {
            $sender->push((int)$fd, $data, 1, true);
        }
    }

    /**
     * 客户端ID缓存
     * @param int $fd
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function cacheClientFd (int $fd): void
    {
        BaseService::setCache(EnumType::WS_CLIENT_FD_CACHE_KEY, [
            'fd_' . $fd => $fd,
        ], 3600 * 5);
    }

    /**
     * 清除客户端ID缓存
     * @param int $fd
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function delClientFdCache (int $fd): void
    {
        BaseService::hdelCache(EnumType::WS_CLIENT_FD_CACHE_KEY, 'fd_' . $fd);
    }

    /**
     * 发送数据
     * @param int $fd
     * @param string $data
     * @param int $opcode
     * @param bool $finish
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function sendData(int $fd, string $data, int $opcode = 1, bool $finish = true): void
    {
        ApplicationContext::getContainer()->get(Sender::class)->push($fd, $data, $opcode, $finish);
    }

    /**
     * 发送数据格式
     * @param int $msgId
     * @param string $data
     * @return string
     */
    public static function sendDataFormat(int $msgId = 0, string $data = ''): string
    {
        return json_encode([
            'msg_id' => $msgId,
            'data' => json_decode($data, true),
        ]);
    }
}