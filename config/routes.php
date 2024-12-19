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
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');
//eq9slots檢查玩家帳號
Router::get('/cq9slots/player/check/{account}', 'App\Controller\slots\Cq9slotsController::check');
//eq9slots取得玩家錢包餘額
Router::get('/cq9slots/transaction/balance/{account}', 'App\Controller\slots\Cq9slotsController::balance');

Router::get('/favicon.ico', function () {
    return '';
});

// websocket
Router::addServer('ws', function () {
    Router::get('/ws', 'App\Controller\websocket\WebSocketController');
});
