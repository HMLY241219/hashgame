<?php
declare(strict_types=1);
namespace App\Common;


use Hyperf\DbConnection\Db;
use function Hyperf\Config\config;




/**
 *  一些杂物处理
 */
class My
{

    private static array $not_device_id_array = ['00000000000000000000000000000000'];

    /**
     * @return void 获取用户设备、手机、邮箱、关联银行账户姓名、关联银行卡账户
     */
    public static function getUidinformation(int $uid){
        $share_strlog = Db::table('share_strlog')->select('phone','email','login_ip','device_id')->where('uid',$uid)->first();
        if(!$share_strlog){
            return ['code' => 201,'msg' => '用户不存在','data' => []];
        }
        $data = [];
        if($share_strlog['phone']){
            $data['phone']= $share_strlog['phone'];
        }
        if($share_strlog['email']){
            $data['email']= $share_strlog['email'];
        }


        if($share_strlog['device_id'] && !in_array($share_strlog['device_id'],self::$not_device_id_array)){
            $data['deviceid']= $share_strlog['device_id'];
        }

        if($share_strlog['login_ip']){
            $data['ip']= $share_strlog['login_ip'];
        }
        $user_withinfo = Db::table('user_withinfo')->selectRaw('bankaccount')->where(['uid' => $uid,'type' => 1])->first();
        if($user_withinfo['bankaccount']){
            $data['bankaccount']= $user_withinfo['bankaccount'];
        }
        return ['code' => 200,'msg' => '成功','data' => $data];
    }


    /**
     * @return void 获取用户的关联用户
     * @param $uid 用户的uid
     */
    public static function glUid($uid){


        $share_strlog = Db::table('share_strlog')->select(Db::raw('phone,email,ip,device_id'))->where('uid',$uid)->first();
        $where = '';
        if($share_strlog['phone']){
            $where .= "phone = '".$share_strlog['phone']."'";
        }

        if($share_strlog['email']){
            $where .= $where ? " OR email = '".$share_strlog['email']."'" : "email = '".$share_strlog['email']."'";
        }


        if($share_strlog['device_id'] && !in_array($share_strlog['device_id'],self::$not_device_id_array)){
            $where .= $where ? " OR device_id = '".$share_strlog['device_id']."'" : "device_id = '".$share_strlog['device_id']."'";
        }


        if($share_strlog['ip']){
            $where .= $where ? " OR ip = '".$share_strlog['ip']."'" : "ip = '".$share_strlog['ip']."'";
        }

        $share_strlog_array = [];
        if($where){
            $share_strlog_array = Db::table('share_strlog')->select('uid')->whereRaw($where)->groupBy('uid')->get()->toArray();
        }
        $user_withinfo = Db::table('user_withinfo')->select('account')->where('uid',$uid)->get()->toArray();

        $user_withinfo_array = [];
        if($user_withinfo){
            $user_withinfo_where = '';
            $count = 0;
            foreach ($user_withinfo as $v){
                $user_withinfo_where .= $count == 0 ? "account = '".$v['account']."'" : " OR account = '".$v['account']."'";
                $count ++ ;
            }
            $user_withinfo_array = Db::table('user_withinfo')->select('uid')->whereRaw($user_withinfo_where)->groupBy('uid')->get()->toArray();
        }

        $data = array_merge($share_strlog_array,$user_withinfo_array);
        $uid_array = [];
        foreach ($data as $l){
            $uid_array[] = $l['uid'];
        }
        $uid_array = array_unique($uid_array); //去重
        $index = array_search($uid, $uid_array); //返回自己uid的索引
        if ($index !== false) { // 如果找到了
            unset($uid_array[$index]); // 删除该元素
        }
        return $uid_array;
    }


    /**
     * @return void 获取用户的关联用户按照设备、手机、邮箱、关联银行账户姓名、关联银行卡账户
     * @param $uid 用户的uid
     */
    public static function glTypeUid($uid){

        $share_strlog = Db::table('share_strlog')->select(Db::raw('phone,email,ip,device_id'))->where('uid',$uid)->first();

        $phoneUid = []; //关联的电话
        if($share_strlog['phone']) $phoneUid = Db::table('share_strlog')->where([['uid','<>',$uid],['phone','=',$share_strlog['phone']]])->pluck('uid')->toArray();



        $emailUid = []; //关联的邮箱
        if($share_strlog['email']) $emailUid = Db::table('share_strlog')->where([['uid','<>',$uid],['email','=',$share_strlog['email']]])->pluck('uid')->toArray();



        $deviceUid = []; //关联的设备
        if($share_strlog['device_id'] && !in_array($share_strlog['device_id'],self::$not_device_id_array)){
            $share_strlog_array = Db::table('share_strlog')->selectRaw('uid')->where([['uid','<>',$uid],['device_id','=',$share_strlog['device_id']]])->get()->toArray();
            if($share_strlog_array){
                foreach ($share_strlog_array as $ll){
                    $deviceUid[] = $ll['uid'];
                }
            }
        }


        $ipUid = []; //关联的IP
        if($share_strlog['ip']) $ipUid = Db::table('share_strlog')->where([['uid','<>',$uid],['ip','=',$share_strlog['ip']]])->pluck('uid')->toArray();




        $user_withinfo = Db::table('user_withinfo')->select('account')->where('uid',$uid)->get()->toArray();
        $bankaccountUid = []; //关联的银行账号
        if($user_withinfo){
            $bankaccountWhere = [];
            foreach ($user_withinfo as $val){
                $bankaccountWhere[] = $val['account'];
            }

            $bankaccountWhere = array_unique($bankaccountWhere); //去重


            if($bankaccountWhere)$bankaccountUid = Db::table('user_withinfo')->where('uid','<>',$uid)->whereIn('account',$bankaccountWhere)->groupBy('uid')->pluck('uid')->toArray();

        }


        return [$phoneUid,$emailUid,$ipUid,$bankaccountUid,$bankaccountUid,$deviceUid];
    }




    /**
     * @return void 用户、付费留存
     * @param $type 1=用户留存，2=付费留存-再付费 ,3=付费留存-再登录
     * @param $start 开始的天数
     * @param $strtotime 查询表的时间戳
     */
    public static function statisticsRetained($type = 1,$start = 2,$strtotime = ''){
        $day_array15 = DateTime::getStartTimes(15,1,$start);  //获取近14日的时间戳
        $day30 = strtotime('00:00:00 -30 day');
        $day45 = strtotime('00:00:00 -45 day');
        switch ($type){
            case 1:
                $fun = 'setStatisticsRetained';
                break;
            case 2:
                $fun = 'statisticsRetentionPaid';
                break;
            default:
                $fun = 'setStatisticsRetainedLg';
        }
        foreach ($day_array15 as $k => $v){
            self::$fun($k+2,$v,$strtotime);
        }

        self::$fun(30,$day30,$strtotime);
        self::$fun(45,$day45,$strtotime);
    }

    /**
     * 用户留存数据
     * @param $fieldnum 修改的字段数字
     * @param $time 数据表time的时间
     * @param $strtotime 查询表的时间戳
     * @return void
     */
    public static function setStatisticsRetained($fieldnum,$time,$strtotime = ''){
        $strtotime = $strtotime ?: strtotime('-1 day');
        $field = 'day'.$fieldnum;
        $statistics_retaineduser = Db::table('statistics_retaineduser')->select('uids','package_id','channel')->where(['time' => $time])->get()->toArray();
        if(!$statistics_retaineduser){
            return ['code'=>201,'msg' => '暂无数据','data' =>[]];
        }
        $table = 'login_'.date('Ymd',$strtotime);
        $sql = "SHOW TABLES LIKE 'br_".$table."'";
        if(!Db::select($sql)){
            return ['code'=>201,'msg' => '数据表不存在','data' =>[]];
        }
        foreach ($statistics_retaineduser as $v){  //获取每日不同包不同渠道的用户
            $uidsArray = explode(',',$v['uids']);
            $count = Db::table($table)->whereIn('uid',$uidsArray)->count();

            if($count > 0){
                Db::table('statistics_retained')->where(['time'=> $time, 'package_id' => $v['package_id'], 'channel' => $v['channel']])
                    ->update([
                        $field => $count
                    ]);
            }
        }
    }



    /**
     * 付费留存-再付费数据
     * @param $fieldnum 修改的字段数字
     * @param $time 数据表time的时间
     * @return void
     */
    public static function statisticsRetentionPaid($fieldnum,$time,$strtotime = ''){
        $strtotime = $strtotime ?: strtotime('-1 day');
        $field = 'day'.$fieldnum;
        $payMoneyField = 'paymoney'.$fieldnum;
        $statistics_retainedpaiduser = Db::table('statistics_retainedpaiduser')->select(Db::raw('uids,package_id,channel'))->where(['time' => $time])->get()->toArray();
        if(!$statistics_retainedpaiduser){
            return ['code'=>201,'msg' => '暂无数据','data' =>[]];
        }
        $table = 'user_day_'.date('Ymd',$strtotime);
        $sql = "SHOW TABLES LIKE 'br_".$table."'";
        if(!Db::select($sql)){
            return ['code'=>201,'msg' => '数据表不存在','data' =>[]];
        }
        foreach ($statistics_retainedpaiduser as $v){  //获取每日不同包不同渠道的用户
            $uids = explode(',',$v['uids']);
            $day_table = Db::table($table)->select('total_pay_score')->whereIn('uid',$uids)->where([['total_pay_score','>',0]])->get()->toArray();

            if($day_table){
                $count = 0;
                $paymoney = 0;
                foreach ($day_table as $val){
                    $count = $count + 1;
                    $paymoney = bcadd($val['total_pay_score'],$paymoney,0);
                }
                Db::table('statistics_retentionpaid')->where(['time'=> $time, 'package_id' => $v['package_id'], 'channel' => $v['channel']])
                    ->update([
                        $field => $count,
                        $payMoneyField => $paymoney,
                    ]);
            }
        }
    }



    /**
     * 付费留存-再登录数据
     * @param $fieldnum 修改的字段数字
     * @param $time 数据表time的时间
     * @param $strtotime 查询表的时间戳
     * @return void
     */
    public static function setStatisticsRetainedLg($fieldnum,$time,$strtotime = ''){
        $strtotime = $strtotime ?: strtotime('-1 day');
        $field = 'day'.$fieldnum;
        $statistics_retainedpaiduser = Db::table('statistics_retainedpaiduser')->select('uids','package_id','channel')->where(['time' => $time])->get()->toArray();
        if(!$statistics_retainedpaiduser){
            return ['code'=>201,'msg' => '暂无数据','data' =>[]];
        }
        $table = 'login_'.date('Ymd',$strtotime);
        $sql = "SHOW TABLES LIKE 'br_".$table."'";
        if(!Db::select($sql)){
            return ['code'=>201,'msg' => '数据表不存在','data' =>[]];
        }
        foreach ($statistics_retainedpaiduser as $v){  //获取每日不同包不同渠道的用户
            $uids = explode(',',$v['uids']);
            $count = Db::table($table)->whereIn('uid',$uids)->count();

            if($count > 0){
                Db::table('statistics_retentionpaidlg')->where(['time'=> $time, 'package_id' => $v['package_id'], 'channel' => $v['channel']])
                    ->update([
                        $field => $count
                    ]);
            }
        }
    }

    /**
     * @return void 监控定时任务执行时间与次数
     * @param $fun_name 方法名
     * @param $id  $id > 0 修改 ， $id <= 0 添加
     *
     */
    public static function setFunGetTime($fun_name,$id = 0){
        if($id > 0){
            Db::table('fun_gettime')
                ->where('id',$id)
                ->update([
                    'time' => Db::raw(time().' - createtime')
                ]);
            return 0;
        }
        return Db::table('fun_gettime')->insertGetId([
            'fun_name' => $fun_name,
            'createtime' => time(),
        ]);

    }



    /**
     * 签到赠送、注册赠送、首充活动、破产活动、周卡、分享返利、免费转盘、客损活动、bonus转化cash金额等活动参与人数和赠送金额
     * @param $type 1= 定时任务 ， 2= 查询某天数据并返回
     * @param $where 自定义条件
     * @param $time 查询哪天的时间戳
     */
    public static function gameSendCash($type = 1,$where = [],$time = ''){
        $starttime = $time ?: strtotime('00:00:00 -1 day');
        $endtime = $starttime + 86400;

        $active_type = [ 0 => '新手活动', 3 => '100%充值活动' , 2 => '每日充值', 5 => '充值卡' , 6 => '破产活动'];
        $active_array = [0,3,2,5,6];
        $active_log = Db::table('active_log')->select(Db::raw('type,sum(zs_money) as zs_money,sum(zs_bonus) as zs_bonus,package_id,channel'))->where([['createtime','>=',$starttime],['createtime','<',$endtime]])->whereIn('type',$active_array)->where($where)->groupBy('type','package_id','channel')->get()->toArray();

        $active_log_data = [];
        if($active_log){
            foreach ($active_log as $v){
                //这里的数据不多，就直接循环查询就是了
                $userCount = Db::table('active_log')
                    ->where([
                        ['createtime', '>=', $starttime],
                        ['createtime', '<', $endtime],
                        ['type', '=',$v['type']],
                        ['package_id', '=',$v['package_id']],
                        ['channel', '=',$v['channel']],
                    ])
                    ->groupBy('uid')
                    ->count();

                $active_log_data[] = [
                    'time' => $starttime,
                    'title' => $active_type[$v['type']],
                    'money' => $v['zs_money'],
                    'bonus' => $v['zs_bonus'],
                    'package_id' => $v['package_id'],
                    'channel' => $v['channel'],
                    'num' => $userCount,
                ];
            }
        }


        $reson_config = [36 => 'VIP周奖励', 37 => 'VIP月奖励',9 => '绑定手机',11 => '注册赠送',10 => 'Bonus转化为Cash',34 => '签到赠送', 23 => '推广返利',24 => '每日任务',25 => '每周任务',18	=>'每日竞赛', 20	=>'自身流水返利', 21	=>'下家VIP等级提升给上家奖励', 22 => '自身VIP等级提升奖励' , 13 => '转盘奖励',38 => '兑换码奖励'];
        $reson = [36,37,9,11,10,34,23,24,25,18,20,21,22,13,38];
        $coinData = [];
        if(Db::select("SHOW TABLES LIKE 'br_coin_".date('Ymd',$starttime)."'")){
            $coin = Db::table('coin_'.date('Ymd',$starttime))->select(Db::raw('uid,sum(num) as num,reason,package_id,channel'))->where($where)->whereIn('reason',$reson)->where('num','>',0)->where('channel','>',0)->groupBy('reason','uid')->get()->toArray();
            if($coin){
                foreach ($coin as $value){
                    if(isset($coinData[$value['reason'].$value['package_id'].$value['channel']])){
                        $coinData[$value['reason'].$value['package_id'].$value['channel']]['money'] = $coinData[$value['reason'].$value['package_id'].$value['channel']]['money'] + $value['num'];
                        $coinData[$value['reason'].$value['package_id'].$value['channel']]['num'] = $coinData[$value['reason'].$value['package_id'].$value['channel']]['num'] + 1;
                    }else{
                        $coinData[$value['reason'].$value['package_id'].$value['channel']] = [
                            'time' => $starttime,
                            'title' => $reson_config[$value['reason']],
                            'money' => $value['num'],
                            'bonus' => 0,
                            'package_id' => $value['package_id'],
                            'channel' => $value['channel'],
                            'num' => 1,
                        ];
                    }
                }

            }
        }


        $reson_tpc_config = [36 => 'VIP周奖励', 37 => 'VIP月奖励',9 => '绑定手机',11 => '注册赠送',10 => 'Bonus转化为Cash',34 => '签到赠送', 23 => '推广返利',24 => '每日任务',25 => '每周任务',18	=>'每日竞赛', 20	=>'自身流水返利', 21	=>'下家VIP等级提升给上家奖励', 22 => '自身VIP等级提升奖励' , 13 => '转盘奖励',38 => '兑换码奖励'];
        $reson_tpc = [36,37,9,11,10,34,23,24,25,18,20,21,22,13,38];
        $tpc = Db::table('tpc')->select(Db::raw("uid,sum(score) as score,reason,package_id,channel"))->where([['time_stamp','>=',$starttime],['time_stamp','<',$endtime],['type','=',1],['score','>',0]])->whereIn('reason',$reson_tpc)->where($where)->groupBy('reason','uid')->get()->toArray();
        $tpcData = [];
        if($tpc){
            foreach ($tpc as $tpcValue){
                if($coinData && isset($coinData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']])){
                    $coinData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']]['bonus'] = $coinData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']]['bonus'] + $tpcValue['score'];
                } elseif(isset($tpcData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']])){
                    $tpcData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']]['bonus'] = $tpcData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']]['bonus'] + $tpcValue['score'];
                    $tpcData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']]['num'] = $tpcData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']]['num'] + 1;
                }else{
                    $tpcData[$tpcValue['reason'].$tpcValue['package_id'].$tpcValue['channel']] = [
                        'time' => $starttime,
                        'title' => $reson_tpc_config[$tpcValue['reason']],
                        'money' => 0,
                        'bonus' => $tpcValue['score'],
                        'package_id' => $tpcValue['package_id'],
                        'channel' => $tpcValue['channel'],
                        'num' => 1,
                    ];
                }

            }
        }

        $order_config = [0 => '普通充值赠送'];
        $order_config_array = [0];

        $order = Db::table('order')
            ->select(Db::raw('active_id,sum(zs_money) as zs_money,sum(zs_bonus) as zs_bonus,package_id,channel'))
            ->where([['finishtime','>=',$starttime],['finishtime','<',$endtime],['pay_status','=',1]])
            ->whereIn('active_id',$order_config_array)
            ->whereRaw('(zs_bonus + zs_money) > 0')
            ->where($where)
            ->groupBy('uid')
            ->get()->toArray();
        $orderData = [];
        if($order){
            foreach ($order as $orderVal){
                if(isset($orderData[$orderVal['active_id'].$orderVal['package_id'].$orderVal['channel']])){
                    $orderData[$orderVal['active_id'].$orderVal['package_id'].$orderVal['channel']]['money'] = $orderData[$orderVal['active_id'].$orderVal['package_id'].$orderVal['channel']]['money'] + $orderVal['zs_money'];
                    $orderData[$orderVal['active_id'].$orderVal['package_id'].$orderVal['channel']]['bonus'] = $orderData[$orderVal['active_id'].$orderVal['package_id'].$orderVal['channel']]['bonus'] + $orderVal['zs_bonus'];
                    $orderData[$orderVal['active_id'].$orderVal['package_id'].$orderVal['channel']]['num'] = $orderData[$orderVal['active_id'].$orderVal['package_id'].$orderVal['channel']]['num'] + 1;
                }else{
                    $orderData[$orderVal['active_id'].$orderVal['package_id'].$orderVal['channel']] = [
                        'time' => $starttime,
                        'title' => $order_config[$orderVal['active_id']],
                        'money' => $orderVal['zs_money'],
                        'bonus' =>$orderVal['zs_bonus'],
                        'package_id' => $orderVal['package_id'],
                        'channel' => $orderVal['channel'],
                        'num' => 1,
                    ];
                }
            }


        }


        $statistics_gamesendcash = array_merge($active_log_data,$coinData,$tpcData,$orderData);
        if($type == 1){
            Db::table('statistics_gamesendcash')->insert($statistics_gamesendcash);
            return '统计成功';
        }
        return $statistics_gamesendcash;
    }

}
