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

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

abstract class AbstractController
{
    #[Inject]
    protected ContainerInterface $container;

    #[Inject]
    protected RequestInterface $request; //获取请求消息

    #[Inject]
    protected ResponseInterface $response; //获取响应消息
    #[Inject]
    protected LoggerInterface $logger; //存储日志
    #[Inject]
    protected ReturnJsonController $ReturnJson;  //返回数据

    #[Inject]
    protected ValidatorFactoryInterface $validatorFactory;//数据验证
}
