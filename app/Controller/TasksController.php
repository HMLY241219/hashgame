<?php
namespace App\Controller;

use App\Common\My;
use App\Common\DateTime;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use App\Common\SqlUnion;
use Psr\Log\LoggerInterface;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Common\Common;
#[Controller(prefix: 'Tasks')]
class TasksController extends AbstractController {

    #[Inject]
    protected SqlUnion $SqlUnion;

    #[Inject]
    protected LoggerInterface $logger;



    /**
     * 每日奖励
     * @return null
     *
     */
    #[RequestMapping(path: 'dailyRewardLog')]
    public function dailyRewardLog(){
        $date = date('Ymd',strtotime('-1 day'));
        $daily_reward_max_amount = Common::getConfigValue('daily_reward_max_amount'); //每个用户最大的返奖
        if(!$daily_reward_max_amount)return '活动暂未开启';
//        $user_day  = Db::table('slots_log_'.$date)
//            ->select('uid','vip','total_cash_water_score','channel','package_id')->where('total_cash_water_score','>',0)->get()->toArray();


        $user_day = Db::table("slots_log_$date as a")
            ->join('userinfo as b','a.uid','=','b.uid')
            ->selectRaw('br_a.uid,br_b.vip,sum(br_a.cashBetAmount) as total_cash_water_score,br_b.channel,br_b.package_id')
            ->where('a.cashBetAmount','>',0)
            ->where('a.terrace_name','zy')
            ->groupBy('a.uid')
            ->get()
            ->toArray();

        if(!$user_day)return '暂无数据返佣';
        $vipConfig = Db::table('vip')->pluck('daily_reward_bili','vip');
        if(!$vipConfig)return '暂无更多配置';

        $betrayal_log = Db::table('daily_reward_log')->where('dailyreward_start_date',$date)->first();
        if($betrayal_log)return '昨天奖励已统计';

        $dailyRewardData = []; //每日奖励数据
        foreach ($user_day as $value){
            //获取每日奖励配置
            $daily_reward_bili = $vipConfig[$value['vip']] ?? 0;
            if(!$daily_reward_bili)continue;

            $amount = bcmul((string)$daily_reward_bili,$value['total_cash_water_score'],0); //实际反水的金额
            if($amount > $daily_reward_max_amount)$amount = $daily_reward_max_amount;
            $dailyRewardData[] = [
                'amount' => $amount,
                'uid' => $value['uid'],
                'total_cash_water_score' => $value['total_cash_water_score'],
                'vip' => $value['vip'],
                'daily_reward_bili' => $daily_reward_bili, //用户的上周结算的反水比例
                'package_id' => $value['package_id'],
                'channel' => $value['channel'],
                'createtime' => time(), //创建时间
                'dailyreward_start_date' => $date
            ];
        }
        if($dailyRewardData)Db::table('daily_reward_log')->insert($dailyRewardData);

        return '成功';

    }


//    /**
//     * vip反水
//     * @return null
//     *
//     */
//    #[RequestMapping(path: 'vipBetrayal')]
//    public function vipBetrayal(){
//        //按照配置表每周反水
//        [$strattime,$endtime] = DateTime::startEndWeekTime(strtotime('-7 day'));
//        $dateArray = DateTime::createDateRange($strattime,$endtime,'Ymd');
//        $dateArray = array_reverse($dateArray);
//        $field = "uid,vip,cash_total_score,channel,package_id,total_cash_water_score";
//        $list = $this->SqlUnion->SubTableQueryList($dateArray,'user_day_',$field,[['total_cash_water_score','<>',0]]);
//        if(!$list)return '暂无数据返佣';
//        $cashbackConfig = Db::table('cashback_config')->get()->toArray();
//        if(!$cashbackConfig)return '暂无更多配置';
//        $newList  = $this->getBetrayalUid($list);
//
//        $betrayal_start_date = end($dateArray); //最开始结算的那天时间
//
//        $betrayal_log = Db::table('betrayal_log')->where('betrayal_start_date',$betrayal_start_date)->first();
//        if($betrayal_log)return '上周奖励已统计';
//
//        $betrayalUid = []; //反水数据
//        foreach ($newList as $uid => $value){
//            if($value['cash_total_score'] >= 0)continue;
//            //获取反水配置
//            $cashbackData = [];
//            foreach ($cashbackConfig as $v){ //获取反水配置
//                if($v['minwater'] <= $value['total_cash_water_score'] && $v['maxwater'] >= $value['total_cash_water_score']){
//                    $cashbackData = $v;
//                    break;
//                }
//            }
//            if(!$cashbackData)continue;
//            $total_score =bcsub('0', (string)$value['cash_total_score'],0); //总共输的金额
//            $amount = bcmul((string)$cashbackData['bili'],$total_score,0); //实际反水的金额
//            if($amount > $cashbackData['maxamount'])$amount = $cashbackData['maxamount'];
//            $betrayalUid[] = [
//                'amount' => $amount,
//                'uid' => $uid,
//                'total_cash_water_score' => $value['total_cash_water_score'],
//                'cash_total_score' => $value['cash_total_score'], //用户上周结算输的金额
//                'vip' => $value['vip'], //用户的上周结算VIP等级
//                'betrayal_bili' => $cashbackData['bili'], //用户的上周结算的反水比例
//                'package_id' => $value['package_id'],
//                'channel' => $value['channel'],
//                'createtime' => time(), //创建时间
//                'betrayal_start_date' => $betrayal_start_date
//            ];
//        }
//        if($betrayalUid)Db::table('betrayal_log')->insert($betrayalUid);
//
//        return '成功';
//
//        //每周按照VIP等级处理
////        $vipbetrayal_max_amount = Common::getConfigValue('vipbetrayal_max_amount'); //每次最大反水金额
////        [$strattime,$endtime] = DateTime::startEndWeekTime(strtotime('-7 day'));
////        $dateArray = DateTime::createDateRange($strattime,$endtime,'Ymd');
////        $dateArray = array_reverse($dateArray);
////        $field = "uid,cash_total_score,vip,channel,package_id";
////        $list = $this->SqlUnion->SubTableQueryList($dateArray,'user_day_',$field,[['cash_total_score','<>',0],['vip','>',0]]);
////        if(!$list)return '暂无数据返佣';
////        $vipConfig = Db::table('vip')->pluck('betrayal_bili','vip');
////        if(!$vipConfig)return '暂无更多配置';
////        $newList  = $this->getBetrayalUid($list);
////
////        $betrayal_start_date = end($dateArray); //最开始结算的那天时间
////
////        $betrayal_log = Db::table('betrayal_log')->where('betrayal_start_date',$betrayal_start_date)->first();
////        if($betrayal_log)return '上周奖励已统计';
////
////        $betrayalUid = []; //反水数据
////        foreach ($newList as $uid => $value){
////            if($value['cash_total_score'] >= 0)continue;
////            //获取返水比例
////            if(!isset($vipConfig[$value['vip']]) || $vipConfig[$value['vip']] <= 0)continue;
////            $total_score =bcsub('0', $value['cash_total_score'],0); //总共输的金额
////            $amount = bcmul($vipConfig[$value['vip']],$total_score,0); //实际反水的金额
////            if($amount > $vipbetrayal_max_amount)$amount = $vipbetrayal_max_amount;
////            $betrayalUid[] = [
////                'amount' => $amount,
////                'uid' => $uid,
////                'cash_total_score' => $value['cash_total_score'], //用户上周结算输的金额
////                'vip' => $value['vip'], //用户的上周结算VIP等级
////                'betrayal_bili' => $vipConfig[$value['vip']], //用户的上周结算的反水比例
////                'package_id' => $value['package_id'],
////                'channel' => $value['channel'],
////                'createtime' => time(), //创建时间
////                'betrayal_start_date' => $betrayal_start_date
////            ];
////        }
////        if($betrayalUid)Db::table('betrayal_log')->insert($betrayalUid);
////        return '成功';
//
//        //每天按照VIP等级处理
////        $vipbetrayal_max_amount = Common::getConfigValue('vipbetrayal_max_amount'); //每次最大反水金额
////        $time = date('Ymd',strtotime('-1 day'));
////        $list = Db::table('user_day_'.$time)
////            ->select('uid','cash_total_score','vip','channel','package_id')
////            ->where([['cash_total_score','<',0],['vip','>',0]])
////            ->get()
////            ->toArray();
////
////        if(!$list)return '暂无数据返佣';
////        $vipConfig = Db::table('vip')->pluck('betrayal_bili','vip');
////        if(!$vipConfig)return '暂无更多配置';
////        $newList  = $this->getBetrayalUid($list);
////
////        $betrayalUid = []; //反水数据
////        foreach ($newList as $uid => $value){
////            if($value['cash_total_score'] >= 0)continue;
////            //获取返水比例
////            if(!isset($vipConfig[$value['vip']]) || $vipConfig[$value['vip']] <= 0)continue;
////            $total_score =bcsub(0, $value['cash_total_score'],0); //总共输的金额
////            $amount = bcmul($vipConfig[$value['vip']],$total_score,0); //实际反水的金额
////            if($amount > $vipbetrayal_max_amount)$amount = $vipbetrayal_max_amount;
////            $betrayalUid[] = [
////                'amount' => $amount,
////                'uid' => $uid,
////                'cash_total_score' => $value['cash_total_score'], //用户昨天结算输的金额
////                'vip' => $value['vip'], //用户的昨天结算VIP等级
////                'betrayal_bili' => $vipConfig[$value['vip']], //用户的昨天结算的反水比例
////                'package_id' => $value['package_id'],
////                'channel' => $value['channel'],
////                'createtime' => time(), //创建时间
////                'betrayal_start_date' => $time
////            ];
////        }
////        if($betrayalUid)Db::table('betrayal_log')->insert($betrayalUid);
////
////        return '成功';
//    }




    /**
     * vip反水
     * @return null
     *
     */
    #[RequestMapping(path: 'vipBetrayal')]
    public function vipBetrayal(){
        //按照配置表每周反水
        $time = date('Ymd',strtotime('-1 day'));
        $list = Db::table('user_day_'.$time)
            ->select('uid','cash_total_score','vip','channel','package_id','total_cash_water_score')
            ->where([['cash_total_score','<',0],['vip','>',0]])
            ->get()
            ->toArray();
        if(!$list)return '暂无数据返佣';
        $cashbackConfig = Db::table('cashback_config')->get()->toArray();
        if(!$cashbackConfig)return '暂无更多配置';
        $newList  = $this->getBetrayalUid($list);

        $betrayal_start_date = $time; //最开始结算的那天时间

        $betrayal_log = Db::table('betrayal_log')->where('betrayal_start_date',$betrayal_start_date)->first();
        if($betrayal_log)return '昨日奖励已统计';

        $betrayalUid = []; //反水数据
        foreach ($newList as $uid => $value){
            if($value['cash_total_score'] >= 0)continue;
            //获取反水配置
            $cashbackData = [];
            foreach ($cashbackConfig as $v){ //获取反水配置
                if($v['minwater'] <= $value['total_cash_water_score'] && $v['maxwater'] >= $value['total_cash_water_score']){
                    $cashbackData = $v;
                    break;
                }
            }
            if(!$cashbackData)continue;
            $total_score =bcsub('0', (string)$value['cash_total_score'],0); //总共输的金额
            $amount = bcmul((string)$cashbackData['bili'],$total_score,0); //实际反水的金额
            if($amount > $cashbackData['maxamount'])$amount = $cashbackData['maxamount'];
            $betrayalUid[] = [
                'amount' => $amount,
                'uid' => $uid,
                'total_cash_water_score' => $value['total_cash_water_score'],
                'cash_total_score' => $value['cash_total_score'], //用户上周结算输的金额
                'vip' => $value['vip'], //用户的上周结算VIP等级
                'betrayal_bili' => $cashbackData['bili'], //用户的上周结算的反水比例
                'package_id' => $value['package_id'],
                'channel' => $value['channel'],
                'createtime' => time(), //创建时间
                'betrayal_start_date' => $betrayal_start_date
            ];
        }
        if($betrayalUid)Db::table('betrayal_log')->insert($betrayalUid);

        return '成功';

    }

    /**
     * @return void 整理反水用户的数据
     */

    private function getBetrayalUid($list){
        $newList = [];
        foreach ($list as $v){
            if(isset($newList[$v['uid']])){
                $newList[$v['uid']]['cash_total_score'] += $v['cash_total_score'] ;
                $newList[$v['uid']]['total_cash_water_score'] += $v['total_cash_water_score'] ;//按照配置表每周反水
            }else{
                $newList[$v['uid']] = [
                    'cash_total_score' => $v['cash_total_score'],
                    'vip' => $v['vip'],
                    'channel' => $v['channel'],
                    'package_id' => $v['package_id'],
                    'total_cash_water_score' => $v['total_cash_water_score'],//按照配置表每周反水
                ];
            }
        }
        return $newList;
    }



    /**
     * 每日统计下级返佣数据
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    #[RequestMapping(path: 'dayCommission')]
    public function dayCommission(){
        //$date = date('Ymd',strtotime('-1 day'));
        $date = date('Ymd');
        $user_day = Db::table('user_day_' . $date)
            ->where('total_cash_water_score','>',0)
            ->orWhere('total_bonus_water_score','>',0)
            ->selectRaw('uid,total_cash_water_score,total_bonus_water_score')
            ->get()->toArray();
        if(!$user_day) return '暂无需要返利的数据返利json';

        $commissionlog_data = []; //返利记录
        $uidArray = []; //后期需要处理的每个返利用户加多少钱
        $bill_list = Db::table('commission_bill')->get()->toArray();

        foreach ($user_day as $v){
            //返利投注金额
            $commission_money = bcadd($v['total_cash_water_score'], $v['total_bonus_water_score'],0);
            if($commission_money <= 0)continue;

            $teamlevel = Db::table('teamlevel')->where('uid', $v['uid'])->where('level','>',0)->select('puid','level')->groupBy('puid')->orderBy('level','asc')->get()->toArray();
            if(!$teamlevel)continue;

            $yf_bili = 0; //以分的比例
            foreach ($teamlevel as $value){
                //团队流水
                $water = Db::table('user_water')->where('uid',$value['puid'])->first();
                if (!$water) continue;
                $team_water = bcadd($water['total_cash_water_score'], $water['total_bonus_water_score']);
                //返利比例
                $bill = 0;
                foreach ($bill_list as $kk=>$item) {
                    if ($item['total_amount'] > $team_water) {
                        $bill = bcdiv($bill_list[$kk-1]['bili'],10000,4);
                        break;
                    }
                }
                if($bill <= 0)continue;

                $user_bili = bcsub($bill, $yf_bili, 4);
                if($user_bili <= 0)break;//如果上级莫个用户没有分的了，那直接break，上面的肯定都没法分了
                //实际返利金额
                $really_money = bcmul($user_bili, $commission_money, 0);
                $commissionlog_data[] = [
                    'uid' => $value['puid'],
                    'char_uid' => $v['uid'],
                    'commission_money' => $commission_money,
                    'really_money' => $really_money,
                    'bili' => bcmul($user_bili,10000),
                    'level' => $value['level'],
                    'createtime' => time(),
                ];

                $uidArray[$value['puid']] = isset($uidArray[$value['puid']]) ? bcadd($uidArray[$value['puid']],$really_money,0) : $really_money;
                //处理已分的比例
                $yf_bili = bcadd($yf_bili,$user_bili,4);
            }
        }

        try {
            Db::beginTransaction();
            //返佣记录
            if($commissionlog_data)Db::table('commissionlog')->insert($commissionlog_data);
            //上级数据修改
            if($uidArray)foreach ($uidArray as $uid => $amount){
                Db::table('userinfo')->where('uid',$uid)->update([
                    'commission' => Db::raw('commission + '.$amount)
                ]);
                //User::userEditCoin($uid, $amount, 9, '下级返佣');
                //User::editUserTotalGiveScore($uid, $amount);
            }

            Db::commit();
            return '每日返佣成功';

        }catch (\Throwable $exception){
            Db::rollback();
            $this->logger->error("错误文件===" . $exception->getFile() . '===错误行数===' . $exception->getLine() . '===错误信息===' . $exception->getMessage());
            return '每日返佣失败';
        }
    }


    /**
     * 实时计算每日用户获得返佣
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    #[RequestMapping(path: 'realCommission')]
    public function realCommission(){
        //$date = date('Ymd',strtotime('-1 day'));
        $date = date('Ymd');
        $user_day = Db::table('user_day_' . $date)
            ->where('total_cash_water_score','>',0)
            ->orWhere('total_bonus_water_score','>', 0)
            ->selectRaw('uid,puid,total_cash_water_score,total_bonus_water_score')
            ->get()->toArray();
        if(!$user_day) return '暂无需要返利的数据返利（实时）';

        $uidArray = []; //后期需要处理的每个返利用户加多少钱
        $bill_list = Db::table('commission_bill')->get()->toArray();

        foreach ($user_day as $v){
            //返利投注金额
            $commission_money = bcadd($v['total_cash_water_score'], $v['total_bonus_water_score'],0);
            if($commission_money <= 0)continue;

            $teamlevel = Db::table('teamlevel')->where('uid', $v['uid'])->where('level','>',0)->select('puid','level')->groupBy('puid')->orderBy('level')->get()->toArray();
            if(!$teamlevel)continue;

            $yf_bili = 0; //以分的比例
            foreach ($teamlevel as $value){
                //
                $uidArray[$value['puid']]['bet'] = isset($uidArray[$value['puid']]['bet']) ? bcadd($uidArray[$value['puid']]['bet'], $commission_money, 0) : $commission_money;
                $uidArray[$value['puid']]['bet_num'] = isset($uidArray[$value['puid']]['bet_num']) ? bcadd($uidArray[$value['puid']]['bet_num'], 1, 0) : 1;

                //团队流水
                $water = Db::table('user_water')->where('uid',$value['puid'])->first();
                if (!$water) continue;
                $team_water = bcadd($water['total_cash_water_score'], $water['total_bonus_water_score']);
                //返利比例
                $bill = 0;
                $bill_level = 1;
                foreach ($bill_list as $kk=>$item) {
                    if ($item['total_amount'] > $team_water) {
                        $bill = bcdiv($bill_list[$kk-1]['bili'],10000,4);
                        $bill_level = $bill_list[$kk-1]['id'];
                        break;
                    }
                }
                if($bill <= 0)continue;

                $user_bili = bcsub($bill, $yf_bili, 4);
                if($user_bili <= 0)break;//如果上级莫个用户没有分的了，那直接break，上面的肯定都没法分了
                //实际返利金额
                $really_money = bcmul($user_bili, $commission_money, 0);

                $uidArray[$value['puid']]['really_money'] = isset($uidArray[$value['puid']]['really_money']) ? bcadd($uidArray[$value['puid']]['really_money'],$really_money,0) : $really_money;
                $uidArray[$value['puid']]['bill_level'] = $bill_level;
                //处理已分的比例
                $yf_bili = bcadd($yf_bili,$user_bili,4);
            }

            //计算投注
            /*if ($v['puid'] > 0) {
                $uidArray[$v['puid']]['bet'] = isset($uidArray[$v['puid']]['bet']) ? bcadd($uidArray[$v['puid']]['bet'], $commission_money, 0) : $commission_money;
                $uidArray[$v['puid']]['bet_num'] = isset($uidArray[$v['puid']]['bet_num']) ? bcadd($uidArray[$v['puid']]['bet_num'], 1, 0) : 1;
            }*/
        }

        $day_time = strtotime(date('Y-m-d'));
        //统计注册
        $day_teamlevel = Db::table('teamlevel')->where('level','>',0)->where('createtime','>',$day_time)->get()->toArray();
        if (!empty($day_teamlevel)){
            foreach ($day_teamlevel as $dtk=>$dtv){
                if (isset($uidArray[$dtv['puid']]['num'])){
                    $uidArray[$dtv['puid']]['num'] += 1;
                }else{
                    $uidArray[$dtv['puid']]['num'] = 1;
                }
            }
        }

        try {
            Db::beginTransaction();

            //上级数据修改
            if($uidArray)foreach ($uidArray as $uid => $uav){
                $commission_day = Db::table('commission_day')->where('uid',$uid)->whereDay('date',date('d'))->first();
                if (empty($commission_day)){
                    Db::table('commission_day')->insert([
                        'uid' => $uid,
                        'date' => date('Y-m-d'),
                        'commission' => isset($uav['really_money']) ? $uav['really_money'] : 0,
                        'bet' => isset($uav['bet']) ? $uav['bet'] : 0,
                        'bet_num' => isset($uav['bet_num']) ? $uav['bet_num'] : 0,
                        'bill_level' => isset($uav['bill_level']) ? $uav['bill_level'] : 0,
                        'num' => isset($uav['num']) ? $uav['num'] : 0,
                    ]);
                }else{
                    Db::table('commission_day')->where('id',$commission_day['id'])->update([
                        'commission' => isset($uav['really_money']) ? $uav['really_money'] : 0,
                        'bet' => isset($uav['bet']) ? $uav['bet'] : 0,
                        'bet_num' => isset($uav['bet_num']) ? $uav['bet_num'] : 0,
                        'bill_level' => isset($uav['bill_level']) ? $uav['bill_level'] : 0,
                        'num' => isset($uav['num']) ? $uav['num'] : 0,
                    ]);
                }
                //dd($commission_day);
            }

            Db::commit();
            return '每日返佣成功（实时）';

        }catch (\Throwable $exception){
            Db::rollback();
            $this->logger->error("错误文件===" . $exception->getFile() . '===错误行数===' . $exception->getLine() . '===错误信息===' . $exception->getMessage());
            return '每日返佣失败（实时）';
        }
    }

    /**
     * 实时计算每日用户获得返佣
     * Vip周奖励
     */
    #[RequestMapping(path: 'VipWeekAmountSend')]
    public function VipWeekAmountSend(){
        $vipArray = Db::table('vip')->where('vip','>',1)->pluck('week_amount','vip');
        $data = [];
        Db::table('userinfo')->select('uid','vip')->where('vip','>',1)->orderBy('uid')->chunk(1000, function ($users) use(&$data,$vipArray){
            foreach ($users as $user) {
                if(!isset($vipArray[$user['vip']]) || $vipArray[$user['vip']] <= 0)continue;
                $data[] = [
                    'uid' => $user['uid'],
                    'vip' => $user['vip'],
                    'type' => 2,
                    'amount' => $vipArray[$user['vip']],
                ];
            }
        });
        if(!$data) return '暂无奖励';
        $vip_log = $this->getUserOrderMoney($data);
        if(!$vip_log) return '暂无奖励';
        Db::table('vip_log')->insert($vip_log);

        return '周奖励完成';
    }

    /**
     * 实时计算每日用户获得返佣
     * Vip月奖励
     */
    #[RequestMapping(path: 'VipMonthAmountSend')]
    public function VipMonthAmountSend(){
        $vipArray = Db::table('vip')->where('vip','>',1)->pluck('month_amount','vip');
        $data = [];
        Db::table('userinfo')->select('uid','vip')->where('vip','>',1)->orderBy('uid')->chunk(1000, function ($users) use(&$data,$vipArray){
            foreach ($users as $user) {
                if(!isset($vipArray[$user['vip']]) || $vipArray[$user['vip']] <= 0)continue;
                $data[] = [
                    'uid' => $user['uid'],
                    'vip' => $user['vip'],
                    'type' => 3,
                    'amount' => $vipArray[$user['vip']],
                ];
            }
        });
        $vip_log = $this->getUserOrderMoney($data,2);
        if(!$vip_log) return '暂无奖励';
        Db::table('vip_log')->insert($vip_log);
        return '月奖励完成';
    }

    /**
     * 获取Vi配置
     * @return array
     */
    private function getVipConfig():array{
        $vip = Db::table('vip')
            ->where('vip', '>', 1)
            ->select(['vip', 'week_order_money', 'month_order_money'])
            ->get()
            ->toArray();
        $vipArray = [];
        foreach ($vip as $v){
            $vipArray[$v['vip']] = [
                'week_order_money' => $v['week_order_money'],
                'month_order_money' => $v['month_order_money'],
            ];
        }
        return $vipArray;
    }

    /**
     * @param $data array
     * @param $type int 类型:1=周奖励，2=月奖励
     * @return array
     */
    private function getUserOrderMoney(array $data,int $type = 1):array{
        $filed = $type == 1 ? 'week_order_money' : 'month_order_money';
        $min_order_money = Common::getConfigValue($filed); //最低充值金额
        if($min_order_money <= 0)return $data;
        $where = $type == 1 ? [['createtime','>=',(strtotime('00:00:00') - (30 * 86400))]] : [['createtime','>=',(strtotime('00:00:00') - (90 * 86400))]];
        $uidArray = array_column($data, 'uid');

        $order = Db::table('order')->select(Db::raw('sum(price) as allprice,uid'))->whereIn('uid',$uidArray)->where('pay_status',1)->where($where)->groupBy('uid')->get()->toArray();
        if(!$order)return $data;
        $orderArray = [];
        foreach ($order as $value)$orderArray[$value['uid']] = $value['allprice'];
        $newData = [];
        foreach ($data as $v){
            if(!isset($orderArray[$v['uid']]))continue;
            if($orderArray[$v['uid']] >= $min_order_money){
                $newData[] = [
                    'uid' => $v['uid'],
                    'vip' => $v['vip'],
                    'type' => $v['type'],
                    'order_money' => $orderArray[$v['uid']],
                    'amount' => $v['amount'],
                    'createtime' => time(),
                ];
            }
        }

        return $newData;

    }



    /**
     * @return void 定时修改用户的游戏天数
     */
    #[RequestMapping(path: 'totalGameDay')]
    public function total_game_day(){
        $table = 'user_day_'.date('Ymd',strtotime('-1 day'));
        $userday = Db::table($table)
            ->where('total_game_num','>',0)
            ->pluck('uid')
            ->toArray();

        if(!$userday) return '暂时不需要跟新天数';


        Db::table('userinfo')->whereIn('uid',$userday)->increment('total_game_day');

        return '用户的游戏天数跟新成功';
    }

    /**
     * @return void 用户、付费留存
     * @param $type 1=用户留存，2=付费留存-再付费 ,3=付费留存-再登录
     * @param $start 开始的天数
     */
    #[RequestMapping(path: 'statisticsRetained')]
    public function statisticsRetained(){
        $data = $this->request->all();
        $time = $data['time'] ?? '';
        My::statisticsRetained($data['type'],2,$time);
        return '统计成功';
    }

    /**
     * http://1.13.81.132:5009/api/Tasks/GenerateSqlTable
     * 生成数据表
     * @return void
     */
    #[RequestMapping(path: 'GenerateSqlTable')]
    public function GenerateSqlTable(){
        $data = $this->request->all();
        $date = $data['date'] ?? '';
        $date = $date ?: date('Y-m-d');
        $nextWeekStart = strtotime('next Monday', strtotime($date)); // 下周开始的天
        $nextWeekEnd = strtotime('next Sunday', $nextWeekStart); //下周结束的天
        $timeArray = dateTime::createDateRange($nextWeekStart,$nextWeekEnd,'Ymd');
        foreach ($timeArray as $v){
            SqlController::getLoginTable($v);
            SqlController::getRegistTable($v);
            SqlController::getCoinTable($v);
            SqlController::getBonusTable($v);
            SqlController::getUserDayTable($v);
            SqlController::getSlotsLogTable($v);
            SqlController::getBlockGameBetTable($v);
            SqlController::getBlockGamePeriodsTable($v);
        }
        return '生成完成';

    }
}





