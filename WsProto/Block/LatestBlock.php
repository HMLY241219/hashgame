<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ws.proto

namespace WsProto\Block;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * 最近区块
 *
 * Generated from protobuf message <code>WsProto.Block.LatestBlock</code>
 */
class LatestBlock extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>int32 block_number = 1;</code>
     */
    protected $block_number = 0;
    /**
     * Generated from protobuf field <code>optional string block_hash = 2;</code>
     */
    protected $block_hash = null;
    /**
     * Generated from protobuf field <code>int32 network = 3;</code>
     */
    protected $network = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int $block_number
     *     @type string $block_hash
     *     @type int $network
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Ws::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>int32 block_number = 1;</code>
     * @return int
     */
    public function getBlockNumber()
    {
        return $this->block_number;
    }

    /**
     * Generated from protobuf field <code>int32 block_number = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setBlockNumber($var)
    {
        GPBUtil::checkInt32($var);
        $this->block_number = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>optional string block_hash = 2;</code>
     * @return string
     */
    public function getBlockHash()
    {
        return isset($this->block_hash) ? $this->block_hash : '';
    }

    public function hasBlockHash()
    {
        return isset($this->block_hash);
    }

    public function clearBlockHash()
    {
        unset($this->block_hash);
    }

    /**
     * Generated from protobuf field <code>optional string block_hash = 2;</code>
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
     * Generated from protobuf field <code>int32 network = 3;</code>
     * @return int
     */
    public function getNetwork()
    {
        return $this->network;
    }

    /**
     * Generated from protobuf field <code>int32 network = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setNetwork($var)
    {
        GPBUtil::checkInt32($var);
        $this->network = $var;

        return $this;
    }

}

