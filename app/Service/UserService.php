<?php

namespace App\Service;

use App\Common\Common;
use App\Enum\EnumType;
use App\Exception\ErrMsgException;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;

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
}