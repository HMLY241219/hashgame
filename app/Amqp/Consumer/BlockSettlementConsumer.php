<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Enum\EnumType;
use App\Service\BlockGamePeriodsService;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Di\Annotation\Inject;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

#[Consumer(exchange: 'block', routingKey: 'settlement', queue: 'block-settlement', name: "BlockSettlementConsumer", nums: 1)]
class BlockSettlementConsumer extends ConsumerMessage
{
    #[Inject]
    protected LoggerInterface $logger;

    public function consumeMessage($data, AMQPMessage $message): Result
    {
        $res = BlockGamePeriodsService::periodsSettlement3S([
            'periods_no' => $data['block_number'] ?? 0,
            'network' => EnumType::NETWORK_TRX,
        ]);
        if (!empty($res['block_number'])) {
            return Result::ACK;
        } else {
            $this->logger->alert('BlockSettlementConsumer.Error.$data：' . var_export($data, true) );
            $this->logger->alert('BlockSettlementConsumer.Error.$res：' . var_export($res, true) );
            return Result::DROP;
        }
    }
}
