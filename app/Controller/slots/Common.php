<?php
/**
 * 三方游戏公共方法
 */

namespace App\Controller\slots;

use Hyperf\Context\Context;
use App\Amqp\Producer\SlotsProducer;
//use Hyperf\Amqp\Producer;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;
use function Hyperf\Support\env;
use App\Common\Common as appCommon;


class Common {

    #[Inject]
    protected LoggerInterface $logger;

//    #[Inject]
//    protected Producer $producer;
    #[Inject]
    protected DealWithController $DealWithController;

    /**
     * 扣除用户余额。返回用户的Cash和Bonus总和
     * @param $uid
     * @param $type 1 = 带入Bonus , 2=不带Bonus ，针对莫些不带bonus的游戏接口
     * @return array
     */
    public function setUserMoney($uid,int $type = 1){
        //用户不存在
        $userinfo = Db::table('userinfo')->select('coin','bonus','total_pay_score')->where('uid',$uid)->first();
        if(!$userinfo)return ['code' => 201,'msg' => '用户不存在','data' => []];
        $money = ($type == 1 && config('slots.is_carry_bonus') == 1 && (appCommon::getConfigValue('recharge_and_get_bonus') >= $userinfo['total_pay_score'])) ? $userinfo['coin'] + $userinfo['bonus'] : $userinfo['coin'];

        if($money <= 0)return ['code' => 200,'msg' => '成功!余额为0','data' => 0];

//        Db::beginTransaction();
//
//        if($userinfo['coin'] > 0){
//            $res = User::userEditCoin($uid,bcsub(0,$userinfo['coin'],0),3,'玩家:'.$uid.'转入Cash-Slots带入:'.bcdiv($userinfo['coin'],100,2));
//            if(!$res){
//                Db::rollback();
//                return ['code' => 200,'msg' => '扣款Cash失败!余额为0','data' => 0];
//            }
//        }
//
//
//        if($userinfo['bonus'] > 0){
//            $res = User::userEditCoin($uid,bcsub(0,$userinfo['bonus'],0),3,'玩家:'.$uid.'转入Bonus-Slots带入:'.bcdiv($userinfo['bonus'],100,2));
//            if(!$res){
//                Db::rollback();
//                return ['code' => 200,'msg' => '扣出Bonus失败!余额为0','data' => 0];
//            }
//        }
//
//        Db::commit();
        return ['code' => 200,'msg' => '成功','data' => $money];
    }


    /**
     * 计算本次的Bonus和Cash输赢情况
     *
     *
     * @param $coin string (分)
     * @param $bet_amount string  下注金额(分)
     * @param $win_amount string 结算金额(分)
     * @return array
     */
    private function winningAndLosingStatus(string $coin,string $bet_amount,string $win_amount):array{
        //Cash下注金额,Bonus下注金额,Cash结算金额,Bonus结算金额,Cash实际输赢金额,Bonus实际输赢金额
        $cashBetAmount = '0';$bonusBetAmount = '0';$cashWinAmount = '0';$bonusWinAmount = '0';$cashTransferAmount = '0';$bonusTransferAmount = '0';
        //如果是子账单,有Cash余额就算Cash,没有Cash就算Bonus
        if($bet_amount <= 0){
            if($coin > 0){
                $cashBetAmount = $bet_amount;$cashWinAmount = $win_amount;$cashTransferAmount = bcsub($win_amount,$bet_amount,0);
            }else{
                $bonusBetAmount = $bet_amount;$bonusWinAmount = $win_amount;$bonusTransferAmount = bcsub($win_amount,$bet_amount,0);
            }
        }else{//如果是正常的母账单
            if($coin >= $bet_amount){ //如果coin大于下注金额
                $cashBetAmount = $bet_amount;$cashWinAmount = $win_amount;$cashTransferAmount = bcsub($win_amount,$bet_amount,0);
            }elseif ($coin > 0){//这里是coin和Bonus同时下注
                //求出比例
                $coin_bili = bcdiv($coin,$bet_amount,2);
                $cashBetAmount = $coin;
                $bonusBetAmount = bcsub($bet_amount,$coin,0);
                $cashWinAmount = bcmul($win_amount,$coin_bili,0);
                $bonusWinAmount = bcsub($win_amount,$cashWinAmount,0);
                $cashTransferAmount = bcsub($cashWinAmount,$cashBetAmount,0);
                $bonusTransferAmount = bcsub($bonusWinAmount,$bonusBetAmount,0);
            }else{ //只用Bonus下注
                $bonusBetAmount = $bet_amount;$bonusWinAmount = $win_amount;$bonusTransferAmount = bcsub($win_amount,$bet_amount,0);
            }
        }

        return [$cashBetAmount,$bonusBetAmount,$cashWinAmount,$bonusWinAmount,$cashTransferAmount,$bonusTransferAmount];

    }

    /**
     * 三方游戏(锦标赛，jockpot,adjust余额调整等处理)
     * @param array $slots_bonus_data 奖金数据
     * @param int $amount 奖金金额
     * @return int
     */
    public function slotsBonus(array $slots_bonus_data,int $amount):int{
        Db::beginTransaction();
        $res = Db::table('slots_bonus')->insert($slots_bonus_data);

        if($slots_bonus_data['type'] == 1){
            $reason = 12;
            $title = 'jockpot';
        }elseif($slots_bonus_data['type'] == 2){
            $reason = 13;
            $title = '锦标赛';
        }else{
            $reason = 14;
            $title = 'adjust余额调整';
        }

        if(!$res){
            Db::rollback();
            $this->logger->error($title.'日志创建失败:'.json_encode($slots_bonus_data));
            return 0;
        }

        $res = \App\Common\User::userEditCoin($slots_bonus_data['uid'],$amount,$reason,'玩家UID:'.$slots_bonus_data['uid'].'获取'.$title.'奖金:'.bcdiv((string)$amount,'100',2));
        if(!$res){
            Db::rollback();
            $this->logger->error('玩家-UID:'.$slots_bonus_data['uid'].'获取'.$title.'奖金-Cash:'.bcdiv((string)$amount,'100',2).'发放失败!');
            return 0;
        }
        Db::commit();
        return 1;
    }


    /**
     * 队列MQ处理游戏记录其它事情
     * @param $data
     * @return void
     */
    public function mqDealWith($data){
        //其它事务队列里面操作
        co(function () use ($data){
            $this->DealWithController->SlotsLogDealWith($data);
        });
//        try {
//            $data['consumer_tag'] = 'slotsLog';
//            $message = new SlotsProducer($data);
//            $this->producer->produce($message);
//        } catch (\Throwable $ex) {
//            $this->logger->error("三方游戏记录消息进入，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
//        }
        //MqProducer::pushMessage($data,config('rabbitmq.slots_queue'));
    }

    /**
     * 处理三方游戏数据
     * @param $data 存储slots_log数据表的数据
     * @param $coin (分)
     * @param $bonus (分)
     * @param $bet_amount 下注金额(分)
     * @param $win_amount 结算金额(分)
     * @param $type  1=添加游戏记录执行mq，2=添加数据不需要执行mq,3=修改数据同时不执行mq
     * @param $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return void
     */
    public function slotsLog($data,$coin,$bonus,$bet_amount,$win_amount,$type = 1,int $is_slots_time = 1){
        [$data['cashBetAmount'],$data['bonusBetAmount'],$data['cashWinAmount'],$data['bonusWinAmount'],$data['cashTransferAmount'],$data['bonusTransferAmount']] = $this->winningAndLosingStatus((string)$coin,(string)$bet_amount,(string)$win_amount);
        Db::beginTransaction();

        if($type != 3){
            $res = $this->installSlotsLog($data,$is_slots_time);
            if(!$res){
                $this->logger->error('uid:'.$data['uid'].'三方游戏记录表存储失败');
                Db::rollback();
                return ['code' => 201,'msg' => '三方游戏记录表存储失败'];
            }
        }else{
            $res = $this->updateSlotsLog($data['betId'],$data,$is_slots_time);
            if(!$res){
                $this->logger->error('uid:'.$data['uid'].'三方游戏记录表修改失败');
                Db::rollback();
                return ['code' => 201,'msg' => '三方游戏记录表修改失败'];
            }
        }

        $res = $this->userFundChange($data['uid'],$data['cashTransferAmount'],$data['bonusTransferAmount'],bcadd((string)$coin,(string)$data['cashTransferAmount'],0),bcadd((string)$bonus,(string)$data['bonusTransferAmount'],0),$data['channel'],$data['package_id']);
        if(!$res){
            $this->logger->error('uid:'.$data['uid'].'三方游戏余额修改失败-Cash输赢:'.$data['cashTransferAmount'].'-Bonus输赢:'.$data['bonusTransferAmount']);
            Db::rollback();
            return ['code' => 201,'msg' => '三方游戏余额修改失败'];
        }
        Db::commit();

        if($type == 1)$this->mqDealWith($data);

        return ['code' => 200,'msg' => '成功','data' => bcadd(bcadd((string)$coin,(string)$bonus,0),bcsub((string)$win_amount,(string)$bet_amount,0),0)];
    }


    public function slotsLog2($data,$coin,$bonus,$bet_amount,$bet_amount2,$win_amount,$type = 1,int $is_slots_time = 1,$change_type = 1){
        [$data['cashBetAmount'],$data['bonusBetAmount'],$data['cashWinAmount'],$data['bonusWinAmount'],$data['cashTransferAmount'],$data['bonusTransferAmount']] = $this->winningAndLosingStatus((string)$coin,(string)$bet_amount,(string)$win_amount);
        [$cashBetAmount,$bonusBetAmount,$cashWinAmount,$bonusWinAmount,$cashTransferAmount,$bonusTransferAmount] = $this->winningAndLosingStatus((string)$coin,(string)$bet_amount2,(string)$win_amount);
        Db::beginTransaction();

        if($type == 2){
            $res = $this->installSlotsLog($data,$is_slots_time);
            if(!$res){
                $this->logger->error('uid:'.$data['uid'].'三方游戏记录表存储失败');
                Db::rollback();
                return ['code' => 201,'msg' => '三方游戏记录表存储失败'];
            }
        }else {
            $res = $this->updateSlotsLog($data['betId'], $data, $is_slots_time);
            if (!$res) {
                $this->logger->error('uid:' . $data['uid'] . '三方游戏记录表修改失败');
                Db::rollback();
                return ['code' => 201, 'msg' => '三方游戏记录表修改失败'];
            }
        }

        //if ($type == 1) {
        if ($change_type == 1) {
            $res = $this->userFundChange($data['uid'], $cashTransferAmount, $bonusTransferAmount, bcadd((string)$coin, (string)$cashTransferAmount, 0), bcadd((string)$bonus, (string)$bonusTransferAmount, 0), $data['channel'], $data['package_id'], $change_type);
        }else{
            $res = $this->userFundChange($data['uid'], $cashTransferAmount, $bonusTransferAmount, $coin, $bonus, $data['channel'], $data['package_id'], $change_type);
        }
        if (!$res) {
            $this->logger->error('uid:' . $data['uid'] . '三方游戏余额修改失败-Cash输赢:' . $cashTransferAmount . '-Bonus输赢:' . $bonusTransferAmount);
            Db::rollback();
            return ['code' => 201, 'msg' => '三方游戏余额修改失败'];
        }
        //}
        Db::commit();

        if($type == 2)$this->mqDealWith($data);

        return ['code' => 200,'msg' => '成功','data' => bcadd(bcadd((string)$coin,(string)$bonus,0),bcsub((string)$win_amount,(string)$bet_amount2,0),0)];
    }

    public function slotsLogZy($data,$coin,$bonus,$is_slots_time=1){
        Db::beginTransaction();

        $res = $this->installSlotsLog($data,$is_slots_time);
        if(!$res){
            $this->logger->error('uid:'.$data['uid'].'三方游戏记录表存储失败');
            Db::rollback();
            return ['code' => 201,'msg' => '三方游戏记录表存储失败'];
        }

        $res = $this->userFundChange($data['uid'], $data['cashTransferAmount'], $data['bonusTransferAmount'], $coin, $bonus, $data['channel'], $data['package_id'], 2);
        if (!$res) {
            $this->logger->error('uid:' . $data['uid'] . '三方游戏余额修改失败-Cash输赢:' . $data['cashTransferAmount'] . '-Bonus输赢:' . $data['bonusTransferAmount']);
            Db::rollback();
            return ['code' => 201, 'msg' => '三方游戏余额修改失败'];
        }
        //}
        Db::commit();

        $this->mqDealWith($data);

        return ['code' => 200,'msg' => '成功','data' => bcadd((string)$coin,(string)$bonus)];
    }

    /**
     * 处理订单回滚重新进入计算
     * @param array $slotsData 游戏历史数据
     * @param array $userinfo 用户信息
     * @param int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return array
     */
    public function unresettlementDealWith(array $slotsData,array $userinfo,int $is_slots_time = 1){


        //用户本次需要修改的cash和bonus
        $editCash = $slotsData['cashWinAmount'] > 0 ? -$slotsData['cashWinAmount'] : 0;
        $editBonus = $slotsData['bonusWinAmount'] > 0 ? -$slotsData['bonusWinAmount'] : 0;

        $syCoin = bcadd((string)$userinfo['coin'],$editCash,0);  //用户实际剩余的cash
        $syBonus = bcadd((string)$userinfo['bonus'],$editBonus,0);//用户实际剩余的bonus

        $data = [
            'is_settlement' => 4,
            'cashWinAmount' => 0,
            'bonusWinAmount' => 0,
            'cashTransferAmount' => -$slotsData['cashBetAmount'],
            'bonusTransferAmount' => -$slotsData['bonusBetAmount'],
        ];

        $res = $this->UnOrReSettle($slotsData,$data,(string)$editCash,(string)$editBonus,$syCoin,$syBonus,$userinfo,$is_slots_time);
        if(!$res)return 0;

        return 1;


    }


    /**
     * 处理重新结算数据金额
     * @param array $slotsData 游戏历史数据
     * @param array $userinfo 用户信息
     * @param string $win_amount  结算金额
     * @param int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return array
     */
    public function resettlementDealWith(array $slotsData,array $userinfo,string $win_amount,int $is_slots_time = 1){
        [$data['cashWinAmount'],$data['bonusWinAmount'],$data['cashTransferAmount'],$data['bonusTransferAmount']] = $this->resultAmonut($slotsData,$userinfo,$win_amount);

        //用户本次需要修改的cash和bonus
        $editCash = bcsub($data['cashTransferAmount'],(string)$slotsData['cashTransferAmount'],0);
        $editBonus = bcsub($data['bonusTransferAmount'],(string)$slotsData['bonusTransferAmount'],0);

        $syCoin = bcadd((string)$userinfo['coin'],$editCash,0);  //用户实际剩余的cash
        $syBonus = bcadd((string)$userinfo['bonus'],$editBonus,0);//用户实际剩余的bonus


        $res = $this->UnOrReSettle($slotsData,$data,$editCash,$editBonus,$syCoin,$syBonus,$userinfo,$is_slots_time);
        if(!$res)return 0;

        return 1;
    }


    /**
     * 结算单独处理赢得结果
     * @param array $slotsData 游戏历史数据
     * @param array $userinfo 用户信息
     * @param string $win_amount  结算金额
     * @param string $type  类型:1=不执行mqdealWith表示订单未完成,2=执行mqdealWith表示订单已完成
     * @param  int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return array
     */
    public function resultDealWith(array $slotsData,array $userinfo,string $win_amount,int $type = 1,int $is_slots_time = 1):array{
        [$data['cashWinAmount'],$data['bonusWinAmount'],$data['cashTransferAmount'],$data['bonusTransferAmount']] = $this->resultAmonut($slotsData,$userinfo,$win_amount);
        //处理用户金额,这里由于用户的钱是开始就扣除了的，所有这里直接加结算金额
        $syCoin = bcadd((string)$userinfo['coin'],$data['cashWinAmount'],0);  //用户实际剩余的cash
        $syBonus = bcadd((string)$userinfo['bonus'],$data['bonusWinAmount'],0);//用户实际剩余的bonus

        Db::beginTransaction();
        $res = $this->userFundChange($slotsData['uid'],$data['cashWinAmount'],$data['bonusWinAmount'],$syCoin,$syBonus,$userinfo['channel'],$userinfo['package_id']);
        if(!$res){
            $this->logger->error('uid:'.$slotsData['uid'].'三方游戏resultDealWith余额修改失败-Cash输赢:'.$data['cashTransferAmount'].'-Bonus输赢:'.$data['bonusTransferAmount']);
            Db::rollback();
            return ['code' => 201,'data' => 0];
        }
        Db::commit();
        //将订单标记为完成
        co(function ()use ($slotsData,$data,$type,$is_slots_time){
            $UpdateSlotsLog = [
                'is_settlement' => $type === 1 ? 3 : 1,
                'cashWinAmount' => $data['cashWinAmount'],
                'bonusWinAmount' => $data['bonusWinAmount'],
                'cashTransferAmount' => $data['cashTransferAmount'],
                'bonusTransferAmount' => $data['bonusTransferAmount'],
            ];
            $this->updateSlotsLog($slotsData['betId'],$UpdateSlotsLog,$is_slots_time);
            //处理mq消息
            if($type === 2){
                $slotsData['is_settlement'] = 1;
                $slotsData['cashWinAmount'] = $data['cashWinAmount'];
                $slotsData['bonusWinAmount'] = $data['bonusWinAmount'];
                $slotsData['cashTransferAmount'] = $data['cashTransferAmount'];
                $slotsData['bonusTransferAmount'] = $data['bonusTransferAmount'];
                $this->mqDealWith($slotsData);
            }
        });
        return ['code' => 200,'data' => (int)bcadd($syCoin,$syBonus,0)];
    }


    /**
     * 只记录游戏输赢记录，同时执行事务
     * @param $data 存储slots_log数据表的数据
     * @param $coin (分)
     * @param $bonus (分)
     * @param $bet_amount 下注金额(分)
     * @param $win_amount 结算金额(分)
     * @param $type  1=正常计算，2=直接结算减去下注金额
     * @param $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return void
     */
    public function logDealWith($data,$coin,$bonus,$bet_amount,$win_amount,int $type = 1,int $is_slots_time = 1){
        if($type == 1){
            [$data['cashBetAmount'],$data['bonusBetAmount'],$data['cashWinAmount'],$data['bonusWinAmount'],$data['cashTransferAmount'],$data['bonusTransferAmount']] = $this->winningAndLosingStatus((string)$coin,(string)$bet_amount,(string)$win_amount);
            [$data['cashBetAmount'],$data['bonusBetAmount'],$data['cashWinAmount'],$data['bonusWinAmount'],$data['cashTransferAmount'],$data['bonusTransferAmount']] = $this->winningAndLosingStatus((string)$coin,(string)$bet_amount,(string)$win_amount);
        }else{
            $data['cashBetAmount'] = $bet_amount;$data['cashWinAmount']=$win_amount;
            $data['cashTransferAmount'] = bcsub((string)$win_amount,(string)$bet_amount,0);
        }
        Db::beginTransaction();
        $res = $this->installSlotsLog($data,$is_slots_time);
        if(!$res){
            $this->logger->error('uid:'.$data['uid'].'三方游戏记录表存储失败');
            Db::rollback();
            return ['code' => 201,'msg' => '三方游戏记录表存储失败'];
        }
        Db::commit();
        $this->mqDealWith($data);

        return ['code' => 200,'msg' => '成功','data' => bcadd(bcadd((string)$coin,(string)$bonus,0),bcsub((string)$win_amount,(string)$bet_amount,0),0)];
    }

    /**
     * 计算赢的结果的金额
     * @param array $slotsData 游戏历史数据
     * @param array $userinfo 用户信息
     * @param string $win_amount 赢得金额
     * @return string[]
     */
    private function resultAmonut(array $slotsData,array $userinfo,string $win_amount):array{
        //Cash结算金额,Bonus结算金额,Cash实际输赢金额,Bonus实际输赢金额
        $cashWinAmount = '0';$bonusWinAmount = '0';$cashTransferAmount = '0';$bonusTransferAmount = '0';
        //如果是子账单,有Cash余额就算Cash,没有Cash就算Bonus
        if($slotsData['cashBetAmount'] + $slotsData['bonusBetAmount'] <= 0){
            if($userinfo['coin'] > 0){
                $cashWinAmount = $win_amount;$cashTransferAmount = $win_amount;
            }else{
                $bonusWinAmount = $win_amount;$bonusTransferAmount = $win_amount;
            }
        }else{//如果是正常的母账单
            //计算bonus和cash分钱的比例
            $all_bet_amount = bcadd((string)$slotsData['cashBetAmount'],(string)$slotsData['bonusBetAmount'],0);
            $cash_bili = bcdiv((string)$slotsData['cashBetAmount'],$all_bet_amount,2); //cash结算比例
            $bonus_bili = bcdiv((string)$slotsData['bonusBetAmount'],$all_bet_amount,2); //bonus结算比例

            $cashWinAmount = bcmul($win_amount,$cash_bili,0);
            $bonusWinAmount = bcmul($win_amount,$bonus_bili,0);
            $cashTransferAmount = bcsub($cashWinAmount,(string)$slotsData['cashBetAmount'],0);
            $bonusTransferAmount = bcsub($bonusWinAmount,(string)$slotsData['bonusBetAmount'],0);
        }

        return [$cashWinAmount,$bonusWinAmount,$cashTransferAmount,$bonusTransferAmount];
    }



    /**
     * 处理slots退款的订单
     * @param $slotsLog
     * @param  int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return void
     */
    public function setRefundSlotsLog($slotsLog,$money,int $is_slots_time = 1){
        $refund_cash_amount = bcmul((string)$money,bcdiv((string)$slotsLog['cashBetAmount'],bcadd((string)$slotsLog['cashBetAmount'],(string)$slotsLog['bonusBetAmount'],0),2),0);
        $refund_bonus_amount = bcmul((string)$money,bcdiv((string)$slotsLog['bonusBetAmount'],bcadd((string)$slotsLog['cashBetAmount'],(string)$slotsLog['bonusBetAmount'],0),2),0);
        Db::beginTransaction();
        $userinfo = Db::table('userinfo')->select('coin','bonus')->where('uid',$slotsLog['uid'])->first();
        $res = $this->userFundChange($slotsLog['uid'],$refund_cash_amount,$refund_bonus_amount,bcadd((string)$userinfo['coin'],$refund_cash_amount,0),bcadd((string)$userinfo['bonus'],$refund_bonus_amount,0),$slotsLog['channel'],$slotsLog['package_id']);
        if(!$res){
            $this->logger->error('uid:'.$slotsLog['uid'].'三方游戏余额修改失败-Cash退还:'.$refund_cash_amount.'-Bonus退还:'.$refund_bonus_amount);
            Db::rollback();
            return ['code' => 201,'msg' => '三方游戏余额修改失败'];
        }
        $updateData = [
            'is_settlement' => 2,
            'cashRefundAmount' => $refund_cash_amount,
            'bonusRefundAmount' => $refund_bonus_amount,
        ];
        $res = $this->updateSlotsLog($slotsLog['betId'],$updateData,$is_slots_time);
        if(!$res){
            $this->logger->error('uid:'.$slotsLog['uid'].'三方游戏退还历史记录修改失败-betId:'.$slotsLog['betId']);
            Db::rollback();
            return ['code' => 201,'msg' => '三方游戏余额修改失败'];
        }

        Db::commit();

    }

    /**
     * 获取用户信息
     * @param $uid
     * @param $type 1 = 带入Bonus , 2=不带Bonus ，针对莫些不带bonus的游戏接口
     * @return array

     */
    public function getUserInfo($uid,int $type = 1){
        $Redis6379_3 = appCommon::Redis('RedisMy6379_3');
        $Redis6379_3->multi();
        $userinfo = $Redis6379_3->hGetAll('user_'.$uid);
        $results = $Redis6379_3->exec();
        $userinfo = json_decode(json_encode($results),true)[0];
        if (!$userinfo) {
            //$userinfo = Db::table('userinfo')->selectRaw('uid,puid,channel,package_id,coin,bonus,total_pay_score')->where('uid', $uid)->first();
            $userinfo = Db::table('userinfo as a')
                ->join('user_token as b','a.uid','=','b.uid')
                ->where('a.uid',$uid)
                ->selectRaw('br_a.uid,br_a.puid,br_a.coin,br_a.bonus,br_a.total_pay_score,br_a.channel,br_a.package_id,
            br_b.token')
                ->first();
            $Redis6379_3->hMSet('user_'.$uid, $userinfo);
            $Redis6379_3->expire('user_'.$uid, 3600*24*2);
        }

//        $this->logger->error('$userinfo redis:=>'.json_encode($userinfo));

        if(!$userinfo)return [];
        //如果要带Bonus进入,就把这句话注释了
        $userinfo['bonus'] = ($type == 1 && config('slots.is_carry_bonus') == 1 && (appCommon::getConfigValue('recharge_and_get_bonus') >= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        return $userinfo;
    }

    /**
     * 通过token获取用户信息
     * @param $token
     * @param int $type
     * @return array|\Hyperf\Database\Model\Model|\Hyperf\Database\Query\Builder|mixed[]|object|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function getUserInfoToken($token,int $type = 1){
        $Redis6379_2 = appCommon::Redis('RedisMy6379_2');
        $userinfo = $Redis6379_2->hGetAll($token);
        if (!$userinfo) {
            $userinfo = Db::table('user_token as a')
                ->join('userinfo as b','a.uid','=','b.uid')
                ->where('a.token',$token)
                ->selectRaw('br_b.uid,br_b.puid,br_b.coin,br_b.bonus,br_b.total_pay_score,br_b.channel,br_b.package_id')
                ->first();

            $Redis6379_2->hMSet($token, $userinfo);
            $Redis6379_2->expire($token, 3600*24*2);
        }


        if(!$userinfo)return [];
        //如果要带Bonus进入,就把这句话注释了
        $userinfo['bonus'] = ($type == 1 && config('slots.is_carry_bonus') == 1 && (appCommon::getConfigValue('recharge_and_get_bonus') >= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        return $userinfo;
    }


    /**
     * 获取Slots下注修改数据
     * @param $data
     * @return array
     */
    private function getUpdateSlotsData(array $data){
        return [
            'is_settlement' => 1,
            'betEndTime' => time(),
            'cashBetAmount' => $data['cashBetAmount'],
            'bonusBetAmount' => $data['bonusBetAmount'],
            'cashWinAmount' => $data['cashWinAmount'],
            'bonusWinAmount' => $data['bonusWinAmount'],
            'cashTransferAmount' => $data['cashTransferAmount'],
            'bonusTransferAmount' => $data['bonusTransferAmount'],
        ];
    }


    /**
     * 通过TraceId获取用户的Uid
     * @param $token string  token
     * @return void
     */
    public function getUserUid(string $token){
        $Redis6379_2 = appCommon::Redis('RedisMy6379_2');
        $uid = $Redis6379_2->hGet($token, 'uid');
        if (!$uid) {
            $userinfo = Db::table('user_token as a')
                ->join('userinfo as b','a.uid','=','b.uid')
                ->where('a.token',$token)
                ->selectRaw('br_b.uid,br_b.puid,br_b.coin,br_b.bonus,br_b.total_pay_score,br_b.channel,br_b.package_id')
                ->first();
            $uid = $userinfo['uid'];

            $Redis6379_2->hMSet($token, $userinfo);
            $Redis6379_2->expire($token, 3600*24*2);
        }
        return $uid;
    }



    /**
     * 通过UID获取玩家Token
     * @param $uid int  uid
     * @return void
     */
    public function getUserToken(int $uid){
        return Db::table('user_token')->where('uid',$uid)->value('token');
    }


    /**
     * 存储三方游戏数据
     * @param array $slotsData 三方游戏数据
     * @param  int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return int
     */
    public function installSlotsLog(array $slotsData,int $is_slots_time = 1):int{
        $redis = appCommon::Redis('RedisMy6379');
        $status = 0;
        $data = [];
        foreach ($slotsData as $key => $value){
            if($key == 'is_settlement')$status = 1;
            $data[$key] = (string)$value;
        }
        if(!$status)$data['is_settlement'] = '1';
        $redis->hMset((string)$slotsData['betId'],$data);
        if($is_slots_time == 1){
            $redis->expire((string)$slotsData['betId'], 1800);
        }else{
            $redis->expire((string)$slotsData['betId'], 1296000);
        }
        return 1;



//        $status = 0;
//        foreach ($slotsData as $key => $value){
//            if($key == 'is_settlement')$status = 1;
//            $redis->hSet((string)$slotsData['betId'],$key,(string)$value);
//        }
//        if(!$status)$redis->hSet((string)$slotsData['betId'],'is_settlement','1');
//        if($is_slots_time == 1){
//            $redis->expire((string)$slotsData['betId'], 1800);
//        }else{
//            $redis->expire((string)$slotsData['betId'], 126000);
//        }
//        return 1;



//        $date = date('Ymd');
//        Db::beginTransaction();
//        $res = Db::table('slots_log_'.$date)->insert($slotsData);
//        if(!$res){
//            $this->logger->error('installSlotsLog方法中存储slots_log失败-存储的数据为:'.json_encode($slotsData));
//            Db::rollback();
//            return 0;
//        }
//
//        if($is_slots_time == 2){
//            //存储sbs游戏数据是哪一天的
//            $res = Db::table('slots_time')->insert([
//                'betId' => $slotsData['betId'],
//                'date' => $date,
//            ]);
//            if(!$res){
//                $this->logger->error('installSlotsLog方法中存储slots_time数据失败-存储的数据为:'.json_encode($slotsData));
//                Db::rollback();
//                return 0;
//            }
//        }
//        Db::commit();
//        return 1;
    }


    /**
     * 修改SlotsLog 数据表
     * @param string $betId 下注ID
     * @param array $updateData 修改的数据
     * @param  int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return int
     */
    public function updateSlotsLog(string $betId,array $updateData,int $is_slots_time = 1):int{
        $redis = appCommon::Redis('RedisMy6379');

        $data = [];
        foreach ($updateData as $key => $value){
            $data[$key] = (string)$value;
        }
        $redis->hMSet($betId, $data);
        if($is_slots_time == 1){
            $redis->expire($betId, 1800);
        }else{
            $redis->expire($betId, 1296000);
        }
        return 1;



//        foreach ($updateData as $key => $value){
//            $redis->hSet($betId,$key,(string)$value);
//        }
//
//        if($is_slots_time == 1){
//            $redis->expire($betId, 1800);
//        }else{
//            $redis->expire($betId, 126000);
//        }
//        return 1;



//        if($is_slots_time == 1){
//            $res = Db::table('slots_log_'.date('Ymd'))->where('betId',$betId)->update($updateData);
//            if(!$res)Db::table('slots_log_'.date('Ymd',strtotime( '-1 day')))->where('betId',$betId)->update($updateData);
//        }else{
//            $date = Db::table('slots_time')->where('betId',$betId)->value('date');
//            if(!$date){
//                $this->logger->error('未在slots_time找到betId为'.$betId.'的数据');
//                return 1;
//            }
//            Context::set('slots_time_date', $date);//设置哪一天数据修改其它天表好使用
//            $res = Db::table('slots_log_'.$date)->where('betId',$betId)->update($updateData);
//        }
//        return $res;
    }
    /**
     * 修改SlotsLog 数据表
     * @param string $betId 下注ID
     * @param int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return int
     */
    public function deleteSlotsLog(string $betId,int $is_slots_time = 1):int{

        $redis = appCommon::Redis('RedisMy6379');
        $redis->del($betId);
        return 1;
//        return $redis->hGetAll($betId);
//        if($is_slots_time == 1){
//            $res = Db::table('slots_log_'.date('Ymd'))->where('betId',$betId)->delete();
//            if(!$res)Db::table('slots_log_'.date('Ymd',strtotime( '-1 day')))->where('betId',$betId)->delete();
//        }else{
//            $date = Db::table('slots_time')->where('betId',$betId)->value('date');
//            if(!$date){
//                $this->logger->error('未在slots_time找到betId为'.$betId.'的数据');
//                return 1;
//            }
//            $res = Db::table('slots_log_'.$date)->where('betId',$betId)->delete();
//            Db::table('slots_time')->where('betId',$betId)->delete();
//        }
//        return $res;
    }

    /**
     * @param string $betId 下注ID
     * @param int $is_sleep 是否等待一秒在重新查看数据 1 = 是 ，0= 否
     * @param int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行

     */
    public function SlotsLogView(string $betId,int $is_sleep = 0,int $is_slots_time = 1){

        $redis = appCommon::Redis('RedisMy6379');
        return $redis->hGetAll($betId);

//        if($is_slots_time == 1){
//            $slots_log = Db::table('slots_log_'.date('Ymd'))->where('betId',$betId)->first();
//            if(!$slots_log) $slots_log = Db::table('slots_log_'.date('Ymd',strtotime( '-1 day')))->where('betId',$betId)->first();
//        }else{
//            $date = Db::table('slots_time')->where('betId',$betId)->value('date');
//            if(!$date){
//                $this->logger->error('未在slots_time找到betId为'.$betId.'的数据');
//                return [];
//            }
//            $slots_log = Db::table('slots_log_'.$date)->where('betId',$betId)->first();
//        }
//
//        if(!$slots_log && $is_sleep == 1){ //这里在消费游戏的游戏,可能还没有创建起游戏，这里需要等待1秒，数据表创建起了游戏在消费
//            sleep(1);
//            return $this->SlotsLogView($betId,0,$is_slots_time);
//        }
//        return $slots_log;
    }


    /**
     * 获取时间
     * @return string
     */
    public function getDate(){
        date_default_timezone_set(env('APP_TIMEZONE'));
        return date('c');
    }


    /**
     * 资金变化
     * @param $uid 用户id
     * @param $cash_transferAmount 变化cash金额
     * @param $bouns_transferAmount 变化bouns金额
     * @param $new_cash 变化后cash金额
     * @param $new_bouns 变化后bouns金额
     * @param $channel 渠道号
     * @param $package_id 包id
     * @return void
     * @throws \think\db\exception\DbException
     */
    public function userFundChange($uid, $cash_transferAmount, $bouns_transferAmount,$new_cash, $new_bouns, $channel, $package_id,$type=1){
        if($cash_transferAmount == 0 && $bouns_transferAmount == 0)return true;
        $res = 1;
        if ($type == 1) {
            $Redis6379_3 = appCommon::Redis('RedisMy6379_3');
            $Redis6379_3->multi();
            $user_redis = $Redis6379_3->hGetAll('user_'.$uid);
            //$this->logger->error('user_redis:==>'.json_encode($user_redis));
            //更新redis
            $Redis6379_3->hIncrBy('user_' . $uid, 'coin', (int)$cash_transferAmount);
            $Redis6379_3->expire('user_' . $uid, 3600*24*2);
            $results = $Redis6379_3->exec();
            $user_redis = json_decode(json_encode($results),true)[0];

            $Redis6379_2 = appCommon::Redis('RedisMy6379_2');
            $Redis6379_2->hIncrBy($user_redis['token'], 'coin', (int)$cash_transferAmount);
            $Redis6379_2->expire($user_redis['token'], 3600*24*2);

            if (config('slots.is_carry_bonus') == 1) {
                $res = Db::table('userinfo')->where('uid', $uid)->update([
                    'coin' => Db::raw('coin + ' . $cash_transferAmount),
                    'bonus' => Db::raw('bonus + ' . $bouns_transferAmount),
                ]);

                $Redis6379_3->hIncrBy('user_' . $uid, 'bonus', (int)$bouns_transferAmount);
                $Redis6379_2->hIncrBy($user_redis['token'], 'bonus', (int)$bouns_transferAmount);
            } else {
                $res = Db::table('userinfo')->where('uid', $uid)->update(['coin' => Db::raw('coin + ' . $cash_transferAmount)]);
            }



        }

        if ($cash_transferAmount != 0) {
            $cash_data = [
                'uid' => $uid,
                'num' => $cash_transferAmount,
                'total' => $new_cash,
                'reason' => 3,
                'type' => $cash_transferAmount < 0 ? 0 : 1,
                'content' => '玩家:' . $uid . '玩三方游戏，资金变化' . $cash_transferAmount,
                'channel' => $channel,
                'package_id' => $package_id,
                'createtime' => time(),
            ];
            Db::table('coin_' . date('Ymd'))->insert($cash_data);
        }

        if ($bouns_transferAmount != 0) {
            $bouns_data = [
                'uid' => $uid,
                'num' => $bouns_transferAmount,
                'total' => $new_bouns,
                'reason' => 3,
                'type' => $bouns_transferAmount < 0 ? 0 : 1,
                'content' => '玩家:' . $uid . '玩三方游戏，资金变化' . $bouns_transferAmount,
                'channel' => $channel,
                'package_id' => $package_id,
                'createtime' => time(),
            ];
            Db::table('bonus_' . date('Ymd'))->insert($bouns_data);
        }
        if ($res){
            return true;
        }else{
            return false;
        }
    }


    /**
     * 处理重新计算数据与回滚数据
     * @param array $slotsData  游戏历史数据
     * @param array $data 本次修改的 $slotsData 数据
     * @param string $editCash 修改的Cash
     * @param string $editBonus 修改的bonus
     * @param string $syCoin 剩余的cash
     * @param string $syBonus 剩余的bonus
     * @param array $userinfo 用户数据
     * @param int $is_slots_time $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return int
     */

    private function UnOrReSettle(array $slotsData,array $data,string $editCash,string $editBonus,string $syCoin,string $syBonus,array $userinfo,int $is_slots_time = 1):int{
        $this->updateSlotsLog($slotsData['betId'],$data,$is_slots_time);
        if($editCash == 0 && $editBonus == 0){
            return 1;
        }

        Db::beginTransaction();

        $res = $this->userFundChange($slotsData['uid'],$editCash,$editBonus,$syCoin,$syBonus,$userinfo['channel'],$userinfo['package_id']);
        if(!$res){
            $this->logger->error('uid:'.$slotsData['uid'].'三方游戏UnOrReSettle用户余额修改失败-Cash修改:'.$editCash.'-Bonus修改:'.$editBonus);
            Db::rollback();
            return 0;
        }

        $withdraw_money = $this->getWithdrawMoney($slotsData,$data,$syCoin,(string)$slotsData['uid']);
        $res = Db::table('userinfo')->where('uid',$slotsData['uid'])->update([
            'cash_total_score' => Db::raw('cash_total_score + '.$editCash),
            'bonus_total_score' => Db::raw('bonus_total_score + '.$editBonus),
            'withdraw_money' =>$withdraw_money,
        ]);
        if(!$res){
            $this->logger->error('uid:'.$slotsData['uid'].'三方游戏UnOrReSettle用户总输赢修改失败-cashTotalScore修改:'.$editCash.'-bonusTotalScore修改:'.$editBonus);
            Db::rollback();
            return 0;
        }
        //修改用户的user_day数据
        $date = Context::get('slots_time_date','');

        $table = $date ? 'user_day_'.$date : 'user_day_'.date('Ymd');
        $res = Db::table($table)->where('uid',$slotsData['uid'])->update([
            'cash_total_score' =>  Db::raw('cash_total_score + '.$editCash),
            'bonus_total_score' =>  Db::raw('bonus_total_score + '.$editBonus),
        ]);
        if(!$res){
            $this->logger->error('uid:'.$slotsData['uid'].'三方游戏UnOrReSettle用户每日总输赢修改失败-cashTotalScore修改:'.$editCash.'-bonusTotalScore修改:'.$editBonus);
            Db::rollback();
            return 0;
        }
        Db::commit();


        return 1;
    }

    /**
     * 获取本次修改的提现额度
     * @param array $slotsData 之前的历史数据
     * @param array $data 本次修改的值
     * @param string $syCoin 用户剩余的cash
     * @return string
     */
    private function getWithdrawMoney(array $slotsData,array $data,string $syCoin,string $uid):string{
        //用户剩余的退款额度
        $sy_withdraw_money = Db::table('userinfo')->where('uid',$uid)->value('withdraw_money');

        //修改用户总输赢、提现额度
        $withdraw_money = '0';
        if($slotsData['cashTransferAmount'] > 0 && $data['cashTransferAmount'] < 0){
            $withdraw_money = bcsub('0',(string)$slotsData['cashTransferAmount'],0); //把之前赢得直接减去
        }elseif ($slotsData['cashTransferAmount'] < 0 && $data['cashTransferAmount'] > 0){
            $withdraw_money = (string)$data['cashTransferAmount']; //加上本次的赢钱额度
        }elseif ($slotsData['cashTransferAmount'] > 0 && $data['cashTransferAmount'] > 0){
            $withdraw_money = bcsub((string)$data['cashTransferAmount'],(string)$slotsData['cashTransferAmount'],0);//本次减去之前的额度
        }
        $really_withdraw_money = bcadd($sy_withdraw_money,$withdraw_money,0);//用户本次真实的退款额度
        if($really_withdraw_money > $syCoin)return $syCoin;
        return $really_withdraw_money;
    }

    /**
     * 获取用户余额redis的值
     * @param $uid
     * @return array|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function getRedisUser($uid){
        $Redis5502 = appCommon::Redis('Redis5502');

        return $Redis5502->hMGet('user_'.$uid, ['coins','bonus']);
    }

    /**
     * 设置用户redis信息
     * @param $uid
     * @param
     * @param
     * @return bool|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function setRedisUser($uid){
        $userinfo = Db::table('userinfo as a')
            ->join('user_token as b','a.uid','=','b.uid')
            ->where('a.uid',$uid)
            ->selectRaw('br_a.puid,br_a.coin,br_a.bonus,br_a.total_pay_score,br_a.channel,br_a.package_id,
            br_b.token')
            ->first();
        if (empty($userinfo)) return;

        $Redis6379_2 = appCommon::Redis('RedisMy6379_2');
        $token_data = [
            'uid' => $uid,
            'puid' => $userinfo['puid'],
            'coin' => $userinfo['coin'],
            'bonus' => $userinfo['bonus'],
            'total_pay_score' => $userinfo['total_pay_score'],
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
        ];
        $Redis6379_2->hMSet($userinfo['token'], $token_data);
        $Redis6379_2->expire($userinfo['token'], 3600*24*2);

        $Redis6379_3 = appCommon::Redis('RedisMy6379_3');
        $uid_data = [
            'token' => $userinfo['token'],
            'uid' => $uid,
            'puid' => $userinfo['puid'],
            'coin' => $userinfo['coin'],
            'bonus' => $userinfo['bonus'],
            'total_pay_score' => $userinfo['total_pay_score'],
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
        ];
        $Redis6379_3->hMSet('user_'.$uid, $uid_data);
        $Redis6379_3->expire('user_'.$uid, 3600*24*2);

        return true;

    }

    /**
     * 获取玩家当前进入的游戏
     * @param $uid
     * @param string $terrace_name
     * @return int
     */
    public function getUserRunGameTage($uid,string $terrace_name):int{
        $Redis = appCommon::Redis('Redis5501');
        if($terrace_name == 'sbs')$uid = explode('_',$uid)[0]; //sbs这里测试环境有个下划线，所以这里需要分隔拿出UID
        $really_terrace_name = $Redis->hGet('gameTage',(string)$uid);
        if($really_terrace_name && $really_terrace_name != $terrace_name){
            $this->logger->error('用户UID:'.$uid.'下注游戏厂商验证失败-玩家记录的游戏厂商:'.$really_terrace_name.'-实际下注的游戏厂商:'.$terrace_name);
            return 0;
        }
        return 1;
    }
}





