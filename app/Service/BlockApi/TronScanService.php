<?php

namespace App\Service\BlockApi;

use App\Common\Curl;
use App\Enum\EnumType;
use App\Service\BaseService;
use function Hyperf\Support\env;

/**
 * 波场浏览器API
 */
class TronScanService extends BaseService
{
    protected static string $baseUrl = "https://apilist.tronscanapi.com/";
    protected static string $apiKey = "7d3e2cab-9fea-421d-9a97-4efd17e135d1";
    // 缓存前缀
    protected static string $cacheTbPrefixLatestBlock = "LATEST_BLOCK_1";
    protected static string $cacheTbPrefixBlockInfo = "BLOCK_INFO_1";

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
        $url = self::getApiUrl('api/block', [
            'sort' => '-number',
            'start' => 0,
            'limit' => 1,
        ]);
//        $url = self::getApiUrl('api/system/tps');
        self::logger()->alert('TronScanService.getLatestBlock.url：' . $url);
        $res = json_decode(Curl::getSimple($url, self::getHeaders()), true);
        $block = [];
        if (!empty($res['data']) && count($res['data'])) {
            $block['block_number'] = $res['data'][0]['number'];
            $block['block_hash'] = $res['data'][0]['hash'];
            $block['timestamp'] = $res['data'][0]['timestamp'];
//            $block['block_number'] = $res['data']['blockHeight'];
//            $block['block_hash'] = '';

            // 缓存数据，30秒
            self::setCache($hTbName, $block, 30);
        } else {
            self::logger()->alert('TronScanService.getLatestBlock：' . var_export($res, 1));
        }

        return $block;
    }

    /**
     * 获取区块信息
     * @param int $blockNo
     * @return array|bool|mixed|\Redis
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
        $url = self::getApiUrl('api/block', ['number' => $blockNo]);
        self::logger()->alert('TronScanService.getBlockInfo.url：' . $url);
        $res = json_decode(Curl::getSimple($url, self::getHeaders()), true);
        $block = [];
        if (!empty($res['data']) && count($res['data'])) {
            $block['block_number'] = $res['data'][0]['number'];
            $block['block_hash'] = $res['data'][0]['hash'];
            $block['parent_hash'] = $res['data'][0]['parentHash'];
            $block['timestamp'] = $res['data'][0]['timestamp'];
            $block['transfer_hash'] = $res['data'][0]['transferHash'] ?? '';
            $block['transfer_count'] = $res['data'][0]['transferCount'];

            // 缓存数据
            self::setCache($hTbName, $block, 3600);
        } else {
            self::logger()->alert('TronScanService.getBlockInfo：' . var_export($res, 1));
        }

        return $block;
    }

    /**
     * 获取交易信息
     * @param string $tranHash
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getTransactionInfo(string $tranHash): mixed
    {
        // 从缓存取
        $hTbName = EnumType::TRANSACTION_HASH_INFO_PREFIX . $tranHash;
        $cacheData = self::getCache($hTbName);
        if ($cacheData) {
            return $cacheData;
        }

        // 从远程api获取
        $url = self::getApiUrl('api/transaction-info', [
            'hash' => $tranHash,
        ]);
        self::logger()->alert('TronScanService.getTransactionInfo.url：' . $url);
        $res = json_decode(Curl::getSimple($url, self::getHeaders()), true);
        self::logger()->alert('TronScanService.getTransactionInfo：' . var_export($res, 1));
        $info = [];
        if (!empty($res['hash'])) {
            $info['hash'] = $res['hash'];
            $info['symbol'] = EnumType::NETWORK_CHAR_TRX;
            $info['from_address'] = $res['ownerAddress'] ?? '';
            $info['to_address'] = $res['toAddress'] ?? '';
            $info['amount'] = $res['contractData']['amount'] ?? 0;
            $info['block_number'] = $res['block'] ?? 0;
            $info['block_hash'] = $res['block_hash'] ?? '';
            $info['timestamp'] = $res['timestamp'] ?? 0;
            // 是否是代币交易
            if (!empty($res['tokenTransferInfo'])) {
                $info['symbol'] = $res['tokenTransferInfo']['symbol'] ?? '';
                $info['to_address'] = $res['tokenTransferInfo']['to_address'] ?? '';
                $info['amount'] = $res['tokenTransferInfo']['amount_str'] ?? 0;
            }
            // 金额保留6位小数
            $info['amount'] = number_format($info['amount'], 6, '.', '');
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
        return self::$baseUrl . $action . '?' . http_build_query($params);
    }

    /**
     * 获取请求header
     * @param array $params
     * @return array
     */
    protected static function getHeaders(array $params = []): array
    {
        return array_merge([
            'TRON-PRO-API-KEY:' . env('BLOCK_SCAN_APIKEY_TRX', self::$apiKey),
        ], $params);
    }
}