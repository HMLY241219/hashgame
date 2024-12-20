<?php

namespace App\Service;

use App\Common\Common;
use App\Enum\EnumType;
use App\Exception\ErrMsgException;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;

/**
 * 区块游戏服务
 */
class BlockGameService extends BaseService
{
    // 表名
    protected static string $tbName = 'block_game';

    // 缓存表前缀
    protected static string $cacheTbPrefixList = 'BLOCK_GAME_LIST_';
    protected static string $cacheTbPrefixInfo = 'BLOCK_GAME_INFO_';

    /**
     * 获取游戏列表
     * @param array $params
     * @param bool $all true全部、false分页
     * @param bool $cached 是否从缓存获取
     * @return array
     */
    public static function getGameList(array $params = [], bool $all = false, bool $cached = true): array
    {
        // 从缓存获取
        $params['all'] = $all;
        $hTbName = self::$cacheTbPrefixList.self::createHashKey($params);
        $cacheList = self::getCache($hTbName);
        if ($cacheList) {
            $list = $cacheList['data'] ? json_decode($cacheList['data'], true) : [];
        } else {
            // 字段
            $field = empty($params['field']) ? ['*'] : $params['field'];
            // 排序
            $order = empty($params['order']) ? 'sort desc' : $params['order'];

            $model = self::getPoolTb(self::$tbName)
                ->where('status', 1)
                ->when(!empty($params['game_type_top']), function ($query) use ($params) {
                    $query->where('game_type_top', $params['game_type_top']);
                })
                ->when(!empty($params['play_method']), function ($query) use ($params) {
                    $query->where('play_method', $params['play_method']);
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
     * 获取游戏信息
     * @param int $gameId
     * @return array|\Hyperf\Database\Model\Model|\Hyperf\Database\Query\Builder|mixed|object|null
     */
    public static function getGameInfo(string $gameId): mixed
    {
        // 从缓存获取
        $hTbName = self::$cacheTbPrefixInfo.$gameId;
        $info = self::getCache($hTbName);
        if (!$info) {
            // 从数据库获取
            $info = self::getPoolTb(self::$tbName)
                ->where('game_id', $gameId)
                ->leftJoin('slots_game sg', self::$tbName.'.game_id', '=', 'sg.slotsgameid')
                ->first([self::$tbName.'.*', 'sg.game_id slots_game_id', 'sg.englishname slots_game_name']);
            if ($info) {
                // 数据缓存
                self::setCache($hTbName, $info, self::$cacheExpire);
            }
        }

        if ($info) self::handleInfo($info);

        return $info;
    }

    /**
     * 根据下注钱包地址获取游戏信息
     * @param string $betAddress
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getGameInfoByAddress(string $betAddress): mixed
    {
        // 从缓存获取
        $hTbName = EnumType::BET_ADDRESS_BIND_GAME_PREFIX.$betAddress;
        $info = self::getCache($hTbName);
        if (!$info) {
            // 从数据库获取
            $info = self::getPoolTb(self::$tbName)->where('transfer_bet_rule', 'like', "%{$betAddress}%")->first();
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
        if(!empty($info['page_bet_rule'])) $info['page_bet_rule'] = json_decode($info['page_bet_rule'], true);
        if(!empty($info['transfer_bet_rule'])) $info['transfer_bet_rule'] = json_decode($info['transfer_bet_rule'], true);
        // 图片url组装
        if(!empty($info['icon_img'])) $info['icon_img'] = Common::domain_name_path($info['icon_img']);
        if(!empty($info['cover_img'])) $info['cover_img'] = Common::domain_name_path($info['cover_img']);
    }

    /**
     * 设置游戏
     * @param array $params
     * @return array
     */
    public function setGame(array $params = []): array
    {
        $currTime = Carbon::now(); // 当前时间

        // 检测参数
        $this->checkParams($params);

        // 保存数据
        $saveData = [
            'game_name' => $params['game_name'],
            'game_brief' => $params['game_brief'] ?? '',
            'icon_img' => $params['icon_img'],
            'cover_img' => $params['cover_img'],
            'allow_bet_way' => $params['allow_bet_way'],
            'network' => $params['network'],
            'game_type_top' => $params['game_type_top'],
            'game_type_second' => $params['game_type_second'],
            'play_method' => $params['play_method'],
            'page_bet_rule' => $params['page_bet_rule'],
            'transfer_bet_rule' => $params['transfer_bet_rule'] ?? '',
            'sort' => $params['sort'] ?? 0,
            'update_time' => $currTime,
        ];

        $gameId = Db::transaction(function () use ($params, $saveData, $currTime) {
            if (!empty($params['game_id'])) {
                // 更新数据
                self::getPoolTb(self::$tbName)->where('game_id', $params['game_id'])->update($saveData);
                $saveData['game_id'] = $params['game_id'];

                // 清除游戏缓存
                self::delCache(self::$cacheTbPrefixInfo.$params['game_id']);

            } else {
                // 新增数据
                $saveData['game_id'] = Common::createIdSn(5, 'G'); // 生成游戏ID
                $saveData['create_time'] = $currTime;
                self::getPoolTb(self::$tbName)->insert($saveData);
            }

            // 更新slots_game表
            $this->setSlotsGame($saveData);

            return $saveData['game_id'];
        });

        return ['game_id' => $gameId];
    }

    /**
     * 设置slots_game
     * @param array $blockGame
     * @return void
     */
    private function setSlotsGame(array $blockGame): void
    {
        $saveData = [
            'name' => $blockGame['game_name'] ?? '',
            'englishname' => $blockGame['game_name'] ?? '',
            'slotsgameid' => $blockGame['game_id'] ?? '',
            'image' => $blockGame['icon_img'] ?? '',
            'image2' => $blockGame['cover_img'] ?? '',
            'terrace_id' => 19,
            'type' => 4,
            'updatetime' => strtotime($blockGame['update_time']),
        ];

        // 检测数据是否存在
        $num = self::getPoolTb('slots_game')->where('slotsgameid', $saveData['slotsgameid'])->count();
        if ($num) {
            unset($saveData['image'], $saveData['image2']); // 不更新图片
            // 更新数据
            self::getPoolTb('slots_game')->where('slotsgameid', $saveData['slotsgameid'])->update($saveData);
        } else {
            // 新增数据
            $saveData['createtime'] = strtotime(date(self::$dateTimeFormat));
            self::getPoolTb('slots_game')->insert($saveData);
        }
    }

    /**
     * 检测参数
     * @param array $params
     * @return void
     */
    protected function checkParams(array $params): void
    {
        // 游戏名称是否存在
        if (isset($params['game_name'])) {
            $info = self::getPoolTb(self::$tbName)->where('game_name', $params['game_name'])
                ->when(!empty($params['game_id']), function ($query) use ($params) {
                    $query->where('game_id', '<>', $params['game_id']);
                })
                ->first();
            if ($info) {
                throw new ErrMsgException('', 3001);
            }
        }

        // 页面投注规则格式是否正确
        if (!empty($params['page_bet_rule'])) {
            $rule = json_decode($params['page_bet_rule'], true);
            if (!is_array($rule)) {
                throw new ErrMsgException('', 3002);
            }
        }

        // 转账投注规则格式是否正确
        if (!empty($params['transfer_bet_rule'])) {
            $rule = json_decode($params['transfer_bet_rule'], true);
            if (!is_array($rule)) {
                throw new ErrMsgException('', 3006);
            }
        }

    }

    /**
     * 根据游戏房间等级获取游戏玩法规则
     * @param array $rules
     * @param int $level
     * @return array
     */
    public static function getGameRuleByRoomLevel(array $rules, int $level = 1): array
    {
        return match ($level) {
            EnumType::ROOM_CJ => $rules['room_cj'] ?? [], // 初级场
            EnumType::ROOM_ZJ => $rules['room_zj'] ?? [], // 中级场
            EnumType::ROOM_GJ => $rules['room_gj'] ?? [], // 高级场
            default => []
        };
    }

    /**
     * 根据关键字获取下注房间等级
     * @param string $key
     * @return int
     */
    public static function getBetRoomLevelByKey(string $key = EnumType::ROOM_KEY_CJ): int
    {
        return match ($key) {
            EnumType::ROOM_KEY_CJ => EnumType::ROOM_CJ, // 初级场
            EnumType::ROOM_KEY_ZJ => EnumType::ROOM_ZJ, // 中级场
            EnumType::ROOM_KEY_GJ => EnumType::ROOM_GJ, // 高级场
        };
    }

    /**
     * 根据编号获取下注房间等级
     * @param int $number
     * @return string
     */
    public static function getBetRoomLevelByNumber(int $number = EnumType::ROOM_CJ): string
    {
        return match ($number) {
            EnumType::ROOM_CJ => EnumType::ROOM_KEY_CJ, // 初级场
            EnumType::ROOM_ZJ => EnumType::ROOM_KEY_ZJ, // 中级场
            EnumType::ROOM_GJ => EnumType::ROOM_KEY_GJ, // 高级场
        };
    }
}