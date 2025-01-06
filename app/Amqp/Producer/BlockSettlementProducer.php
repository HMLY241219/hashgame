<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

/**
 * 区块结算
 */
#[Producer(exchange: 'block.settlement', routingKey: 'block-settlement')]
class BlockSettlementProducer extends ProducerMessage
{
    public function __construct($data)
    {
        $this->payload = $data;
    }
}
