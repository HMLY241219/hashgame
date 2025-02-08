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
     * @return array
     */
    public function getCurrencyAndRatio(array $where = [],int $type = 1,string $field = 'id,name,image,type,bili')
    {
        $currency_and_ratio =  Db::connection('readConfig')
            ->table('currency_and_ratio')
            ->selectRaw($field)
            ->where($where)
            ->orderBy('weight','desc')
            ->get()
            ->toArray();

        $data = [];
        foreach ($currency_and_ratio as &$v){
            if($v['image'])Common::domain_name_path($v['image']);
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
     * @return mixed[]
     */
    public function getPaymentType(array $where = [],string $field = 'id,name,image,type,url,zs_bili,protocol_ids,first_zs_bonus_bili,zs_bonus_bili')
    {

        $payment_type = Db::connection('readConfig')
            ->table('payment_type')
            ->selectRaw($field)
            ->where($where)
            ->orderBy('weight','desc')
            ->get()
            ->toArray();
        foreach ($payment_type as&$v){
            if($v['image'])Common::domain_name_path($v['image']);
        }
        return $payment_type;
    }


    /**
     * 获取数字货币支付协议
     * @param string $field
     * @return mixed[]
     */
    public function getDigitalCurrencyProtocol(string $field = 'id,englishname,icon,name,min_money,max_money'){
        return Db::connection('readConfig')
            ->table('digital_currency_protocol')
            ->selectRaw($field)
            ->get()
            ->toArray();
    }
}
