<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Service\BlockGamePeriodsService;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Di\Annotation\Inject;
use PhpAmqpLib\Message\AMQPMessage;
use Hyperf\Amqp\Message\Type;
use Psr\Log\LoggerInterface;

#[Consumer(exchange: 'block.transfer.bet.settlement', routingKey: 'block-transfer-bet-settlement', queue: 'block-transfer-bet-settlement', name: "BlockTransferBetSettlementConsumer", nums: 1)]
class BlockTransferBetSettlementConsumer extends ConsumerMessage
{
    #[Inject]
    protected LoggerInterface $logger;

    protected Type|string $type = Type::DIRECT; //Type::FANOUT;

    protected ?array $qos = [
        'prefetch_size' => 0,
        'prefetch_count' => 4,
        'global' => false,
    ];

    public function consumeMessage($data, AMQPMessage $message): Result
    {
        // 转账下注，结算并转账
        $res = BlockGamePeriodsService::periodsSettlementByTransfer($data['bet_cache_key']);
        if ($res === true) {
            return Result::ACK;
        } elseif ($res === 0) {
            return Result::DROP;
        } else {
            $this->logger->error('BlockTransferBetSettlementConsumer.Error.$data：' . var_export($data, true) );
            return Result::REQUEUE;
        }
    }
}
