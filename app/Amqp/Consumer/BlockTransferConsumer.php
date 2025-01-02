<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Service\BlockApi\TronNodeService;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Di\Annotation\Inject;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

#[Consumer(exchange: 'block', routingKey: 'transfer', queue: 'block-transfer', name: "BlockTransferConsumer", nums: 2)]
class BlockTransferConsumer extends ConsumerMessage
{
    #[Inject]
    protected LoggerInterface $logger;

    protected ?array $qos = [
        'prefetch_size' => 0,
        'prefetch_count' => 4,
        'global' => false,
    ];

    public function consumeMessage($data, AMQPMessage $message): Result
    {
        // 发起转账
        $res = TronNodeService::sendTransaction($data['to_address'], $data['amount'], $data['currency']);
        if (!empty($res)) {
            return Result::ACK;
        } else {
            $this->logger->alert('BlockTransferConsumer.Error.$data：' . var_export($data, true) );
            return Result::DROP;
        }
    }
}
