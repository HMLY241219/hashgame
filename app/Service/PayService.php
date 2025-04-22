<?php

namespace App\Service;

use App\Common\Common;
use Hyperf\DbConnection\Db;


/**
 * 代收
 */
class PayService extends BaseService
{
    /**
     * 获取货币与U的比例
     * @param array $where  数组
     * @param int $type 类型   1 = 区分法币与虚拟币，同时返回以货币名称为key的值  2 = 直接返回查询出来的数据
     * @param string $field 字段
     * @param int $selectType 查询类型 ： 1= 查询多个数据  ，2= 查询单个数据
     * @return array
     */
    public function getCurrencyAndRatio(array $where = [],int $type = 1,string $field = 'id,name,image,type,bili,pay_min_max',int $selectType = 1)
    {
        $query =  Db::connection('readConfig')
            ->table('currency_and_ratio')
            ->selectRaw($field)
            ->where($where);

        $currency_and_ratio = match ($selectType){
            1 => $query->orderBy('weight','desc')
                ->get()
                ->toArray(),
            2 => $query->first(),
        };

        $data = [];
        if($selectType == 1)foreach ($currency_and_ratio as &$v){
            if($v['image'])$v['image'] = Common::domain_name_path($v['image']);
            if($type == 1)$data[$v['type']][$v['name']] = $v;
        }

        return match ($type){
            1 => $data,
            2 => $currency_and_ratio,
        };
    }


    /**
     * 获取支付方式
     * @param array $where
     * @param string $field
     * @param int $selectType 查询类型 ： 1= 查询多个数据  ，2= 查询单个数据
     * @return mixed[]
     */
    public function getPaymentType(array $where = [],string $field = 'id,name,image,type,url,zs_bili,protocol_ids,first_zs_bonus_bili,zs_bonus_bili',int $selectType = 1)
    {

        $query = Db::connection('readConfig')
            ->table('payment_type')
            ->selectRaw($field)
            ->where($where);

        $payment_type = match ($selectType){
            1 => $query->orderBy('weight','desc')
                ->get()
                ->toArray(),
            2 => $query->first(),

        };

        if($selectType == 1)foreach ($payment_type as &$v){
            if(isset($v['image']) && $v['image'])$v['image'] = Common::domain_name_path($v['image']);
        }
        return $payment_type;
    }


    /**
     * 获取数字货币支付协议
     * @param array $where  条件
     * @param string $field
     * @return mixed[]
     */
    public function getDigitalCurrencyProtocol(array $where = [],string $field = 'id,englishname,icon,name,min_money,max_money,digital_currency_address,digital_currency_url'){
        return Db::connection('readConfig')
            ->table('digital_currency_protocol')
            ->where($where)
            ->selectRaw($field)
            ->get()
            ->toArray();
    }

    /**
     * 获取退款方式
     * @param array $where  条件
     * @param string $field 字段
     * @param int $selectType  查询类型 ： 1= 查询多个数据  ，2= 查询单个数据 ， 3 = 特殊情况返回虚拟获取与对应的法币数据
     * @param string $currency  法币，当 $selectType = 3 的时候必须传入
     * @return
     */
    public function getWithRefundMethodType(array $where = [],string $field = 'id,name,type,currency,protocol_ids',int $selectType = 1,string $currency = 'VND'){
        $query =  Db::connection('readConfig')
            ->table('refundmethod_type')
            ->selectRaw($field);

        return match ($selectType){
            1 => $query->where($where)->orderBy('weight','desc')->get()->toArray(),
            2 => $query->where($where)->first(),
            3 => $query->where(function ($q) use($currency) {
                $q->where(['status' => 1, 'currency' => $currency])
                    ->orWhere(['status' => 1, 'type' => 4]);
            })->orderBy('weight','desc')->get()->toArray(),
        };
    }


    /**
     * 获取玩家退款地址
     * @param array $where  条件
     * @param string $field 字段
     * @param int $selectType  查询类型 ： 1= 查询多个数据  ，2= 查询单个数据
     * @return void
     */
    public function getUserWithdrawInfo(array $where = [],string $field = 'id,backname,account,ifsccode,phone,email,type,currency',int $selectType = 1){
        $query = Db::connection('readConfig')
            ->table('user_withinfo')
            ->selectRaw($field)
            ->where($where);

        return match ($selectType){
            1 => $query->get()
                ->toArray(),
            2 => $query->first(),

        };
    }



    /**
     * 获取玩家钱包地址
     * @param array $where  条件
     * @param string $field 字段
     * @param int $selectType  查询类型 ： 1= 查询多个数据  ，2= 查询单个数据
     * @return void
     */
    public function getUserWalletAddressInfo(array $where = [],string $field = 'id,address,protocol_name,type',int $selectType = 1){
        $query = Db::connection('readConfig')
            ->table('user_wallet_address')
            ->selectRaw($field)
            ->where($where);

        return match ($selectType){
            1 => $query->get()
                ->toArray(),
            2 => $query->first(),

        };
    }



    /**
     * 充值钱包地址
     * @param array $where  条件
     * @param string $field 字段
     * @param int $selectType  查询类型 ： 1= 查询多个数据  ，2= 查询单个数据
     * @return void
     */
    public function getPayWalletAddressInfo(array $where = [],string $field = 'id,address,protocol_name,type',int $selectType = 1){
        $query = Db::connection('readConfig')
            ->table('pay_wallet_address')
            ->selectRaw($field)
            ->where($where);

        return match ($selectType){
            1 => $query->get()
                ->toArray(),
            2 => $query->first(),

        };
    }




    /**
     * 退款钱包地址
     * @param array $where  条件
     * @param string $field 字段
     * @param int $selectType  查询类型 ： 1= 查询多个数据  ，2= 查询单个数据
     * @return void
     */
    public function getWithdrawWalletAddressInfo(array $where = [],string $field = 'id,address,protocol_name,type',int $selectType = 1){
        $query = Db::connection('readConfig')
            ->table('withdraw_wallet_address')
            ->selectRaw($field)
            ->where($where);

        return match ($selectType){
            1 => $query->get()
                ->toArray(),
            2 => $query->first(),

        };
    }




    /**
     * 获取数字货币与U的相互转换
     * @param string $money  法币或U
     * @param int|float $bili  转换比例
     * @param int $type 1=法币转U  2=U转法币
     * @return void
     */
    public function getFiatCryptoConversion(string $money, int|float $bili, int $type = 1){
        if($bili == 1)return $money;
        return match ($type){
            1 => bcdiv($money,(string)$bili,0),
            2 => bcmul($money,(string)$bili,0),
        };
    }
}
