<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Service\BlockApi\WebHookService;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Di\Annotation\Inject;
use PhpAmqpLib\Message\AMQPMessage;
use Hyperf\Amqp\Message\Type;
use Psr\Log\LoggerInterface;

#[Consumer(exchange: 'block.transfer.bet', routingKey: 'block-transfer-bet', queue: 'block-transfer-bet', name: "BlockTransferBetConsumer", nums: 2)]
class BlockTransferBetConsumer extends ConsumerMessage
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
        try {
            $res = WebHookService::handleData($data);
            if ($res === true) {
                return Result::ACK;
            } else {
                $this->logger->error('BlockTransferBetConsumer.Error.$data：' . var_export($data, true) );
                return Result::DROP;
            }
        } catch (\Throwable $e) {
            $this->logger->error('BlockTransferBetConsumer.Exception：' . $e->getMessage() . $e->getTraceAsString());
            if ($e->getCode() == 3017) {
                return Result::REQUEUE;
            } else {
                return Result::DROP;
            }
        }


    }
}
