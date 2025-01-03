<?php

namespace App\Service;

use App\Amqp\Producer\BlockSettlementProducer;
use App\Amqp\Producer\BlockTransferProducer;
use App\Common\Common;
use App\Enum\EnumType;
use App\Service\BlockApi\BlockApiService;
use Hyperf\Amqp\Producer;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use function Hyperf\Coroutine\go;

/**
 * 区块游戏期数服务
 */
class BlockGamePeriodsService extends BaseService
{
    // 表名
    protected static string $tbName = 'block_game_periods';

    /**
     * 获取游戏期数列表
     * @param array $params
     * @param bool $all
     * @param bool $cached 是否从缓存获取
     * @return array
     */
    public static function getGamePeriodsList(array $params = [], bool $all = false, bool $cached = true): array
    {
        // 从缓存获取
        $params['all'] = $all;
        $hTbName = EnumType::PERIODS_LIST_PREFIX.self::createHashKey($params);
        $cacheList = self::getCache($hTbName);
        if ($cacheList && $cached) {
            $list = $cacheList['data'] ? json_decode($cacheList['data'], true) : [];
        } else {
            // 字段
            $field = empty($params['field']) ? ['*'] : $params['field'];
            // 排序
            $order = empty($params['order']) ? 'create_time desc' : $params['order'];

            $model = self::getPartTb(self::$tbName, self::getTbSuffix($params))
                ->when(!empty($params['game_id']), function ($query) use ($params) {
                    $query->where('game_id', $params['game_id']);
                })
                ->when(isset($params['is_open']), function ($query) use ($params) {
                    $query->where('is_open', $params['is_open']);
                })
                ->when(!empty($params['open_block']), function ($query) use ($params) {
                    $query->where('open_block', $params['open_block']);
                })
                ->when(!empty($params['block_hash']), function ($query) use ($params) {
                    $query->where('block_hash', $params['block_hash']);
                })
                ->when(!empty($params['transaction_hash']), function ($query) use ($params) {
                    $query->where('transaction_hash', $params['transaction_hash']);
                })
                ->when(!empty($params['date']), function ($query) use ($params) {
                    $query->where('date', $params['date']);
                })
                ->when(isset($params['time']) && is_array($params['time']) && count($params['time']) == 2, function ($query) use ($params) {
                    $query->whereBetween('create_time', $params['time']);
                })
                ->when(!empty($params['game_type_top']), function ($query) use ($params) {
                    $query->where('game_type_top', $params['game_type_top']);
                })
                ->when(!empty($params['play_method']), function ($query) use ($params) {
                    // 根据玩法返回数据
                    if ($params['play_method'] == EnumType::PLAY_METHOD_HASH_1M) {
                        $query->where('is_one_min', 1);
                    } elseif ($params['play_method'] == EnumType::PLAY_METHOD_HASH_3M) {
                        $query->where('is_three_min', 1);
                    }
                })
                ->orderByRaw($order);

            $list = $all ? $model->select($field)->get()->toArray() : $model->paginate(empty($params['page_size']) ? self::$pageSize : (int)$params['page_size'], $field)->toArray();
            // 数据缓存
            self::setCache($hTbName, ['data' => json_encode($list)], self::$cacheExpire);
        }

        if ($all) {
            foreach ($list as &$value) {
                self::handleInfo($value);
            }
        } else {
            foreach ($list['data'] as &$value) {
                self::handleInfo($value);
            }
        }

        return $list;
    }

    /**
     * 获取游戏开奖期数信息
     * @param int $gameId
     * @param int $periodsNo
     * @return array|\Hyperf\Database\Model\Model|\Hyperf\Database\Query\Builder|mixed|object|null
     */
    public static function getGamePeriodsInfo(string $gameId, int $periodsNo): mixed
    {
        // 从缓存获取
        $hTbName = EnumType::PERIODS_INFO_PREFIX. $periodsNo . '_' . $gameId;
        $info = self::getCache($hTbName);
        if (!$info) {
            // 从数据库获取
            $info = self::getPartTb(self::$tbName)
                ->where('game_id', $gameId)
                ->where('curr_periods', $periodsNo)
                ->first();
            if ($info) {
                // 数据缓存
                self::setCache($hTbName, $info, self::$cacheExpire);
            }
        }

        if ($info) self::handleInfo($info);

        return $info;
    }

    /**
     * 详情统一处理
     * @param array $info
     * @return void
     */
    protected static function handleInfo(array &$info): void
    {
        if (!empty($info['open_data'])) $info['open_data'] = json_decode($info['open_data'], true);
    }

    /**
     * 秒周期结算
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function periodsSettlement3S(array $params): array
    {
        // 检测当前期数是否结算
        $info = self::getPartTb(self::$tbName)->where('curr_periods', $params['periods_no'])->first(['periods_id', 'open_block']);
        if (!$info) {
            $blockNumber = self::periodsSettlement($params['periods_no'], $params['network'], false);
            if ($blockNumber) {
                // 清除指定的丢失区块
                self::clearMissBlockCache((string)$blockNumber, (int)$params['network']);
            }
        } else {
            $blockNumber = $info['open_block'];
        }
        return ['block_number' => $blockNumber];
    }

    /**
     * 期数结算
     * @param int $periodsNo 结算指定期数，期数编号
     * @param int $network
     * @param bool $isAuto 是否是自动结算
     * @return int
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function periodsSettlement(int $periodsNo, int $network = EnumType::NETWORK_TRX, bool $isAuto = true): int
    {
        // 当前开奖区块
        $currOpenBlockNumber = $periodsNo; // 结算指定区块

        // 添加缓存锁，避免多个进程同时结算相同区块
        $lockKey = EnumType::LOCK_LAST_OPEN_BLOCK .'_'. $network . '_'. $currOpenBlockNumber;
        if (!self::setCacheLock($lockKey, 20)) {
//            self::logger()->alert('BlockGamePeriodsService.periodsSettlement：The current block is already locked, block number ' . $currOpenBlockNumber);
            return 0;
        }

        // 获取开奖区块信息
        $openBlockInfo = BlockApiService::getBlockInfo($currOpenBlockNumber, $network);
        if (!$openBlockInfo) {
            // 解锁，未查询到区块信息说明区块信息还未同步到当前节点，解锁让后续队列来重试
            self::delCache($lockKey);

            self::logger()->alert('BlockGamePeriodsService.periodsSettlement：No block info, block number ' . $currOpenBlockNumber);
            return 0;
        }

        // 缓存没有结算到丢失的区块
        if ($isAuto) {
            self::cacheMissBlock($openBlockInfo);
        }

        // 游戏期数数据
        list($gamePeriodsList, $gameRuleList) = self::packagePeriodsData($openBlockInfo, $network);
        // 获取当前区块待结算的下注
        $betUsers = $betDataList = $settlementCacheKeys = $userCoinChange = $needTransferBackData = [];
        $cacheKeys = self::getCacheKeys(self::getBetDataCachePrefix([
            'network' => $network,
            'block_number' => $currOpenBlockNumber,
        ]));
        foreach ($cacheKeys as $key) {
            // 获取下注数据
            $betData = self::getCache($key);
            if ($betData['open_block'] != $currOpenBlockNumber) {
                continue;
            }

            // 当前游戏当期开奖数据
            $openPeriods = $gamePeriodsList[$betData['game_id']] ?? [];
            if (!$openPeriods) {
                continue;
            }

            // 当前游戏下注开奖规则
            $gameRule = $gameRuleList[$betData['game_id']] ?? [];
            if (!$gameRule) {
                continue;
            }
            $roomLevelChar = BlockGameService::getBetRoomLevelByNumber((int)$betData['bet_level']);
            $openRule = $betData['bet_way'] == EnumType::BET_WAY_TRANSFER ?
                $gameRule['transfer_bet_rule'][$roomLevelChar] :
                $gameRule['page_bet_rule'][$roomLevelChar];

            // 更新下注数据
            $betData['periods_id'] = $openPeriods['periods_id']; // 游戏期数ID
            $betData['curr_periods'] = $openPeriods['open_block']; // 当前期数
            $betData['is_open'] = EnumType::BET_IS_OPEN_YES; // 已开奖
            $betData['open_data'] = $openPeriods['open_data']; // 开奖数据
            $betData['open_result'] = $openPeriods['open_result']; // 开奖结果
            $betData['open_time'] = $openPeriods['open_time']; // 开奖时间
            $betData['block_hash'] = $openPeriods['block_hash']; // 开奖区块hash
            $betData['transaction_hash'] = $openPeriods['transaction_hash']; // 开奖交易hash
            $betData['is_valid'] = EnumType::BET_IS_VALID_YES; // 是否有效
            // 根据开奖结果获取下注结果
            $betRes = self::getBetResult($betData, $openPeriods['open_result'], json_decode($openPeriods['open_data'], true), $openRule, (int)$betData['game_type_second']);
            $betData['is_win'] = $betRes['is_win']; // 输赢状态
            $betData['win_lose_amount'] = $betRes['win_lose_amount']; // 输赢金额-cash
            $betData['win_lose_amount_bonus'] = $betRes['win_lose_amount_bonus']; // 输赢金额-bonus
            $betData['settlement_amount'] = $betRes['settlement_amount']; // 结算金额-cash
            $betData['settlement_amount_bonus'] = $betRes['settlement_amount_bonus']; // 结算金额-bonus
            $betData['sxfee_ratio'] = $betRes['sxfee_ratio'] ?? 0; // 手续费率
            $betData['sxfee_amount'] = $betRes['sxfee_amount'] ?? 0; // 手续费-cash
            $betData['sxfee_amount_bonus'] = $betRes['sxfee_amount_bonus'] ?? 0; // 手续费-bonus
            $betData['refund_amount'] = $betRes['refund_amount'] ?? 0; // 退还金额-cash
            $betData['refund_amount_bonus'] = $betRes['refund_amount_bonus'] ?? 0; // 退还金额-bonus
            $betData['status'] = $betRes['status']; // 下注状态：0（待结算）、1（已完成）、2（已退款）

            // 当前开奖数据统计
            if (!in_array($betData['uid'], $betUsers)) {
                $gamePeriodsList[$betData['game_id']]['bet_user_num'] += 1; // 当期下注人数
                $betUsers[] = $betData['uid'];
            }
            $gamePeriodsList[$betData['game_id']]['bet_total_amount'] += ($betData['bet_amount'] + $betData['bet_amount_bonus']);

            // 用户余额变更（下注方式为余额下注）
            if ($betData['bet_way'] == EnumType::BET_WAY_BALANCE) {
                if (isset($userCoinChange[$betData['uid']])) {
                    $userCoinChange[$betData['uid']]['coin_change'] += $betData['settlement_amount'];
                    $userCoinChange[$betData['uid']]['bonus_change'] += $betData['settlement_amount_bonus'];
                } else {
                    $userCoinChange[$betData['uid']] = [
                        'uid' => $betData['uid'],
                        'coin_change' => $betData['settlement_amount'],
                        'bonus_change' => $betData['settlement_amount_bonus'],
                    ];
                }
            } elseif ($betData['bet_way'] == EnumType::BET_WAY_TRANSFER) { // 下注方式为转账下注
                // 是否需要结算回用户钱包地址
                if ($betData['settlement_amount'] > 0) {
                    $needTransferBackData[] = [
                        'amount' => $betData['settlement_amount'] / self::$amountDecimal,
                        'to_address' => $betData['bet_address'],
                        'currency' => self::getBetCurrencyByNumber($betData['bet_currency']),
                    ];
                }
            }

            $betDataList[] = $betData;
            $settlementCacheKeys[] = $key; // 已结算缓存key
        }

        // 没有结算下注数据
//        if (!$betDataList) {
//            self::logger()->alert('BlockGamePeriodsService.periodsSettlement：No bet data, block number ' . $currOpenBlockNumber);
//        }

        // 组装用户余额变更日志记录
        $userCoinLogs = $userBonusLogs = [];
        if ($userCoinChange) {
            list($userCoinLogs, $userBonusLogs) = self::packUserBalanceChangeLog($betDataList, array_keys($userCoinChange));
        }

        try {
            Db::transaction(function () use ($betDataList, $gamePeriodsList, $settlementCacheKeys, $userCoinChange, $userCoinLogs, $userBonusLogs) {
                // 保存当前开奖期数数据
                self::getPartTb(self::$tbName)->insert(array_values($gamePeriodsList));
                // 保存下注数据
                if ($betDataList) {
                    BlockGameBetService::saveBetData($betDataList);
                    unset($betDataList);
                }
                // 更新用户余额
                if ($userCoinChange) {
                    UserService::batchUpdateUserCoin($userCoinChange);
                    // 新增余额变更日志
                    if ($userCoinLogs) {
                        self::getPartTb('coin')->insert($userCoinLogs);
                    }
                    if ($userBonusLogs) {
                        self::getPartTb('bonus')->insert($userBonusLogs);
                    }
                    unset($userCoinChange, $userCoinLogs, $userBonusLogs);
                }
            });
            // 清除下注缓存数据
            array_map(function ($key) { self::delCache($key); }, $settlementCacheKeys);

            // 需要结算回用户钱包地址的数据
            if ($needTransferBackData) {
                $producer = ApplicationContext::getContainer()->get(Producer::class); // 注入生产者
                foreach ($needTransferBackData as $v) {
                    // MQ生产消息
                    $producer->produce(new BlockTransferProducer($v));
                }
            }

            unset($settlementCacheKeys, $gamePeriodsList, $needTransferBackData);
            return $currOpenBlockNumber;
        } catch (\Exception $e) {
            // 解锁
            self::delCache($lockKey);
            self::logger()->error('BlockGamePeriodsService.periodsSettlement.Exception：' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 转账下注结算
     * @param string $betCacheKey
     * @return bool|int
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function periodsSettlementByTransfer(string $betCacheKey)
    {
        $currTime = time();
        // 下注信息
        $betData = self::getCache($betCacheKey);
        if (!$betData) {
            self::logger()->alert('BlockGamePeriodsService.periodsSettlementByTransfer.$betCacheKey：' . $betCacheKey . ' Not Found');
            return 0;
        }

        // 获取游戏信息
        $game = BlockGameService::getGameInfo($betData['game_id']);
        // 当前游戏当前区块开奖结果
        $openRes = self::getOpenResult($betData['block_hash'], (string)$betData['block_number'], $game['game_type_second']);

        // 当前游戏下注开奖规则
        $roomLevelChar = BlockGameService::getBetRoomLevelByNumber((int)$betData['bet_level']);
        $openRule = $game['transfer_bet_rule'][$roomLevelChar];

        // 更新下注数据
        $betData['periods_id'] = 0; // 游戏期数ID
        $betData['curr_periods'] = $betData['block_number']; // 当前期数
        $betData['is_open'] = EnumType::BET_IS_OPEN_YES; // 已开奖
        $betData['open_data'] = json_encode($openRes['data']); // 开奖数据
        $betData['open_result'] = $openRes['result']; // 开奖结果
        $betData['open_time'] = date(self::$dateTimeFormat, $currTime); // 开奖时间
        $betData['is_valid'] = EnumType::BET_IS_VALID_YES; // 是否有效
        // 根据开奖结果获取下注结果
        $betRes = self::getBetResult($betData, $openRes['result'], json_decode($betData['open_data'], true), $openRule, (int)$game['game_type_second']);
        $betData['is_win'] = $betRes['is_win']; // 输赢状态
        $betData['win_lose_amount'] = $betRes['win_lose_amount']; // 输赢金额-cash
        $betData['win_lose_amount_bonus'] = $betRes['win_lose_amount_bonus']; // 输赢金额-bonus
        $betData['settlement_amount'] = $betRes['settlement_amount']; // 结算金额-cash
        $betData['settlement_amount_bonus'] = $betRes['settlement_amount_bonus']; // 结算金额-bonus
        $betData['sxfee_ratio'] = $betRes['sxfee_ratio'] ?? 0; // 手续费率
        $betData['sxfee_amount'] = $betRes['sxfee_amount'] ?? 0; // 手续费-cash
        $betData['sxfee_amount_bonus'] = $betRes['sxfee_amount_bonus'] ?? 0; // 手续费-bonus
        $betData['refund_amount'] = $betRes['refund_amount'] ?? 0; // 退还金额-cash
        $betData['refund_amount_bonus'] = $betRes['refund_amount_bonus'] ?? 0; // 退还金额-bonus
        $betData['status'] = $betRes['status']; // 下注状态：0（待结算）、1（已完成）、2（已退款）

        try {
            // 保存下注数据
            BlockGameBetService::saveBetData([$betData]);
            // 清除下注缓存数据
            self::delCache($betCacheKey);
            // 转账
            if ($betData['settlement_amount'] > 0 && !empty($betData['bet_address'])) {
                BlockApiService::sendTransaction($betData['bet_address'], (float)$betData['settlement_amount'], $betData['bet_currency']);
            }
            return true;
        } catch (\Throwable $e) {
            self::logger()->error('BlockGamePeriodsService.periodsSettlementByTransfer.Exception：' . $e->getMessage());
            self::logger()->alert('BlockGamePeriodsService.periodsSettlementByTransfer.BetData：' . var_export($betData, true));
            return false;
        }
    }

    /**
     * 组装用户余额变更日志记录
     * @param array $betDataList
     * @param array $uids
     * @return array
     */
    protected static function packUserBalanceChangeLog(array $betDataList, array $uids): array
    {
        // 获取所有结算用户信息
        $betUsersTmp = self::getPoolTb('userinfo')->whereIn('uid', $uids)
            ->select('uid', 'coin', 'bonus', 'channel', 'package_id')->get()->toArray();
        $betUsers = [];
        foreach ($betUsersTmp as $user) {
            $betUsers[$user['uid']] = $user;
        }
        unset($betUsersTmp);
        // 组装用户余额变更日志记录
        $currTime = time();
        $userCoinLogs = $userBonusLogs = [];
        foreach ($betDataList as $bet) {
            if ($bet['bet_way'] == EnumType::BET_WAY_TRANSFER) continue;
            $logTmp = [
                'uid' => $bet['uid'],
                'num' => 0,
                'total' => 0,
                'reason' => 1,
                'type' => 1,
                'content' => "hash游戏订单[{$bet['bet_id']}]下注结算",
                'channel' => $betUsers[$bet['uid']]['channel'],
                'package_id' => $betUsers[$bet['uid']]['package_id'],
                'createtime' => $currTime,
            ];
            if ($bet['settlement_amount'] > 0) {
                // 余额变更日志
                $betUsers[$bet['uid']]['coin'] += $bet['settlement_amount']; // 用户余额累加-cash
                $logTmp['num'] = $bet['settlement_amount'];
                $logTmp['total'] = $betUsers[$bet['uid']]['coin'];
                $userCoinLogs[] = $logTmp;
            }
            if ($bet['settlement_amount_bonus'] > 0) {
                // 余额变更日志
                $betUsers[$bet['uid']]['bonus'] += $bet['settlement_amount_bonus']; // 用户余额累加-bonus
                $logTmp['num'] = $bet['settlement_amount_bonus'];
                $logTmp['total'] = $betUsers[$bet['uid']]['bonus'];
                $userBonusLogs[] = $logTmp;
            }
        }

        return [$userCoinLogs, $userBonusLogs];
    }

    /**
     * 缓存丢失的区块
     * @param array $currBlock
     * @return void
     */
    public static function cacheMissBlock(array $currBlock): void
    {
        try {
            // 获取最后结算区块
            $lastBlockCacheKey = EnumType::PERIODS_LAST_SETTLEMENT_BLOCK_CACHE . EnumType::NETWORK_TRX;
            $lastBlock = BaseService::getCache($lastBlockCacheKey);
            if ($lastBlock) {
                // 检测当前结算区块号和最后结算区块号之间差距
                $diffNum = $currBlock['block_number'] - $lastBlock['block_number'];
                if ($diffNum > 1) {
                    // 获取未结算到的区块
                    $cacheKey = EnumType::PERIODS_MISS_BLOCK_CACHE . EnumType::NETWORK_TRX;
                    $producer = ApplicationContext::getContainer()->get(Producer::class); // 注入生产者
                    for ($i = 1; $i < $diffNum; $i++) {
                        $field = (string)($lastBlock['block_number'] + $i);
                        // 缓存未计算到的区块号
                        BaseService::setFieldCache($cacheKey, $field, 1);
                        // MQ生产消息
                        $producer->produce(new BlockSettlementProducer(['block_number' => $field]));
                    }
                }
            }

            // 缓存最后结算区块
            BaseService::setCache($lastBlockCacheKey, $currBlock);

        } catch (\Throwable $exception) {
            self::logger()->alert('BlockGamePeriodsService.cacheMissBlock.Exception：' . $exception->getMessage());
        }
    }

    /**
     * 清除指定的丢失区块
     * @param string $clearBlockNumber
     * @param string int
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function clearMissBlockCache(string $clearBlockNumber, int $network): void
    {
        BaseService::hDelCache(EnumType::PERIODS_MISS_BLOCK_CACHE . $network, $clearBlockNumber);
    }

    /**
     * 组装游戏当期开奖广播推送数据
     * @param string $blockHash
     * @param string $blockNumber
     * @param array $game
     * @return array
     */
    public static function packageGamePeriodsPushData(string $blockHash, string $blockNumber, array $game): array
    {
        $openRes = self::getOpenResult($blockHash, $blockNumber, (int)$game['game_type_second']);
        $area = $openRes['result'];
        // 牛牛
        if (in_array($game['game_type_second'],[EnumType::GAME_TYPE_HASH_NN, EnumType::GAME_TYPE_HASH_NN_1M, EnumType::GAME_TYPE_HASH_NN_3M])) {
            if ($openRes['result'] === 1) { // 庄赢
                $area = $openRes['data'] ? $openRes['data'][0] : 0;
            } elseif ($openRes['result'] === 2) { // 闲赢
                $area = $openRes['data'] ? 10 + $openRes['data'][1] : 0;
            } elseif ($openRes['result'] === 3) { // 和
                $area = $openRes['data'] ? 20 + $openRes['data'][0] : 0;
            }
        }
        return [
            'game_id' => $game['game_id'],
            'announce_area' => $area,
        ];
    }

    /**
     * 根据不同玩法获取缓存key
     * @param int $playMethod
     * @param int $network
     * @return array
     */
    public static function getCacheKeysByPlayMethod(int $playMethod, int $network): array
    {
        return match ($playMethod) {
            // 余额hash
            EnumType::PLAY_METHOD_HASH_BALANCE => [
                // 最后开奖区块缓存key
                'lastOpenBlockCacheKey' => EnumType::PERIODS_LAST_OPEN_BLOCK_CACHE_3S . $network,
                // 下一开将区块增量
                'nextBlockIncrement' => EnumType::NEXT_BLOCK_INCREMENT_3S,
            ],
            // 1分hash
            EnumType::PLAY_METHOD_HASH_1M => [
                // 最后开奖区块缓存key
                'lastOpenBlockCacheKey' => EnumType::PERIODS_LAST_OPEN_BLOCK_CACHE_1M . $network,
                // 下一开将区块增量
                'nextBlockIncrement' => EnumType::NEXT_BLOCK_INCREMENT_1M,
            ],
            // 3分hash
            EnumType::PLAY_METHOD_HASH_3M => [
                // 最后开奖区块缓存key
                'lastOpenBlockCacheKey' => EnumType::PERIODS_LAST_OPEN_BLOCK_CACHE_3M . $network,
                // 下已开将区块增量
                'nextBlockIncrement' => EnumType::NEXT_BLOCK_INCREMENT_3M,
            ],
        };
    }

    /**
     * 组装当期开奖数据
     * @param array $openBlock
     * @param int $network
     * @return array
     */
    public static function packagePeriodsData(array $openBlock, int $network): array
    {
        $currTime = time(); // 当前时间
        // 获取指定网络游戏
        $gameList = BlockGameService::getGameList([
            'network' => $network,
        ], true);
        // 游戏当期开奖数据
        $gamePeriodsList = $gameRuleList = [];
        foreach ($gameList as $game) {
            // 获取开奖结果
            $openRes = self::getOpenResult($openBlock['block_hash'], (string)$openBlock['block_number'], $game['game_type_second']);
            $gamePeriodsList[$game['game_id']] = [
                'periods_id' => Common::createIdSn(5, 'P'), // 游戏期数ID
                'game_id' => $game['game_id'],
                'game_name' => $game['game_name'],
                'network' => $game['network'],
                'game_type_top' => $game['game_type_top'],
                'game_type_second' => $game['game_type_second'],
                'curr_periods' => $openBlock['block_number'] ?? 0,
                'start_time' => date(self::$dateTimeFormat, $openBlock['timestamp'] - 3),
                'end_time' => date(self::$dateTimeFormat, $openBlock['timestamp']),
                'is_open' => EnumType::BET_IS_OPEN_YES,
                'open_data' => json_encode($openRes['data']), // 开奖数据
                'open_result' => $openRes['result'], // 开奖结果
                'open_time' => date(self::$dateTimeFormat, $currTime),
                'open_block' => $openBlock['block_number'] ?? 0,
                'block_hash' => $openBlock['block_hash'] ?? '',
                'transaction_hash' => $openBlock['transaction_hash'] ?? '',
                'bet_user_num' => 0,
                'bet_total_amount' => 0,
                'is_one_min' => (($openBlock['block_number'] % EnumType::NEXT_BLOCK_INCREMENT_1M) == 0) ? 1 : 0, // 标记为1分开奖期数
                'is_three_min' => (($openBlock['block_number'] % EnumType::NEXT_BLOCK_INCREMENT_3M) == 0) ? 1 : 0, // 标记为3分开奖期数,
                'date' => date('Ymd', $currTime),
                'create_time' => date(self::$dateTimeFormat, $currTime),
                'update_time' => date(self::$dateTimeFormat, $currTime),
            ];
            // 游戏规则
            $gameRuleList[$game['game_id']] = [
                'game_id' => $game['game_id'],
                'page_bet_rule' => $game['page_bet_rule'],
                'transfer_bet_rule' => $game['transfer_bet_rule'],
            ];
        }

        unset($gameList);
        return [$gamePeriodsList, $gameRuleList];
    }

    /**
     * 获取开奖结果
     * @param string $blockHash
     * @param string $blockNumber
     * @param int $gameType
     * @return array
     */
    public static function getOpenResult(string $blockHash, string $blockNumber = '', int $gameType = EnumType::GAME_TYPE_HASH_DX): array
    {
        return match ($gameType) {
            // hash大小
            EnumType::GAME_TYPE_HASH_DX,
            EnumType::GAME_TYPE_HASH_DX_1M,
            EnumType::GAME_TYPE_HASH_DX_3M => self::computeOpenResultHashDX($blockHash),
            // hash单双
            EnumType::GAME_TYPE_HASH_DS,
            EnumType::GAME_TYPE_HASH_DS_1M,
            EnumType::GAME_TYPE_HASH_DS_3M => self::computeOpenResultHashDS($blockHash),
            // hash牛牛
            EnumType::GAME_TYPE_HASH_NN,
            EnumType::GAME_TYPE_HASH_NN_1M,
            EnumType::GAME_TYPE_HASH_NN_3M => self::computeOpenResultHashNN($blockHash),
            // 幸运庄闲
            EnumType::GAME_TYPE_HASH_ZX,
            EnumType::GAME_TYPE_HASH_ZX_1M,
            EnumType::GAME_TYPE_HASH_ZX_3M => self::computeOpenResultHashZX($blockHash),
            // hash幸运
            EnumType::GAME_TYPE_HASH_XY,
            EnumType::GAME_TYPE_HASH_XY_1M,
            EnumType::GAME_TYPE_HASH_XY_3M => self::computeOpenResultHashXY($blockHash),
            // hash和值大小
            EnumType::GAME_TYPE_HASH_HZ_DX => self::computeOpenResultHashHZDX($blockHash, $blockNumber),
            // hash和值单双
            EnumType::GAME_TYPE_HASH_HZ_DS => self::computeOpenResultHashHZDS($blockHash, $blockNumber),
        };
    }

    /**
     * 计算开奖结果（hash大小）
     * @param string $blockHash
     * @return array
     */
    public static function computeOpenResultHashDX(string $blockHash): array
    {
        $char = substr(filter_var($blockHash, FILTER_SANITIZE_NUMBER_INT), -1);
        if (in_array($char, ['0', '1', '2', '3', '4'])) {
            $res = 1; // 小
        } else {
            $res = 2; // 大
        }
        return ['result' => $res, 'data' => [$char]];
    }


    /**
     * 计算开奖结果（hash单双）
     * @param string $blockHash
     * @return array
     */
    public static function computeOpenResultHashDS(string $blockHash): array
    {
        $char = substr(filter_var($blockHash, FILTER_SANITIZE_NUMBER_INT), -1);
        if (in_array($char, ['1', '3', '5', '7', '9'])) {
            $res = 1; // 单
        } else {
            $res = 2; // 双
        }
        return ['result' => $res, 'data' => [$char]];
    }

    /**
     * 计算开奖结果（hash幸运）
     * @param string $blockHash
     * @return array
     */
    public static function computeOpenResultHashXY(string $blockHash): array
    {
        $char = substr($blockHash, -2);
        if (!ctype_alpha($char) && !ctype_digit($char)) {
            $res = 1; // 数字和字母（赢）
        } else {
            $res = 2; // 非数字和字母（输）
        }
        return ['result' => $res, 'data' => [$char]];
    }

    /**
     * 计算开奖结果（hash牛牛）
     * @param string $blockHash
     * @return array
     */
    public static function computeOpenResultHashNN(string $blockHash): array
    {
        $char = substr($blockHash, -5);
        // 庄
        $charZ1 = substr($char, 0, 1);
        $charZ2 = substr($char, 1, 1);
        $charZ3 = substr($char, 2, 1);
        $charZ1 = ctype_digit($charZ1) ? (int)$charZ1 : 10;
        $charZ2 = ctype_digit($charZ2) ? (int)$charZ2 : 10;
        $charZ3 = ctype_digit($charZ3) ? (int)$charZ3 : 10;
        $numberZ = ($charZ1 + $charZ2 + $charZ3) % 10;
        $numberZ = $numberZ ?: 10;

        // 闲
        $charX1 = substr($char, 2, 1);
        $charX2 = substr($char, 3, 1);
        $charX3 = substr($char, 4, 1);
        $charX1 = ctype_digit($charX1) ? (int)$charX1 : 0;
        $charX2 = ctype_digit($charX2) ? (int)$charX2 : 0;
        $charX3 = ctype_digit($charX3) ? (int)$charX3 : 0;
        $numberX = ($charX1 + $charX2 + $charX3) % 10;
        $numberX = $numberX ?: 10;

        if ($numberZ > $numberX) {
            $res = 1; // 庄
        } elseif ($numberZ < $numberX) {
            $res = 2; // 闲
        } else {
            $res = 3; // 和
        }
        return ['result' => $res, 'data' => [$numberZ, $numberX]];
    }

    /**
     * 计算开奖结果（幸运庄闲）
     * @param string $blockHash
     * @return array
     */
    public static function computeOpenResultHashZX(string $blockHash): array
    {
        $char = substr($blockHash, -5);
        // 庄
        $charZ1 = substr($char, 0, 1);
        $charZ2 = substr($char, 1, 1);
        $charZ1 = ctype_digit($charZ1) ? (int)$charZ1 : 0;
        $charZ2 = ctype_digit($charZ2) ? (int)$charZ2 : 0;
        $numberZ = ($charZ1 + $charZ2) % 10;

        // 闲
        $charX1 = substr($char, 3, 1);
        $charX2 = substr($char, 4, 1);
        $charX1 = ctype_digit($charX1) ? (int)$charX1 : 0;
        $charX2 = ctype_digit($charX2) ? (int)$charX2 : 0;
        $numberX = ($charX1 + $charX2) % 10;

        if ($numberZ > $numberX) {
            $res = 1; // 庄
        } elseif ($numberZ < $numberX) {
            $res = 2; // 闲
        } else {
            $res = 3; // 和
        }
        return ['result' => $res, 'data' => [$numberZ, $numberX]];
    }

    /**
     * 计算开奖结果（和值大小）
     * @param string $blockHash
     * @param string $blockNumber
     * @return array
     */
    public static function computeOpenResultHashHZDX(string $blockHash, string $blockNumber): array
    {
        $char1 = (int)substr(filter_var($blockHash, FILTER_SANITIZE_NUMBER_INT), -1);
        $char2 = (int)substr($blockNumber, -1);
        $char = ($char1 + $char2) % 10;
        if (in_array($char, [0, 1, 2, 3, 4])) {
            $res = 1; // 小
        } else {
            $res = 2; // 大
        }
        return ['result' => $res, 'data' => [$char]];
    }

    /**
     * 计算开奖结果（和值单双）
     * @param string $blockHash
     * @param string $blockNumber
     * @return array
     */
    public static function computeOpenResultHashHZDS(string $blockHash, string $blockNumber): array
    {
        $char1 = (int)substr(filter_var($blockHash, FILTER_SANITIZE_NUMBER_INT), -1);
        $char2 = (int)substr($blockNumber, -1);
        $char = ($char1 + $char2) % 10;
        if (in_array($char, [1, 3, 5, 7, 9])) {
            $res = 1; // 单
        } else {
            $res = 2; // 双
        }
        return ['result' => $res, 'data' => [$char]];
    }

    /**
     * 获取下注结果
     * @param array $betData
     * @param int $openResult
     * @param array $openData
     * @param int $gameType
     * @return array
     */
    public static function getBetResult(array $betData, int $openResult, array $openData = [], array $rule = [], int $gameType = EnumType::GAME_TYPE_HASH_DX): array
    {
        $result = [
            'is_win' => EnumType::BET_IS_WIN_YES,
            'win_lose_ratio' => 0, // 输赢赔率
            'win_lose_amount' => 0, // 输赢金额-cash
            'win_lose_amount_bonus' => 0, // 输赢金额-bonus
            'settlement_amount' => 0, // 结算金额-cash
            'settlement_amount_bonus' => 0, // 结算金额-bonus
            'sxfee_amount' => 0, // 手续费-cash
            'sxfee_amount_bonus' => 0, // 手续费-bonus
            'sxfee_ratio' => 0, // 手续费率
            'status' => EnumType::BET_STATUS_COMPLETE,
        ];
        return match ($gameType) {
            // hash大小、hash单双、hash和值大小、hash和值单双
            EnumType::GAME_TYPE_HASH_DX,
            EnumType::GAME_TYPE_HASH_DX_1M,
            EnumType::GAME_TYPE_HASH_DX_3M,
            EnumType::GAME_TYPE_HASH_DS,
            EnumType::GAME_TYPE_HASH_DS_1M,
            EnumType::GAME_TYPE_HASH_DS_3M,
            EnumType::GAME_TYPE_HASH_HZ_DX,
            EnumType::GAME_TYPE_HASH_HZ_DS => self::computeBetSettlementResultNormal($result, $betData, $openResult, $rule),
            // hash牛牛
            EnumType::GAME_TYPE_HASH_NN,
            EnumType::GAME_TYPE_HASH_NN_1M,
            EnumType::GAME_TYPE_HASH_NN_3M => self::computeBetSettlementResultHashNN($result, $betData, $openResult, $rule, $openData),
            // 幸运庄闲
            EnumType::GAME_TYPE_HASH_ZX,
            EnumType::GAME_TYPE_HASH_ZX_1M,
            EnumType::GAME_TYPE_HASH_ZX_3M => self::computeBetSettlementResultHashZX($result, $betData, $openResult, $rule),
            // hash幸运
            EnumType::GAME_TYPE_HASH_XY,
            EnumType::GAME_TYPE_HASH_XY_1M,
            EnumType::GAME_TYPE_HASH_XY_3M => self::computeBetSettlementResultHashXY($result, $betData, $openResult, $rule),
        };
    }

    /**
     * 计算下注结算结果(通用)
     * @param array $result
     * @param array $betData
     * @param int $openResult
     * @return array
     */
    public static function computeBetSettlementResultNormal(array $result, array $betData, int $openResult, array $rules): array
    {
        $currFun = function ($bonus = '') use (&$result, $betData, $openResult, $rules) {
            // 判断输赢
            if ($betData['bet_area'] == $openResult) {
                $result['settlement_amount'.$bonus] = round($betData['bet_amount'.$bonus] * $rules['loss_ratio']); // 结算金额
                $result['win_lose_amount'.$bonus] = $result['settlement_amount'.$bonus] - $betData['bet_amount'.$bonus]; // 输赢金额
                $result['sxfee_amount'.$bonus] = $betData['bet_amount'.$bonus] - $result['win_lose_amount'.$bonus]; // 手续费
                $result['sxfee_ratio'] = 2 - $rules['loss_ratio']; // 手续费率
                $result['win_lose_ratio'] = $rules['loss_ratio']; // 输赢赔率
            } else {
                $result['win_lose_amount'.$bonus] = -$betData['bet_amount'.$bonus]; // 输赢金额
                $result['is_win'] = EnumType::BET_IS_WIN_NO; // 输赢状态
            }
        };
        // cash
        if ($betData['bet_amount'] > 0) {
            $currFun();
        }
        // bonus
        if ($betData['bet_amount_bonus'] > 0) {
            $currFun('_bonus');
        }

        return $result;
    }

    /**
     * 计算下注结算结果(hash牛牛)
     * @param array $result
     * @param array $betData
     * @param int $openResult
     * @param array $rules
     * @param array $openData
     * @return array
     */
    public static function computeBetSettlementResultHashNN(array $result, array $betData, int $openResult, array $rules, array $openData): array
    {
        $currFun = function ($bonus = '') use (&$result, $betData, $openResult, $rules, $openData) {
            if ($openResult == 3) { // 和
                // 计算结算金额
                $sxFee = round($betData['bet_amount'.$bonus] * $rules['sxfee_refund_ratio']); // 退还手续费
                $result['refund_amount'.$bonus] = $result['settlement_amount'.$bonus] = $betData['bet_amount'.$bonus] - $sxFee; // 退还和结算金额
                $result['sxfee_amount'.$bonus] = $sxFee; // 手续费
                $result['sxfee_ratio'] = $rules['sxfee_refund_ratio']; // 手续费率
                $result['win_lose_ratio'] = $rules['loss_ratio']; // 输赢赔率
                $result['is_win'] = EnumType::BET_IS_WIN_EQUAL;
                $result['status'] = EnumType::BET_STATUS_REFUND;
            } elseif ($openResult == 2) { // 闲赢
                $pointsNum = $openData[1] ?? 0;
                if (!$pointsNum) {
                    return $result;
                }
                $betAmountPart = $betData['bet_amount'.$bonus] / 10; // 十分之一金额
                // 输赢赔率，牛9-10需扣除手续费
                $lossRatio = $pointsNum >= 9 ? $rules['nn_loss_ratio'] : $rules['loss_ratio'];
                // 计算输赢金额
                $winAmountTmp = $betAmountPart * $pointsNum;
                $winAmount = round($winAmountTmp * ($lossRatio - 1));
                $result['sxfee_amount'.$bonus] = $winAmountTmp - $winAmount; // 手续费
                $result['sxfee_ratio'] = 2 - $lossRatio; // 手续费率
                $result['win_lose_ratio'] = $lossRatio; // 输赢赔率
                $result['win_lose_amount'.$bonus] = $winAmount; // 输赢金额
                $result['settlement_amount'.$bonus] = $betData['bet_amount'.$bonus] + $winAmount; // 结算金额
                $result['is_win'] = EnumType::BET_IS_WIN_YES;
            } elseif ($openResult == 1) { // 庄赢
                $pointsNum = $openData[0] ?? 0;
                if (!$pointsNum) {
                    return $result;
                }
                $betAmountPart = $betData['bet_amount'.$bonus] / 10; // 十分之一金额
                // 输赢金额
                $winAmount = round($betAmountPart * $pointsNum);
                $result['win_lose_ratio'] = $rules['loss_ratio']; // 输赢赔率
                $result['win_lose_amount'.$bonus] = -$winAmount; // 输赢金额
                $result['refund_amount'.$bonus] = $result['settlement_amount'.$bonus] = $betData['bet_amount'.$bonus] - $winAmount; // 退还和结算金额
                $result['is_win'] = EnumType::BET_IS_WIN_NO;
            }
        };

        // cash
        if ($betData['bet_amount'] > 0) {
            $currFun();
        }
        // bonus
        if ($betData['bet_amount_bonus'] > 0) {
            $currFun('_bonus');
        }

        return $result;
    }

    /**
     * 计算下注结算结果(幸运庄闲)
     * @param array $result
     * @param array $betData
     * @param int $openResult
     * @param array $rules
     * @return array
     */
    public static function computeBetSettlementResultHashZX(array $result, array $betData, int $openResult, array $rules): array
    {
        $currFun = function ($bonus = '') use (&$result, $betData, $openResult, $rules) {
            // 是否压中
            if ($openResult == $betData['bet_area']) { // 压中
                $lossRatio = $openResult == 3 ? $rules['zx_equal_loss_ratio'] : $rules['loss_ratio'];
                // 计算结算金额
                $result['settlement_amount'.$bonus] = round($betData['bet_amount'.$bonus] * $lossRatio); // 结算金额
                $result['win_lose_ratio'] = $lossRatio; // 输赢赔率
                $result['win_lose_amount'.$bonus] = $result['settlement_amount'.$bonus] - $betData['bet_amount'.$bonus]; // 输赢金额
            } else { // 未压中
                // 和，扣除手续费后退还
                if ($openResult == 3) {
                    // 计算结算金额
                    $sxFee = round($betData['bet_amount'.$bonus] * $rules['sxfee_refund_ratio']); // 退还手续费
                    $result['refund_amount'.$bonus] = $result['settlement_amount'.$bonus] = $betData['bet_amount'.$bonus] - $sxFee; // 退还和结算金额
                    $result['sxfee_ratio'] = $rules['sxfee_refund_ratio']; // 手续费率
                    $result['sxfee_amount'.$bonus] = $sxFee; // 手续费
                    $result['win_lose_ratio'] = $rules['loss_ratio']; // 输赢赔率
                    $result['is_win'] = EnumType::BET_IS_WIN_EQUAL;
                    $result['status'] = EnumType::BET_STATUS_REFUND;
                } else {
                    $result['win_lose_amount'.$bonus] = $betData['bet_amount'.$bonus]; // 输赢金额
                    $result['is_win'] = EnumType::BET_IS_WIN_NO;
                }
            }
        };
        // cash
        if ($betData['bet_amount'] > 0) {
            $currFun();
        }
        // bonus
        if ($betData['bet_amount_bonus'] > 0) {
            $currFun('_bonus');
        }

        return $result;
    }

    /**
     * 计算下注结算结果(hash幸运)
     * @param array $result
     * @param array $betData
     * @param int $openResult
     * @param array $rules
     * @return array
     */
    public static function computeBetSettlementResultHashXY(array $result, array $betData, int $openResult, array $rules): array
    {
        $currFun = function ($bonus = '') use (&$result, $betData, $openResult, $rules) {
            // 判断输赢
            if ($openResult == 1) {
                $result['settlement_amount'.$bonus] = round($betData['bet_amount'.$bonus] * $rules['loss_ratio']); // 结算金额
                $result['win_lose_ratio'] = $rules['loss_ratio']; // 输赢赔率
                $result['win_lose_amount'.$bonus] = $result['settlement_amount'.$bonus] - $betData['bet_amount'.$bonus]; // 输赢金额
                $result['sxfee_amount'.$bonus] = $betData['bet_amount'.$bonus] - $result['win_lose_amount'.$bonus]; // 手续费
                $result['sxfee_ratio'] = 2 - $rules['loss_ratio']; // 手续费率
            } else {
                $result['win_lose_amount'.$bonus] = -$betData['bet_amount'.$bonus]; // 输赢金额
                $result['is_win'] = EnumType::BET_IS_WIN_NO; // 输赢状态
            }
        };
        // cash
        if ($betData['bet_amount'] > 0) {
            $currFun();
        }
        // bonus
        if ($betData['bet_amount_bonus'] > 0) {
            $currFun('_bonus');
        }

        return $result;
    }

    /**
     * 获取下注数据缓存前缀
     * @param array $params
     * @return string
     */
    public static function getBetDataCachePrefix(array $params): string
    {
        $prefix = EnumType::BET_DATA_PREFIX . EnumType::BET_STATUS_WAITE .
            '_' . $params['network'];
        if (!empty($params['block_number'])) {
            $prefix .= '_' . $params['block_number'];
        }
        if (!empty($params['uid'])) {
            $prefix .= '_' . $params['uid'];
        }
        return $prefix.'*';
    }

    /**
     * 最后开奖区块开奖信息
     * @param string $gameId
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function lastOpenBlockInfo(string $gameId): array
    {
        // 获取游戏信息
        $game = BlockGameService::getGameInfo($gameId);
        // 获取最新区块
        $latestOpenBlock = BlockApiService::getLatestBlock();
        $increment = $game['play_method'] == EnumType::PLAY_METHOD_HASH_3M ? EnumType::NEXT_BLOCK_INCREMENT_3M : EnumType::NEXT_BLOCK_INCREMENT_1M;
        $diffBlockNumber = $latestOpenBlock['block_number'] % $increment;
        $lastOpenBlockNumber = $latestOpenBlock['block_number']; // 最后开奖区块
        if ($diffBlockNumber > 0) {
            $lastOpenBlockNumber = $latestOpenBlock['block_number'] - $diffBlockNumber;
        }

        // 获取最后开奖区块信息
        $block = BlockApiService::getBlockInfo($lastOpenBlockNumber);
        // 获取开奖区域
        $openArea = BlockGamePeriodsService::packageGamePeriodsPushData($block['block_hash'], (string)$block['block_number'], $game);

        return [
            'game_id' => $game['game_id'],
            'open_result' => (string)$openArea['announce_area'] ?? '',
            'block_number' => (int)$block['block_number'],
            'block_hash' => $block['block_hash'] ?? '',
            'transaction_hash' => $block['transaction_hash'] ?? '',
            'timestamp' => $block['timestamp'],
            'timestamp_server' => time(),
        ];
    }
}