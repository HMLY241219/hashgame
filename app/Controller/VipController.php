<?php

declare(strict_types=1);
namespace App\Controller;


use App\Common\User;
use Hyperf\DbConnection\Db;
use App\Common\DateTime;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Coroutine\Exception\ParallelExecutionException;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Parallel;
use function Hyperf\Support\value;
use App\Common\SqlUnion;
use App\Common\Common;

#[Controller(prefix:"Vip")]
class VipController extends AbstractController {

    #[Inject]
    protected SqlUnion $SqlUnion;

    private $receiveHours = '06:00:00'; //每周领取第一天的反水的时间

    private $receiveDayNum = 7; //每周领取反水的有效天数

    /**
     * 获取VIP配置
     * @return null
     *
     */
    #[RequestMapping(path: "config", methods: "get,post")]
    public function config(){
        $vip = Db::connection('readConfig')
            ->table('vip')
            ->select(Db::raw('(sj_amount + sj_bonus) as sj_amount,(week_amount + week_bonus) as week_amount,(month_amount + month_bonus) as month_amount,
            id,vip,need_water,betrayal_bili,day_withdraw_num,need_pay_price,day_withdraw_money,withdraw_max_money'))
            ->orderBy('vip','desc')
            ->get()
            ->toArray();
        return $this->ReturnJson->successFul(200,$vip);
    }



    /**
     * 获取Cash配置
     * @return null
     *
     */
    #[RequestMapping(path: "cashBackConfig", methods: "get,post")]
    public function cashBackConfig(){
        $cashback_config = Db::connection('readConfig')
            ->table('cashback_config')
            ->select('bili','minwater','maxamount')
            ->orderBy('bili')
            ->get()
            ->toArray();
        return $this->ReturnJson->successFul(200,$cashback_config);
    }



    /**
     * 获取新版Cash配置
     * @return null
     *
     */
    #[RequestMapping(path: "cashNewBackConfig", methods: "get,post")]
    public function cashNewBackConfig(){
        $cashback_config = Db::connection('readConfig')
            ->table('new_week_cashback_config')
            ->select('bili','minwater','maxamount')
            ->orderBy('bili')
            ->get()
            ->toArray();
        return $this->ReturnJson->successFul(200,$cashback_config);
    }

    /**
     * 新版本获取用户返水首页
     * @return void
     */
    #[RequestMapping(path: "getUserBetrayalIndex", methods: "get,post")]
    public function getUserBetrayalIndex(){
        $uid = $this->request->post('uid');
        $type = $this->request->post('type') ?? 2; //类型:1=自研.2=三方
        $type = $type == 1 ? 1 : 2;
        $cashback_type = Common::getConfigValue('cashback_type');//1= 是新的反水需求和定时任务 ，2=  老版本反水定时任务,这个值判断客户端调取老的反水还是新的反水接口
        if($cashback_type == 1){ //新版
            return $this->getNewUserBetrayalIndex($uid,$type);
        }else{ //老版本
            if($type == 1) return $this->ReturnJson->successFul(200,[]);
            return $this->getOldUserBetrayalIndex($uid);
        }
    }


    /**
     * 新版本获取用户返水首页
     * @return void
     */

    public function getNewUserBetrayalIndex($uid,$type = 2){

        $userinfo = Db::table('userinfo')->selectRaw('vip,(total_cash_water_score + total_bonus_water_score) as total_water_score,total_pay_score')->where('uid',$uid)->first(); //总Cash流水
        if($userinfo){
            $data['total_water_score'] = $userinfo['total_water_score'];
            $data['vip'] = $userinfo['vip'];
            $data['total_pay_score'] = $userinfo['total_pay_score'];
        }else{
            $data['total_water_score'] = 0;
            $data['vip'] = 0;
            $data['total_pay_score'] = 0;
        }



        //每天发奖逻辑
        $data['settlement_start_date'] = date('Y-m-d H:i:s',strtotime('-1 day 00:00:00')); //昨天结算的开始时间
        $data['settlement_end_date'] = date('Y-m-d H:i:s',strtotime($data['settlement_start_date']) + 86399); //昨天结算的的结束时间
        $data['receive_start_date'] = date('Y-m-d').' '.$this->receiveHours; //领取的开始时间
        $data['receive_end_date'] = date('Y-m-d H:i:s',strtotime('00:00:00') + 86399);//领取的结束时间
        $data['now_time'] = time(); //当前的时间戳
        $data['receive_end_time'] = strtotime($data['receive_start_date']) + 86400; //本周发奖预计领取的开始时间


        if($type == 1){
            $zyCashbackConfig = $this->zyCashbackConfig();
        }

        //判断是否到领取的时间
        if(time() < strtotime($data['receive_start_date'])){
            $data['amount'] = -1;//-1代表问号
        }else{ //计算昨日领取的金额
            $new_betrayal_log = Db::table('new_betrayal_log')->selectRaw('(cash_amount + bonus_amount) as amount,status')->where([['uid','=',$uid],['type','=',$type],['betrayal_start_date','=',date('Ymd',strtotime($data['settlement_start_date']))]])->first();
            if(!$new_betrayal_log){
                $new_betrayal_log['amount'] = 0;
                $new_betrayal_log['status'] = 0;
            }
            if($new_betrayal_log['status'] == 1){ //判断是否已经领取过
                $data['amount'] = -1;
            }elseif ($type == 1){ //未领取自研时
                $data['amount'] = $new_betrayal_log['amount'];
                if(!$data['amount'] || $data['amount'] <= 0){
                    [$zs_cash_basis_value,$zs_bonus_basis_value] = $this->getDefaultZyAmount($zyCashbackConfig,$uid,2);
                    $data['amount'] = (int)bcadd((string)$zs_cash_basis_value,(string)$zs_bonus_basis_value,0);
                    if($data['amount'] <= 0) $data['amount'] = -1;
                }
            }else{ //未领取时
                $data['amount'] = $new_betrayal_log['amount'] ? (int)$new_betrayal_log['amount'] : -1;
            }
        }

        //今日领取的金额
        if($type == 1){ //自研
            $data['week_amount'] = Db::table('new_betrayal_log')->selectRaw('(cash_amount + bonus_amount) as amount,total_cash_water_score')->where([['uid','=',$uid],['type','=',$type],['betrayal_start_date','=',date('Ymd')],['status','=',0]])->value(Db::raw('(cash_amount + bonus_amount) as amount'));
            if(!$data['week_amount'] || $data['week_amount'] <= 0){
                [$zs_cash_basis_value,$zs_bonus_basis_value] = $this->getDefaultZyAmount($zyCashbackConfig,$uid,1,$userinfo['total_pay_score']);
                $data['week_amount'] = (int)bcadd((string)$zs_cash_basis_value,(string)$zs_bonus_basis_value,0);
            }

        }else{//三方
            //今日领取的金额
            $new_betrayal_log = Db::table('new_betrayal_log')->selectRaw('(cash_amount + bonus_amount) as amount,total_cash_water_score')->where([['uid','=',$uid],['type','=',$type],['betrayal_start_date','=',date('Ymd')],['status','=',0]])->first();
            if($new_betrayal_log){
                $data['week_amount'] = $new_betrayal_log['amount'];
                $data['total_cash_water_score'] = $new_betrayal_log['total_cash_water_score'];
            }else{
                $data['week_amount'] = 0;
                $data['total_cash_water_score'] = 0;
            }
        }

        return $this->ReturnJson->successFul(200,$data);

    }

    /**
     * 获取自研游戏的返水配置
     * @return mixed
     */
    private function zyCashbackConfig(){
        return Db::table('zy_cashback_config')->get()->toArray();
    }


    /**
     * 获取默认自研反水金额
     * @param array $zyCashbackConfig 用户UID
     * @param int $uid 用户UID
     * @param int $tasksType   1= 统计今天实时数据,2=昨日数据
     * @param int $total_pay_score 总充值
     * @return void
     */
    private function getDefaultZyAmount(array $zyCashbackConfig,int $uid, int $tasksType = 1,int $total_pay_score = 0){
        if($tasksType == 2){
            $total_pay_score = Db::table('order')->where([['uid','=',$uid],['createtime','<',strtotime('00:00:00')],['pay_status','=',1]])->sum('price');
        }
        $zs_cash_basis_value = 0;
        $zs_bonus_basis_value = 0;
        foreach ($zyCashbackConfig as $v){ //获取反水配置
            if($v['minmoney'] <= $total_pay_score && $v['maxmoney'] >=$total_pay_score){
                $zs_cash_basis_value = $v['zs_cash'];
                $zs_bonus_basis_value = $v['zs_bonus'];
                break;
            }
        }
        return [$zs_cash_basis_value,$zs_bonus_basis_value];
    }


    /**
     * 老版本获取用户返水首页
     * @return void
     */

    public function getOldUserBetrayalIndex($uid){

        $userinfo = Db::table('userinfo')->selectRaw('vip,(total_cash_water_score + total_bonus_water_score) as total_water_score,total_pay_score')->where('uid',$uid)->first(); //总Cash流水

        if($userinfo){
            $data = [
                'total_water_score' => $userinfo['total_water_score'] ?: 0,
                'vip' => $userinfo['vip'] ?: 0,
                'total_pay_score' => $userinfo['total_pay_score'] ?: 0,
            ];
        }else{
            $data = [
                'total_water_score' => 0,
                'vip' => 0,
                'total_pay_score' => 0,
            ];
        }

        //每天发奖逻辑
        $data['settlement_start_date'] = date('Y-m-d H:i:s',strtotime('-1 day 00:00:00')); //昨天结算的开始时间
        $data['settlement_end_date'] = date('Y-m-d H:i:s',strtotime($data['settlement_start_date']) + 86399); //昨天结算的的结束时间
        $data['receive_start_date'] = date('Y-m-d').' '.$this->receiveHours; //领取的开始时间
        $data['receive_end_date'] = date('Y-m-d H:i:s',strtotime('00:00:00') + 86399);//领取的结束时间
        $data['now_time'] = time(); //当前的时间戳
        $data['receive_end_time'] = strtotime($data['receive_start_date']) + 86400; //本周发奖预计领取的开始时间
        [$data['week_amount'],$data['total_cash_water_score']] = $this->getNowWeekCashBack((int)$uid,0,0);
        //判断是否到领取的时间
        if(time() < strtotime($data['receive_start_date'])){
            $data['amount'] = -1;//-1代表问号
        }else{ //这里由于上周的数据是本周结算的，所有创建时间用本周的
            $amount = Db::table('betrayal_log')->where([['uid','=',$uid],['betrayal_start_date','=',date('Ymd',strtotime($data['settlement_start_date']))],['status','=',0]])->value('amount');
            $data['amount'] = $amount ? (int)$amount : -1;
        }

        return $this->ReturnJson->successFul(200,$data);


        //周发奖逻辑
//        [$week_start,$week_end] = DateTime::startEndWeekTime(time());
//        $data['settlement_start_date'] = date('Y-m-d H:i:s',$week_start); //本周结算的开始时间
//        $data['settlement_end_date'] = date('Y-m-d H:i:s',$week_end); //本周结算的结束时间
//        $data['receive_start_date'] = date('Y-m-d',$week_end + 1).' '.$this->receiveHours; //领取的开始时间
//        $data['receive_end_date'] = date('Y-m-d H:i:s',($week_end + ($this->receiveDayNum * 86400)));//领取的结束时间
//
//        $data['now_time'] = time(); //当前的时间戳
//        $data['receive_end_time'] = strtotime($data['receive_start_date']); //本周发奖预计领取的开始时间
//
//        //本周可以领取的金额
//        [$data['week_amount'],$data['total_cash_water_score']] = $this->getNowWeekCashBack((int)$uid,(int)$week_start,(int)$week_end);
//
//
//        [,$last_week_end] = DateTime::startEndWeekTime(strtotime(' -7 day'));
//        $last_receive_start_date = strtotime( date('Y-m-d',$last_week_end + 1).' '.$this->receiveHours);//上周领取的开始时间
//        $last_receive_end_date = ($last_week_end + ($this->receiveDayNum * 86400));//上周领取的结束时
//        //判断是否到领取的时间
//        if(time() > $last_receive_end_date || time() < $last_receive_start_date){
//            Db::table('betrayal_log')->where('uid',$uid)->update(['status' => 2]);
//            $data['amount'] = -1;//-1代表问号
//        }else{ //这里由于上周的数据是本周结算的，所有创建时间用本周的
//            $amount = Db::table('betrayal_log')->where([['uid','=',$uid],['createtime','>=',$week_start],['createtime','<=',$week_end],['status','=',0]])->value('amount');
//            $data['amount'] = $amount ? (int)$amount : -1;
//        }
//        return $this->ReturnJson->successFul(200,$data);

    }


    /**
     * 获取当个用户本周反水金额
     * @param int $uid 用户UID
     * @param int $week_start 计算周的开始时间
     * @param int $week_end 计算的结束时间
     * @return int
     */
    private function getNowWeekCashBack(int $uid,int $week_start,int $week_end):array{
        //按照配置表每周反水
//        $dateArray = DateTime::createDateRange($week_start,$week_end,'Ymd');
//        $dateArray = array_reverse($dateArray);
//        $field = "cash_total_score,total_cash_water_score";
//        $list = $this->SqlUnion->SubTableQueryList($dateArray,'user_day_',$field,[['total_cash_water_score','<>',0],['uid','=',$uid]]);


        $list = Db::table('user_day_'.date('Ymd'))
            ->select('cash_total_score','total_cash_water_score')
            ->where([['cash_total_score','<',0],['vip','>',0],['uid','=',$uid]])
            ->get()
            ->toArray();

        if(!$list)return [0,0];
        $cashbackConfig = Db::table('cashback_config')->get()->toArray();
        if(!$cashbackConfig)return [0,0];
        [$cash_total_score,$total_cash_water_score]  = $this->getBetrayalUid($list);
        $cashbackData = [];
        foreach ($cashbackConfig as $value){ //获取反水配置
            if($value['minwater'] <= $total_cash_water_score && $value['maxwater'] >=$total_cash_water_score){
                $cashbackData = $value;
                break;
            }
        }
        if(!$cashbackData)return [0,0];
        $total_score =bcsub('0', (string)$cash_total_score,0); //总共输的金额
        $amount = bcmul((string)$cashbackData['bili'],$total_score,0); //实际反水的金额
        if($amount > $cashbackData['maxamount'])$amount = $cashbackData['maxamount'];
        return [(int)$amount,$total_cash_water_score];
        //每周按照VIP等级处理
//        $vipbetrayal_max_amount = Common::getConfigValue('vipbetrayal_max_amount'); //每次最大反水金额
//        $dateArray = DateTime::createDateRange($week_start,$week_end,'Ymd');
//        $dateArray = array_reverse($dateArray);
//        $field = "uid,cash_total_score,vip,channel,package_id";
//        $list = $this->SqlUnion->SubTableQueryList($dateArray,'user_day_',$field,[['cash_total_score','<>',0],['vip','>',0],['uid','=',$uid]]);
//        if(!$list)return 0;
//        $vipConfig = Db::table('vip')->pluck('betrayal_bili','vip');
//        if(!$vipConfig)return 0;
//        [$cash_total_score,$vip]  = $this->getBetrayalUid($list);
//        if($cash_total_score >= 0) return 0;
//        if(!isset($vipConfig[$vip]) || $vipConfig[$vip] <= 0)return 0;
//        $total_score =bcsub('0', (string)$cash_total_score,0); //总共输的金额
//        $amount = bcmul((string)$vipConfig[$vip],$total_score,0); //实际反水的金额
//        if($amount > $vipbetrayal_max_amount)$amount = $vipbetrayal_max_amount;
//        return (int)$amount;
    }


//    /**
//     * 每周按照VIP等级处理
//     * @param array $list
//     * @return array
//     */
//
//    private function getBetrayalUid(array $list){
//        $cash_total_score = 0;
//        $vip = 0;
//        foreach ($list as $key => $v){
//            if($key == 0)$vip = $v['vip'];
//            $cash_total_score += $v['cash_total_score'];
//        };
//        return [$cash_total_score,$vip];
//    }



    /**
     * 每周按照配置表处理
     * @param array $list
     * @return array
     */

    private function getBetrayalUid(array $list){
        $cash_total_score = 0;
        $total_cash_water_score = 0;
        foreach ($list as $v){
            $cash_total_score += $v['cash_total_score'];
            $total_cash_water_score += $v['total_cash_water_score'];
        };
        return [$cash_total_score,$total_cash_water_score];
    }


    /**
     * 领取返水奖励
     * @return void
     */
    #[RequestMapping(path: "getRebatesAmount", methods: "get,post")]
    public function getRebatesAmount(){
        $uid = $this->request->post('uid');
        $type = $this->request->post('type') ?? 2; //类型:1=自研.2=三方
        $type = $type == 1 ? 1 : 2;
        $cashback_type = Common::getConfigValue('cashback_type');//1= 是新的反水需求和定时任务 ，2=  老版本反水定时任务,这个值判断客户端调取老的反水还是新的反水接口
        if($cashback_type == 1){ //新版
            return $this->getNewRebatesAmount($uid,$type);
        }else{ //老版本
            if($type == 1) return $this->ReturnJson->failFul(248);;
            return $this->getOldRebatesAmount($uid);
        }
    }



    /**
     * 新版本领取返水奖励
     * @return void
     */

    public function getNewRebatesAmount($uid,$type){
        //每天发奖逻辑
        $data['receive_start_date'] = date('Y-m-d').' '.$this->receiveHours; //领取的开始时间
        $betrayal_start_date = date('Ymd',strtotime('-1 day'));
        if(time() < strtotime($data['receive_start_date']))return $this->ReturnJson->failFul(247);
        $betrayal_log = Db::table('new_betrayal_log')->select('cash_amount','bonus_amount','id','status')->where([['type','=',$type],['uid','=',$uid],['betrayal_start_date','=',$betrayal_start_date]])->first();
        if($betrayal_log && $betrayal_log['status'] == 1) return $this->ReturnJson->failFul(248);
        if(!$betrayal_log  || ($betrayal_log['cash_amount'] <= 0 && $betrayal_log['bonus_amount'] <= 0)){
            if($type == 2){
                return $this->ReturnJson->failFul(248);
            }else{
                $zyCashbackConfig = $this->zyCashbackConfig();
                [$betrayal_log['cash_amount'],$betrayal_log['bonus_amount']] = $this->getDefaultZyAmount($zyCashbackConfig,$uid,2);
                if($betrayal_log['cash_amount'] <= 0 && $betrayal_log['bonus_amount'] <= 0) return $this->ReturnJson->failFul(248);
                $betrayal_log['id'] = 0;
            }

        }
        Db::beginTransaction();
        if(!$betrayal_log['id']){
            $res = Db::table('new_betrayal_log')->insert([
                'uid' => $uid,
                'total_cash_water_score' => 0,
                'cash_amount' => $betrayal_log['cash_amount'],
                'bonus_amount' => $betrayal_log['bonus_amount'],
                'createtime' => time(),
                'type' => 1,
                'status' => 1,
                'betrayal_start_date' => $betrayal_start_date
            ]);

        }else{
            $res = Db::table('new_betrayal_log')->where('id',$betrayal_log['id'])->update(['status' => 1]);
        }

        if(!$res){
            Db::rollback();
            return $this->ReturnJson->failFul(249);
        }


        if($betrayal_log['cash_amount'] > 0){
            //Cash奖励
            $res = User::userEditCoin($uid,$betrayal_log['cash_amount'],6,'用户:'.$uid.'返水奖励:'.bcdiv((string)$betrayal_log['cash_amount'],'100',2),2,7);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
            $res = User::editUserTotalGiveScore($uid,$betrayal_log['cash_amount']);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
        }

        if($betrayal_log['bonus_amount'] > 0){
            //Bonus奖励
            $res = User::userEditBonus($uid,$betrayal_log['bonus_amount'],6,'用户:'.$uid.'返水奖励'.bcdiv((string)$betrayal_log['bonus_amount'],'100',2),2,7);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }



        Db::commit();

        return $this->ReturnJson->successFul();


    }


    /**
     * 老版本领取返水奖励
     * @return void
     */

    public function getOldRebatesAmount($uid){
        //每天发奖逻辑
        $data['receive_start_date'] = date('Y-m-d').' '.$this->receiveHours; //领取的开始时间
        if(time() < strtotime($data['receive_start_date']))return $this->ReturnJson->failFul(247);
        $betrayal_log = Db::table('betrayal_log')->select('amount','id')->where([['status','=',0],['uid','=',$uid],['betrayal_start_date','=',date('Ymd',strtotime('-1 day'))]])->first();
        if(!$betrayal_log || $betrayal_log['amount'] <= 0) return $this->ReturnJson->failFul(248);
        Db::beginTransaction();
        $res = Db::table('betrayal_log')->where('id',$betrayal_log['id'])->update(['status' => 1]);
        if(!$res){
            Db::rollback();
            return $this->ReturnJson->failFul(249);
        }

        $cashback_amonut_type = Common::getConfigValue('cashback_amonut_type');

        if($cashback_amonut_type == 1){
            //Cash奖励
            $res = User::userEditCoin($uid,$betrayal_log['amount'],6,'用户:'.$uid.'返水奖励:'.bcdiv((string)$betrayal_log['amount'],'100',2),2,7);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
            $res = User::editUserTotalGiveScore($uid,$betrayal_log['amount']);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
        }else{
            //Bonus奖励
            $res = User::userEditBonus($uid,$betrayal_log['amount'],6,'用户:'.$uid.'返水奖励'.bcdiv((string)$betrayal_log['amount'],'100',2),2,7);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }


        Db::commit();

        return $this->ReturnJson->successFul();



        //周奖励逻辑
//        $uid = $this->request->post('uid');
//        [,$last_week_end] = DateTime::startEndWeekTime(strtotime(' -7 day'));
//        $last_receive_start_date = strtotime( date('Y-m-d',$last_week_end + 1).' '.$this->receiveHours);//上周领取的开始时间
//        $last_receive_end_date = ($last_week_end + ($this->receiveDayNum * 86400));//上周领取的结束时间
//        //判断是否到领取的时间
//        if(time() > $last_receive_end_date || time() < $last_receive_start_date) return $this->ReturnJson->failFul(247);
//        [$week_start,$week_end] = DateTime::startEndWeekTime(time());
//        $betrayal_log = Db::table('betrayal_log')->select('amount','id')->where([['status','=',0],['uid','=',$uid],['createtime','>=',$week_start],['createtime','<=',$week_end]])->first();
//        if(!$betrayal_log || $betrayal_log['amount'] <= 0) return $this->ReturnJson->failFul(248);
//
//        Db::beginTransaction();
//        $res = Db::table('betrayal_log')->where('id',$betrayal_log['id'])->update(['status' => 1]);
//        if(!$res){
//            Db::rollback();
//            return $this->ReturnJson->failFul(249);
//        }
//
//        //Bonus奖励
//        $res = User::userEditBonus($uid,$betrayal_log['amount'],6,'用户:'.$uid.'返水奖励'.bcdiv((string)$betrayal_log['amount'],'100',2),2,7);
//        if(!$res){
//            Db::rollback();
//            return $this->ReturnJson->failFul(249); //奖励领取失败
//        }
//
//        //Cash奖励
////        $res = User::userEditCoin($uid,$betrayal_log['amount'],6,'用户:'.$uid.'返水奖励:'.bcdiv((string)$betrayal_log['amount'],'100',2),2,7);
////        if(!$res){
////            Db::rollback();
////            return $this->ReturnJson->failFul(249);
////        }
////
////
////        $res = User::editUserTotalGiveScore($uid,$betrayal_log['amount']);
////        if(!$res){
////            Db::rollback();
////            return $this->ReturnJson->failFul(249);
////        }
//        Db::commit();
//
//        return $this->ReturnJson->successFul();
    }

    /**
     * 获取用户VIP奖励
     * @return void
     */
    #[RequestMapping(path: "getUserVipRewards", methods: "get,post")]
    public function getUserVipRewards(){
        $uid = $this->request->post('uid');
        $vip = Db::connection('readConfig')
            ->table('vip')
            ->select(Db::raw('vip,order_pay_money,(sj_amount + sj_bonus) as sj_money,(week_amount + week_bonus) as week_money,(month_amount + month_bonus) as month_money,day_withdraw_money'))
            ->orderBy('id')
            ->get()
            ->toArray();
        //整理Vip数据
        $vipData = $this->organizeVipData($vip);

        $userinfo = Db::table('userinfo')
            ->select('vip')
            ->where('uid',$uid)
            ->first();
        //获取用户成为Vip2的时间
        $vipCreatetime = (int)$this->getVipCreatetime((int)$uid);

        $package_id = $this->getPackAgeId();


        $parallel = new Parallel(5);
        $data = [];
        $Receive_array = [];//是否有可以领取的数据
        for ($i = 1;$i <= 3;$i++) {

            $parallel->add(function () use($i,$uid,&$data,$userinfo,&$Receive_array,$vipCreatetime,$vipData,$package_id){
                if($i == 2){ //周返利
                    [$status,$min_order_money] = $this->getUserOrderMoney($uid,$vipCreatetime,$vipData[$userinfo['vip'] ?? 1]['order_pay_money'],$package_id);
                    $amount = $vipData[$userinfo['vip']]['week_money'] ?? 0;
                }elseif ($i == 3){//月返利
                    // 获取本月的开始时间
                    [$status,$min_order_money] = $this->getUserOrderMoney($uid,$vipCreatetime,$vipData[$userinfo['vip'] ?? 1]['order_pay_money'],$package_id,3);
                    $amount = $vipData[$userinfo['vip']]['month_money'] ?? 0;
                }else{
                    $vip_log = Db::table('vip_log')->select('id','amount','vip','bonus','status')->where(['uid' => $uid,'type' => 1])->groupBy('vip')->get()->toArray();
                    if(!$vip_log)return $i;
                    $status = 0;
                    foreach ($vip_log as $v){
                        if($v['status'] == 0)$status = 1;
                        $data[] = $this->getVipGetData((int)bcadd((string)$v['amount'],(string)$v['bonus'],0),$i,$v['vip'],$v['status'] == 0 ? 1 : 2,$v['id']);
                    }
                    $Receive_array[] = $status;
                    return $i;
                }
                $data[] = $this->getVipGetData((int)$amount,$i,$userinfo['vip'],$status);

                $Receive_array[] = $status;
                return $i;
            });
        }
        try{
            $parallel->wait();
        } catch(ParallelExecutionException $e){
            $this->logger->error('VIP获取领取记录失败返回值:'.json_encode($e->getResults()));
            $this->logger->error('VIP获取领取记录失败出现的异常:'.json_encode($e->getThrowables()));
        }

        if($data)foreach ($data as $value){
            switch ($value['type']){
                case 1:
                    $vipData[$value['vip']]['sj_money'] = $value['amount'];
                    $vipData[$value['vip']]['sj_status'] = $value['status'];
                    $vipData[$value['vip']]['vip_log_id'] = $value['vip_log_id'];
                    break;
                case 2:
                    $vipData[$value['vip']]['week_money'] = $value['amount'];
                    $vipData[$value['vip']]['week_status'] = $value['status'];
                    break;
                default:
                    $vipData[$value['vip']]['month_money'] = $value['amount'];
                    $vipData[$value['vip']]['month_status'] = $value['status'];
            }
        }

        $Receive_status = in_array(1,$Receive_array) ? 1 : 0;
        return $this->ReturnJson->successFul(200,['Receive_status' => $Receive_status,'data' => array_values($vipData)]);


    }

    /**
     * 获取包id
     * @return void
     */
    public function getPackAgeId(){
        $packname = $this->request->getAttribute('packname');
        $package_id = Db::connection('readConfig')->table('apppackage')->where('appname',$packname)->value('id');
        if(!$package_id)$package_id = 1;
        return $package_id;
    }

    /**
     * 整理Vip数据
     * @param array $vipData
     * @return array
     */
    private function organizeVipData(array $vipData):array{
        $vip = [];
        foreach ($vipData as $vipDatum){
            $vip[$vipDatum['vip']] = [
                'sj_money' => $vipDatum['sj_money'],
                'week_money' => $vipDatum['week_money'],
                'month_money' => $vipDatum['month_money'],
                'sj_status' => $vipDatum['vip'] == 1 ? 2 : 0,//Vip1的话直接是已领取
                'month_status' => 0,
                'week_status' => 0,
                'vip_log_id' => 0,
                'day_withdraw_money' => $vipDatum['day_withdraw_money'],
                'order_pay_money' => $vipDatum['order_pay_money'],
            ];
        }
        return $vip;
    }

    /**
     * 获取Vip领取记录
     * @param $amount int 领取金额
     * @param $type int 类型 1= 升级，2=周奖励，3=月奖励
     * @param $vip int Vip等级
     * @param $status  int 是否可以领取状态 0=不能领取，1=可以领取,2=已领取
     * @param $vip_log_id  int 升级奖励可以领取的ID
     * @return array
     */
    private function getVipGetData(int $amount,int $type,int $vip,int $status,int $vip_log_id = 0):array{
        return [
            'status' => $status,
            'amount' => $amount,
            'type' => $type,
            'vip' => $vip,
            'vip_log_id' => $vip_log_id,
        ];
    }

    /**
     * 获取用户升级Vip的时间
     * @param int $uid
     * @return
     */
    private function getVipCreatetime(int $uid){
        return Db::table('vip_log')->where([['uid','=',$uid],['vip','>=',2],['type','=',1]])->orderBy('id','desc')->value('createtime');
    }

    /**
     * @param $uid int|string
     * @param $vipCreatetime int 成为Vip的时间
     * @param $min_order_money int|string 前30天需要充值金额
     * @param $package_id int|string 包ID
     * @param $type int 类型:2=周奖励，3=月奖励
     * @return array
     */
    private function getUserOrderMoney(int|string $uid,int $vipCreatetime,int|string $min_order_money,$package_id = 1,int $type = 2):array{
        $special_package_ids = Common::getConfigValue('special_package_ids'); //新包ID
        $special_package_Array = $special_package_ids ? explode(',',$special_package_ids) : [];

        //老包用系统配置，新包用Vip配置
        if(!in_array($package_id,$special_package_Array)){
            $filed = $type == 2 ? 'week_order_money' : 'month_order_money';
            $min_order_money = Common::getConfigValue($filed); //最低充值金额
            if(!$vipCreatetime)return [0,$min_order_money];
            if($type == 2 && ($vipCreatetime + (86400 * 7)) >= time()){
                return [0,$min_order_money];
            }elseif($type == 3 && ($vipCreatetime + (86400 * 30)) >= time()){
                return [0,$min_order_money];
            }

            //判断用户是否已经参与
            $vip_log = Db::table('vip_log')->where(['status' => 1,'uid' => $uid,'type' => $type])->orderBy('receivetime','desc')->value('receivetime');
            $status = true;
            $thisTime = $vip_log ?: $vipCreatetime;
            if($type == 2 && $vip_log && ((int)$vip_log + (86400 * 7)) >= time()){
                $status = false;
            }elseif($type == 3 && $vip_log && ((int)$vip_log + ( 86400 * 30)) >= time()){
                $status = false;
            }
            if(!$status)return [2,$min_order_money];
            $order = Db::table('order')->select(Db::raw('sum(price) as allprice'))->where(['pay_status' => 1,'uid' => $uid])->where('createtime','>=',$thisTime)->first();
        }else{
            if(!$vipCreatetime)return [0,$min_order_money];
            $receivetime = Db::table('vip_log')->where(['status' => 1,'uid' => $uid,'type' => $type])->orderBy('receivetime','desc')->value('receivetime');
            $status = true;
            //新包按照自然月领取
            $monthStartTime = strtotime(date('Y-m-01 00:00:00', time()));
            if($type == 2 && $receivetime && ((int)$receivetime + (86400 * 7)) >= time()){
                $status = false;
            }elseif($type == 3 && $receivetime && ($receivetime >= $monthStartTime)){
                $status = false;
            }
            if(!$status)return [2,$min_order_money];
            $order = Db::table('order')->select(Db::raw('sum(price) as allprice'))->where(['pay_status' => 1,'uid' => $uid])->where('createtime','>=',$monthStartTime)->first();
        }

        if(!$order)return [0,$min_order_money];
        if($order['allprice'] < $min_order_money)return [0,$min_order_money];
        return [1,$min_order_money];


    }


    /**
     * 领取用户VIP奖励
     */
    #[RequestMapping(path: "ReceiveUserVipRewards", methods: "get,post")]
    public function ReceiveUserVipRewards(){
        $uid = $this->request->post('uid');
        $type = $this->request->post('type'); //类型:1=升级奖励,2=周奖励,3=月奖励
        $vip_log_id = $this->request->post('vip_log_id') ?? 0; //领取奖励ID,升级奖励需要传入

        $package_id = $this->getPackAgeId();

        Db::beginTransaction();
        $vipCreatetime = (int)$this->getVipCreatetime((int)$uid);
        if($type == 2){
            $userinfo = Db::table('userinfo as a')
                ->leftJoin('vip as b','a.vip','=','b.vip')
                ->select('a.vip','b.week_amount','b.week_bonus','b.order_pay_money')
                ->where('a.uid',$uid)
                ->first();
            // 获取本周的开始时间
            [$status,] = $this->getUserOrderMoney($uid,$vipCreatetime,$userinfo['order_pay_money'],$package_id);
            if(!$status)return $this->ReturnJson->failFul(248);
            $reason = 16;
            $amount = $userinfo['week_amount'];
            $bonus = $userinfo['week_bonus'];
            $res = $this->setVipLog(['uid' => $uid,'vip' => $userinfo['vip'],'type' => 2,'amount' => $amount,'bonus' => $bonus]);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
            $water_multiple_type = 4;
        }elseif ($type == 3){
            $userinfo = Db::table('userinfo as a')
                ->leftJoin('vip as b','a.vip','=','b.vip')
                ->select('a.vip','b.month_amount','b.month_bonus','b.order_pay_money')
                ->where('a.uid',$uid)
                ->first();
            // 获取本月的开始时间
            [$status,] = $this->getUserOrderMoney($uid,$vipCreatetime,$userinfo['order_pay_money'],$package_id,3);
            if(!$status)return $this->ReturnJson->failFul(248);
            $reason = 17;
            $amount = $userinfo['month_amount'];
            $bonus = $userinfo['month_bonus'];
            $res = $this->setVipLog(['uid' => $uid,'vip' => $userinfo['vip'],'type' => 3,'amount' => $amount,'bonus' => $bonus]);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
            $water_multiple_type = 5;
        }else{
            $reason = 15;
            $vip_log = Db::table('vip_log')->select('id','amount','bonus')->where(['uid' => $uid,'id' => $vip_log_id,'status' => 0])->orderBy('createtime','desc')->first();
            if(!$vip_log){
                return $this->ReturnJson->failFul(248);
            }
            $res = Db::table('vip_log')->where(['id' => $vip_log['id']])->update(['status' => 1,'receivetime' => time()]);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
            $amount = $vip_log['amount'];
            $bonus = $vip_log['bonus'];
            $water_multiple_type = 3;
        }

        if($amount > 0){
            $res = User::userEditCoin($uid,$amount,$reason,'用户:'.$uid.'返水奖励:'.bcdiv((string)$amount,'100',2),2,$water_multiple_type);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }


            $res = User::editUserTotalGiveScore($uid,$amount);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
        }
        if($bonus > 0){
            $res = User::userEditBonus($uid,$bonus,$reason,'用户:'.$uid.'返水奖励'.bcdiv((string)$bonus,'100',2),2,$water_multiple_type);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }
        Db::commit();

        return $this->ReturnJson->successFul();
    }

    /**
     * 添加vip奖励领取记录
     * @param array $data
     * @return bool
     */
    private function setVipLog(array $data){
        $vip_log = [
            'uid' => $data['uid'],
            'vip' => $data['vip'],
            'type' => $data['type'],
            'order_money' => 0,
            'amount' => $data['amount'],
            'bonus' => $data['bonus'],
            'createtime' => time(),
            'status' => 1,
            'receivetime' =>  time(),
        ];
        return Db::table('vip_log')->insert($vip_log);
    }


    /**
     * 得到每日奖励领取状态
     * @return void
     */
    #[RequestMapping(path: "getDailyRewardStatus", methods: "get,post")]
    public function getDailyRewardStatus(){
        $uid = $this->request->post('uid');
        $date = date('Ymd',strtotime('-1 day'));
        $daily_reward_log = Db::table('daily_reward_log')->where(['uid' => $uid,'dailyreward_start_date' => $date,'status' => 0])->first();
        if(!$daily_reward_log)return $this->ReturnJson->successFul(200,0);
        return $this->ReturnJson->successFul(200,$daily_reward_log['amount']);

    }


    /**
     * 领取每日奖励
     * @return void
     */
    #[RequestMapping(path: "receiveDailyReward", methods: "get,post")]
    public function receiveDailyReward(){
        $uid = $this->request->post('uid');
        $date = date('Ymd',strtotime('-1 day'));
        $daily_reward_log = Db::table('daily_reward_log')->where(['uid' => $uid,'dailyreward_start_date' => $date,'status' => 0])->first();
        if(!$daily_reward_log)return $this->ReturnJson->failFul(248);
        Db::beginTransaction();

        $res = Db::table('daily_reward_log')->where('id',$daily_reward_log['id'])->update(['status' => 1]);
        if(!$res){
            $this->logger->error('每日奖励领取日志更新失败');
            Db::rollback();
            return $this->ReturnJson->failFul(249); //奖励领取失败
        }

        $res = User::userEditBonus($uid,$daily_reward_log['amount'],22,'用户:'.$uid.'每日奖励领取'.bcdiv((string)$daily_reward_log['amount'],'100',2),2,13);
        if(!$res){
            $this->logger->error('每日奖励发送Bonus失败');
            Db::rollback();
            return $this->ReturnJson->failFul(249); //奖励领取失败
        }


        Db::commit();
        return $this->ReturnJson->successFul();
    }


    /**
     * 新版本每日反水用户详情
     * @return void
     */
    #[RequestMapping(path: "newCashBackIndex", methods: "get,post")]
    public function newCashBackIndex(){
        $uid = $this->request->post('uid');
        [$data['amount'],$data['sy_amount']] = $this->newCashBackData($uid);
        $data['time'] = time();
        return $this->ReturnJson->successFul(200,$data);
    }


    /**
     * 新版本领取每日反水
     * @return void
     */
    #[RequestMapping(path: "receiveNewCashBack", methods: "get,post")]
    public function receiveNewCashBack(){
        $uid = $this->request->post('uid');
        [$data['amount'],] = $this->newCashBackData($uid);
        if(!$data['amount'] || $data['amount'] <= 0)return $this->ReturnJson->failFul(248);
        $new_day_cash_back_amount_type = Common::getConfigValue('new_day_cash_back_amount_type');
        $water_multiple_type = 20;
        Db::beginTransaction();

        //存储今日日志
        $res = Db::table('new_day_cash_bask_log')->insert([
            'uid' => $uid,
            'amount' => $data['amount'],
            'createtime' => time(),
            'date' => date('Ymd')
        ]);
        if(!$res){
            Db::rollback();
            return $this->ReturnJson->failFul(249);
        }

        //修改剩余数量
        $res = Db::table('new_day_cash_bask')->where('uid',$uid)->update([
            'sy_amount' => Db::raw('sy_amount - '.$data['amount']),
        ]);
        if(!$res){
            Db::rollback();
            return $this->ReturnJson->failFul(249);
        }

        if($new_day_cash_back_amount_type == 1){
            $res = User::userEditCoin($uid,$data['amount'],32,'用户:'.$uid.'新每日返水奖励:'.bcdiv((string)$data['amount'],'100',2),2,$water_multiple_type);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }


            $res = User::editUserTotalGiveScore($uid,$data['amount']);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
        }else{
            $res = User::userEditBonus($uid,$data['amount'],32,'用户:'.$uid.'返水奖励'.bcdiv((string)$data['amount'],'100',2),2,$water_multiple_type);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }
        Db::commit();
        return $this->ReturnJson->successFul();
    }

    /**
     * 新版本每日反水统计数据
     * @param $uid
     * @return array|int[]
     */
    public function newCashBackData($uid){
        $date = date('Ymd');
        $SystemConfig = Common::getMore('new_day_min_amount,new_day_max_amount,new_day_cash_back_bili'); //特殊包每日反水统计
        $new_day_cash_bask = Db::table('new_day_cash_bask')->where('uid',$uid)->first();
        if(!$new_day_cash_bask)return [0,0];
        //判断今日是否下注
        $total_game_num = Db::table('user_day_'.$date)->where('uid',$uid)->value('total_game_num');
        if(!$total_game_num)return [0,$new_day_cash_bask['sy_amount']];

        //判断今日是否已经领取
        $new_day_cash_bask_log = Db::table('new_day_cash_bask_log')->where(['uid' => $uid,'date' => $date])->value('id');
        if($new_day_cash_bask_log)return [0,$new_day_cash_bask['sy_amount']];

        if($SystemConfig['new_day_max_amount'] <= 0 || $SystemConfig['new_day_cash_back_bili'] <= 0)return [0,$new_day_cash_bask['sy_amount']];

        $SystemConfig['new_day_min_amount'] = max(0,$SystemConfig['new_day_min_amount']);
        //计算今日预估领取金额
        $amount = bcmul((string)$new_day_cash_bask['sy_amount'],(string)$SystemConfig['new_day_cash_back_bili'],0);

        //剩余金额不能低于最低领取金额
        if($amount <= $SystemConfig['new_day_min_amount'])return [0,$new_day_cash_bask['sy_amount']];
        //领取金额不能高于最大领取金额
        if($amount > $SystemConfig['new_day_max_amount'])$amount = $SystemConfig['new_day_max_amount'];
        return [$amount,$new_day_cash_bask['sy_amount']];
    }



    /**
     * 新版本每周反水用户详情
     * @return void
     */
    #[RequestMapping(path: "newWeekCashBackIndex", methods: "get,post")]
    public function newWeekCashBackIndex(){
        $uid = $this->request->post('uid');

        $userinfo = Db::table('userinfo')->selectRaw('vip,(total_cash_water_score + total_bonus_water_score) as total_water_score,total_pay_score')->where('uid',$uid)->first(); //总Cash流水
        if($userinfo){
            $data['total_water_score'] = $userinfo['total_water_score'];
            $data['vip'] = $userinfo['vip'];
            $data['total_pay_score'] = $userinfo['total_pay_score'];
        }else{
            $data['total_water_score'] = 0;
            $data['vip'] = 0;
            $data['total_pay_score'] = 0;
        }

        //周发奖逻辑
        [$week_start,$week_end] = DateTime::startEndWeekTime(time());
        $data['settlement_start_date'] = date('Y-m-d H:i:s',$week_start); //本周结算的开始时间
        $data['settlement_end_date'] = date('Y-m-d H:i:s',$week_end); //本周结算的结束时间
        $data['receive_start_date'] = date('Y-m-d',$week_end + 1).' '.$this->receiveHours; //领取的开始时间
        $data['receive_end_date'] = date('Y-m-d H:i:s',($week_end + ($this->receiveDayNum * 86400)));//领取的结束时间

        $data['now_time'] = time(); //当前的时间戳
        $data['receive_end_time'] = strtotime($data['receive_start_date']); //本周发奖预计领取的开始时间


        [$last_week_start,$last_week_end] = DateTime::startEndWeekTime(strtotime(' -7 day'));
        $last_receive_start_date = strtotime( date('Y-m-d',$last_week_end + 1).' '.$this->receiveHours);//上周领取的开始时间
        $last_receive_end_date = ($last_week_end + ($this->receiveDayNum * 86400));//上周领取的结束时


        //本周可以领取的金额
        [$data['week_amount'],$data['total_cash_water_score'],] = $this->newWeekCashBackData((int)$uid, (int)$week_start);

        //判断是否到领取的时间
        if(time() > $last_receive_end_date || time() < $last_receive_start_date){
            $data['amount'] = -1;//-1代表问号
        }else{ //这里由于上周的数据是本周结算的，所有创建时间用本周的
            $amount = Db::table('new_week_cashback_log')->where([['uid','=',$uid],['betrayal_start_date','>=',date('Ymd',$last_week_start)],['status','=',0]])->value('amount');
            $data['amount'] = $amount ? (int)$amount : -1;
        }
        return $this->ReturnJson->successFul(200,$data);

    }


    /**
     * 新版领取上周反水金额
     * @return void
     */
    #[RequestMapping(path: "receiveNewWeekCashBack", methods: "get,post")]
    public function receiveNewWeekCashBack(){
        $uid = $this->request->post('uid');
        [$week_start,$week_end] = DateTime::startEndWeekTime(time());
        $last_receive_start_date = strtotime( date('Y-m-d',$week_start).' '.$this->receiveHours);//本周领取的开始时间
        $last_receive_end_date = ($week_end + ($this->receiveDayNum * 86400));//本周领取的结束时间
        //判断是否到领取的时间
        if(time() > $last_receive_end_date || time() < $last_receive_start_date) return $this->ReturnJson->failFul(247);
        //检查今日是否已经下注
        $total_game_num = Db::table('user_day_'.date('Ymd'))->where('uid',$uid)->value('total_game_num');
        if(!$total_game_num)return $this->ReturnJson->failFul(277);


        [$last_week_start,] = DateTime::startEndWeekTime(strtotime(' -7 day'));
        $new_week_cashback_log = Db::table('new_week_cashback_log')->select('amount','id')->where([['status','=',0],['uid','=',$uid],['betrayal_start_date','=',date('Ymd',$last_week_start)]])->first();
        if(!$new_week_cashback_log || $new_week_cashback_log['amount'] <= 0) return $this->ReturnJson->failFul(248);


        $new_cashback_amonut_type = Common::getConfigValue('new_cashback_amonut_type');

        Db::beginTransaction();
        $res = Db::table('new_week_cashback_log')->where('id',$new_week_cashback_log['id'])->update(['createtime' => time(),'status' => 1]);
        if(!$res){
            Db::rollback();
            return $this->ReturnJson->failFul(249);
        }

        $water_multiple_type = 20;

        if($new_cashback_amonut_type == 1){
            //Cash奖励
            $res = User::userEditCoin($uid, $new_week_cashback_log['amount'],33,'用户:'.$uid.'返水奖励:'.bcdiv((string) $new_week_cashback_log['amount'],'100',2),2,$water_multiple_type);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
            $res = User::editUserTotalGiveScore($uid, $new_week_cashback_log['amount']);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
        }else{
            //Bonus奖励
            $res = User::userEditBonus($uid, $new_week_cashback_log['amount'],33,'用户:'.$uid.'返水奖励'.bcdiv((string) $new_week_cashback_log['amount'],'100',2),2,$water_multiple_type);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }


        Db::commit();

        return $this->ReturnJson->successFul();

    }


    /**
     * 新版本上周反水领取金额
     * @param $uid
     * @param $week_start 对应周的开始时间
     * @return array|int[]
     */
    public function newWeekCashBackData(int $uid,int $week_start):array{

        $date = date('Ymd',$week_start);



        $new_week_cash_bask = Db::table('new_week_cash_bask')->where(['uid' => $uid,'date' => $date])->first();
        if(!$new_week_cash_bask)return [0,0,0,0];
        if($new_week_cash_bask['cashTransferAmount'] >= 0 || $new_week_cash_bask['status'] != 0)return [0,$new_week_cash_bask['cashBetAmount'],$new_week_cash_bask['id'],$new_week_cash_bask['cashTransferAmount']];

        //获取周反水配置
        $new_week_cashback_config = Db::table('new_week_cashback_config')
            ->selectRaw('maxamount,bili')
            ->where([['minwater','<=',$new_week_cash_bask['cashBetAmount']],['maxwater','>',$new_week_cash_bask['cashBetAmount']]])
            ->first();
        if(!$new_week_cashback_config || $new_week_cashback_config['bili'] <= 0)return [0,$new_week_cash_bask['cashBetAmount'],$new_week_cash_bask['id'],$new_week_cash_bask['cashTransferAmount']];

        //获取每周最低领取金额
        $vip = Db::table('userinfo as a')
            ->leftJoin('vip as b','a.vip','=','b.vip')
            ->select('b.new_week_min_amount')
            ->where('a.uid',$uid)
            ->first();
        if(!$vip)return [0,$new_week_cash_bask['cashBetAmount'],$new_week_cash_bask['id'],$new_week_cash_bask['cashTransferAmount']];

        //理论能获取的值
        $amount = bcmul((string)$new_week_cashback_config['bili'],(string)(abs((int)$new_week_cash_bask['cashTransferAmount'])),2);

        //获取真实的反水金额
        $reallyAmount = min(max($amount,$vip['new_week_min_amount']),$new_week_cashback_config['maxamount']);

        return [$reallyAmount,$new_week_cash_bask['cashBetAmount'],$new_week_cash_bask['id'],$new_week_cash_bask['cashTransferAmount']];
    }

}






