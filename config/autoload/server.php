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
use Hyperf\Server\Event;
use Hyperf\Server\Server;
use Swoole\Constant;
use function Hyperf\Support\env;

$serverHttp = [
    'name' => env('SERVER_NAME_HTTP', 'http'),
    'type' => Server::SERVER_HTTP,
    'host' => '0.0.0.0',
    'port' => (int)env('SERVER_PORT_HTTP', 9501),
    'sock_type' => SWOOLE_SOCK_TCP,
    'callbacks' => [
        Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
    ],
    'options' => [
        // Whether to enable request lifecycle event
        'enable_request_lifecycle' => false,
    ],
];
$serverWs = [
    'name' => env('SERVER_NAME_WS', 'ws'),
    'type' => Server::SERVER_WEBSOCKET,
    'host' => '0.0.0.0',
    'port' => (int)env('SERVER_PORT_HTTP', 9502),
    'sock_type' => SWOOLE_SOCK_TCP,
    'callbacks' => [
        Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
    ],
    'settings' => [
        Constant::OPTION_OPEN_WEBSOCKET_PING_FRAME => true,
        Constant::OPTION_HEARTBEAT_IDLE_TIME => 60,
        Constant::OPTION_HEARTBEAT_CHECK_INTERVAL => 30,
    ],
];
// 判断服务类型
$serverType = env('SERVER_TYPE', 'all');
$servers = [];
if ($serverType == 'http') {
    $servers[] = $serverHttp;
} else if ($serverType == 'ws') {
    $servers[] = $serverWs;
} else if ($serverType == 'all') {
    $servers[] = $serverHttp;
    $servers[] = $serverWs;
}
return [
    'mode' => SWOOLE_PROCESS,
    'servers' => $servers,
    'settings' => [
        Constant::OPTION_ENABLE_COROUTINE => true,
        Constant::OPTION_WORKER_NUM => swoole_cpu_num(),
        Constant::OPTION_PID_FILE => BASE_PATH . '/runtime/hyperf.pid',
        Constant::OPTION_OPEN_TCP_NODELAY => true,
        Constant::OPTION_MAX_COROUTINE => 100000,
        Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
        Constant::OPTION_MAX_REQUEST => 100000,
        Constant::OPTION_SOCKET_BUFFER_SIZE => 2 * 1024 * 1024,
        Constant::OPTION_BUFFER_OUTPUT_SIZE => 2 * 1024 * 1024,
    ],
    'callbacks' => [
        Event::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
    ],
];
