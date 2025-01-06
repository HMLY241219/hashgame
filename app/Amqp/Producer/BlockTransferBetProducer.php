<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Amqp\Message\Type;

/**
 * 转账下注
 */
#[Producer(exchange: 'block.transfer.bet', routingKey: 'block-transfer-bet')]
class BlockTransferBetProducer extends ProducerMessage
{
    protected Type|string $type = Type::DIRECT; //Type::FANOUT;

    public function __construct($data)
    {
        $this->payload = $data;
    }
}
