<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Service\BlockApi\BlockApiService;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Di\Annotation\Inject;
use PhpAmqpLib\Message\AMQPMessage;
use Hyperf\Amqp\Message\Type;
use Psr\Log\LoggerInterface;

#[Consumer(exchange: 'block', routingKey: 'transfer', queue: 'block-transfer', name: "BlockTransferConsumer", nums: 2)]
class BlockTransferConsumer extends ConsumerMessage
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
        // 发起转账
        $res = BlockApiService::sendTransaction($data['to_address'], (float)$data['amount'], $data['currency']);
        if (!empty($res)) {
            return Result::ACK;
        } else {
            $this->logger->alert('BlockTransferConsumer.Error.$data：' . var_export($data, true) );
            return Result::DROP;
        }
    }
}
