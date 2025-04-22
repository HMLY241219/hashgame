<?php

namespace App\Service\BlockApi;

use App\Common\Curl;
use App\Enum\EnumType;
use App\Service\BaseService;
use function Hyperf\Support\env;

/**
 * 币安智能链浏览器API
 */
class BscScanService extends BaseService
{
    protected static string $baseUrl = "https://api.bscscan.com/";
    protected static string $apiKey = "2Z8U3P62JMJJT7T677FPYYZXDRC3P5FQMG";
    // 缓存前缀
    protected static string $cacheTbPrefixLatestBlock = "LATEST_BLOCK_3";
    protected static string $cacheTbPrefixBlockInfo = "BLOCK_INFO_3";

    /**
     * 获取最新区块
     * @return array|bool|mixed|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getLatestBlock(): mixed
    {
        // 从缓存取
        $hTbName = self::$cacheTbPrefixLatestBlock . time();
        $cacheData = self::getCache($hTbName);
        if ($cacheData) {
            return $cacheData;
        }

        // 从远程api获取
        $url = self::getApiUrl('api', [
            'module' => 'proxy',
            'action' => 'eth_blockNumber',
        ]);
        self::logger()->alert('BscScanService.getLatestBlock.url：' . $url);
        $res = json_decode(Curl::getSimple($url), true);
        self::logger()->alert('BscScanService.getLatestBlock：' . var_export($res, 1));
        $block = [];
        if (!empty($res['id']) && !empty($res['result'])) {
            $block['block_number'] = base_convert($res['result'], 16, 10);
            $block['block_hash'] = '';

            // 缓存数据，30秒
            self::setCache($hTbName, $block, 30);
        }

        return $block;
    }

    /**
     * 获取区块信息
     * @param int $blockNo
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getBlockInfo(int $blockNo): mixed
    {
        // 从缓存取
        $hTbName = self::$cacheTbPrefixBlockInfo . $blockNo;
        $cacheData = self::getCache($hTbName);
        if ($cacheData) {
            return $cacheData;
        }

        // 从远程api获取
        $url = self::getApiUrl('api', [
            'module' => 'proxy',
            'action' => 'eth_getBlockByNumber',
            'tag' => base_convert($blockNo, 10, 16), // 10进制转16进制
            'boolean' => false,
        ]);
        self::logger()->alert('BscScanService.getBlockInfo.url：' . $url);
        $res = json_decode(Curl::getSimple($url), true);
        self::logger()->alert('BscScanService.getBlockInfo：' . var_export($res, 1));
        $block = [];
        if (!empty($res['id']) && !empty($res['result'])) {
            $block['block_number'] = base_convert($res['result']['number'], 16, 10);
            $block['block_hash'] = $res['result']['hash'];
            $block['block_size'] = base_convert($res['result']['size'], 16, 10);
            $block['parent_hash'] = $res['result']['parentHash'];
            $block['timestamp'] = base_convert($res['result']['timestamp'], 16, 10);
            $block['confirmed'] = true;
            $block['transfer_count'] = 0;

            // 缓存数据
            self::setCache($hTbName, $block, 3600);
        }

        return $block;
    }

    /**
     * 获取交易信息
     * @param string $tranHash
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getTransactionInfo(string $tranHash): array
    {
        // 从缓存取
        $hTbName = EnumType::TRANSACTION_HASH_INFO_PREFIX . $tranHash;
        $cacheData = self::getCache($hTbName);
        if ($cacheData) {
            return $cacheData;
        }

        // 从远程api获取
        $url = self::getApiUrl('api', [
            'module' => 'proxy',
            'action' => 'eth_getTransactionByHash',
            'txhash' => $tranHash,
        ]);
        self::logger()->alert('BscScanService.getTransactionInfo.url：' . $url);
        $res = json_decode(Curl::getSimple($url), true);
        self::logger()->alert('BscScanService.getTransactionInfo：' . var_export($res, 1));
        $info = [];
        if (!empty($res['id']) && !empty($res['result'])) {
            $info['hash'] = $res['result']['hash'] ?? '';
            $info['symbol'] = $res['result']['symbol'] ?? '';
            $info['from_address'] = $res['result']['from'] ?? '';
            $info['to_address'] = $res['result']['to'] ?? '';
            $info['amount'] = !empty($res['result']['value']) ? base_convert($res['result']['value'], 16, 10) : 0;
            $info['block_number'] = !empty($res['result']['blockNumber']) ? base_convert($res['result']['blockNumber'], 16, 10) : 0;
            $info['block_hash'] = $res['result']['blockHash'] ?? '';
            $info['timestamp'] = $res['result']['timestamp'] ?? 0;
            // 是否是代币交易
//            if (!empty($res['tokenTransferInfo'])) {
//                $info['symbol'] = $res['tokenTransferInfo']['symbol'] ?? '';
//                $info['to_address'] = $res['tokenTransferInfo']['to_address'] ?? '';
//                $info['amount'] = $res['tokenTransferInfo']['amount_str'] ?? 0;
//            }
            // 缓存数据
            self::setCache($hTbName, $info, 3600);
        }

        return $info;
    }

    /**
     * 返回apiUrl
     * @param string $action
     * @return string
     */
    protected static function getApiUrl(string $action, array $params = []): string
    {
        $params['apikey'] = env('BLOCK_SCAN_APIKEY_BSC', self::$apiKey);
        return self::$baseUrl . $action . '?' . http_build_query($params);
    }
}