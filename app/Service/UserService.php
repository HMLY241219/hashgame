<?php

namespace App\Service;

use App\Common\Common;
use App\Enum\EnumType;
use App\Exception\ErrMsgException;
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
            $sqlArr[] = "UPDATE br_userinfo SET coin = coin + {$v['coin_change']} WHERE uid = {$v['uid']}";
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
     * @param int $uid
     * @return mixed
     */
    public static function getUserBalance(int $uid): mixed
    {
        return self::getPoolTb(self::$tbName)->where('uid', $uid)->first(['uid', 'coin', 'bonus']);
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
}