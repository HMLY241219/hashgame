<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ws.proto

namespace WsProto\Block;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>WsProto.Block.Result</code>
 */
class Result extends \Google\Protobuf\Internal\Message
{
    /**
     *游戏ID
     *
     * Generated from protobuf field <code>string game_id = 1;</code>
     */
    protected $game_id = '';
    /**
     *开奖区域 （牛牛：1-10庄赢 11-20闲赢 21-30和）
     *
     * Generated from protobuf field <code>string announce_area = 2;</code>
     */
    protected $announce_area = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $game_id
     *          游戏ID
     *     @type string $announce_area
     *          开奖区域 （牛牛：1-10庄赢 11-20闲赢 21-30和）
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Ws::initOnce();
        parent::__construct($data);
    }

    /**
     *游戏ID
     *
     * Generated from protobuf field <code>string game_id = 1;</code>
     * @return string
     */
    public function getGameId()
    {
        return $this->game_id;
    }

    /**
     *游戏ID
     *
     * Generated from protobuf field <code>string game_id = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setGameId($var)
    {
        GPBUtil::checkString($var, True);
        $this->game_id = $var;

        return $this;
    }

    /**
     *开奖区域 （牛牛：1-10庄赢 11-20闲赢 21-30和）
     *
     * Generated from protobuf field <code>string announce_area = 2;</code>
     * @return string
     */
    public function getAnnounceArea()
    {
        return $this->announce_area;
    }

    /**
     *开奖区域 （牛牛：1-10庄赢 11-20闲赢 21-30和）
     *
     * Generated from protobuf field <code>string announce_area = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setAnnounceArea($var)
    {
        GPBUtil::checkString($var, True);
        $this->announce_area = $var;

        return $this;
    }

}

