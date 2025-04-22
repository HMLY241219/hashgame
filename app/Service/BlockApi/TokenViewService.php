<?php

namespace App\Service\BlockApi;

use App\Common\Curl;
use App\Enum\EnumType;
use App\Service\BaseService;
use function Hyperf\Support\env;

class TokenViewService extends BaseService
{
    protected static string $baseUrl = "https://services.tokenview.io/vipapi/";
    protected static string $apiKey = "HzUWtNtQh82k7vtPHc70";
    // 缓存前缀
    protected static string $cacheTbPrefixLatestBlock = "LATEST_BLOCK_";

    /**
     * 获取最新区块
     * @param int $network
     * @return array|bool|mixed|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getLatestBlock(int $network = 1)
    {
        // 从缓存取
        $hTbName = self::$cacheTbPrefixLatestBlock . $network . time();
        $cacheData = self::getCache($hTbName);
        if ($cacheData) {
            return $cacheData;
        }

        // 从远程api获取
        $url = self::getApiUrl('block/latest/' . strtolower(self::getBlockNetworkByNumber($network)));
        $res = json_decode(Curl::getSimple($url), true);
        $block = [];
        if (!empty($res['code']) && $res['code'] === 1 && !empty($res['data'][0])) {
            $block['block_number'] = $res['data'];
            // 缓存数据，30秒
            self::setCache($hTbName, $block, 30);
        }

        return $block;
    }

    public static function getBlockInfo(int $blockNo, int $network = 1)
    {
        // 从缓存取
        $hTbName = self::$cacheTbPrefixLatestBlock . $network . time();
        $cacheData = self::getCache($hTbName);
        if ($cacheData) {
            return $cacheData;
        }

        // 从远程api获取
        $url = self::getApiUrl('block/latest/' . strtolower(self::getBlockNetworkByNumber($network)) . '/' . $blockNo);
        $res = json_decode(Curl::getSimple($url), true);
        $block = [];
        if (!empty($res['code']) && $res['code'] === 1 && !empty($res['data'][0])) {
            $block = $res['data'][0];
            // 缓存数据，30秒
            self::setCache($hTbName, $block, 30);
        }

        return $block;
    }

    /**
     * 获取交易信息
     * @param string $tranHash
     * @param int $network
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getTransactionInfo(string $tranHash, int $network = 1): array
    {
        // 从缓存取
        $hTbName = EnumType::TRANSACTION_HASH_INFO_PREFIX . $tranHash;
        $cacheData = self::getCache($hTbName);
        if ($cacheData) {
            return $cacheData;
        }

        // 从远程api获取
        $url = self::getApiUrl('tx/' . strtolower(self::getBlockNetworkByNumber($network)) . '/' . $tranHash);
        self::logger()->alert('TokenViewService.getTransactionInfo.url：' . $url);
        $res = json_decode(Curl::getSimple($url), true);
        self::logger()->alert('TokenViewService.getTransactionInfo：' . var_export($res, 1));
        $info = [];
        if (!empty($res['code']) && $res['code'] === 1 && !empty($res['data'])) {
            $info['hash'] = $res['data']['txid'] ?? '';
            $info['symbol'] = $res['data']['symbol'] ?? '';
            $info['from_address'] = $res['data']['from'] ?? '';
            $info['to_address'] = $res['data']['to'] ?? '';
            $info['amount'] = $res['data']['value'] ?? 0;
            $info['block_number'] = $res['data']['block_no'] ?? 0;
            $info['block_hash'] = $res['data']['blockHash'] ?? '';
            $info['timestamp'] = $res['data']['time'] ?? 0;
            // 是否是代币交易
            if (!empty($res['data']['tokenTransfer'])) {
                $info['symbol'] = $res['data']['tokenTransfer'][0]['tokenSymbol'] ?? '';
                $info['to_address'] = $res['data']['tokenTransfer'][0]['to'] ?? '';
                $info['amount'] = $res['data']['tokenTransfer'][0]['value'] ?? 0;
                // 金额精度计算
                $info['amount'] = $info['amount'] > 0 ? $info['amount']/1000000 : 0;
            }
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
    protected static function getApiUrl(string $action): string
    {
        return self::$baseUrl . $action . "?apikey=" . env('BLOCK_SCAN_APIKEY_TOKEN_VIEW', self::$apiKey);
    }
}