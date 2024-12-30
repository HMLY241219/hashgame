<?php

namespace App\Service;

use App\Common\Common;
use App\Common\User;
use App\Controller\slots\DealWithController;
use App\Enum\EnumType;
use App\Exception\ErrMsgException;
use App\Service\BlockApi\BlockApiService;
use Hyperf\DbConnection\Db;

/**
 * 区块游戏下注服务
 */
class BlockGameBetService extends BaseService
{
    // 表名
    public static string $tbName = 'block_game_bet';

    // 缓存表前缀
    protected static string $cacheTbPrefixList = 'BLOCK_GAME_BET_LIST_';
    protected static string $cacheTbPrefixInfo = 'BLOCK_GAME_BET_INFO_';

    /**
     * 获取游戏下注列表
     * @param array $params
     * @param bool $all
     * @param bool $cached
     * @return array
     */
    public static function getGameBetList(array $params = [], bool $all = false, bool $cached = true): array
    {
        // 从缓存获取
        $params['all'] = $all;
        $hTbName = self::$cacheTbPrefixList.self::createHashKey($params);
        $cacheList = self::getCache($hTbName);
        if ($cacheList && $cached) {
            $list = $cacheList['data'] ? json_decode($cacheList['data'], true) : [];
        } else {
            // 字段
            $field = empty($params['field']) ? ['*'] : $params['field'];
            // 排序
            $order = empty($params['order']) ? 'create_time desc' : $params['order'];

            $model = self::getPartTb(self::$tbName, self::getTbSuffix($params))
                ->when(!empty($params['bet_id']), function ($query) use ($params) {
                    $query->where('bet_id', $params['bet_id']);
                })
                ->when(!empty($params['bet_way']), function ($query) use ($params) {
                    $query->where('bet_way', $params['bet_way']);
                })
                ->when(!empty($params['uid']), function ($query) use ($params) {
                    $query->where('uid', $params['uid']);
                })
                ->when(!empty($params['game_id']), function ($query) use ($params) {
                    $query->where('game_id', $params['game_id']);
                })
                ->when(isset($params['is_open']), function ($query) use ($params) {
                    $query->where('is_open', $params['is_open']);
                })
                ->when(isset($params['is_valid']), function ($query) use ($params) {
                    $query->where('is_valid', $params['is_valid']);
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
                ->when(isset($params['status']), function ($query) use ($params) {
                    $query->where('status', $params['status']);
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
     * 获取游戏下注信息
     * @param int $betId
     * @param string $suffix 分表后缀
     * @return array|\Hyperf\Database\Model\Model|\Hyperf\Database\Query\Builder|mixed|object|null
     */
    public static function getGameBetInfo(int $betId, string $suffix = ''): mixed
    {
        // 从缓存获取
        $hTbName = self::$cacheTbPrefixInfo.$betId;
        $info = self::getCache($hTbName);
        if (!$info) {
            $info = self::getPartTb(self::$tbName, $suffix)
                ->where('bet_id', $betId)->first();
            if ($info) {
                $info = $info->toArray();
                // 数据缓存
                self::setCache($hTbName, $info, self::$cacheExpire);
            }
        }
        self::handleInfo($info);

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
     * 获取下注用户信息
     * @param $uid
     * @param bool $cached
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getBetUserInfo($uid, bool $cached = false): array
    {
        // 从缓存获取
        $cacheKey = EnumType::BET_USER_INFO_PREFIX.$uid;
        $info = self::getCache($cacheKey);
        if (!$cached || !$info) {
            // 从数据库获取
            $info = self::getPoolTb('userinfo')->where('uid', $uid)
                ->first(['uid', 'channel', 'puid', 'package_id', 'coin', 'bonus']);
            if ($info) {
                // 数据缓存
                self::setCache($cacheKey, $info, self::$cacheExpire);
            }
        }

        return $info;
    }

    /**
     * 缓存下注数据
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function cacheGameBet(array $params): array
    {
        $currTime = time();
        // 检测参数
        list($game, $betData, $uInfo) = self::checkParams($params);
        // 下注区块
        $betBlock = self::getBetBlock($game['network'], $game['play_method']); // 下注区块
        // 缓存key前缀
        $checkCacheKeyPrefix = self::betDataCacheKey([
            'open_block' => $betBlock['block_number'],
            'network' => $game['network'],
            'uid' => $params['uid'],
        ]);
        // 检测是否重复下注
        if ($game['play_method'] == EnumType::PLAY_METHOD_HASH_BALANCE) {
            $checkKeys = self::getCacheKeys($checkCacheKeyPrefix . '*');
            if ($checkKeys) {
                throw new ErrMsgException('Repeat the bet', 3015);
            }
        } else {
            // 检测是否封盘
            $increment = $game['play_method'] == EnumType::PLAY_METHOD_HASH_3M ? EnumType::NEXT_BLOCK_INCREMENT_3M : EnumType::NEXT_BLOCK_INCREMENT_1M;
            if (($betBlock['timestamp'] + $increment * 3) - $currTime <= 3) { // 最后三秒不允许下注
                throw new ErrMsgException('Do not bet', 3016);
            }

            // 追加下注
            $checkKeys = self::getCacheKeys($checkCacheKeyPrefix . '*');
            if ($checkKeys) {
                $betData = self::addToBets($betData, $betBlock['block_number'], $game, $checkKeys);
            }
        }

        foreach ($betData as $v) {
            // 下注数据
            $bd['bet_id'] = Common::createIdSn(5, 'B'); // 生成下注ID;
            $bd['uid'] = $params['uid'];
            $bd['puid'] = $uInfo['puid'];
            $bd['channel'] = $uInfo['channel'];
            $bd['package_id'] = $uInfo['package_id'];
            $bd['slots_game_id'] = $game['slots_game_id'] ?? 0;
            $bd['bet_way'] = $params['bet_way'] ?? EnumType::BET_WAY_BALANCE; // 下注方式：1（平台余额）、2（地址转账）
            $bd['bet_currency'] = $v['bet_currency']; // 下注币种：1（金币）、2（USDT）、3（TRX）
            $bd['bet_level'] = $v['bet_level']; // 下注等级：1（初级场）、2（中级场）、3（高级场）
            $bd['bet_area'] = $v['bet_area']; // 下注区域：1（左）、2（右）、3（中）
            $bd['bet_amount'] = $v['bet_amount']; // 下注金额-cash
            $bd['bet_amount_bonus'] = $v['bet_amount_bonus'] ?? 0; // 下注金额-bonus
            $bd['game_id'] = $game['game_id']; // 游戏ID
            $bd['game_name'] = $game['game_name'] ?? ''; // 游戏名称;
            $bd['network'] = $game['network'] ?? ''; // 游戏网络;
            $bd['game_type_top'] = $game['game_type_top'] ?? ''; // 游戏顶级分类;
            $bd['game_type_second'] = $game['game_type_second'] ?? ''; // 游戏第二分类;
            $bd['open_block'] = $betBlock['block_number']; // 下注区块号;
            $bd['win_lose_ratio'] = $v['rule']['loss_ratio'] ?? 0; // 输赢赔付率;
            $bd['sxfee_ratio'] = $v['rule']['sxfee_ratio'] ?? 0; // 手续费率;
            $bd['status'] = EnumType::BET_STATUS_WAITE; // 下注状态;
            $bd['start_time'] = date(self::$dateTimeFormat, $betBlock['timestamp'] - 3); // 当期开始时间;
            $bd['end_time'] = date(self::$dateTimeFormat, $betBlock['timestamp']); // 当期结束时间;
            $bd['create_time'] = date(self::$dateTimeFormat, $currTime); // 创建时间;
            $bd['update_time'] = date(self::$dateTimeFormat, $currTime); // 更新时间;
            $bd['date'] = date('Ymd', $currTime); // 日期;

            $cacheKey = self::betDataCacheKey($bd); // 缓存key
            try {
                // 数据缓存
                self::setCache($cacheKey, $bd);
                // 更新用户余额
                $updateData = [
                    'coin_change' => -$bd['bet_amount'],
                    'reason' => 1,
                    'content' => "hash游戏订单[{$bd['bet_id']}]下注",
                ];
                if ($bd['bet_amount_bonus'] > 0) $updateData['bonus_change'] = -$bd['bet_amount_bonus'];
                UserService::updateUserBalance($bd['uid'], $updateData);
            } catch (\Exception $e) {
                self::delCache($cacheKey);
                self::logger()->alert('BlockGameBetService.cacheGameBet：' . $e->getMessage());
                throw new ErrMsgException('The bet failed', 3009);
            }
        }

        return [
            'game_id' => $game['game_id'],
            'uid' => $params['uid'],
            'bet_block' => $betBlock['block_number'],
            'network' => $game['network'],
            'timestamp' => $currTime,
        ];
    }

    /**
     * 检测参数
     * @param array $params
     * @return array
     */
    protected static function checkParams(array $params = []): array
    {
        // 检测用户
        if (empty($params['uid'])) {
            throw new ErrMsgException('Error code 226', 226);
        }

        // 游戏信息
        $game = BlockGameService::getGameInfo($params['game_id']);
        if (!$game) {
            throw new ErrMsgException('Error code 3003', 3003);
        }
        // 获取用户余额
        $uInfo = UserService::getUserBalance($params['uid']);
        $balanceCheck = $uInfo['balance'] ?? 0; // 用于检测的余额

        // 检测下注数据
        $betData = $betLevelArea = [];
        foreach ($params['bet_data'] as $v) {
            // 检测下注等级
            if (empty($v['bet_level']) || !in_array($v['bet_level'], [EnumType::ROOM_CJ, EnumType::ROOM_ZJ, EnumType::ROOM_GJ])) {
                throw new ErrMsgException('Error code 3010', 3010);
            }

            // 检测下注区域
            if (empty($v['bet_area']) || !in_array($v['bet_area'], [EnumType::BET_AREA_LEFT, EnumType::BET_AREA_RIGHT, EnumType::BET_AREA_CENTER])) {
                throw new ErrMsgException('Error code 3011', 3011);
            }

            // 检测下注币种
            $v['bet_currency'] = $v['bet_currency'] ?? EnumType::BET_CURRENCY_COIN;
            if (empty($v['bet_currency']) || !in_array($v['bet_currency'], [EnumType::BET_CURRENCY_COIN, EnumType::BET_CURRENCY_USDT, EnumType::BET_CURRENCY_TRX])) {
                throw new ErrMsgException('Error code 3012', 3012);
            }

            // 检测下注金额
            if (empty($v['bet_amount'])) {
                throw new ErrMsgException('Error code 3004', 3004);
            }

            // 获取下注规则
            $rule = [];
            if ($params['bet_way'] == EnumType::BET_WAY_BALANCE) {
                $rule = BlockGameService::getGameRuleByRoomLevel($game['page_bet_rule'], $v['bet_level']); // 页面投注规则
            } elseif ($params['bet_way'] == EnumType::BET_WAY_TRANSFER) {
                $rule = BlockGameService::getGameRuleByRoomLevel($game['transfer_bet_rule'], $v['bet_level']); // 转账投注规则
            }
            $betCurrency = self::getBetCurrencyByNumber($v['bet_currency']);
            if ($v['bet_area'] == 3) { // hash庄闲下注“和”
                $betLimit = $rule['bet_limit_other'][$betCurrency] ?? [];
            } else { // 通用限红
                $betLimit = $rule['bet_limit'][$betCurrency] ?? [];
            }
            if (empty($betLimit) || count($betLimit) !== 2) {
                throw new ErrMsgException('Error code 3007', 3007);
            }
            if ($v['bet_amount'] < $betLimit[0] * self::$amountDecimal || $v['bet_amount'] > $betLimit[1] * self::$amountDecimal) {
                throw new ErrMsgException('Error code 3004', 3004);
            }

            $v['rule'] = $rule;

            // 余额下注，检测用户余额
            if ($params['bet_way'] == EnumType::BET_WAY_BALANCE) {
                if ($balanceCheck < $v['bet_amount']) {
                    throw new ErrMsgException('Error code 3008', 3008);
                }
                $balanceCheck -= $v['bet_amount']; // 检测余额扣除
                if ($uInfo['with_bonus']) {
                    // 划分下注金额，cash和bonus
                    if ($v['bet_amount'] <= $uInfo['coin']) { // cash足够
                        $v['bet_amount_bonus'] = 0;
                        // 检测cash扣除
                        $uInfo['coin'] -= $v['bet_amount'];
                    } else { // cash不足
                        $v['bet_amount_bonus'] = $v['bet_amount']- $uInfo['coin'];
                        $v['bet_amount'] = $uInfo['coin'];
                        // 检测cash扣除
                        $uInfo['coin'] = 0;
                        $uInfo['bonus'] -= $v['bet_amount_bonus'];
                    }
                }
            } else {
                $v['bet_amount_bonus'] = 0;
            }

            // 合并相同等级和区域的下注数据
            $betDataTmpKey = $v['bet_level'] . $v['bet_area'] . $v['bet_currency'];
            if (isset($betData[$betDataTmpKey])) {
                $betData[$betDataTmpKey]['bet_amount'] += $v['bet_amount'];
                $betData[$betDataTmpKey]['bet_amount_bonus'] += $v['bet_amount_bonus'];
            } else {
                $betData[$betDataTmpKey] = $v;
            }
            // 不同下注等级所对应的下注区域，用于检测是否存在对立区域下注
            $betLevelArea[$v['bet_level']][] = $v['bet_area'];
        }

        // 检测是否存在对立区域下注
        foreach ($betLevelArea as $ba) {
            // 最多同时支持两个下注区域
            if (count($ba) > 2) {
                throw new ErrMsgException('Error code 3013', 3013);
            }
            $baTmp = array_unique($ba);
            if (count($baTmp) == 2 && self::checkBetAreaIsOpposite($game['game_type_second'], $baTmp)) {
                throw new ErrMsgException('Error code 3014', 3014);
            }
        }

        return [$game, array_values($betData), $uInfo];
    }

    /**
     * 追加下注
     * @param array $betData
     * @param int $betBlockNumber
     * @param array $game
     * @param array $checkKeys
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function addToBets(array $betData, int $betBlockNumber, array $game, array $checkKeys): array
    {
        // 重新组装下注数据
        $betDataList = $betLevelArea = [];
        foreach ($betData as $v) {
            $betDataList[$v['bet_level'].$v['bet_area']] = $v;
            $betLevelArea[$v['bet_level']][] = $v['bet_area'];
        }

        // 追加下注数据
        $addBetList = [];
        foreach ($checkKeys as $key) {
            // 获取缓存下注数据
            $bd = self::getCache($key);
            // 检测用户是否存在当前游戏区块下注
            if ($bd['game_id'] != $game['game_id'] || $bd['open_block'] != $betBlockNumber) {
                continue;
            }

            // 检测是否是存在对立区域下注
            if (isset($betLevelArea[$bd['bet_level']])) {
                $betLevelArea[$bd['bet_level']] = array_merge([$bd['bet_area']], $betLevelArea[$bd['bet_level']]);
                if (self::checkBetAreaIsOpposite($game['game_type_second'], $betLevelArea[$bd['bet_level']])) {
                    throw new ErrMsgException('Error code 3014', 3014);
                }
            }

            // 需要追加下注的订单
            $index = $bd['bet_level'].$bd['bet_area'];
            if (isset($betDataList[$index])) {
                if ($betDataList[$index]['bet_amount'] > 0) {
                    $bd['bet_amount'] += $betDataList[$index]['bet_amount'];
                }
                if ($betDataList[$index]['bet_amount_bonus'] > 0) {
                    $bd['bet_amount_bonus'] += $betDataList[$index]['bet_amount_bonus'];
                }
                $addBetList[$key] = $bd;
                // 删除已标记为追加下注的数据，剩下的则为非追加下注数据
                unset($betDataList[$index]);
            }
        }

        // 重新将下注数据保存到缓存
        if ($addBetList) {
            foreach ($addBetList as $ck => $abl) {
                self::setCache($ck, $abl);
            }
        }

        return array_values($betDataList);
    }

    /**
     * 检测下注区域是否是对立区域
     * @param int $gameType
     * @param array $betArea
     * @return bool
     */
    public static function checkBetAreaIsOpposite(int $gameType, array $betArea): bool
    {
        return match ($gameType) {
            // hash大小、hash单双、幸运庄闲
            EnumType::GAME_TYPE_HASH_DX,
            EnumType::GAME_TYPE_HASH_DX_1M,
            EnumType::GAME_TYPE_HASH_DX_3M,
            EnumType::GAME_TYPE_HASH_DS,
            EnumType::GAME_TYPE_HASH_DS_1M,
            EnumType::GAME_TYPE_HASH_DS_3M,
            EnumType::GAME_TYPE_HASH_ZX,
            EnumType::GAME_TYPE_HASH_ZX_1M,
            EnumType::GAME_TYPE_HASH_ZX_3M => (function () use ($betArea) {
                if (empty(array_diff([1, 2], $betArea))) {
                    return true;
                } else {
                    return false;
                }
            })(),

            default => false,
        };
    }

    /**
     * 获取下注区块
     * @param int $network
     * @param int $playMethod
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getBetBlock(int $network = 1, int $playMethod = 1): array
    {
        // 获取最近区块
        $block = BlockApiService::getLatestBlock($network);
        if (!$block) {
            throw new ErrMsgException('Error code 3005', 3005);
        }
        if ($playMethod === 1) { // 余额hash
            $block['block_number'] = (int)$block['block_number'] + EnumType::NEXT_BLOCK_INCREMENT_3S;
            $block['timestamp'] = (int)$block['timestamp'] + 3;
        } else { // 1分hash、3分hash
            $increment = $playMethod == EnumType::PLAY_METHOD_HASH_3M ? EnumType::NEXT_BLOCK_INCREMENT_3M : EnumType::NEXT_BLOCK_INCREMENT_1M;
            $diffBlockNumber = $block['block_number'] % $increment;
            if ($diffBlockNumber > 0) {
                $diffNum = $increment - $diffBlockNumber;
                $block['block_number'] = $block['block_number'] + $diffNum;
                $block['timestamp'] = $block['timestamp'] + $diffNum * 3;
            }
        }
        return $block;
    }

    /**
     * 下注数据缓存key
     * @param array $data
     * @return string
     */
    public static function betDataCacheKey(array $data): string
    {
        $tmpArr = [
            $data['status'] ?? EnumType::BET_STATUS_WAITE, // 状态
            $data['network'] ?? EnumType::NETWORK_TRX, // 网络
            $data['open_block'] ?? 0, // 下注区块号
            $data['uid'] ?? 0, // 用户ID
        ];
        if (isset($data['bet_id'])) {
            $tmpArr[] = $data['bet_id'];
        }
        return EnumType::BET_DATA_PREFIX . implode('_', $tmpArr);
    }

    /**
     * 保存下注数据
     * @param array $data
     * @return void
     */
    public static function saveBetData(array $data): void
    {
        self::getPartTb(self::$tbName)->insert($data);

        // 添加slots游戏日志
        \Hyperf\Coroutine\go(function () use ($data) {
            self::slotsLogAdd($data);
        });
    }

    /**
     * 添加slots游戏记录
     * @param array $data
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function slotsLogAdd(array $data): void
    {
        try {
            $dealWith = new DealWithController();
            // 添加slots游戏日志
            $slotsLogData = [];
            foreach ($data as $d) {
                $log = [
                    'betId' => $d['bet_id'],
                    'parentBetId' => '',
                    'uid' => $d['uid'],
                    'puid' => $d['puid'],
                    'terrace_name' => 'Hash',
                    'slotsgameid' => $d['game_id'],
                    'game_id' => $d['slots_game_id'],
                    'englishname' => $d['game_name'] ?? '',
                    'cashBetAmount' => $d['bet_amount'],
                    'bonusBetAmount' => $d['bet_amount_bonus'],
                    'cashWinAmount' => $d['settlement_amount'],
                    'bonusWinAmount' => $d['settlement_amount_bonus'],
                    'cashTransferAmount' => $d['win_lose_amount'],
                    'bonusTransferAmount' => $d['win_lose_amount_bonus'],
                    'cashRefundAmount' => $d['refund_amount'] ?? 0,
                    'bonusRefundAmount' => $d['refund_amount_bonus'] ?? 0,
                    'transaction_id' => '',
                    'betTime' => strtotime($d['start_time']),
                    'package_id' => $d['package_id'],
                    'channel' => $d['channel'],
                    'betEndTime' => strtotime($d['end_time']),
                    'createtime' => strtotime($d['create_time']),
                    'is_consume' => 1,
                    'is_sports' => 0,
                    'is_settlement' => 1,
                    'really_betAmount' => 0,
                    'other' => json_encode([
                        'block_number' => $d['open_block'],
                        'block_hash' => $d['block_hash'],
                        'transaction_hash' => $d['transaction_hash'],
                        'is_win' => $d['is_win'],
                        'bet_area' => $d['bet_area'],
                        'win_lose_amount' => $d['win_lose_amount'],
                    ])
                ];
                $slotsLogData[] = $log;

                // 将数据统一存入到Redis，用户出来以后在统计总输赢,流水等
                $dealWith->setUserWaterTransferAmount($log);
            }
            if ($slotsLogData) {
                // 批量插入记录
                self::getPartTb('slots_log')->insert($slotsLogData);
            }
        } catch (\Exception $e) {
            self::logger()->alert('BlockGameBetService.slotsLogAdd.Exception：' . $e->getMessage());
        }

    }

    /**
     * 获取游戏房间下注统计数据
     * @param string $gameId
     * @param int $betLevel
     * @param $uid
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getGameRoomBetStatisticsData(string $gameId, int $betLevel = EnumType::ROOM_CJ, $uid = null): array
    {
        // 获取游戏信息
        $game = BlockGameService::getGameInfo($gameId);
        if (!$game) {
            return [];
        }

        // 统计数据
        $statisticsData = [
            'game_id' => $gameId,
            'bet_level' => $betLevel,
            'data' => [// 当期下注人数、金额
                'user_num' => [0,0,0],
                // 当期下注金额
                'bet_amount' => [0,0,0],
                // 当前用户当期下注金额
                'player_bet_amount' => [0,0,0],
            ],
        ];
        // 检测玩法
        if ($game['play_method'] == EnumType::PLAY_METHOD_HASH_BALANCE) { // 余额hash
            // 获取缓存中所有下注订单
            $cacheKeys = self::getCacheKeys(BlockGamePeriodsService::getBetDataCachePrefix([
                'network' => $game['network'],
            ]));
            // 获取游戏房间指定下注区域统计数据
            $statisticsData['data'] = self::getGameRoomBetAreaStatisticsData($game, $betLevel, $cacheKeys);
        } elseif ($game['play_method'] == EnumType::PLAY_METHOD_HASH_1M || $game['play_method'] == EnumType::PLAY_METHOD_HASH_3M) { // 1分和3分hash
            // 获取最后下注区块
            $pmKeys = BlockGamePeriodsService::getCacheKeysByPlayMethod($game['play_method'], $game['network']);
            $lastOpenBlock = self::getCache($pmKeys['lastOpenBlockCacheKey']);
            if (!$lastOpenBlock) {
                // 获取最新区块
                $lastOpenBlock = BlockApiService::getLatestBlock();
                $diffBlockNumber = $lastOpenBlock['block_number'] % $pmKeys['nextBlockIncrement'];
                if ($diffBlockNumber > 0) {
                    $lastOpenBlock['block_number'] -= $diffBlockNumber;
                }
            }
            $currBlockNumber = $lastOpenBlock['block_number'] + $pmKeys['nextBlockIncrement'];
            // 获取当前区块下注订单
            $cacheKeys = self::getCacheKeys(BlockGamePeriodsService::getBetDataCachePrefix([
                'network' => $game['network'],
                'block_number' => $currBlockNumber,
            ]));
            // 获取游戏房间指定下注区域统计数据
            $statisticsData['data'] = self::getGameRoomBetAreaStatisticsData($game, $betLevel, $cacheKeys, $uid);
        }

        return $statisticsData;
    }

    /**
     * 获取当天下注人数
     * @param array $params
     * @return int
     */
    public static function getCurrDateBetUserNum(array $params): int
    {
        return self::getPartTb(self::$tbName)
            ->where('game_id', $params['game_id'])
            ->where('is_valid', EnumType::BET_IS_VALID_YES)
            ->where('bet_way', $params['bet_way'])
            ->where('bet_level', $params['bet_level'])
            ->where('bet_area', $params['bet_area'])
            ->count(DB::raw('DISTINCT uid'));
    }

    /**
     * 获取当天下注用户下注数
     * @param array $params
     * @return array
     */
    public static function getCurrDateUserBetNum(array $params): array
    {
        $date = date('Ymd');
        $isValid = EnumType::BET_IS_VALID_YES;
        $dataTmp = self::doQuery("SELECT
                                        uid,
                                        count( bet_id ) num 
                                    FROM
                                        br_block_game_bet_{$date} 
                                    WHERE
                                        game_id = '{$params['game_id']}' 
                                        AND is_valid = {$isValid}
                                        AND bet_way = {$params['bet_way']}
                                        AND bet_level = {$params['bet_level']} 
                                        AND bet_area = {$params['bet_area']} 
                                    GROUP BY
                                        uid");
        $data = [];
        foreach ($dataTmp as $v) {
            $data[$v['uid']] = $v;
        }
        unset($dataTmp);
        return $data;
    }

    /**
     * 获取当天下注金额
     * @param array $params
     * @return int
     */
    public static function getCurrDateBetAmount(array $params): int
    {
        return self::getPartTb(self::$tbName)
            ->where('game_id', $params['game_id'])
            ->where('is_valid', EnumType::BET_IS_VALID_YES)
            ->where('bet_way', $params['bet_way'])
            ->where('bet_level', $params['bet_level'])
            ->where('bet_area', $params['bet_area'])
            ->sum('bet_amount');
    }

    /**
     * 获取游戏房间下注各区域统计数据
     * @param array $game
     * @param int $betLevel
     * @param array $betCacheKeys
     * @param $uid
     * @return array|int[]
     */
    public static function getGameRoomBetAreaStatisticsData(array $game, int $betLevel, array $betCacheKeys = [], $uid = null): array
    {
        return match ((int)$game['game_type_second']) {
            // hash大小、hash单双
            EnumType::GAME_TYPE_HASH_DX,
            EnumType::GAME_TYPE_HASH_DX_1M,
            EnumType::GAME_TYPE_HASH_DX_3M,
            EnumType::GAME_TYPE_HASH_DS,
            EnumType::GAME_TYPE_HASH_DS_1M,
            EnumType::GAME_TYPE_HASH_DS_3M => (function () use ($game, $betLevel, $betCacheKeys, $uid) {
                $betArea = [EnumType::BET_AREA_LEFT, EnumType::BET_AREA_RIGHT]; //下注区域
                if ($game['play_method'] == EnumType::PLAY_METHOD_HASH_BALANCE) {
                    return self::getGameRoomBetAreaStatisticsData3S($game['game_id'], $betCacheKeys, $betLevel, $betArea, $uid);
                } else {
                    return self::getGameRoomBetAreaStatisticsData13M($game['game_id'], $betCacheKeys, $betLevel, $betArea, $uid);
                }
            })(),
            // 幸运庄闲
            EnumType::GAME_TYPE_HASH_ZX,
            EnumType::GAME_TYPE_HASH_ZX_1M,
            EnumType::GAME_TYPE_HASH_ZX_3M => (function () use ($game, $betLevel, $betCacheKeys, $uid) {
                $betArea = [EnumType::BET_AREA_LEFT, EnumType::BET_AREA_RIGHT, EnumType::BET_AREA_CENTER]; //下注区域
                if ($game['play_method'] == EnumType::PLAY_METHOD_HASH_BALANCE) {
                    return self::getGameRoomBetAreaStatisticsData3S($game['game_id'], $betCacheKeys, $betLevel, $betArea, $uid);
                } else {
                    return self::getGameRoomBetAreaStatisticsData13M($game['game_id'], $betCacheKeys, $betLevel, $betArea, $uid);
                }
            })(),
            // hash幸运、hash牛牛
            EnumType::GAME_TYPE_HASH_XY,EnumType::GAME_TYPE_HASH_XY_1M,EnumType::GAME_TYPE_HASH_XY_3M,
            EnumType::GAME_TYPE_HASH_NN,EnumType::GAME_TYPE_HASH_NN_1M,EnumType::GAME_TYPE_HASH_NN_3M => (function () use ($game, $betLevel, $betCacheKeys, $uid) {
                $betArea = [EnumType::BET_AREA_LEFT]; //下注区域
                if ($game['play_method'] == EnumType::PLAY_METHOD_HASH_BALANCE) {
                    return self::getGameRoomBetAreaStatisticsData3S($game['game_id'], $betCacheKeys, $betLevel, $betArea, $uid);
                } else {
                    return self::getGameRoomBetAreaStatisticsData13M($game['game_id'], $betCacheKeys, $betLevel, $betArea, $uid);
                }
            })(),
            // hash和值大小
            EnumType::GAME_TYPE_HASH_HZ_DX => [],
            // hash和值单双
            EnumType::GAME_TYPE_HASH_HZ_DS => [],
        };
    }

    /**
     * 获取游戏房间下注各区域统计数据-余额hash
     * @param string $gameId
     * @param array $betCacheKeys
     * @param int $betLevel
     * @param array $betArea
     * @param $uid
     * @return array
     */
    public static function getGameRoomBetAreaStatisticsData3S(string $gameId, array $betCacheKeys, int $betLevel, array $betArea, $uid): array
    {
        $statisticsData = [
            'user_num' => [], // 下注人数
            'bet_amount' => [], // 下注金额
            'player_bet_amount' => [] // 当前用户当期下注金额
        ];
        $userBetNumList = [];
        foreach ($betArea as $ba) {
            // 用户下注数
            $userBetNumList[] = self::getCurrDateUserBetNum([
                'game_id' => $gameId, 'bet_way' => EnumType::BET_WAY_BALANCE, 'bet_area' => $ba, 'bet_level' => $betLevel
            ]);

            // 下注金额
            $statisticsData['bet_amount'][] = self::getCurrDateBetAmount([
                'game_id' => $gameId, 'bet_way' => EnumType::BET_WAY_BALANCE, 'bet_area' => $ba, 'bet_level' => $betLevel
            ]);
            // 下注人数
            $statisticsData['user_num'][] = 0; // 初始化
            // 当前用户当期下注金额
            $statisticsData['player_bet_amount'][] = 0; // 初始化
        }

        // 最近开奖区块
        $lastBlock = self::getCache(EnumType::PERIODS_LAST_OPEN_BLOCK_CACHE_3S.EnumType::NETWORK_TRX);
        $lastBlockNumber = $lastBlock['block_number'] ?? 0;
        // 缓存中的下注数据
        foreach ($betCacheKeys as $key) {
            $betData = self::getCache($key);
            // 非当前游戏和转账下注不统计
            if (!$betData || $betData['game_id'] != $gameId || $betData['bet_way'] == EnumType::BET_WAY_TRANSFER) continue;
            // 不是指定房间等级
            if ($betData['bet_level'] != $betLevel) continue;
            // 当前用户最近一期下注金额
            $index = $betData['bet_area']-1;
            if ($betData['uid'] == $uid && $betData['open_block'] == $lastBlockNumber+1) {
                $statisticsData['player_bet_amount'][$index] += $betData['bet_amount'];
            }

            // 下注金额在数据库查询数据上累加
            $statisticsData['bet_amount'][$index] += $betData['bet_amount'];

            // 用户下注数累加
            if (isset($userBetNumList[$index][$betData['uid']])) {
                $userBetNumList[$index][$betData['uid']]['num'] += 1;
            } else {
                $userBetNumList[$index][$betData['uid']]['num'] = 1;
            }
        }

        // 下注人数统计
        foreach ($statisticsData['user_num'] as $k => &$num) {
            $num = count($userBetNumList[$k]);
        }

        return $statisticsData;
    }

    /**
     * 获取游戏房间下注各区域统计数据-1分、3分hash
     * @param string $gameId
     * @param array $betCacheKeys
     * @param int $betLevel
     * @param array $betArea
     * @param $uid
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getGameRoomBetAreaStatisticsData13M(string $gameId, array $betCacheKeys, int $betLevel, array $betArea, $uid = null): array
    {
        $statisticsData = [
            'user_num' => [], // 下注人数
            'bet_amount' => [], // 下注金额
            'player_bet_amount' => [] // 当前用户当期下注金额
        ];
        foreach ($betArea as $ba) {
            $index = $ba - 1;
            // 下注人数
            $statisticsData['user_num'][$index] = 0;
            // 下注金额
            $statisticsData['bet_amount'][$index] = 0;
            // 当前用户当期下注金额
            $statisticsData['player_bet_amount'][$index] = 0;
        }

        // 缓存中的下注数据
        foreach ($betCacheKeys as $key) {
            $betData = self::getCache($key);
            // 非当前游戏和转账下注不统计
            if (!$betData || $betData['game_id'] != $gameId || $betData['bet_way'] == EnumType::BET_WAY_TRANSFER) continue;
            // 不是指定房间等级
            if ($betData['bet_level'] != $betLevel) continue;

            $index = $betData['bet_area'] - 1;
            // 当期下注人数
            $statisticsData['user_num'][$index] += 1;
            // 当期下注金额
            $statisticsData['bet_amount'][$index] += $betData['bet_amount'];
            // 当前用户当期下注金额
            if ($uid && $betData['uid'] == $uid) {
                $statisticsData['player_bet_amount'][$index] += $betData['bet_amount'];
            }
        }

        return $statisticsData;
    }

    public static function userGameBetDataStatistics(array $params): array
    {
        // 获取转账下注数据
        $list = self::getPartTb(self::$tbName, $params['date'] ?? '')
            ->select([
                'bet_currency',
                Db::raw('COUNT(bet_id) AS bet_num'),
                Db::raw('SUM(bet_amount) AS bet_amount'),
                Db::raw('SUM(win_lose_amount) AS win_lose_amount')
            ])
            ->where('game_id', $params['game_id'])
            ->where('uid', $params['uid'])
            ->when(!empty($params['bet_way']), function($query) use ($params) {
                $query->where('bet_way', $params['bet_way']);
            })
            ->groupBy('bet_currency')->get()->toArray();
        foreach ($list as &$v) {
            $v['bet_currency_char'] = self::getBetCurrencyByNumber($v['bet_currency']);
        }
        return $list;
    }

    /**
     * 获取钱包地址转账下注区域
     * @param int $betAmount
     * @param int $gameType
     * @return int
     */
    public static function getAddressBetArea(int $betAmount, int $gameType = EnumType::GAME_TYPE_HASH_DX): int
    {
        return match ($gameType) {
            // hash大小
            EnumType::GAME_TYPE_HASH_DX,
            EnumType::GAME_TYPE_HASH_DX_1M,
            EnumType::GAME_TYPE_HASH_DX_3M => self::computeBetAreaHashDX($betAmount),
            // hash单双
            EnumType::GAME_TYPE_HASH_DS,
            EnumType::GAME_TYPE_HASH_DS_1M,
            EnumType::GAME_TYPE_HASH_DS_3M => self::computeBetAreaHashDS($betAmount),
            // 幸运庄闲
            EnumType::GAME_TYPE_HASH_ZX,
            EnumType::GAME_TYPE_HASH_ZX_1M,
            EnumType::GAME_TYPE_HASH_ZX_3M => self::computeBetAreaHashZX($betAmount),
            // hash幸运、hash牛牛
            EnumType::GAME_TYPE_HASH_XY,EnumType::GAME_TYPE_HASH_XY_1M,EnumType::GAME_TYPE_HASH_XY_3M,
            EnumType::GAME_TYPE_HASH_NN,EnumType::GAME_TYPE_HASH_NN_1M,EnumType::GAME_TYPE_HASH_NN_3M => 1,
            // hash和值大小
            EnumType::GAME_TYPE_HASH_HZ_DX => self::computeBetAreaHashHZDX($betAmount),
            // hash和值单双
            EnumType::GAME_TYPE_HASH_HZ_DS => self::computeBetAreaHashHZDS($betAmount),
        };
    }

    /**
     * 计算下注区域（hash大小）
     * @param int $betAmount
     * @return int
     */
    public static function computeBetAreaHashDX(int $betAmount): int
    {
        if (in_array(intval($betAmount) % 10, [0, 1, 2, 3, 4])) {
            $betArea = 1; // 小
        } else {
            $betArea = 2; // 大
        }
        return $betArea;
    }

    /**
     * 计算下注区域（hash单双）
     * @param int $betAmount
     * @return int
     */
    public static function computeBetAreaHashDS(int $betAmount): int
    {
        if (in_array(intval($betAmount) % 10, [1, 3, 5, 7, 9])) {
            $betArea = 1; // 单
        } else {
            $betArea = 2; // 双
        }
        return $betArea;
    }

    /**
     * 计算下注区域（hash幸运）
     * @param string $blockHash
     * @return int
     */
    public static function computeBetAreaHashXY(string $blockHash): int
    {
        $charLast = substr($blockHash, -2);
//        ctype_alnum($charLast); // 数字和字母
        if (ctype_alpha($charLast)) {
            $betArea = 1; // 全字母
        } elseif (ctype_digit($charLast)) {
            $betArea = 2; // 全数字
        } else {
            $betArea = 3; // 数字和字母
        }
        return $betArea;
    }

    /**
     * 计算下注区域（幸运庄闲）
     * @param int $betAmount
     * @return int
     */
    public static function computeBetAreaHashZX(int $betAmount): int
    {
        $charNumber = intval($betAmount) % 10; // 金额个位
        if ($charNumber === 1) {
            $betArea = 1; // 庄
        } elseif ($charNumber === 2) {
            $betArea = 2; // 闲
        } elseif ($charNumber === 3) {
            $betArea = 3; // 平
        } else {
            $betArea = 0; // 无效
        }
        return $betArea;
    }

    /**
     * 计算下注区域（和值大小）
     * @param int $betAmount
     * @return int
     */
    public static function computeBetAreaHashHZDX(int $betAmount): int
    {
        if (in_array(intval($betAmount) % 10, [0, 1, 2, 3, 4])) {
            $betArea = 1; // 小
        } else {
            $betArea = 2; // 大
        }
        return $betArea;
    }

    /**
     * 计算下注区域（和值单双）
     * @param int $betAmount
     * @return int
     */
    public static function computeBetAreaHashHZDS(int $betAmount): int
    {
        if (in_array(intval($betAmount) % 10, [1, 3, 5, 7, 9])) {
            $betArea = 1; // 单
        } else {
            $betArea = 2; // 双
        }
        return $betArea;
    }

    /**
     * 中奖排行榜
     * @param string $type
     * @return array
     */
    public static function winRankingList(string $type = 'real'): array
    {
        $field = ['bet_id', 'bet_way', 'bet_level', 'uid', 'game_id', 'game_name', 'network', 'open_block',
            'block_hash', 'transaction_hash', 'bet_amount', 'bet_currency', 'is_win', 'win_lose_amount', 'settlement_amount',
            'date', 'create_time', 'update_time'];
        $limitNum = 20; // 每张表查询数据条数
        $rankingData = match ($type) {
            // 实时中奖
            'real' => (function () use ($field, $limitNum) {
                // 获取今日实时中奖下注记录
                return self::getPartTb(self::$tbName)->where('is_win', EnumType::BET_IS_WIN_YES)
                    ->select($field)->orderBy('create_time', 'desc')->limit($limitNum)->get()->toArray();
            })(),
            // 日中奖
            'day' => (function () use ($field, $limitNum) {
                // 获取今日中奖排行
                return self::getPartTb(self::$tbName)->where('is_win', EnumType::BET_IS_WIN_YES)
                    ->select($field)->orderBy('win_lose_amount', 'desc')->limit($limitNum)->get()->toArray();
            })(),
            // 周中奖
            'week' => self::getRankingStatisticsData(EnumType::RANKING_TYPE_WIN_WEEK, $field, $limitNum),
            // 月中奖
            'month' => self::getRankingStatisticsData(EnumType::RANKING_TYPE_WIN_MONTH, $field, $limitNum),
        };
        $ranking = [];
        foreach ($rankingData as $k => $v) {
            if ($k+1 > $limitNum) break;
            $ranking[] = [
                'ranking_no' => $k + 1,
                'game_name' => $v['game_name'],
                'block_hash' => $v['block_hash'],
                'transaction_hash' => $v['transaction_hash'],
                'win_lose_amount' => $v['win_lose_amount'],
                'create_time' => $v['create_time'],
            ];
        }
        return $ranking;
    }

    /**
     * 获取排行榜统计数据
     * @param int $rankingType
     * @param array $field
     * @param int $limitNum
     * @return array
     */
    public static function getRankingStatisticsData(int $rankingType, array $field, int $limitNum): array
    {
        $rankingToday = self::getPartTb(self::$tbName)->where('is_win', EnumType::BET_IS_WIN_YES)
            ->select($field)->orderBy('win_lose_amount', 'desc')->limit($limitNum)->get()->toArray();
        // 获取排行榜统计数据
        $rankingStatistics = self::getPoolTb('block_game_bet_ranking')->where('ranking_type', $rankingType)->select()->get()->toArray();

        // 合并今日数据
        $rankingList = array_merge($rankingStatistics, $rankingToday);
        // 重新排序
        array_multisort(array_column($rankingList, 'win_lose_amount'),SORT_DESC, SORT_NUMERIC, $rankingList);
        return $rankingList;
    }
}