<?php

namespace App\Service\BlockApi;

use App\Enum\EnumType;
use App\Exception\ErrMsgException;
use App\Service\BaseService;
use App\Service\BlockGameBetService;
use App\Service\BlockGameService;
use App\Service\UserService;
use App\Service\WebSocket\SysConfService;

/**
 * 钱包地址交易监控
 */
class WebHookService extends BaseService
{
    /**
     * 获取最新区块
     * @param array $params
     * @return bool
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function handleData(array $params)
    {
        self::logger()->alert('WebHookService.handleData.$params：' . var_export($params, 1));
        // 检测参数
        $check = self::checkParams($params);
        // 网络
        $network = self::getBlockNetworkByChar($params['coin']);

        // 检测是否是激活钱包
        $conf = SysConfService::getHashGameConf();
        self::logger()->alert('WebHookService.handleData.$conf：' . var_export($conf, 1));
        if ($check['amount'] == $conf['active_transfer_amount'] && $check['symbol'] == strtolower($conf['active_transfer_currency'])
            && $params['address'] == $conf['active_transfer_address']) {
            // 获取交易信息
            $transactionInfo = BlockApiService::getTransactionInfo($params['txid'], $network);
            if (!$transactionInfo) {
                throw new ErrMsgException('Transaction not found', 3017);
            }
            // 激活钱包
            UserService::addressActive($transactionInfo['from_address']);

            return true;
        }

        // 检测当前交易hash是否已经下过注
        $hTbName = EnumType::BET_BY_TRANS_HASH_PREFIX . $params['txid'];
        $cacheData = self::getCache($hTbName);
        if ($cacheData) {
            throw new ErrMsgException('Hash bet repeated');
        }

        // 根据充值地址获取游戏信息
        $game = BlockGameService::getGameInfoByAddress($params['address']);
        if (!$game) {
            throw new ErrMsgException('Game not found');
        }
        // 获取下注房间等级
        $betRoomLevel = EnumType::ROOM_CJ;
        foreach ($game['transfer_bet_rule'] as $key => $rule) {
            if (empty($rule['bet_address'])) continue;
            if ($rule['bet_address'] == $params['address']) {
                $betRoomLevel = BlockGameService::getBetRoomLevelByKey($key);
                break;
            }
        }

        // 获取交易信息

        $transactionInfo = BlockApiService::getTransactionInfo($params['txid'], $network);
        if (!$transactionInfo) {
            throw new ErrMsgException('Transaction not found', 3017);
        }

        // 获取转账钱包对应绑定的用户
        $userAddress = self::getPoolTb('user_wallet_address')->where('address', $transactionInfo['from_address'])
            ->where('is_active', 1)->first();
        if (!$userAddress) {
            throw new ErrMsgException('Address not activate');
        }

        // 获取钱包地址转账下注区域
        $betArea = BlockGameBetService::getAddressBetArea((int)$check['amount'], $game['game_type_second']);

        // 缓存下注数据
        BlockGameBetService::cacheGameBet([
            'uid' => $userAddress['uid'],
            'game_id' => $game['game_id'],
            'bet_way' => EnumType::BET_WAY_TRANSFER,
            'bet_block' => $transactionInfo,
            'bet_data' => [
                [
                    'bet_level' => $betRoomLevel,
                    'bet_area' => $betArea,
                    'bet_currency' => self::getBetCurrencyByChar($check['symbol']),
                    'bet_amount' => $check['amount'] * self::$amountDecimal,
                    'bet_address' => $transactionInfo['from_address'],
                ]
            ],
        ]);

        // 缓存交易hash下注标识
        self::setCache($hTbName, ['is_bet' => 1]);

        return true;
    }

    /**
     * 检测参数
     * @param array $params
     * @return array
     */
    protected static function checkParams(array $params): array
    {
        // 检测token的数量变化，如果存在则为代币交易，只保留大于0的交易
        if (isset($params['tokenValue']) && $params['tokenValue'] <= 0) {
            throw new ErrMsgException('Amount invalid');
        }
        // 检测本链币的数量变化，只保留大于0的交易
        if (!isset($params['tokenValue']) && isset($params['value']) && $params['value'] <= 0) {
            throw new ErrMsgException('Amount invalid');
        }
        // 检测交易网络
        if (empty($params['coin'])) {
            throw new ErrMsgException('Network empty');
        }
        // 检测交易网络是否是允许的
        if (!in_array($params['coin'], [EnumType::NETWORK_CHAR_TRX, EnumType::NETWORK_CHAR_BSC])) {
            throw new ErrMsgException('Network invalid');
        }
        // 检测代币类型
        if (isset($params['tokenSymbol']) && $params['tokenSymbol'] != EnumType::TOKEN_SYMBOL_USDT) {
            throw new ErrMsgException('Currency not supported');
        }
        // 检测是否是币安本链币
        if ($params['coin'] == EnumType::NETWORK_CHAR_BSC && !isset($params['tokenValue'])) {
            throw new ErrMsgException('Currency not supported');
        }
        // 检测交易hash
        if (empty($params['txid'])) {
            throw new ErrMsgException('Hash empty');
        }
        // 检测交易接收地址
        if (empty($params['address'])) {
            throw new ErrMsgException('Address empty');
        }

        return [
            'symbol' => strtolower(isset($params['tokenSymbol']) ? EnumType::TOKEN_SYMBOL_USDT: EnumType::TOKEN_SYMBOL_TRX), // 货币符号单位
            'amount' => $params['tokenValue'] ?? $params['value'], // 充值金额
        ];
    }
}