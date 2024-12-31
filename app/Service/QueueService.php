<?php

namespace App\Service;

use App\Enum\EnumType;
use Hyperf\AsyncQueue\Annotation\AsyncQueueMessage;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 队列服务
 */
class QueueService
{
    #[Inject]
    protected LoggerInterface $logger;

    #[AsyncQueueMessage]
    public function push(array $params)
    {
        // 需要异步执行的代码逻辑
        // 这里的逻辑会在 ConsumerProcess 进程中执行
        switch ($params['action']) {
            // 区块结算
            case EnumType::QUEUE_ACTION_BLOCK_SETTLEMENT:
                $res = BlockGamePeriodsService::periodsSettlement3S([
                    'periods_no' => $params['block_number'] ?? 0,
                    'network' => EnumType::NETWORK_TRX,
                ]);
                $this->logger->alert('QueueService.BLOCK_SETTLEMENT：' . var_export($res, 1) );
                break;
        }
    }
}