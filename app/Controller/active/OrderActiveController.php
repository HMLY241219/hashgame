<?php

declare(strict_types=1);

namespace App\Controller\active;

use App\Common\Common;
use App\Common\User;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use App\Controller\OrderController;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use function Hyperf\Config\config;
use function Hyperf\Support\value;


#[Controller(prefix:'orderActive')]
class OrderActiveController extends AbstractController
{

    #[Inject]
    protected OrderController $order;
    private array $BankruptcyWheelImage = [
        1 => '/uploads/bankruptcywheel/cash.png',
        2 => '/uploads/bankruptcywheel/bonus.png',
        3 => '/uploads/bankruptcywheel/empty.png'
    ];

    /**
     * 判断用户是否能参加活动3天活动
     * @return
     */
    #[RequestMapping(path:'getThreeDayPassStatus')]
    public function getThreeDayPassStatus(){
        $uid = $this->request->post('uid');
        //效验是否满足领取条件
        $order_active_array = $this->getRechargeActivityAmount((int)$uid);
        if($order_active_array) return $this->ReturnJson->successFul(200, $order_active_array);

        $userinfo = Db::table('userinfo')
            ->select(Db::raw('total_pay_score,coin,bonus,(total_cash_water_score + total_bonus_water_score) as total_water_score,total_give_score'))
            ->where('uid',$uid)
            ->first();
        if(!$userinfo || $userinfo['total_pay_score'] <= 0)return $this->ReturnJson->successFul(200, []);

        $withdraw_money = \App\Controller\WithdrawlogController::getUserTotalExchangeMoney($uid);  //用户提现的金额，包含待审核和处理中
        $withdraw_bili =  bcdiv((string)$withdraw_money,(string)$userinfo['total_pay_score'],2);
        $user_recharge_amount_bili = bcdiv((string)$userinfo['total_water_score'],bcadd((string)$userinfo['total_pay_score'],(string)$userinfo['total_give_score'],0),2); //打码量比例
        $order_active = Db::table('order_active')
            ->select('id','price','get_cash','get_bonus','next_get_cash','next_get_bonus','last_get_cash','last_get_bonus','num','active_tage_hour','type','image')
            ->where([
                ['min_order_price','<=',$userinfo['total_pay_score']],
                ['max_order_price','>=',$userinfo['total_pay_score']],
                ['lose_water_multiple','>=',$user_recharge_amount_bili],
                ['withdraw_bili','>=',$withdraw_bili],
                ['lose_money','>=',bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0)],
                ['status','=',1],
                ['type','=',1],
            ])
            ->first();
        if(!$order_active)return $this->ReturnJson->successFul(200, []);

        //判断自己当前是否还存在该活动没有领取完
        $order_active_log_num = Db::table('order_active_log')->where(['type' => 1,'uid' => $uid])->orderBy('id','desc')->value('num');
        if($order_active_log_num)return $this->ReturnJson->successFul(200, []);

        //判断玩家总参与次数是否已经达到了最大值
        $order_active_log_all_num = Db::table('order_active_log')->where(['type' => 1,'uid' => $uid])->count();
        if($order_active_log_all_num >= $order_active['num'])return $this->ReturnJson->successFul(200, []);


        //检测IP是否能参加活动
        $res = $this->order->getOrderIp((int)$uid);
        if(!$res)return $this->ReturnJson->successFul(200, []);


        //检查用户是否活动已经被标记，如果标记且时间过了，则不能参与活动
        $active_tage_createtime = Db::table('order_active_tage')->where(['active_id' => $order_active['id'],'uid' => $uid])->value('createtime');
        if($active_tage_createtime && $order_active['active_tage_hour'] && time() > bcadd((string)$active_tage_createtime,bcmul((string)$order_active['active_tage_hour'],'3600',0),0))return $this->ReturnJson->successFul(200, []);

        //3天卡不存在标记的话就直接打标记
        if(!$active_tage_createtime)Db::table('order_active_tage')->insert([
            'uid' => $uid,
            'active_id' => $order_active['id'],
            'type' => 1,
            'createtime' => time(),
        ]);

        $order_active['time'] = time();
        $order_active['tage_createtime'] = $active_tage_createtime ?: time();
        $order_active['is_pay_status'] = 1; //是否是支付状态 ： 0 = 领取，1 = 支付
        $order_active['image'] = $this->getActiveImage($order_active['image']);
        return $this->ReturnJson->successFul(200, $order_active);
    }

    /**
     * 获取月卡状态
     * @return null
     */
    #[RequestMapping(path:'getMonthlyCardStatus')]
    public function getMonthlyCardStatus(){
        $uid = $this->request->post('uid');
        //效验是否满足领取条件
        $order_active_array = $this->getRechargeActivityAmount((int)$uid,2);
        if($order_active_array) return $this->ReturnJson->successFul(200, $order_active_array);
        $order_active = Db::table('order_active')
            ->select('id','get_cash','get_bonus','image','day','price')
            ->where('type',2)
            ->where('status',1)
            ->orderBy('id','desc')
            ->first();
        if($order_active)$order_active['image'] = $this->getActiveImage($order_active['image']);
        return $this->ReturnJson->successFul(200, $order_active);

    }


    /**
     * 检查破产活动是否能参与
     * @return void
     */
    #[RequestMapping(path:'getBankruptcyActivitiesStatus')]
    public function getBankruptcyActivitiesStatus(){
        $uid = $this->request->post('uid');
        $is_two_status = $this->request->post('is_two_status') ?? 0; //是否是客户端连续第二次请求 1 = 记录标记 ,2=不记录标记
        $bankruptcy_config = Common::getMore('bankruptcy_touchoff_num,bankruptcy_water_num');
        //用户触发的次数
        $order_active_tage = Db::table('order_active_tage')->where(['uid' => $uid,'type' => 3])->orderBy('createtime','desc')->get()->toArray();
        if($bankruptcy_config['bankruptcy_touchoff_num'] && $order_active_tage &&  count($order_active_tage) >= $bankruptcy_config['bankruptcy_touchoff_num'])return $this->ReturnJson->successFul(200, ['status' => 0]);

        //检查最近一次触发完成以后是否已经充值
        $userPayCreatetime = Db::table('order')->where(['uid' => $uid,'pay_status' => 1])->orderBy('createtime','desc')->value('createtime');
        if(!$userPayCreatetime || (!$is_two_status && count($order_active_tage) > 0 &&  $userPayCreatetime < $order_active_tage[0]['createtime']))return $this->ReturnJson->successFul(200, ['status' => 0]);

        $data = $this->conditionMaximumAmount($uid);
        if($data['code'] != 200)return $this->ReturnJson->successFul(200, ['status' => 0]);
        $userinfo = $data['userinfo'];
        $max_recharge_amount = $data['max_recharge_amount'];

        $withdraw_money = Db::table('withdraw_log')->where('uid',$uid)->whereIn('status',[0,3,1])->sum('money');
        //退款比例
        $withdraw_bili = bcdiv((string)$withdraw_money,(string)$userinfo['total_pay_score'],2);  // 提现/ 充值

        //流水倍数
        $user_recharge_amount_bili = bcdiv((string)$userinfo['total_water_score'],bcadd((string)$userinfo['total_pay_score'],(string)$userinfo['total_give_score'],0),2); //打码量比例

        //第二次进来，直接拿配置最低流水倍的数据
        if($is_two_status){
            //破产活动第一次高于多少倍以后，第二次触发低于多少倍破产活动
            $active_water_multiple = $bankruptcy_config['bankruptcy_water_num'];
        }else{
            $active_water_multiple = $user_recharge_amount_bili;
        }


        //检查破产活动是否能参与
        $order_active = Db::table('order_active')
            ->select('id','price','get_cash','get_bonus','next_get_cash','num','image','type')
            ->where([
                ['type', '=' ,3],
                ['status', '=' , 1],
                ['min_order_price','<=',$max_recharge_amount],
                ['max_order_price','>=',$max_recharge_amount],
                ['lose_money','>=',bcdiv(bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),(string)$userinfo['total_pay_score'],2)],
                ['lose_water_multiple','>=',$active_water_multiple],
                ['high_water_multiple','<',$active_water_multiple],
                ['withdraw_bili','>=',$withdraw_bili],
            ])->first();
        $activeData = $this->getOrderActive($order_active,$uid);

        if($activeData['status'] == 1){
            $activeData['BankruptcyWheel'] = [];
            $is_fw_two_status = $is_two_status;

            //破产转动转盘次数
            $activeData['bankruptcy_wheel_num'] = Common::getConfigValue('bankruptcy_wheel_num'); //转盘转动次数

            if(!$is_two_status){
                Db::table('order_active_tage')->insert([
                    'uid' => $uid,
                    'active_id' => $order_active['id'],
                    'type' => 3,
                    'createtime' => time(),
                ]);
                //如果首次流水倍数低于配置&& 转盘次数大于0直接掉起转盘
                if($user_recharge_amount_bili <= $bankruptcy_config['bankruptcy_water_num'] &&  $activeData['bankruptcy_wheel_num'] > 0)$activeData['BankruptcyWheel'] = $this->getBankruptcyWheelConfig($userinfo);
            }


//            $activeData['bankruptcy_water_num'] = $bankruptcy_config['bankruptcy_water_num']; //破产活动第一次高于多少倍以后，第二次触发低于多少倍破产活动
//            $activeData['user_water_bili'] = $user_recharge_amount_bili; //流水倍数
            if($activeData['BankruptcyWheel']){
                $tag =  time().rand(10000,99999);
                $activeData['BankruptcyWheelTag'] = $tag;
                Db::table('bankruptcy_wheel_tag')->insert([ //存储标记
                    'uid' => $uid,
                    'tag' => $tag,
                ]);
                $is_fw_two_status = 1;
            }
            $activeData['is_fw_two_status'] = $is_fw_two_status; //是否再次请求
        }

        return $this->ReturnJson->successFul(200, $activeData);
    }

    /**
     * 领取转盘奖励
     * @return void
     */
    #[RequestMapping(path:'receiveBankruptcyWheel')]
    public function receiveBankruptcyWheel(){
        $uid = $this->request->post('uid');
        $BankruptcyWheelTag = $this->request->post('BankruptcyWheelTag');

        $userinfo = Db::table('userinfo')->select('total_pay_score')->where('uid',$uid)->first();
        if(!$userinfo)return $this->ReturnJson->failFul(226);

        //判断标记是否存在
        $bankruptcy_wheel_tag = Db::table('bankruptcy_wheel_tag')->where(['tag' => $BankruptcyWheelTag,'uid' => $uid])->value('id');
        if(!$bankruptcy_wheel_tag)return $this->ReturnJson->failFul();

        //判断用户旋转转盘的次数是否已经用完
        $bankruptcy_wheel_num = Common::getConfigValue('bankruptcy_wheel_num'); //转盘转动次数
        $userTriggerNum = Db::table('bankruptcy_wheel_log')->where(['tag' => $BankruptcyWheelTag,'uid' => $uid])->count();

        if($bankruptcy_wheel_num <= $userTriggerNum)return $this->ReturnJson->failFul(273);

        //获取转盘配置
        $BankruptcyWheelConfig = $this->getBankruptcyWheelConfig($userinfo,2);
        //获取抽奖转盘的key值
        $WheelConfigKey = Common::proportionalProbabilityAward($BankruptcyWheelConfig,'probability_config');
        $WheelConfigArray = $BankruptcyWheelConfig[$WheelConfigKey];

        //用户此次已经参与的金额
        $receivedAmount = Db::table('bankruptcy_wheel_log')->where(['tag' => $BankruptcyWheelTag,'uid' => $uid])->sum('amount');
        //用户还剩下多少金额
        $remainingReceivedAmount = bcsub(bcmul((string)$userinfo['total_pay_score'],(string)$WheelConfigArray['zs_pay_bili'],0),(string)$receivedAmount,0);
        if($remainingReceivedAmount <= 0)return $this->ReturnJson->failFul(273);

        if(($userTriggerNum + 1) < $bankruptcy_wheel_num){
            $reallyAmount = rand(1,(int)$remainingReceivedAmount);
        }else{
            $reallyAmount = $remainingReceivedAmount;
        }

        Db::beginTransaction();

        $res = Db::table('bankruptcy_wheel_log')->insert([
            'uid' => $uid,
            'tag' => $BankruptcyWheelTag,
            'all_price' => $userinfo['total_pay_score'],
            'amount' => $reallyAmount,
            'type' => $WheelConfigArray['currency_config'],
            'createtime' => time(),
        ]);
        if(!$res){
            Db::rollback();
            return $this->ReturnJson->failFul(249);
        }

        //赠送Cash
        if($WheelConfigArray['currency_config'] == 1){
            $res = User::userEditCoin($uid,$reallyAmount,26,'用户:'.$uid.'破产转盘:'.bcdiv((string)$reallyAmount,'100',2),2,16);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }


            $res = User::editUserTotalGiveScore($uid,$reallyAmount);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }

        }else{ //赠送Bonus
            $res = User::userEditBonus($uid,$reallyAmount,26,'用户:'.$uid.'破产转盘'.bcdiv((string)$reallyAmount,'100',2),2,16);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }
        Db::commit();
        return $this->ReturnJson->successFul(200, ['id' => $WheelConfigArray['id'] ,'money' => $reallyAmount]);
    }


    /**
     * 获取转盘配置
     * @param array $userinfo
     * @param int $type 1 = 客户端显示转盘 ， 2=用户抽奖
     * @return void
     */
    private function getBankruptcyWheelConfig(array $userinfo,int $type = 1):array{
        $data = [];
        $bankruptcy_wheel = Db::table('bankruptcy_wheel')
            ->where([
                ['minmoney','<=',$userinfo['total_pay_score']],
                ['maxmoney','>=',$userinfo['total_pay_score']],
             ])
            ->orderBy('id')
            ->first();
        if(!$bankruptcy_wheel)return [];
        $amount_config = $this->dealWithBankruptcyWheelValue($bankruptcy_wheel['amount_config']);
        if($type == 1){
            $image_config = $this->dealWithBankruptcyWheelValue($bankruptcy_wheel['image_config']);
            $image_address = $this->BankruptcyWheelImage;
            foreach ($amount_config as $key => $v){
                $data[] = [
                    'id' => $key,
                    'amount' => $v,
                    'image' =>  $image_config[$key] > 0 ? $this->getActiveImage($image_address[$image_config[$key]]) : '',
                    'image_config' =>$image_config[$key], //图片配置(0=奖励直接显示,1=C,2=B,3=哭)
                ];
            }
        }else{
            $probability_config = $this->dealWithBankruptcyWheelValue($bankruptcy_wheel['probability_config']);
            $currency_config = $this->dealWithBankruptcyWheelValue($bankruptcy_wheel['currency_config']);
            foreach ($amount_config as $key => $v){
                $data[] = [
                    'id' => $key,
                    'amount' => $v,
                    'probability_config' => $probability_config[$key], //概率
                    'currency_config' =>$currency_config[$key],//货币配置(1=C,2=B)
                    'zs_pay_bili' =>$bankruptcy_wheel['zs_pay_bili'],//赠送充值的比例
                ];
            }
        }
        return $data;
    }

    /**
     * 转盘的值处理
     * @param string $value
//     * @param int $type 1 = 客户端显示转盘 ， 2=用户抽奖
     * @return void
     */
    private function dealWithBankruptcyWheelValue(string $value){
        return  explode('|',$value);
    }

    /**
     * 检查客损活动是否能参与
     * @return void
     */
    #[RequestMapping(path:'getCustomerActivitiesStatus')]
    public function getCustomerActivitiesStatus(){
        $uid = $this->request->post('uid');

        $data = $this->conditionMaximumAmount($uid);
        if($data['code'] != 200)return $this->ReturnJson->successFul(200, ['status' => 0]);
        $userinfo = $data['userinfo'];
        $max_recharge_amount = $data['max_recharge_amount'];

        $withdraw_money = Db::table('withdraw_log')->where('uid',$uid)->whereIn('status',[0,3,1])->sum('money');
        //客损金额
        $customer_money = bcsub((string)$userinfo['total_pay_score'],bcadd((string)$withdraw_money,bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0)),0); //客损金额  充值 - 提现 - 余额
        $withdraw_bili = bcdiv((string)$withdraw_money,(string)$userinfo['total_pay_score'],2);  // 提现/ 充值

        //检查破产活动是否能参与
        $order_active = Db::table('order_active')
            ->select('id','price','get_cash','get_bonus','next_get_cash','num','image','type')
            ->where([
                ['status', '=' , 1],
                ['min_order_price','<=',$max_recharge_amount],
                ['max_order_price','>=',$max_recharge_amount],
                ['customer_money','<=',$customer_money],
                ['withdraw_bili','>=',$withdraw_bili],
            ])
            ->whereIn('type',[4,6])
            ->first();

        $activeData = $this->getOrderActive($order_active,$uid);
        return $this->ReturnJson->successFul(200, $activeData);
    }


    /**
     * 判断用户条件返回最大金额
     * @param string|int $uid 用户UID
     * @return void|null
     */
    private function conditionMaximumAmount(string|int $uid):array{
        $uid = $this->request->post('uid');
        $userinfo = Db::table('userinfo')
            ->selectRaw('coin,bonus,total_pay_score,(total_cash_water_score + total_bonus_water_score) as total_water_score,total_pay_score,total_give_score')
            ->where(['uid' => $uid])
            ->first();
        if(!$userinfo || $userinfo['total_pay_score'] <= 0)return ['code' => 201];


        //获取用户单笔最大的充值金额
        $max_recharge_amount = Db::table('order')
            ->where(['uid' => $uid,'pay_status' => 1])
            ->orderBy('price','desc')
            ->value('price');
        if(!$max_recharge_amount)return ['code' => 201];
        return ['code' => 200,'max_recharge_amount' => $max_recharge_amount,'userinfo' => $userinfo];

    }


    /**
     * @param $order_active
     * @param string|int $uid
     * @param int $type
     * @return null
     */
    private function getOrderActive($order_active,string|int $uid){
        if(!$order_active)return ['status' => 0];

        $ActiveLogNum = $this->getActiveLogNum($uid,(int)$order_active['type']);
        if($order_active['num'] <= $ActiveLogNum)return ['status' => 0];

        $res = $this->order->getOrderIp((int)$uid);
        if(!$res)return ['status' => 0];

        $order_active['image'] = $order_active['image'] ? $this->getActiveImage($order_active['image']) : '';
        return ['status' => 1,'order_active' => $order_active];
    }

    /**
     * @param $uid string|int 用户uid
     * @param $type int 类型 1 = 3天卡 2=月卡 , 3=破产 ,4=客损
     * @return int
     */
    private function getActiveLogNum(string|int $uid,int $type){
        return Db::table('order_active_log')->where(['uid' => $uid , 'type' => $type])->count();
    }

    /**
     * 获取用户每日领取的金额
     * @param int $uid
     * @param int $type 1 = 3天卡 2=月卡
     * @return array
     */
    public function getRechargeActivityAmount(int $uid,int $type = 1):array{

        $order_active_log = Db::table('order_active_log')->select('id','collectiontime','num','type','expiretime','active_id','createtime')->where(['type' => $type,'uid' => $uid])->orderBy('id','desc')->first();
        if($type == 1){//3天卡判断是否有领取次数，判断今日是否已经领取
            if(!$order_active_log || $order_active_log['num'] <= 0 || ($order_active_log['collectiontime'] && date('Ymd',$order_active_log['collectiontime']) >= date('Ymd'))) return [];
        }else{//
            if(!$order_active_log  || ($order_active_log['expiretime'] && $order_active_log['expiretime'] <= time())) return [];
        }
        $order_active = Db::table('order_active')
            ->select('id','get_cash','get_bonus','next_get_cash','next_get_bonus','last_get_cash','last_get_bonus','image','active_tage_hour')
            ->where('id',$order_active_log['active_id'])
            ->where('status',1)
            ->first();
        if(!$order_active)return [];
        $order_active['time'] = time();
        $order_active['image'] = $this->getActiveImage($order_active['image']);
        $order_active['order_active_id'] = $order_active_log['id'];
        if($type == 1){
            $order_active['sy_num'] = $order_active_log['num'];
            $order_active['tage_createtime'] = Db::table('order_active_tage')->where(['active_id' => $order_active['id'],'uid' => $uid])->value('createtime');
            $order_active['is_pay_status'] = 0;
        }elseif ($type == 2){
            $order_active['expiretime'] = $order_active_log['expiretime'];
            //今天才买的不能领取，领过了以后得不能领取
            $order_active['is_pay_status'] = (date('Ymd',$order_active_log['createtime']) == date('Ymd') || $order_active_log['collectiontime'] && date('Ymd',$order_active_log['collectiontime']) >= date('Ymd')) ? 2 : 0;

        }


//        [$cash_field,$bonus_field] = $this->getCashField($order_active_log['num']);
//        $getOrderActiveLog  = [
//            'get_cash' => $order_active[$cash_field],
//            'get_bonus' => $order_active[$bonus_field],
//            'id' => $order_active_log['id'],
//        ];
        return $order_active;
    }

    /**
     * 领取充值活动金额
     * @return
     */
    #[RequestMapping(path:'receiveRechargeActivity')]
    public function receiveRechargeActivity(){
        $uid = $this->request->post('uid');
        $order_active_id = $this->request->post('order_active_id');
        $order_active_log = Db::table('order_active_log')->where(['uid' => $uid,'id' => $order_active_id])->first();
        if(!$order_active_log)return $this->ReturnJson->failFul(248);
        if($order_active_log['type'] == 1){//3天卡判断是否有领取次数，判断今日是否已经领取
            if( $order_active_log['num'] <= 0 || ($order_active_log['collectiontime'] && date('Ymd',$order_active_log['collectiontime']) >= date('Ymd'))) return $this->ReturnJson->failFul(248);
            $reason = 20;
            $water_multiple_type = 11;
        }else{//月卡天卡过了领取天数，判断今日是否已经领取
            if(($order_active_log['expiretime'] && $order_active_log['expiretime'] < time())|| date('Ymd',$order_active_log['createtime']) == date('Ymd') || ($order_active_log['collectiontime'] && date('Ymd',$order_active_log['collectiontime']) >= date('Ymd'))) return $this->ReturnJson->failFul(248);
            $reason = 21;
            $water_multiple_type = 12;
        }
        $order_active = Db::table('order_active')
            ->select('get_cash','get_bonus','next_get_cash','next_get_bonus','last_get_cash','last_get_bonus','type')
            ->where('id',$order_active_log['active_id'])
            ->first();
        if($order_active['type'] == 1){
            [$cash_field,$bonus_field] = $this->getCashField($order_active_log['num']);
        }else{
            $cash_field = 'next_get_cash';
            $bonus_field = 'next_get_bonus';
        }



        //领取金额
        Db::beginTransaction();

        //修改充值活动历史记录数据
        $order_active_log_data = [
            'collectiontime' => time(),
            'get_money' => $order_active[$cash_field],
            'get_bonus' => $order_active[$bonus_field],
        ];
        if($order_active_log['type'] == 1)$order_active_log_data['num'] = Db::raw('num - 1');

        Db::table('order_active_log')->where(['id' => $order_active_id])->update($order_active_log_data);

        if($order_active[$cash_field] > 0){
            //3天卡和月卡不算是赠送，就是充值获取的
            $res = User::userEditCoin($uid,$order_active[$cash_field],1,'用户:'.$uid.'充值活动:'.bcdiv((string)$order_active[$cash_field],'100',2),2,$water_multiple_type);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249);
            }


//            $res = User::editUserTotalGiveScore($uid,$order_active[$cash_field]);
//            if(!$res){
//                Db::rollback();
//                return $this->ReturnJson->failFul(249);
//            }
        }
        if($order_active[$bonus_field] > 0){
            $res = User::userEditBonus($uid,$order_active[$bonus_field],$reason,'用户:'.$uid.'充值活动'.bcdiv((string)$order_active[$bonus_field],'100',2),2,$water_multiple_type);
            if(!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(249); //奖励领取失败
            }
        }
        Db::commit();
        return $this->ReturnJson->successFul();
    }


    /**
     * @param $num
     * @return void
     */
    private function getCashField($num = 0){
        $cash_field = 'get_cash';
        $bonus_field = 'get_bonus';
        switch ($num){
            case 2:
                $cash_field = 'next_get_cash';
                $bonus_field = 'next_get_bonus';
                break;
            case 1:
                $cash_field = 'last_get_cash';
                $bonus_field = 'last_get_bonus';
                break;
        }
        return [$cash_field,$bonus_field];
    }


    private function getActiveImage($image){
        return Common::domain_name_path($image);
    }


    /**
     * 存钱罐活动
     * @return void
     */
    #[RequestMapping(path:'piggyBankActive')]
    public function piggyBankActive(){
        $uid = $this->request->post('uid');
        $order_active = Db::table('order_active')->select('id','price','get_cash','get_bonus','next_get_cash','piggy_money')->where(['type' => 7,'status' => 1])->orderBy('price','asc')->get()->toArray();
        if(!$order_active)return $this->ReturnJson->successFul();
        //用户存钱罐记录
        $piggy_bank = Db::table('piggy_bank')->select('piggy_bank_money','active_id')->where(['uid' => $uid])->first();
        $activeArray = $this->getPiggyBankActive($piggy_bank,$order_active);

        $data = [
            'activeArray' => $activeArray,
            'piggy_bank_money' => $piggy_bank['piggy_bank_money'] ?? 0,
        ];
        return $this->ReturnJson->successFul(200,$data);
    }

    /**
     * 获取用户的能参与的存钱罐配置
     * @param $piggy_bank 用户的存钱罐记录
     * @param $order_active 充值活动列表
     * @return array
     */
    private function getPiggyBankActive($piggy_bank,$order_active):array{
        $fast_key = 0;
        if($piggy_bank)foreach ($order_active as $key => $value){
            if($piggy_bank['active_id'] > 0 && $piggy_bank['piggy_bank_money'] >= $value['price'] && $value['id'] > $piggy_bank['active_id']){
                $fast_key = $key;
                break;
            }
        }
        $activeArray = [];
        foreach ([$fast_key, $fast_key + 1] as  $item){
            if(isset($order_active[$item])){
                $activeArray[] = [
                    'id' => $order_active[$item]['id'],
                    'price' => $order_active[$item]['price'],
                    'get_cash' => $order_active[$item]['get_cash'],
                    'get_bonus' => $order_active[$item]['get_bonus'],
                    'next_get_cash' => $order_active[$item]['next_get_cash'],
                    'piggy_money' => $order_active[$item]['piggy_money'],
                    'level' => $item + 1,
                ];
            }
        }
        return $activeArray;
    }

    /**
     * 活动日活动
     * @return void
     */
    #[RequestMapping(path:'ActiveDayActive')]
    public function ActiveDayActive(){
        $uid = $this->request->post('uid');
        if(!$uid)return $this->ReturnJson->successFul();
        //判断今日是否已经参加了
        $order_active_log = Db::connection('readConfig')->table('order_active_log')->where([['uid','=',$uid],['type','=',8],['createtime','>=',strtotime('00:00:00')]])->value('id');
        if($order_active_log)return $this->ReturnJson->successFul();
        //判断是否充值
        $max_money = Db::connection('readConfig')->table('order')->where(['uid' => $uid,'pay_status' => 1])->orderBy('price','desc')->value('price');
        if(!$max_money)return $this->ReturnJson->successFul();
        //初步判断用户是否满足活动日活动条件
        $ActiveDayActiveStatus = $this->ActiveDayActiveStatus($uid);
        if(!$ActiveDayActiveStatus)return $this->ReturnJson->successFul();

        $order_active = Db::connection('readConfig')
            ->table('order_active')
            ->selectRaw('id,price,get_cash,get_bonus,next_get_cash,num')
            ->where([
                ['type','=',8],
                ['status','=',1],
                ['min_order_price','<=',$max_money],
                ['max_order_price','>=',$max_money],
            ])
            ->first();
        if(!$order_active)return $this->ReturnJson->successFul();
        $order_active_count = Db::connection('readConfig')->table('order_active_log')->where([['uid','=',$uid],['type','=',8]])->count();
        if($order_active_count >= $order_active['num'])return $this->ReturnJson->successFul();
        return $this->ReturnJson->successFul(200,$order_active);
    }


    /**
     * 初步判断用户是否满足活动日活动条件
     * @return void
     */
    private function ActiveDayActiveStatus($uid){
        //活动日活动用户几天未登录触发,活动日活动节假日是否开启,活动日活动发薪日是否开启,活动日活动每日周几触发
        $getConfig = Common::getMore('order_active_user_day,order_active_is_holiday,order_active_is_payroll,order_active_week_num');
        //获取上次登录时间
        $last_login_time =  Db::connection('readConfig')->table('share_strlog')->where('uid',$uid)->value('last_login_time');
        //获取节假日
        $in_holiday = config('my.in_holiday');
        $now_time = date('md');
        //获取每周星期几发奖励
        $order_active_week_num = [];
        if($getConfig['order_active_week_num'])$order_active_week_num = explode(',',$getConfig['order_active_week_num']);
        $dayOfWeek = date('w', time());
        if($dayOfWeek == 0)$dayOfWeek = 7;
        //活动日活动用户几天未登录触发
        if($getConfig['order_active_user_day'] && $last_login_time && bcdiv(bcsub((string)time(),(string)$last_login_time,0),'86400',2) > $getConfig['order_active_user_day']){
            return 1;
        }elseif ($getConfig['order_active_is_holiday'] && in_array($now_time,$in_holiday)){//活动日活动节假日是否开启
            return 1;
        }elseif ($getConfig['order_active_is_payroll'] && $this->getLastWorkdayOfMonth() == date('Ymd')){//活动日活动发薪日是否开启(每月的最后一个工作日)
            return 1;
        }elseif ($order_active_week_num && in_array($dayOfWeek,$order_active_week_num)){
            return 1;
        }else{
            return 0;
        }

    }

    /**
     * 获取当月最后一天工作日的代码
     * @return string
     */
    private function getLastWorkdayOfMonth() {
        // 获取当前月份的最后一天
        $lastDay = date('Ymt');

        // 获取最后一天的星期几（0=Sunday, 1=Monday, ..., 6=Saturday）
        $dayOfWeek = date('w', strtotime($lastDay));

        // 如果最后一天是周六或周日，则向前递减，直到找到最近的工作日
        if ($dayOfWeek == 6) { // Saturday
            $lastDay = date('Ymd', strtotime($lastDay . ' -1 day'));
        } elseif ($dayOfWeek == 0) { // Sunday
            $lastDay = date('Ymd', strtotime($lastDay . ' -2 days'));
        }

        return $lastDay;
    }
}

