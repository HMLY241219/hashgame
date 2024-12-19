<?php

namespace App\Service\BlockApi;

use App\Enum\EnumType;

/**
 * 波场浏览器API
 */
class BlockApiService
{
    /**
     * 获取最新区块
     * @param int $network 网络
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getLatestBlock(int $network = EnumType::NETWORK_TRX): mixed
    {
        return match ($network) {
            EnumType::NETWORK_TRX => TronNodeService::getLatestBlock(),
            EnumType::NETWORK_ETH => [],
            EnumType::NETWORK_BSC => BscScanService::getLatestBlock(),
        };
    }

    /**
     * 获取区块信息
     * @param int $blockNo 区块编号
     * @param int $network 网络
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getBlockInfo(int $blockNo, int $network = EnumType::NETWORK_TRX): mixed
    {
        return match ($network) {
            EnumType::NETWORK_TRX => TronNodeService::getBlockInfo($blockNo),
            EnumType::NETWORK_ETH => [],
            EnumType::NETWORK_BSC => BscScanService::getBlockInfo($blockNo),
        };
    }

    /**
     * 获取交易信息
     * @param string $tranHash
     * @param int $network
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getTransactionInfo(string $tranHash, int $network = EnumType::NETWORK_TRX): mixed
    {
        return match ($network) {
//            EnumType::NETWORK_TRX => TronScanService::getTransactionInfo($tranHash),
            EnumType::NETWORK_TRX => TronNodeService::getTransactionInfo($tranHash, $network),
            EnumType::NETWORK_ETH => [],
            EnumType::NETWORK_BSC => BscScanService::getTransactionInfo($tranHash),
        };
    }
}