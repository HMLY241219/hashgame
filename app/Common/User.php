<?php

namespace App\Common;


use App\Controller\AbstractController;
use App\Controller\SqlModel;
use Hyperf\DbConnection\Db;
use App\Controller\slots\Common as SlotsCommon;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;


class User extends AbstractController
{

    protected static $cash_water_multiple = [  //Cash流水倍数
        1 => 'cash_pay_water_multiple',  //充值Cash流水倍数
        2 => 'cash_pay_zs_water_multiple', //充值赠送Cash流水倍数
        3 => 'cash_vip_level_water_multiple', //VIP升级Cash流水倍数
        4 => 'cash_vip_week_water_multiple', //VIP周奖励Cash流水倍数
        5 => 'cash_vip_month_water_multiple', //VIP月奖励Cash流水倍数
        6 => 'cash_turntable_water_multiple', //转盘Cash流水倍数
        7 => 'cash_back_water_multiple', //返水Cash流水倍数
        8 => 'cash_redyu_water_multiple', //红包雨Cash流水倍数
        9 => 'cash_share_water_multiple', //分享Cash流水倍数
        10 => 'cash_sign_water_multiple', //签到Cash流水倍数
        11 => 'cash_three_day_water_multiple', //3天卡Cash流水倍数
        12 => 'cash_month_card_water_multiple', //月卡Cash流水倍数
        14 => 'cash_bankruptcy_water_multiple', //破产活动Cash流水倍数
        15 => 'cash_customer_water_multiple', //	客损活动Cash流水倍数
        16 => 'cash_bankruptcy_wheel_water_multiple', //	破产转盘Cash流水倍数
        17 => 'cash_piggybank_water_multiple', //	存钱罐Cash流水倍数
        18 => 'cash_daytop_water_multiple', //	每日排行榜Cash流水倍数
        19 => 'cash_task_water_multiple', //	任务积分Cash流水倍数
        20 => 'cash_newcashback_water_multiple', //	新版反水Cash流水倍数
        21 => 'cash_orderactive_water_multiple', //	活动日活动Cash流水倍数
        22 => 'cash_register_water_multiple', //	注册赠送Cash流水倍数
        23 => 'cash_redemption_code_multiple', //	兑换码Cash流水倍数
        24 => 'cash_free_water_multiple', //	免费游戏Cash流水倍数
    ];

    protected static $bonus_water_multiple = [  //Bonus流水倍数
        1 => 'bonus_pay_zs_water_multiple',  //充值赠送Bonus流水倍数
        3 => 'bonus_vip_level_water_multiple', //VIP升级Bonus流水倍数
        4 => 'bonus_vip_week_water_multiple', //VIP周奖励Bonus流水倍数
        5 => 'bonus_vip_month_water_multiple', //VIP月奖励Bonus流水倍数
        6 => 'bonus_turntable_water_multiple', //转盘Bonus流水倍数
        7 => 'bonus_back_water_multiple', //返水Bonus流水倍数
        8 => 'bonus_redyu_water_multiple',  //红包雨Bonus流水倍数
        10 => 'bonus_sign_water_multiple',  //签到Bonus流水倍数
        11 => 'bonus_three_day_water_multiple', //3天卡Bonus流水倍数
        12 => 'bonus_month_card_water_multiple', //月卡Bonus流水倍数
        13 => 'bonus_dailyreward_water_multiple', //每日奖励Bonus流水倍数
        14 => 'bonus_bankruptcy_water_multiple', //破产活动Bonus流水倍数
        15 => 'bonus_customer_water_multiple', //客损活动Bonus流水倍数
        16 => 'bonus_bankruptcy_wheel_water_multiple', //破产转盘Bonus流水倍数
        17 => 'bonus_piggybank_water_multiple', //存钱罐Bonus流水倍数
        18 => 'bonus_daytop_water_multiple', //每日排行榜Bonus流水倍数
        19 => 'bonus_task_water_multiple', //任务积分Bonus流水倍数
        20 => 'bonus_newcashback_water_multiple', //	新版反水Bonus流水倍数
        21 => 'bonus_orderactive_water_multiple', //	活动日活动Bonus流水倍数
        22 => 'bonus_register_water_multiple', //	注册赠送Bonus流水倍数
        23 => 'bonus_redemption_code_multiple', //	兑换码Bonus流水倍数
        24 => 'bonus_free_water_multiple', //	免费游戏bonus流水倍数
        25 => 'bonus_bankruptcy_one_water_multiple', //	新破产活动1Bonus流水
        26 => 'bonus_bankruptcy_two_water_multiple', //	新破产活动2Bonus流水
        27 => 'bonus_bankruptcy_three_water_multiple', //	新破产活动3Bonus流水
        28 => 'bonus_firstthreeday_one_water_multiple', //	首充3天活动Bonus流水倍数
        29 => 'bonus_firstthreeday_two_water_multiple', //	首充3天活动2Bonus流水倍数
        30 => 'bonus_firstthreeday_three_water_multiple', // 首充3天活动3Bonus流水倍数
        31 => 'bonus_newcustomer_one_water_multiple', // 新客损1活动
        32 => 'bonus_newcustomer_two_water_multiple', // 新客损2活动
        33 => 'bonus_newcustomer_three_water_multiple', // 新客损3活动
        34 => 'bonus_newcustomer_four_water_multiple', // 新客损4活动
        35 => 'bonus_newcustomer_five_water_multiple', // 新客损5活动,
        36 => 'bonus_newrankings_water_multiple', // 新排行榜Bonus流水倍数,
    ];
    /**
     * 用户Coin变化
     * @param $uid
     * @param $num  变化金额 ： 加钱就是正数，减少就是负数
     * @param $reason  变化原因
     * @param $content  内容
     * @param $type  1 = 只改钱 ， 2 =修改需求流水
     * @param $water_multiple_type 1 = 充值Cash流水倍数: cash_pay_water_multiple , 2 = 充值赠送Cash流水倍数: cash_pay_zs_water_multiple , 3 = VIP升级Cash流水倍数: cash_vip_level_water_multiple
     * 4 = VIP周奖励Cash流水倍数: cash_vip_week_water_multiple , 5 = VIP月奖励Cash流水倍数: cash_vip_month_water_multiple , 6 = 转盘Cash流水倍数: cash_turntable_water_multiple
     * 7 = 返水Cash流水倍数: cash_back_water_multiple , 8 = 红包雨Cash流水倍数: cash_redyu_water_multiple , 9 = 分享Cash流水倍数 : cash_share_water_multiple
     *  @param $withdraw_money_other 退款其他金额
     * @return void
     */
    public static function userEditCoin($uid,$num,$reason,$content = '',$type = 1,$water_multiple_type = 1,$withdraw_money_other = 0){
        if(!$num)return 1;

        $userinfo = Db::table('userinfo')->select('coin','package_id','channel','withdraw_money','withdraw_money_other')->where('uid',$uid)->first();
        if(!$userinfo)return 1;

        Db::beginTransaction();
        $coin = bcadd((string)$userinfo['coin'],(string)$num,0);

        if($type == 2){
            $cash_water_multiple_filed = self::$cash_water_multiple[$water_multiple_type] ?? self::$cash_water_multiple[1];
            $cash_water_multiple = Common::getConfigValue($cash_water_multiple_filed);
            $updateData = [
                'coin' => Db::raw('coin + '.$num),
                'need_cash_score_water' => Db::raw('need_cash_score_water + '.bcmul((string)$num,(string)$cash_water_multiple,0)),
            ];
            if($coin < $userinfo['withdraw_money_other'])$updateData['withdraw_money_other'] = $coin;
            $res = Db::table('userinfo')->where('uid',$uid)->update($updateData);

        }elseif ($type == 3){ //退款,同时修改退款额度
            $res = Db::table('userinfo')->where('uid',$uid)->update(['coin' => Db::raw('coin + '.$num),'withdraw_money_other' => Db::raw('withdraw_money_other + '.$withdraw_money_other)]);
        }elseif ($type == 4){ //直接可退款的额度
            $res = Db::table('userinfo')->where('uid',$uid)->update(['coin' => Db::raw('coin + '.$num),'withdraw_money_other' => Db::raw('withdraw_money_other + '.$num)]);
        }else{
            $updateData = [
                'coin' => Db::raw('coin + '.$num),
            ];
            if($coin < $userinfo['withdraw_money_other'])$updateData['withdraw_money_other'] = $coin;
            $res = Db::table('userinfo')->where('uid',$uid)->update($updateData);
        }


//        $withdraw_money = $userinfo['withdraw_money'];
//        if($coin < $userinfo['withdraw_money'])$withdraw_money = $coin;
//
//        if($type == 2){
//            $cash_water_multiple_filed = self::$cash_water_multiple[$water_multiple_type] ?? self::$cash_water_multiple[1];
//            $cash_water_multiple = Common::getConfigValue($cash_water_multiple_filed);
//            $res = Db::table('userinfo')->where('uid',$uid)->update([
//                'coin' => Db::raw('coin + '.$num),
//                'need_cash_score_water' => Db::raw('need_cash_score_water + '.bcmul((string)$num,(string)$cash_water_multiple,0)),
//                'withdraw_money' => $withdraw_money,
//            ]);
//
//        }elseif ($type == 3){ //退款,同时修改退款额度
//            $res = Db::table('userinfo')->where('uid',$uid)->update(['coin' => Db::raw('coin + '.$num),'withdraw_money' => Db::raw('withdraw_money + '.$num)]);
//        }elseif ($type == 4){ //直接可退款的额度
//            $res = Db::table('userinfo')->where('uid',$uid)->update(['coin' => Db::raw('coin + '.$num),'withdraw_money_other' => Db::raw('withdraw_money_other + '.$num)]);
//        }else{
//            $res = Db::table('userinfo')->where('uid',$uid)->update([
//                'coin' => Db::raw('coin + '.$num),
////                'withdraw_money' => $withdraw_money,
//            ]);
//
//        }


        if(!$res){
            Db::rollBack();
            return 0;
        }
        $res = Db::table('coin_'.date('Ymd'))
            ->insert([
                'uid' => $uid,
                'num' => $num,
                'total' => bcadd((string)$userinfo['coin'],(string)$num,0),
                'reason' => $reason,
                'type' => $num > 0 ? 1 : 0,
                'content' => $content,
                'channel' => $userinfo['channel'],
                'package_id' => $userinfo['package_id'],
                'createtime' => time(),
            ]);
        if(!$res){
            Db::rollBack();
            return 0;
        }
        Db::commit();


        return 1;
    }



    /**
     * 用户Bonus变化
     * @param $uid
     * @param $num  变化金额 ： 加钱就是正数，减少就是负数
     * @param $reason  变化原因
     * @param $content  内容
     * @param $type  1 = 只改钱 ， 2 =修改需求流水 , 3 = 转换用户全部bonus，将需求和有效流水清0
     * @param $water_multiple_type 1 = 充值赠送Bonus流水倍数 bonus_pay_zs_water_multiple
     * @return void
     */
    public static function userEditBonus($uid,$num,$reason,$content = '',$type = 1,$water_multiple_type = 1){
        if(!$num)return 1;
        $userinfo = Db::table('userinfo')->select('bonus','get_bonus','package_id','channel')->where('uid',$uid)->first();
        if(!$userinfo)return 1;
        Db::beginTransaction();
//        $bonus = $type == 3 ? 0 : bcadd((string)$userinfo['bonus'],(string)$num,0);
        if($type == 2){
            $bonus_water_multiple_filed = self::$bonus_water_multiple[$water_multiple_type] ?? self::$bonus_water_multiple[1];
            $bonus_water_multiple = Common::getConfigValue($bonus_water_multiple_filed);
            $updateData = [
                'bonus' => Db::raw('bonus + '.$num),
                'need_bonus_score_water' => Db::raw('need_bonus_score_water + '.bcmul((string)$num,(string)$bonus_water_multiple,0)),
                'need_water_bonus' => Db::raw('need_water_bonus + '.$num),
            ];
            if($num > 0 && $reason != 3)$updateData['get_bonus'] = Db::raw('get_bonus + '.$num);
            $res = Db::table('userinfo')->where('uid',$uid)->update($updateData);

        }elseif($type == 3){
            $updateData = [
                'bonus' => Db::raw('bonus +'.$num),
//                'need_bonus_score_water' => 0,
//                'need_water_bonus' => 0,
//                'now_bonus_score_water' => 0,
            ];
            if($reason == 10)$updateData['bonus_cash'] = Db::raw('bonus_cash + '.bcsub('0',(string)$num,0)); //Bonus 转换
            $res = Db::table('userinfo')->where('uid',$uid)->update($updateData);
        }else{
            $updateData = [
                'bonus' => Db::raw('bonus + '.$num),
            ];
            if($num > 0 && $reason != 3)$updateData['get_bonus'] = Db::raw('get_bonus + '.$num);
            $res = Db::table('userinfo')
                ->where('uid',$uid)
                ->update($updateData);
        }


        if(!$res){
            Db::rollback();
            return 0;
        }
        $res = Db::table('bonus_'.date('Ymd'))
            ->insert([
                'uid' => $uid,
                'num' => $num,
                'total' => bcadd((string)$userinfo['bonus'],(string)$num,0),
                'reason' => $reason,
                'type' => $num > 0 ? 1 : 0,
                'content' => $content,
                'channel' => $userinfo['channel'],
                'package_id' => $userinfo['package_id'],
                'createtime' => time(),
            ]);
        if(!$res){
            Db::rollback();
            return 0;
        }
        Db::commit();
        return 1;
    }

    /**
     * @return void
     * @param  $uid 用户UID
     * @param  $total_give_score 赠送金额
     */
    public static function editUserTotalGiveScore($uid,$total_give_score){
        Db::beginTransaction();
        $res = Db::table('userinfo')->where('uid',$uid)->update(['total_give_score' => Db::raw('total_give_score+'.$total_give_score)]);
        if(!$res){
            Db::rollback();
            return 0;
        }

        $user_day = [
            'uid' => $uid.'|up',
            'total_give_score' =>  $total_give_score.'|raw-+',
        ];
        $user_day = new SqlModel($user_day);
        $res = $user_day->userDayDealWith();
        if(!$res){
            Db::rollback();
            return 0;
        }
        Db::commit();
        return 1 ;
    }
}