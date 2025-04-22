<?php

declare(strict_types=1);
namespace App\Controller\active;
use App\Controller\AbstractController;
use App\Common\User;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Coroutine\Exception\ParallelExecutionException;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Parallel;
/**
 * 红包雨
 */
#[Controller(prefix:"active.RedEnvelopes")]
class RedEnvelopesController extends AbstractController
{



    /**
     * 获取用户红包雨参与状态
     * @return null
     */
    #[RequestMapping(path: "redEnvelopesStatus", methods: "get,post")]
    public function redEnvelopesStatus(){
        $uid = $this->request->post('uid');
        $date = date('His');
        $userinfo = Db::table('userinfo')->select('total_pay_score')->where('uid',$uid)->first();
        if(!$userinfo || $userinfo['total_pay_score'] <= 0){
            //不能参与的话返回下次参与时间
            $date = $this->getMinRedEnvelopesTime($date);
            return $this->ReturnJson->successFul(200,['status' => 0,'date' => $date]);
        }
        $red_envelopes_time_id = $this->getLeftEnvelopsStatus($date);
        if($red_envelopes_time_id){
            $red_envelopes_log = Db::table('red_envelopes_log')  //判断今日该活动是否已经参与了
            ->select('id')
                ->where([
                    ['uid','=',$uid],
                    ['red_envelopes_time_id','=',$red_envelopes_time_id],
                    ['createtime','>=',strtotime("00:00:00")]
                ])
                ->first();
            if(!$red_envelopes_log)return $this->ReturnJson->successFul(200,['status' => 1]); //表示能参与
        }
        //不能参与的话返回下次参与时间
        $date = $this->getMinRedEnvelopesTime($date);
        return $this->ReturnJson->successFul(200,['status' => 0,'date' => $date]);
    }


    /**
     * 领取红包雨
     * @return void
     */
    #[RequestMapping(path: "receiveRedEnvelopes", methods: "get,post")]
    public function receiveRedEnvelopes(){
        $uid = $this->request->post('uid');
        $date = date('His');
        $userinfo = Db::table('userinfo')->selectRaw('total_pay_score,channel,package_id')->where('uid',$uid)->first();
        if(!$userinfo || $userinfo['total_pay_score'] <= 0)return $this->ReturnJson->successFul(250);//抱歉!充值用户才能参与该活动

        //判断是否改时间能有理论上的活动可以参与
        $red_envelopes_time_id = $this->getLeftEnvelopsStatus($date);
        if(!$red_envelopes_time_id)return $this->ReturnJson->failFul(252); //抱歉!本次活动您已参与!请不要重复参与哦!

        //判断用户今天是否已经参与了该活动
        $red_envelopes_log = Db::table('red_envelopes_log')  //判断今日该活动是否已经参与了
        ->select('id')
            ->where([
                ['uid','=',$uid],
                ['red_envelopes_time_id','=',$red_envelopes_time_id],
                ['createtime','>=',strtotime("00:00:00")]
            ])
            ->first();
        if($red_envelopes_log)return $this->ReturnJson->failFul(251); //抱歉!本次活动您已参与!请不要重复参与哦!

        //获取本次翻的ID和金额和剩余数量
        [$red_envelopes_id,$num,$red_type,$redCash,$redBonus] = $this->getEnvelopesMoney($uid);

        Db::beginTransaction();

        if($num > 0 && $red_type == 1){
            $res = Db::table('red_envelopes')->where('id',$red_envelopes_id)->update(['num' => Db::raw('num - 1')]);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }

        if(bcadd((string)$redCash,(string)$redBonus) <= 0)return $this->ReturnJson->failFul(248); //暂无奖励可领取

        $res = Db::table('red_envelopes_log')->insert([
            'red_envelopes_time_id' => $red_envelopes_time_id,
            'red_envelopes_id' => $red_envelopes_id,
            'uid' => $uid,
            'money' => $redCash,
            'bonus' => $redBonus,
            'type' => $red_type,
            'createtime' => time(),
        ]);
        if(!$res){
            Db::rollback();
            $this->logger->error('UID:'.$uid.'红包记录red_envelopes_log存储失败');
   
            return $this->ReturnJson->failFul(249); //奖励领取失败
        }

        if($redCash > 0){
            $res = User::userEditCoin($uid,$redCash,8,'用户:'.$uid.'钱包雨获取'.bcdiv((string)$redCash,'100',2),2,8);
            if(!$res){
                Db::rollback();
                $this->logger->error('UID:'.$uid.'红包雨活动用户余额修改失败!');
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }

            $res = User::editUserTotalGiveScore($uid,$redCash);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }
        }
        if($redBonus > 0){
            $res = User::userEditBonus($uid,$redBonus,8,'用户:'.$uid.'钱包雨获取'.bcdiv((string)$redBonus,'100',2),2,8);
            if(!$res){
                Db::rollback();
                $this->logger->error('UID:'.$uid.'红包雨活动用户Bonus修改失败!');
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }
        Db::commit();
        return $this->ReturnJson->successFul(200,bcadd((string)$redBonus,(string)$redCash,0));
    }




    /**
     *
     * 获取下次最近的签到时间
     * @return void
     * @param  $date 本次的His格式时间
     */

    private function getMinRedEnvelopesTime($date){
        $date = date('His');
        //下次的开始时间要大于本次
        $red_envelopes_time = Db::table('red_envelopes_time')
            ->select('day','type','startdate')
            ->where([['startdate','>',$date],['status','=',1]])
            ->orderBy('startdate')
            ->get()
            ->toArray();
        if(!$red_envelopes_time)$red_envelopes_time = Db::table('red_envelopes_time') //如果今日领完了，就从明天开始
        ->select('day','type','startdate')
            ->where([['status','=',1]])
            ->orderBy('startdate')
            ->get()
            ->toArray();
        //type 类型:1=每天,2=每周,3=每月
        $date = 0;
        foreach ($red_envelopes_time as $v){
            if($v['type'] == 1){
                $date = $v['startdate'];
                break;
            }elseif ($v['type'] == 2){
                $statusArray = explode('、',$v['day']); //判断今天是否在这里面
                $day = date('w'); //获取今日是周几
                $day = $day == 0 ? 7 : $day; //0代表周日
                if(in_array($day,$statusArray)){
                    $date = $v['startdate'];
                    break;
                }
            }else{
                $statusArray = explode('、',$v['day']); //判断今天是否在这里面
                $day = date('d'); //获取今天是哪一天
                $day = substr((string)$day,0,1) == '0' ? substr((string)$day,1,1) : $day;//这里1-9返回会多个0，这里需要删除
                if(in_array($day,$statusArray)){
                    $date = $v['startdate'];
                    break;
                }
            }
        }
        if($date)return substr((string)$date,0,2).':'.substr((string)$date,2,2);
        return $date;
    }

    /**
     * 获取本次理论上是否有红包雨活动能参与
     * @return int
     * @param  $date 本次的His格式时间
     */
    private function getLeftEnvelopsStatus($date){

        $red_envelopes = Db::table('red_envelopes_time')
            ->select('id','type','day')
            ->where([['startdate','<=',$date],['enddate','>=',$date],['status','=',1]])
            ->orderBy('type')
            ->get()
            ->toArray();
        // 构建结果数组
        $red_envelopes_time = [];
        foreach ($red_envelopes as $item) {
            $red_envelopes_time[$item['type']] = ['day' => $item['day'], 'id' => $item['id']];
        }

        if(!$red_envelopes_time)return 0;
        //type 类型:1=每天,2=每周,3=每月 .
        if(isset($red_envelopes_time[1]))return $red_envelopes_time[1]['id'] ?? 0; //如果存在是每天的直接返回

        foreach ($red_envelopes_time as $key => $value){
            $statusArray = explode('、',$value['day']); //判断今天是否在这里面
            if($key == 2){  //每周
                $day = date('w'); //获取今日是周几
                $day = $day == 0 ? 7 : $day; //0代表周日
            }else{ //每月
                $day = date('d'); //获取今天是哪一天
                $day = str_starts_with($day, '0') ? substr((string)$day,1,1) : $day; //这里1-9返回会多个0，这里需要删除
            }
            if(in_array($day,$statusArray))return $value['id']; //直接返回ID
        }
        return 0;
    }

    /**
     * 获取本次红包雨的金额
     * @return array
     */
    private function getEnvelopesMoney($uid){
        $userinfo = Db::table('userinfo')->select('total_cash_water_score','total_bonus_water_score','total_exchange','total_pay_score','coin','total_give_score')->where('uid',$uid)->select()->first();
        $money = Db::table('withdraw_log')->where('uid',$uid)->whereIn('status',[0,1])->sum('money');//目前正在处理的退款
        $money = $money ?: 0;
        $parallel = new Parallel();
        $money_array = []; //可以领取的金额
        for ($i = 1;$i <= 3;$i++) {//1=常规奖励,2=新手奖励,3=大R奖励
            $parallel->add(function () use($i,$uid,$userinfo,&$money_array,$money){
                $data = $this->getMoneyArray($i);
                if($i == 2 || $i == 3){ //2=新手奖励,3=大R奖励
                    $user_withdraw_bili = $userinfo['total_pay_score'] == 0 ? bcdiv(bcadd((string)$userinfo['total_exchange'],(string)$money,0),'10000',2) : bcdiv(bcadd((string)$userinfo['total_exchange'],(string)$money,0),(string)$userinfo['total_pay_score'],2); //总提现提现比例
                    $user_sycash = $userinfo['coin'] <= 0 || $i == 3  ? 0 : bcdiv((string)$userinfo['coin'],(string)$userinfo['total_pay_score'],2); //总提现提现比例
                    $user_recharge_amount_bili = bcdiv(bcadd((string)$userinfo['total_cash_water_score'],(string)$userinfo['total_bonus_water_score'],0),bcadd((string)$userinfo['total_pay_score'],(string)$userinfo['total_give_score'],0),2); //打码量比例
                    $red_envelopes = Db::table('red_envelopes')
                        ->select('num','money','bonus','id')
                        ->where([
                            ['min_money','<=',$userinfo['total_pay_score']],
                            ['max_money','>=',$userinfo['total_pay_score']],
                            ['withdraw_bili','>=',$user_withdraw_bili],
                            ['water_multiple','>=',$user_recharge_amount_bili],
                            ['sycash','>=',$user_sycash],
                            ['type','=',$i],
                        ])
                        ->first();
                    if(!$red_envelopes){
                        $money_array[] = $data;
                        return $i;
                    }
                    //参与次数
//                    $join_num = $i == 3
//                        ? Db::table('red_envelopes_log')->where(['uid' => $uid,'type' => $i,'red_envelopes_id' => $red_envelopes['id']])->count()
//                        : Db::table('red_envelopes_log')->where(['uid' => $uid,'type' => $i])->count();

                    $join_num = Db::table('red_envelopes_log')->where(['uid' => $uid,'type' => $i,'red_envelopes_id' => $red_envelopes['id']])->count();

                    if($join_num >= $red_envelopes['num']){
                        $money_array[] = $data;
                        return $i;
                    }
                    $money_array[] = $this->getMoneyArray($i,(string)$red_envelopes['money'],(string)$red_envelopes['bonus'],(int)$red_envelopes['num'],(int)$red_envelopes['id'],1);
                }else{
                    $random = mt_rand() / mt_getrandmax(); // 获取本次的概率
                    $red_envelopes = Db::table('red_envelopes')
                        ->selectRaw('money,probability,id,num,bonus')
                        ->where([['num','=','-99'],['type','=',1]])
                        ->orWhere([['num','>','0'],['type','=',1]])
                        ->orderBy('probability')
                        ->get()
                        ->toArray();
                    if(!$red_envelopes){
                        $money_array[] = $data;
                        return $i;
                    }
                    foreach ($red_envelopes as $v){
                        if($random <= $v['probability']){
                            $money_array[] = $this->getMoneyArray($i,(string)$v['money'],(string)$v['bonus'],(int)$v['num'],(int)$v['id'],1);
                            return $i;
                        }
                    }
                }
                return $i;
            });
        }
        try{
            $parallel->wait();
        } catch(ParallelExecutionException $e){
            $this->logger->error('红包雨获取领取金额失败返回值:'.json_encode($e->getResults()));
            $this->logger->error('红包雨获取领取金额失败出现的异常:'.json_encode($e->getThrowables()));
        }
        if(!$money_array)return [0,0,0,0,0];
        $max_type = 0;//本次参与的最大金额
        $returnData = [0,0,0,0,0];
        foreach ($money_array as $item){
            if($item['status'] == 1){
                if($item['type'] == 2 || $item['type'] == 3){
                    [$cash_min_amount,$cash_max_amount] = explode(' - ',(string)$item['cash']);
                    [$bonus_min_amount,$bonus_max_amount] = explode(' - ',(string)$item['bonus']);
                    $item['cash'] = rand((int)$cash_min_amount,(int)$cash_max_amount);
                    $item['bonus'] = rand((int)$bonus_min_amount,(int)$bonus_max_amount);
                }
                if($item['type'] > $max_type){
                    $returnData = [$item['id'],$item['num'],$item['type'], $item['cash'],$item['bonus']];
                    $max_type = $item['type'];
                }
            }
        }
        return $returnData;
    }

    /**
     * 获取默认值
     * @param int $i
     * @param string $cash
     * @param string $bonus
     * @param int $num
     * @param int $id
     * @param int $status
     * @return int[]
     */
    private function getMoneyArray(int $i,string $cash = '0',string $bonus = '0',int $num = 0,int $id = 0,int $status = 0):array{
        return [
            'type' => $i,
            'cash' => $cash,
            'bonus' => $bonus,
            'num' => $num,
            'id' => $id,
            'status' => $status,
        ];
    }

}

