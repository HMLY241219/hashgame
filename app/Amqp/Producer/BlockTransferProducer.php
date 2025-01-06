<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

/**
 * 转账
 */
#[Producer(exchange: 'block.transfer', routingKey: 'block-transfer')]
class BlockTransferProducer extends ProducerMessage
{
    public function __construct($data)
    {
        $this->payload = $data;
    }
}
