<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Enum\EnumType;
use App\Service\BaseService;
use App\Service\BlockGamePeriodsService;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Di\Annotation\Inject;
use PhpAmqpLib\Message\AMQPMessage;
use Hyperf\Amqp\Message\Type;
use Psr\Log\LoggerInterface;

#[Consumer(exchange: 'block.settlement', routingKey: 'block-settlement', queue: 'block-settlement', name: "BlockSettlementConsumer", nums: 1)]
class BlockSettlementConsumer extends ConsumerMessage
{
    #[Inject]
    protected LoggerInterface $logger;

    protected Type|string $type = Type::DIRECT; //Type::FANOUT;

    public function consumeMessage($data, AMQPMessage $message): Result
    {
        $res = BlockGamePeriodsService::periodsSettlement3S([
            'periods_no' => $data['block_number'] ?? 0,
            'network' => EnumType::NETWORK_TRX,
        ]);
        if (!empty($res['block_number'])) {
            return Result::ACK;
        } else {
            $this->logger->error('BlockSettlementConsumer.Error.$data：' . var_export($data, true) );
            $this->logger->error('BlockSettlementConsumer.Error.$res：' . var_export($res, true) );
            BaseService::setListCache(EnumType::QUEUE_PRODUCER_BLOCK_SETTLEMENT_EXCEPTION_LIST, json_encode($data));
            return Result::DROP;
        }
    }
}
