<?php

namespace App\Service;

use App\Common\Common;
use App\Enum\EnumType;
use App\Exception\ErrMsgException;
use App\Service\WebSocket\SysConfService;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use MrBrownNL\RandomNicknameGenerator\RandomNicknameGenerator;
use Yooper\Nicknames;

/**
 * 用户服务
 */
class UserService extends BaseService
{
    // 表名
    protected static string $tbName = 'userinfo';


    /**
     * 用户钱包地址激活
     * @param string $address
     * @return void
     */
    public static function addressActive(string $address): void
    {
        // 检测地址是否存在
        $addressInfo = self::getPoolTb('user_wallet_address')->where('address', $address)->first();
        if (!$addressInfo) {
            throw new ErrMsgException('Address not found');
        }

        // 激活钱包
        self::getPoolTb('user_wallet_address')->where('id', $addressInfo['id'])->update(['is_active' => 1]);
    }

    /**
     * 批量更新用户Coin
     * @param array $data
     * @return void
     */
    public static function batchUpdateUserCoin(array $data): void
    {
        $sqlArr = [];
        foreach ($data as $v) {
            $setField = '';
            if ($v['coin_change'] > 0) {
                $setField .= "coin = coin + {$v['coin_change']}";
            }
            if ($v['bonus_change'] > 0) {
                $setField .= ",bonus = bonus + {$v['bonus_change']}";
            }
            if ($setField != '') {
                $sqlArr[] = "UPDATE br_userinfo SET {$setField} WHERE uid = {$v['uid']}";
            }
        }

        if ($sqlArr) {
            Db::update(implode(';', $sqlArr));
        }
    }

    /**
     * 用户钱包地址列表
     * @param int $uid
     * @return mixed[]
     */
    public static function userAddressList(int $uid): array
    {
        return self::getPoolTb('user_wallet_address')
            ->where('uid', $uid)
            ->where('type', 3)
            ->select()
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 获取用户余额
     * @param int $uid
     * @return mixed
     */
    public static function getUserBalance(int $uid): mixed
    {
        // 获取配置
        $conf = SysConfService::getHashGameConf();
        $info = self::getPoolTb(self::$tbName)->where('uid', $uid)->first(['uid', 'channel', 'puid', 'package_id', 'coin', 'bonus']);
        if (isset($conf['hash_game_bet_with_bouns']) && $conf['hash_game_bet_with_bouns']) {
            $info['balance'] = $info['coin'] + $info['bonus'];
            $info['with_bonus'] = 1;
        } else {
            $info['balance'] = $info['coin'];
            $info['with_bonus'] = 0;
        }
        return $info;
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
                // 获取配置
                $conf = SysConfService::getHashGameConf();
                if (isset($conf['hash_game_bet_with_bouns']) && $conf['hash_game_bet_with_bouns']) {
                    $info['balance'] = $info['coin'] + $info['bonus'];
                } else {
                    $info['balance'] = $info['coin'];
                }
                // 数据缓存
                self::setCache($cacheKey, $info, self::$cacheExpire);
            }
        }

        return $info;
    }

    /**
     * 用户下注排行榜
     * @param string $rt
     * @return array
     */
    public static function userBetRankingList(string $rt): array
    {
        switch (strtolower($rt)) {
            // 周榜
            case 'week': $rankingType = 2; break;
            // 月榜
            case 'month': $rankingType = 3; break;
            // 日榜
            case 'day':
            default: $rankingType = 1; break;
        }
        $data = self::getPoolTb('user_bet_ranking')
            ->where('ranking_type', $rankingType)
            ->select()
            ->orderBy('bet_amount', 'desc')
            ->get()->toArray();
        foreach ($data as $k => &$v) {
            $v['ranking_no'] = $k + 1;
        }
        return $data;
    }

    /**
     * 更新用户下注排行榜数据
     * @param array $params
     * @return void
     */
    public static function updateUserBetRanking(array $params = []): void
    {
        // 随机生成昵称
        $randomNick = new RandomNicknameGenerator(['useAdjective' => false]);
        $rankingData = [];
        // 生成各个榜单数据
        for ($i = 1; $i <= 20; $i++) {
            // 日榜
            $name = $randomNick->generate();
            $rankingData[] = [
                'uid' => $i,
                'account' => $name,
                'nickname' => $name,
                'bet_amount' => mt_rand($params['day'][0], $params['day'][1]),
                'ranking_no' => 0,
                'ranking_type' => 1,
            ];

            // 周榜
            $name = $randomNick->generate();
            $rankingData[] = [
                'uid' => $i + 100,
                'account' => $name,
                'nickname' => $name,
                'bet_amount' => mt_rand($params['week'][0], $params['week'][1]),
                'ranking_no' => 0,
                'ranking_type' => 2,
            ];

            // 月榜
            $name = $randomNick->generate();
            $rankingData[] = [
                'uid' => $i + 200,
                'account' => $name,
                'nickname' => $name,
                'bet_amount' => mt_rand($params['month'][0], $params['month'][1]),
                'ranking_no' => 0,
                'ranking_type' => 3,
            ];
        }

        // 截断表
        Db::select("TRUNCATE TABLE br_user_bet_ranking");
        // 插入数据
        BaseService::getPoolTb('user_bet_ranking')->insert($rankingData);
    }

    /**
     * 更新用户余额
     * @param $uid
     * @param array $params
     * @return void
     */
    public static function updateUserBalance($uid, array $params): void
    {
        // 用户信息
        $user = self::getPoolTb(self::$tbName)
            ->select('uid', 'coin', 'bonus', 'package_id', 'channel', 'withdraw_money', 'withdraw_money_other')
            ->where('uid', $uid)->first();

        $updateData = [];
        if (isset($params['coin_change']) && $params['coin_change'] != 0) {
            // 添加日志
            self::getPartTb('coin')->insert([
                'uid' => $user['uid'],
                'num' => $params['coin_change'],
                'total' => bcadd((string)$user['coin'], (string)$params['coin_change'],0),
                'reason' => $params['reason'] ?? 1,
                'type' => $params['coin_change'] > 0 ? 1 : 0,
                'content' => $params['content'] ?? '',
                'channel' => $user['channel'],
                'package_id' => $user['package_id'],
                'createtime' => time(),
            ]);
            $updateData['coin'] = Db::raw('coin + ' . $params['coin_change']);
        }
        if (isset($params['bonus_change']) && $params['bonus_change'] != 0) {
            // 添加日志
            self::getPartTb('bonus')->insert([
                'uid' => $user['uid'],
                'num' => $params['bonus_change'],
                'total' => bcadd((string)$user['bonus'], (string)$params['bonus_change'],0),
                'reason' => $params['reason'] ?? 1,
                'type' => $params['bonus_change'] > 0 ? 1 : 0,
                'content' => $params['content'] ?? '',
                'channel' => $user['channel'],
                'package_id' => $user['package_id'],
                'createtime' => time(),
            ]);
            $updateData['bonus'] = Db::raw('bonus + ' . $params['bonus_change']);
        }

        // 更新余额
        self::getPoolTb(self::$tbName)->where('uid', $uid)->update($updateData);
    }
}