<?php

namespace App\Service\BlockApi;

use App\Common\Curl;
use App\Enum\EnumType;
use App\Service\BaseService;
use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Exception\TronException;
use function Hyperf\Support\env;

/**
 * 波场节点服务
 */
class TronNodeService extends BaseService
{
    protected static string $baseUrl = "http://127.0.0.1:8090/";

    // 转账转出地址
    protected static array $fromAddress = [
        // 地址
        'address' => 'TTk1Jpdh9dPPtnJnYsJzbVYm8P8LqzFQqn',
        // 私钥
        'private_key' => 'c6a4ead6b8e300a3063f781c6c67e1f7c9f20d707025d3c0997efaf5791db539',
    ];

    /**
     * 获取最新区块
     * @return array|bool|mixed|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getLatestBlock(): mixed
    {
        // 从远程api获取
        $url = self::getApiUrl('wallet/getblock');
        $res = json_decode(Curl::post(
            $url,
            ['detail' => true],
            ["Content-Type: application/json"]
        ), true);
        $block = [];
        if (!empty($res['blockID'])) {
            $block['block_number'] = $res['block_header']['raw_data']['number'];
            $block['block_hash'] = $res['blockID'];
            $block['timestamp'] = $res['block_header']['raw_data']['timestamp'] / 1000;
            $block['transaction_hash'] = isset($res['transactions']) ? $res['transactions'][0]['txID'] : '';
        } else {
            self::logger()->alert('TronNodeService.getLatestBlock.url：' . $url);
            self::logger()->alert('TronNodeService.getLatestBlock：' . var_export($res, 1));
        }

        return $block;
    }

    /**
     * 获取区块信息
     * @param int $blockNo
     * @param bool $detail
     * @return bool|array|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getBlockInfo(int $blockNo, bool $detail = true): bool|array|\Redis
    {
        // 从远程api获取
        $url = self::getApiUrl('wallet/getblock');
        $res = json_decode(Curl::post(
            $url,
            ['id_or_num' => (string)$blockNo, 'detail' => $detail],
            ["Content-Type: application/json"]
            ), true);
        $block = [];
        if (!empty($res['blockID'])) {
            $block['block_number'] = $res['block_header']['raw_data']['number'];
            $block['block_hash'] = $res['blockID'];
            $block['parent_hash'] = $res['block_header']['raw_data']['parentHash'];
            $block['timestamp'] = $res['block_header']['raw_data']['timestamp'] / 1000;
            $block['transaction_hash'] = isset($res['transactions']) ? $res['transactions'][0]['txID'] : '';
        } else {
            self::logger()->alert('TronNodeService.getBlockInfo.url：' . $url);
            self::logger()->alert('TronNodeService.getBlockInfo：' . var_export($res, 1));
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
        $tron = new Tron(new HttpProvider(env('URL_TRON_NODE_2', self::$baseUrl)));
        $res = $tron->getTransactionInfo($tranHash);
        self::logger()->alert('TronNodeService.getTransactionInfo.$res：' . var_export($res, 1));
        $info = [];
        if (!empty($res['id'])) {
            $info['hash'] = $res['id'] ?? '';
            $info['block_number'] = $res['blockNumber'] ?? '';
            $info['block_hash'] = '';
            $info['timestamp'] = $res['blockTimeStamp'] / 1000;
            if (empty($res['log'])) { // 本链币交易
                $res2 = $tron->getTransaction($tranHash);
                $info['symbol'] = EnumType::TOKEN_SYMBOL_TRX;
                $info['from_address'] = $info['to_address'] = $info['amount'] = 0;
                if (!empty($res2['raw_data'])) {
                    $info['from_address'] = $tron->hexString2Address($res2['raw_data']['contract'][0]['parameter']['value']['owner_address']);
                    $info['to_address'] = $tron->hexString2Address($res2['raw_data']['contract'][0]['parameter']['value']['to_address']);
                    $info['amount'] = $res2['raw_data']['contract'][0]['parameter']['value']['amount'] / 1000000;
                } else {
                    self::logger()->alert('TronNodeService.getTransactionInfo.$res2：' . var_export($res2, 1));
                }
            } else { // 代币交易
                $info['symbol'] = EnumType::TOKEN_SYMBOL_USDT;
                $info['from_address'] = $tron->hexString2Address('41' . substr($res['log'][0]['topics'][1], 24));
                $info['to_address'] = $tron->hexString2Address('41' . substr($res['log'][0]['topics'][2], 24));
                $info['amount'] = hexdec($res['log'][0]['data']) / 1000000;
            }
        } else {
            self::logger()->alert('TronNodeService.getTransactionInfo.$res：' . var_export($res, 1));
        }

        return $info;
    }

    /**
     * 创建账户
     * @return array
     */
    public static function createAccount(): array
    {
        try {
//            $tron = new Tron(new HttpProvider(env('URL_TRON_NODE', self::$baseUrl)));
            $tron = new Tron();
            $generateAddress = $tron->generateAddress(); // or createAddress()

            return $generateAddress->getRawData();
        } catch (TronException $e) {
            self::logger()->alert('TronNodeService.createAccount：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 发送交易
     * @param string $toAddress
     * @param float $amount
     * @param string $currency
     * @return array
     */
    public static function sendTransaction(string $toAddress, float $amount, string $currency = EnumType::BET_CURRENCY_CHAR_TRX): array
    {
        try {
            $tron = new Tron(new HttpProvider(env('URL_TRON_NODE', self::$baseUrl)));
            $fromAddress = env('TRANSFER_ADDRESS_TRON', self::$fromAddress['address']);
            $tron->setAddress($fromAddress);
            $tron->setPrivateKey(env('TRANSFER_PRIVATE_KEY_TRON', self::$fromAddress['private_key']));
            $currency = strtolower($currency);
            if ($currency == EnumType::BET_CURRENCY_CHAR_TRX) {
                $res = $tron->sendTrx($toAddress, $amount);
            } elseif ($currency == EnumType::BET_CURRENCY_CHAR_USDT) {
                $contract = $tron->contract(EnumType::CURRENCY_CONTRACT_TRON_USDT);
                $res = $contract->transfer($toAddress, (string)$amount);
            }
            $info = [];
            if (isset($res['result']) && $res['result'] === true) {
                $info['txid'] = $res['txid'] ?? '';
                $info['from_address'] = $fromAddress;
                $info['to_address'] = $toAddress;
                $info['amount'] = $amount;
                $info['symbol'] = $currency;
                $info['timestamp'] = round($res['raw_data']['timestamp'] / 1000);
            } else {
                self::logger()->alert('TronNodeService.sendTransaction.res：' . var_export($res, true));
            }
            return $info;
        } catch (TronException $e) {
            self::logger()->alert('TronNodeService.sendTransaction.Exception：' . $e->getTraceAsString());
            self::logger()->alert('TronNodeService.sendTransaction.Exception：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 返回apiUrl
     * @param string $action
     * @param array $params
     * @return string
     */
    protected static function getApiUrl(string $action, array $params = []): string
    {
        return env('URL_TRON_NODE', self::$baseUrl) . $action . '?' . http_build_query($params);
    }
}