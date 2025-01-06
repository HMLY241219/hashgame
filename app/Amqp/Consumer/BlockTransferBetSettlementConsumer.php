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

#[Consumer(exchange: 'block.transfer.bet.settlement', routingKey: 'block-transfer-bet-settlement', queue: 'block-transfer-bet-settlement', name: "BlockTransferBetSettlementConsumer", nums: 2)]
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
        $this->logger->alert('BlockTransferBetSettlementConsumer.$data：' . var_export($data, true) );
        // 转账下注，结算并转账
        $res = BlockGamePeriodsService::periodsSettlementByTransfer($data['bet_cache_key']);
        if ($res === true) {
            $this->logger->alert('BlockTransferBetSettlementConsumer.success：' . var_export($data, true) );
            return Result::ACK;
        } else {
            $this->logger->error('BlockTransferBetSettlementConsumer.Error.$data：' . var_export($data, true) );
            return Result::DROP;
        }
    }
}