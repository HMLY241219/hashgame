<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ws.proto

namespace WsProto\Block;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * 开奖结果
 *
 * Generated from protobuf message <code>WsProto.Block.OpenRes</code>
 */
class OpenRes extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>int32 block_num = 1;</code>
     */
    protected $block_num = 0;
    /**
     * Generated from protobuf field <code>string block_hash = 2;</code>
     */
    protected $block_hash = '';
    /**
     * Generated from protobuf field <code>int32 timestamp = 3;</code>
     */
    protected $timestamp = 0;
    /**
     * Generated from protobuf field <code>string transaction_hash = 4;</code>
     */
    protected $transaction_hash = '';
    /**
     * Generated from protobuf field <code>repeated .WsProto.Block.Result results = 5;</code>
     */
    private $results;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int $block_num
     *     @type string $block_hash
     *     @type int $timestamp
     *     @type string $transaction_hash
     *     @type array<\WsProto\Block\Result>|\Google\Protobuf\Internal\RepeatedField $results
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Ws::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>int32 block_num = 1;</code>
     * @return int
     */
    public function getBlockNum()
    {
        return $this->block_num;
    }

    /**
     * Generated from protobuf field <code>int32 block_num = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setBlockNum($var)
    {
        GPBUtil::checkInt32($var);
        $this->block_num = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string block_hash = 2;</code>
     * @return string
     */
    public function getBlockHash()
    {
        return $this->block_hash;
    }

    /**
     * Generated from protobuf field <code>string block_hash = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setBlockHash($var)
    {
        GPBUtil::checkString($var, True);
        $this->block_hash = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 timestamp = 3;</code>
     * @return int
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Generated from protobuf field <code>int32 timestamp = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setTimestamp($var)
    {
        GPBUtil::checkInt32($var);
        $this->timestamp = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string transaction_hash = 4;</code>
     * @return string
     */
    public function getTransactionHash()
    {
        return $this->transaction_hash;
    }

    /**
     * Generated from protobuf field <code>string transaction_hash = 4;</code>
     * @param string $var
     * @return $this
     */
    public function setTransactionHash($var)
    {
        GPBUtil::checkString($var, True);
        $this->transaction_hash = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>repeated .WsProto.Block.Result results = 5;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Generated from protobuf field <code>repeated .WsProto.Block.Result results = 5;</code>
     * @param array<\WsProto\Block\Result>|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setResults($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \WsProto\Block\Result::class);
        $this->results = $arr;

        return $this;
    }

}

