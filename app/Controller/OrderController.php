<?php
declare(strict_types=1);
namespace App\Controller;

use Hyperf\DbConnection\Db;
use App\Common\Common;
use App\Common\pay\Pay;
use Hyperf\Di\Annotation\Inject;
use App\Common\User;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Service\PayService;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;

#[Controller(prefix:'Order')]
class OrderController extends AbstractController {

    #[Inject]
    protected Pay $pay;

    #[Inject]
    protected User $user;
    #[Inject]
    protected AdjustController $adjust;
    #[Inject]
    protected PayService $PayService;
    private array $fbPointPackageID = [1,7,11,12,14,15];  //FB打点的包
    private array $adjustPointPackageID = [1,13,11,14,15,12]; //Adjust打点的包
    private array $notAllPackageId = [40,49]; //所有通道匹配，排除的通道支付ID(这里ID必须是String)
    private array $showVideoUrlPaymentId = [2,25,28];  //需要视频的支付方式ID
    /**
     * @return void 用户充值主页数据
     */
    #[RequestMapping(path:'principalSheetIndex')]
    public function principalSheetIndex(){
        $param = $this->request->all();
        $uid = $param['uid'];
        $data = $this->payFiatCurrencyInfo($uid);

        $data['currency_and_ratio'] = $this->PayService->getCurrencyAndRatio(['status' => 1]);  //获取货币与比例配置

        $sysConfig = Common::getMore("bonus_pay_zs_water_multiple,cash_pay_water_multiple,below_the_pop_prompt,is_people_top,payment_reminder_status,withdraw_type_select,special_pay_types,pay_before_num"); //最低充值金额


        $data['payment_type'] = []; //普通充值

        $payment_type = $this->PayService->getPaymentType(['status' => 1,'type' => 2]);

        //数字货币协议
        $digital_currency_protocol = $this->PayService->getDigitalCurrencyProtocol();
        //数字货币信息
        $data['digital_currency_payment_type'] = $this->getPaymentInfo($payment_type,$data['userinfo'],$digital_currency_protocol);


        if($sysConfig['payment_reminder_status'] == 1) $data['payment_reminder_status'] = 1;
        $data['bonus_pay_zs_water_multiple'] =  $sysConfig['bonus_pay_zs_water_multiple'];
        $data['cash_pay_water_multiple'] =  $sysConfig['cash_pay_water_multiple'];
        $data['is_people_top'] =  $sysConfig['is_people_top']; //人工充值是否在上面
        return $this->ReturnJson->successFul(200, $data);
    }


    /**
     * 获取充值通道
     * @return null
     *
     */
    #[RequestMapping(path:'userGetNewPayType')]
    public function userGetNewPayType(){
        $uid = $this->request->post('uid');
        $payment_type_id = $this->request->post('payment_type_id') ?? 1;
        $money = $this->request->post('money') ?? 0;
        $is_new = $this->request->post('is_new') ?? 0;
        $userinfo = Db::table('userinfo')->select(Db::raw('total_pay_num,package_id,total_pay_score,coin,bonus'))->where('uid',$uid)->first();
        if(!$userinfo){
            $userinfo['total_pay_num'] = 0;$userinfo['package_id'] = 0;$userinfo['total_pay_score'] = 0;$userinfo['coin'] = 0;$userinfo['bonus'] = 0;
        }
        $data['payType'] = $this->getNewPayType($userinfo['package_id'],$uid,$userinfo,$payment_type_id,$money);
        if(!$is_new)  return $this->ReturnJson->successFul(200,  $data['payType']);
        [$data['defaultMoney'],] = $this->getMoneyConfig($userinfo['total_pay_num'],$userinfo['total_pay_score'],$userinfo['coin'],$uid,$userinfo['package_id'],$userinfo['bonus']);
//        $data['defaultNewMoney'] = [];
//        if($data['payType']){
//            foreach ($data['defaultMoney'] as $defaultMoneyValue){
//                if($defaultMoneyValue['money'] >= $data['payType']['minmoney'] && $defaultMoneyValue['money'] <= $data['payType']['maxmoney'])$data['defaultNewMoney'][] = $defaultMoneyValue;
//            }
//        }
        if($payment_type_id && $data['payType'] && !in_array($payment_type_id,$this->showVideoUrlPaymentId))$data['payType']['video_url'] = '';
        $data['defaultNewMoney'] = $data['defaultMoney'];
        return $this->ReturnJson->successFul(200, $data);
    }


    /**
     * 获取客户端支付方式显示赠送比例
     * @param $total_pay_num
     * @param $zs_bili_str
     * @return
     */
    private function getPaymentKhdTageSendBili($total_pay_num,$zs_bili_str){
        $moneyConfigArray = explode('|',$zs_bili_str);

        return $total_pay_num > 0 ? $moneyConfigArray[1] ?? '' : $moneyConfigArray[0] ?? '';
    }



    /**
     * 获取支付赠送金额配置
     * @param $PaymentSendConfigStr
     * @return array
     */
    private function getPaymentSendMoney($PaymentSendConfigStr){
        $moneyConfigArray = explode(' ',$PaymentSendConfigStr);
        $data = [];
        foreach ($moneyConfigArray as $value){
            [$recharge_range,$sendData['zs_bili']] = explode('|',$value);
            [$sendData['min_money'],$sendData['max_money']] = explode('-',$recharge_range);
            $data[] = $sendData;
        }
        return $data;
    }

    /**
     * 获取客服列表
     * @return void
     */
    private function getArtificialService(){

        $artificial_service = Db::connection('readConfig')->table('artificial_service')->selectRaw('image,url,payment_id,title')->where('status',1)->orderBy('weight','desc')->get()->toArray();
        $list = [];
        if($artificial_service)foreach ($artificial_service as $v){
            $list[$v['payment_id']][] = [
                'image' => Common::domain_name_path($v['image']),
                'url' => $v['url'],
                'title' => $v['title']
            ];
        }
        return $list;
    }

    /**
     * 获取u的汇率
     * @return string
     */
    #[RequestMapping(path:'get_rupee_exchange_rate')]
    public function get_rupee_exchange_rate(){
        return $this->ReturnJson->successFul(200, $this->rupee_exchange_rate());
    }

    /**
     * 获取u的汇率
     * @return mixed|string
     */
    private function rupee_exchange_rate(){
        return Common::getConfigValue('rupee_exchange_rate');
    }


    /**
     * 获取支付成功未打点的订单
     * @return string
     */
    #[RequestMapping(path:'getNotPointOrder')]
    public function getNotPointOrder(){
        $uid = $this->request->post('uid');
        $userCreatetime = Db::table('share_strlog')->where('uid',$uid)->value('createtime');
        if(!$userCreatetime)return $this->ReturnJson->failFul();
        $order = Db::table('order')->select('ordersn','price','createtime','is_first')->where(['uid' => $uid,'pay_status' => 1,'is_point' => 0])->orderBy('createtime')->get()->toArray();
        if($order) foreach ($order as &$v){
            if($v['is_first'] == 1 && date('Ymd',$userCreatetime) == date('Ymd',$v['createtime'])){
                $v['status'] = 0;
            }elseif ($v['is_first'] == 1){
                $v['status'] = 1;
            }else{
                $v['status'] = 2;
            }
        }
        return $this->ReturnJson->successFul(200, $order);

    }


    /**
     * 获取支付成功未打点的订单
     * @return string
     */
    #[RequestMapping(path:'setPointOrder')]
    public function setPointOrder(){
        $ordersn = $this->request->post('ordersn');

        Db::table('order')->where(['ordersn' => $ordersn])->update(['is_point' => 1]);

        return $this->ReturnJson->successFul();

    }

    /**
     * @param 订单支付
     */
    #[RequestMapping(path:'OrderPay')]
    public function OrderPay(){

        $uid = $this->request->post('uid');
        $money   = $this->request->post('money') ?? 0;  //充值金额
        $pay_type_id   = $this->request->post('pay_id');  //支付类型ID
        $active_id = $this->request->post('active_id') ?? 0; //活动ID
        $type = $this->request->post('type') ?? 0; //活动类型
        $payment_type_id = $this->request->post('payment_type_id') ?? 1; //活动类型
        $is_hand_enter = $this->request->post('is_hand_enter') ?? 0; //是否是手动输入金额 1=是,0=否
        $currency = $this->request->post('currency') ?? 'VND'; //法币
//        $protocol_name = $this->request->post('protocol_name') ?? ''; //数字货币协议


        $order_min_money = Common::getConfigValue("order_min_money");
        if(!$active_id && !$type && $money < $order_min_money) return $this->ReturnJson->failFul(228);  //抱歉，你的充值金额小于了最低充值金额
        //获取货币比例
        $currency_and_ratio = $this->PayService->getCurrencyAndRatio(['name' => $currency,'status' => 1],2,'bili',2);
        if(!$currency_and_ratio)return $this->ReturnJson->failFul(280);  //抱歉,该区域暂不支持充值!

        $orderTime = $this->OrderStatusNum($uid);
        if($orderTime)return $this->ReturnJson->failFul(230);//对不起！ 您目前有太多订单需要支付。 请稍等一会后再拉取订单

        $share_strlog = Db::table('share_strlog as a')
            ->join('userinfo as b','a.uid', '=', 'b.uid')
            ->selectRaw('br_a.jiaemail,br_b.first_pay_score,br_a.phone,br_a.jiaphone,br_b.total_pay_num, br_b.total_pay_score,
            br_b.coin,br_b.package_id,br_b.channel,br_a.nickname,br_b.vip,br_a.is_brushgang,br_a.brushgang_pay_status,br_a.jianame,br_b.bonus,br_a.fbc,
            br_a.appname,br_a.fbp,br_a.ip,br_a.city,br_a.gpsadid')
            ->where('a.uid', $uid)
            ->first();

        if(!$share_strlog) return $this->ReturnJson->failFul(226);


        $phone = $share_strlog['phone'] ?: ($share_strlog['jiaphone'] ?: rand(7777777777,9999999999));

        $email = $share_strlog['jiaemail'];

        //活动赠送金额等
        $day = 0; // 活动赠送天数，没有就默认1
        $get_money = $money;  //用户得到的金额
        //要求的流水

        $current_money = 0;
        if($active_id || $type){
//            $active_id = $this->getActiveId($active_id,$type,$share_strlog['first_pay_score']); //重新获取active_id ,这里可能传入的是type,需要获取活动id

            $active = Db::table('order_active')->where(['id' => $active_id,'status' =>1 ])->first();

            if(!$active) return $this->ReturnJson->failFul(254); //抱歉活动不存在

            //判断是否超过活动次数
            $active_num = Db::table('order_active as a')
                ->where('a.id', $active_id)
                ->where('a.status', '1')
                ->where('a.num', '>', function ($query) use($active_id,$uid,$active) {
                    $where = $active['type'] == 2 ? [['b.active_id', '=', $active_id]] : [['b.type', '=', $active['type']]];
                    $query->from('order_active_log as b')
                        ->where('b.uid', $uid)
                        ->where($where)
                        ->selectRaw('count(*)');
                })
                ->first();

            if(!$active_num)return $this->ReturnJson->failFul(255);


            [$money,$zs_bonus,$get_money,$zs_money,$day] = $this->activeValue($active);
            $pay_price = $money;
//            if($money < $active['money']){
//                return $this->ReturnJson->failFul(256);
//            }

            $payTypeArray = $this->getPayType($pay_type_id,$money,$share_strlog['package_id'],$uid,$share_strlog,$payment_type_id);
            if($payTypeArray['code'] != 200){
                return $this->ReturnJson->failFul($payTypeArray['code']);
            }
            $pay_type = $payTypeArray['data'];

        }else{
            $payTypeArray = $this->getPayType($pay_type_id, $money, $share_strlog['package_id'],$uid,$share_strlog,$payment_type_id);
            if ($payTypeArray['code'] != 200) {
                return $this->ReturnJson->failFul($payTypeArray['code']);
            }
            $pay_type = $payTypeArray['data'];

            //支付方式赠送
            $payment_id_zs_bonus = '0';
            if($payment_type_id)$payment_id_zs_bonus = $this->paymentIdZsBonus($payment_type_id,$money, $share_strlog['total_pay_num']);

            if($is_hand_enter == 1){
                [$zs_bonus, $cash_bili,$handshop_id] = $this->getHandEnterBonus($money, $share_strlog['total_pay_num'],$uid);  //赠送的bonus
            }else{
                [$zs_bonus, $cash_bili,$shop_id] = $this->getBonus($money, $share_strlog['total_pay_num'],$share_strlog['total_pay_score'],$share_strlog['coin'],$uid,$share_strlog['package_id'],$share_strlog['bonus']);  //赠送的bonus
            }
            $zs_bonus = bcadd((string)$zs_bonus, $payment_id_zs_bonus,0);
            [$zs_money, $current_money] = $this->getVipZs($money, $share_strlog['vip'], $current_money, $pay_type['send_bili'], $cash_bili);


        }


        $all_price = $share_strlog['total_pay_score'];

        //手续费
        $fee = '0';
        if($pay_type['fee_bili'] && $pay_type['fee_bili'] > 0){    //比例手续费
            $fee = bcmul((string)$pay_type['fee_bili'],(string)$money,0);
        }
        if($pay_type['fee_money'] && $pay_type['fee_money'] > 0){  //固定手续费
            $fee = bcadd((string)$pay_type['fee_money'],(string)$fee,0);
        }
        // 创建订单
        $createData = [
            "uid"           => $uid,
            "day"           => $day ,
            "ordersn"  => Common::doOrderSn(000),
            "paytype"       => $pay_type['name'],
            "zs_bonus"      => $this->PayService->getFiatCryptoConversion((string)$zs_bonus,$currency_and_ratio['bili']),  //转换为U
            "zs_money"      => $this->PayService->getFiatCryptoConversion((string)$zs_money,$currency_and_ratio['bili']),//转换为U
            "money"      => $this->PayService->getFiatCryptoConversion(bcadd((string)$get_money,(string)$zs_money,0),$currency_and_ratio['bili']),//转换为U
            'get_money' => $this->PayService->getFiatCryptoConversion((string)$get_money,$currency_and_ratio['bili']),//转换为U
            'price'    => $this->PayService->getFiatCryptoConversion((string)$money,$currency_and_ratio['bili']),//转换为U
            'email'         => $email,
            'phone'        => $phone,
            'nickname'        => $phone,
            'createtime' => time(),
            'packname' => $this->request->getAttribute('packname'),
            'active_id' => $active_id,
            'ip' => Common::getIp($this->request->getServerParams()), //正式
            'all_price' => $all_price,
            'fee_money' => $this->PayService->getFiatCryptoConversion($fee,$currency_and_ratio['bili']),
            'current_money' => $current_money,
            'package_id' => $share_strlog['package_id'],
            'channel' => $share_strlog['channel'],
            'shop_id' => $shop_id ?? 0,
            'handshop_id' => $handshop_id ?? 0,
            'pay_price' => $money,
            'currency' => $currency,
        ];

        $order_id = Db::table('order')->insertGetId($createData);

        if(!$order_id) return $this->ReturnJson->failFul(229);

        $baseUserInfo  = [
            'email' => $email,
            'mobile' => $phone,
            'uid' => $uid,
            'ip' => $createData['ip'],
            'jianame' => $share_strlog['jianame'],
        ];

        //统计支付渠道数据
        if($payment_type_id && $pay_type['id']){
            co(function ()use($uid,$payment_type_id,$pay_type,$order_id){
                $this->setOrderPaymentData($uid,$payment_type_id,$pay_type['id'],$order_id);
            });
        }
        //数字货币充值
//        if($protocol_name){
//            $payment_type_type = Db::connection('readConfig')->table('payment_type')->where('id',$pay_type['payment_ids'])->value('type');
//            if($payment_type_type == 2){
//                $createData['protocol_name'] = $protocol_name; //协议名称
//                $createData['protocol_money'] = $protocol_money; //货币数量
//                $this->setOrderProtocol($order_id,$createData);
//            }
//        }

        $apInfo = $this->pay->pay($pay_type['name'],$createData,$baseUserInfo);


        if($apInfo['code'] != 200) return $this->ReturnJson->failFul(231);//抱歉！三方支付订单拉取失败

        if($apInfo['data']['tradeodersn']){
            Db::table('order')->where('id' ,'=', $order_id)->update(['tradeodersn' => $apInfo['data']['tradeodersn'],'updatetime' => time()]);
        }

        if($share_strlog['fbc']) $this->adjust->fbUploadEvent($share_strlog['appname'],(float)bcdiv((string)$createData["price"],'100',2),0,$createData['ordersn'],$createData['uid'],$share_strlog,4);

        return $this->ReturnJson->successFul(200, $apInfo['data']['payurl']);

    }



    /**
     * 虚拟币订单
     * @param $createData  订单数据
     * @return void
     */
    private function setOrderProtocol($order_id,$createData){
        Db::table('order_protocol')->insert([
            'id' => $order_id,
            'ordersn' => $createData['ordersn'],
            'protocol_name' => $createData['protocol_name'],
            'protocol_money' => $createData['protocol_money'],
            'money' => $createData['price'],
        ]);
    }

    /**
     * 统计支付渠道数据
     * @param $uid 用户UID
     * @param $payment_type_id  支付方式ID
     * @param $pay_type_id 支付类型ID
     * @param $order_id 订单ID
     * @return void
     */
    private function setOrderPaymentData($uid,$payment_type_id,$pay_type_id,$order_id){
        $date = date('Ymd');
        $order_user = Db::table('order_user')->select('id')->where(['date' => $date,'uid' => $uid,'payment_type_id' => $payment_type_id,'pay_type_id' => $pay_type_id])->first();
        if(!$order_user){
            Db::table('order_user')->insert(
                [
                    'date' => $date,
                    'uid' => $uid,
                    'payment_type_id' => $payment_type_id,
                    'pay_type_id' => $pay_type_id,
                ]
            );
        }
        $order_payment = Db::table('order_payment')->select('id')->where(['date' => $date,'payment_type_id' => $payment_type_id,'pay_type_id' => $pay_type_id])->first();
        if($order_payment){
            $updateOrderPayment = [
                'order_num' => Db::raw('order_num + 1'),
            ];
            if(!$order_user)$updateOrderPayment['user_num'] = Db::raw('user_num + 1');
            Db::table('order_payment')
                ->where('id',$order_payment['id'])
                ->update($updateOrderPayment);
        }else{
            Db::table('order_payment')
                ->insert(
                    [
                        'date' => $date,
                        'payment_type_id' => $payment_type_id,
                        'pay_type_id' => $pay_type_id,
                        'order_num' => 1,
                        'user_num' => 1
                    ]
                );
        }
        Db::table('order_tage_payment')->insert(['order_id' => $order_id,'payment_type_id' => $payment_type_id,'pay_type_id' => $pay_type_id]);
    }

    /**
     * 订单回调统计支付方式成功与支付总金额
     * @param $payment_type_id
     * @param $pay_type_id
     * @param $price
     * @return void
     */
    private function setTwoOrderPaymentData($payment_type_id,$pay_type_id,$price){
        $order_payment = Db::table('order_payment')->select('id')->where(['date' => date('Ymd'),'payment_type_id' => $payment_type_id,'pay_type_id' => $pay_type_id])->first();
        if($order_payment){
            $updateOrderPayment = [
                'success_num' => Db::raw('success_num + 1'),
                'all_price' => Db::raw('all_price + '.$price),
            ];
            Db::table('order_payment')
                ->where('id',$order_payment['id'])
                ->update($updateOrderPayment);
        }
    }


    /**
     * 判断是否连续拉了多笔订单未支付限时
     * @param $uid
     * @return void
     */
    private function OrderStatusNum($uid){
        $num = 10; //连续拉多少笔订单未支付进入等待限制
        $time = 30; //限制时间默认30分钟之后才能再次拉去订单
        $order = Db::table('order')
            ->selectRaw('pay_status,createtime')
            ->where('createtime','>=',(time() - ($time * 60)))
            ->where('uid',$uid)
            ->orderBy('createtime','desc')
            ->forPage(1,$num)
            ->get()
            ->toArray();
        if(count($order) < $num){
            return 0;  //表示可以拉取订单
        }
        foreach ($order as $v){
            if($v['pay_status'] == 1)return 0;  //有支付表示可以拉取订单
        }
        $lastOderTime = $order[0]['createtime'] ?? time(); //上一笔订单拉取时间
        if($lastOderTime + (60 * $time) > time()){
            return bcdiv((string)(($lastOderTime + (60 * $time)) - time()),'60',0); //返回还需要多少分钟才能拉取订单
        }
        return 0;
    }



    /**
     * @return void 获取后台金额配置
     * @param $pt_pay_count  支付次数
     * @param $total_pay_score  总充值支付金额
     * @param $coin  余额剩余
     * @param $terminal_type  用户来源终端 2= H5 ，1=APP
     */
    private function getMoneyConfig($pt_pay_count,$total_pay_score,$coin,$uid,$package_id,$bonus){

        [$defaultMoney,$cash_money,$hot_config,$shop_id] = $this->getRechargeConfig($pt_pay_count,$total_pay_score,$coin,$uid,$package_id,$bonus);

        //快捷的提现金额
        $defaultMoney = explode(' ',$defaultMoney);
        $cash_money = $cash_money ? explode(' ',$cash_money) : [];
        $hot_config = $hot_config ? explode(' ',$hot_config) : [];
        $data = [];
        foreach ($defaultMoney as $key => $val){
            [$money,$bouns] = explode('|',$val);
            [,$cash_money_bili] = $cash_money ? explode('|',$cash_money[$key]) : ['0','0'];
            [,$hot_status] = $hot_config ? explode('|',$hot_config[$key]) : ['0','0'];
            $data[] = [
                'money'  => $money,
                'bonus'  => $bouns,
                'hot_status'  => $hot_status,
                'cash_bili' => $cash_money_bili,
            ];

        }
        return [$data,$shop_id];
    }

    /**
     * 客户端获取不同国家金额区间
     * @param
     *
     */
    #[RequestMapping(path:'getRechargeConfiguration')]
    public function getRechargeConfiguration(){
        return $this->ReturnJson->successFul(200, $this->payFiatCurrencyInfo($this->request->post('uid') ?? 0));
    }


    /**
     * 获取法币支付页面信息
     * @param string|int $uid 用户id
     * @return array
     */
    private function payFiatCurrencyInfo(string|int $uid){
        $currency = $this->request->post('currency') ?? 'VND';
        $userinfo = Db::table('userinfo')->select(Db::raw('total_pay_num,coin,total_pay_score,package_id,bonus'))->where('uid',$uid)->first();
        if(!$userinfo){
            $userinfo['total_pay_num'] = 0;$userinfo['total_pay_score'] = 0;$userinfo['coin'] = 0;$userinfo['package_id'] = 0;$userinfo['bonus'] = 0;
        }
        [$data['defaultMoney'],] = $this->getMoneyConfig($userinfo['total_pay_num'],$userinfo['total_pay_score'],$userinfo['coin'],$uid,$userinfo['package_id'],$userinfo['bonus']);

        $payment_type = $this->PayService->getPaymentType(['status' => 1,'currency' => $currency]);
        $data['payType'] = $this->getNewPayType($userinfo['package_id'],$uid,$userinfo,$payment_type ? $payment_type[0]['id'] : 1);
        $data['fiat_currency_payment_type'] = $this->getPaymentInfo($payment_type,$userinfo);  //法币支付方式
        $data['userinfo'] = $userinfo;
        return $data;
    }


    /**
     * 解析支付类型与赠送
     * @param array $payment_type 支付类型
     * @param array $userinfo  用户信息
     * @param array $digital_currency_protocol 虚拟币协议
     * @return array
     */
    private function getPaymentInfo(array $payment_type,array $userinfo,array $digital_currency_protocol = []){
        $new_payment_type = [];
        foreach($payment_type as $key => $v){
            if($v['image'])$v['image'] = Common::domain_name_path((string)$v['image']);
            if ($v['type'] == 2){
                $payment_type_array = explode(',',$v['protocol_ids']);
                foreach ($digital_currency_protocol as $digital_currency){
                    if(in_array($digital_currency['id'],$payment_type_array)){
                        if($digital_currency['icon'])$digital_currency['icon'] = Common::domain_name_path((string)$digital_currency['icon']);
                        $v['pay_type_array'][] = $digital_currency;
                    }
                }
                $v['zs_bili'] = $this->getPaymentKhdTageSendBili($userinfo['total_pay_num'],$v['zs_bili']);
                $v['first_zs_bonus_array'] = $this->getPaymentSendMoney($v['first_zs_bonus_bili']);
                $v['zs_bonus_array'] = $this->getPaymentSendMoney($v['zs_bonus_bili']);
                $new_payment_type[] = $v;
            }else{

                $v['zs_bili'] = $this->getPaymentKhdTageSendBili($userinfo['total_pay_num'],$v['zs_bili']);
                $v['first_zs_bonus_array'] = $this->getPaymentSendMoney($v['first_zs_bonus_bili']);
                $v['zs_bonus_array'] = $this->getPaymentSendMoney($v['zs_bonus_bili']);
                $new_payment_type[] = $v;
            }
        }
        return $new_payment_type;
    }


    /**
     * 获取充值金额与赠送Bonus 、Cash配置
     * @param $pt_pay_count  支付次数
     * @param $total_pay_score  总充值支付金额
     * @param $coin  余额剩余
     * @param $terminal_type  用户来源终端 2= H5 ，1=APP
     */
    private function getRechargeConfig($pt_pay_count,$total_pay_score,$coin,$uid,$package_id,$bonus){
        $currency = $this->request->post('currency') ?? 'VND';
        $user_type = \App\Controller\user\UserController::getUserType($uid); //获取用户类型
        $withdraw_money = \App\Controller\WithdrawlogController::getUserTotalExchangeMoney($uid);  //用户提现的金额，包含待审核和处理中
        $withdraw_bili = $total_pay_score > 0 ? bcdiv((string)$withdraw_money,(string)$total_pay_score,2) : 0;  // 提现/ 充值
        $customer_money = bcsub((string)$total_pay_score,bcadd((string)$withdraw_money,bcadd((string)$coin,(string)$bonus,0)),0); //客损金额  充值 - 提现 - 余额

        $shop_id = 0;
        $pt_pay_count_config = Common::getConfigValue('pt_pay_count') ?: 0;
//        if($pt_pay_count <= bcsub((string)$pt_pay_count_config,'1',0) && $this->getOrderIp((int)$uid)){  //首充充值商城判断  只能参加一次
        try {
            if($pt_pay_count <= bcsub((string)$pt_pay_count_config,'1',0)){  //首充充值商城判断  只能参加一次
                $marketing_shop = Db::connection('readConfig')->table('marketing_shop')->selectRaw('id,bonus_config,cash_config,hot_config')->where(['type'=>'1'.$pt_pay_count,'user_type' => $user_type,'status' => 1,'currency' => $currency])->orderBy('weight','desc')->first();
                if($marketing_shop){
                    $defaultMoney = $marketing_shop['bonus_config'];
                    $cash_money = $marketing_shop['cash_config'];
                    $hot_config = $marketing_shop['hot_config'];
                    $shop_id = $marketing_shop['id'];
                }
            }elseif($total_pay_score){//客损
                $type_array = Db::connection('readConfig')->table('shop_log')->whereIn('type',[7,20])->where([['uid','=',$uid]])->pluck('type')->toArray();
                $customer_where = [];
                foreach ([7,20] as $val) if(!in_array($val,$type_array)) $customer_where[] = $val;

                $marketing_shop = Db::connection('readConfig')->table('marketing_shop')->selectRaw('id,bonus_config,cash_config,hot_config')->whereIn('type',$customer_where)->where([['user_type','=',$user_type],['withdraw_bili','>=',$withdraw_bili],['customer_money','<=',$customer_money],['status','=',1],['currency','=', $currency]])->orderBy('customer_money','desc')->first();
                if($marketing_shop){
                    $defaultMoney = $marketing_shop['bonus_config'];
                    $cash_money = $marketing_shop['cash_config'];
                    $hot_config = $marketing_shop['hot_config'];
                    $shop_id = $marketing_shop['id'];
                }
            }
            if(!isset($defaultMoney) && $total_pay_score){  //破产
                $marketing_shop = Db::connection('readConfig')->table('marketing_shop')->selectRaw('id,bonus_config,cash_config,hot_config,num')->where([['status','=',1],['type','=',6],['user_type','=',$user_type],['withdraw_bili','>=',$withdraw_bili],['coin_money','>=',bcadd((string)$coin,(string)$bonus,0)],['currency','=', $currency]])->orderBy('weight','desc')->first();
                if($marketing_shop && Db::table('shop_log')->where([['uid','=',$uid],['type','=',6]])->count() < $marketing_shop['num']){
                    $defaultMoney = $marketing_shop['bonus_config'];
                    $cash_money = $marketing_shop['cash_config'];
                    $hot_config = $marketing_shop['hot_config'];
                    $shop_id = $marketing_shop['id'];
                }
            }




            if(!isset($defaultMoney)){
                $marketing_shop = Db::connection('readConfig')->table('marketing_shop')->selectRaw('bonus_config,cash_config,hot_config')->where([['status','=',1],['type','=',0],['user_type','=',$user_type],['currency','=', $currency]])->orderBy('weight','desc')->first();
                $defaultMoney = $marketing_shop['bonus_config'];
                $cash_money = $marketing_shop['cash_config'];
                $hot_config = $marketing_shop['hot_config'];
            }
        }catch (\Exception $e){
            $this->logger->error('获取充值商城配置失败:'.$e->getMessage());
            $this->logger->error('获取充值商城currency:'.$currency);
        }

        return [$defaultMoney,$cash_money,$hot_config,$shop_id];

    }



    /**
     * 通道选择
     * @param $pay_type_id 支付渠道id
     * @param $money 支付金额
     * @param  $package_id 包名
     * @param  $share_strlog 用户信息
     * @param  $payment_type_id 支付方式ID
     * @param  $money 输入的金额
     * @return array
     */
    private function getNewPayType($package_id,$uid,$share_strlog,$payment_type_id = 1,$money = 0){
        $currency = $this->request->post('currency') ?? 'VND';
        $withdrawConfig = Common::getMore('pay_before_num'); //充值前几次时匹配特定通道

        if($money && $money > 0){
            $where = [['status','=',1],['minmoney','<=',$money],['maxmoney','>=',$money],['currency','=',$currency],['type','=',1]];
        }else{
            $where = [['status','=',1],['currency','=',$currency],['type','=',1]];
        }


        //获取支付成功订单数据
        $order = [];
        if($uid)$order = Db::connection('readConfig')->table('order')->select('pay_status','paytype')->where('uid',$uid)->orderBy('id','desc')->first();

        $paymentWhere = '';
        if($payment_type_id)$paymentWhere = 'FIND_IN_SET('.$payment_type_id.',payment_ids)';


        $pay_type_ids = Db::connection('readConfig')->table('apppackage_config')->where('package_id',$package_id)->value('pay_type_ids');
        $pay_type_ids = $pay_type_ids ?: '';

        $pay_type_Array = $this->getNewPayTypeArray($where,$paymentWhere,$pay_type_ids,$withdrawConfig['pay_before_num'] > $share_strlog['total_pay_num'] ? 1 : 0);
        if(!$pay_type_Array){
            $pay_type_Array = $this->getNewPayTypeArray($where,$paymentWhere,$pay_type_ids);
            if(!$pay_type_Array) {
                $pay_type_Array = $this->getNewPayTypeArray($where,'',$pay_type_ids);
                if(!$pay_type_Array)return [];
            }
        }

        if(!$order){
            $pay_type = self::getFirstPay($pay_type_Array);
        }elseif ($order['pay_status'] == 1){
            foreach ($pay_type_Array as $key => $v){
                if($v['name'] == $order['paytype']){
                    $keyValue = $key;
                    break;
                }
            }
            $pay_type = (isset($keyValue) && isset($pay_type_Array[$keyValue])) ? $pay_type_Array[$keyValue] : self::getFirstPay($pay_type_Array);
        }else{
            foreach ($pay_type_Array as $key => $v){
                if($v['name'] == $order['paytype']){
                    $keyValue = $key + 1;
                    break;
                }
            }
            $pay_type = (isset($keyValue) && isset($pay_type_Array[$keyValue])) ? $pay_type_Array[$keyValue] : self::getFirstPay($pay_type_Array);
        }


        if(!$pay_type) return [];

        return $pay_type;
    }


    /**
     * 获取支付通道列表
     * @param $where array 基础的where条件
     * @param $paymentWhere string 支付方式条件
     * @param $money string 充值金额
     * @param $pay_type_ids  string 支付ID
     * @param $is_specific_channel int 是否为特殊通道
     * @return mixed[]
     */
    private function getNewPayTypeArray(array $where,string $paymentWhere, string $pay_type_ids,int $is_specific_channel = 0){
        $query = Db::connection('readConfig')
            ->table('pay_type')
            ->where($where);
        if($pay_type_ids) $query->whereIn('id',explode(',',$pay_type_ids));
        if($is_specific_channel == 1)$query->where('is_specific_channel','=',1);
        if($paymentWhere)$query->whereRaw($paymentWhere);
        return $query->orderBy('weight','desc')
            ->get()
            ->toArray();
    }


    /**
     * 新的得到支付渠道
     * @param $pay_type_id 支付渠道id
     * @param $money 支付金额
     * @param  $package_id 包名
     * @param  $share_strlog 用户信息
     * @param  $payment_type_id 支付方式ID
     * @return array
     */
    private function getPayType($pay_type_id,$money,$package_id,$uid,$share_strlog,$payment_type_id){
        $currency = $this->request->post('currency') ?? 'VND';

        //如果客户端传入了支付通道，直接拿取使用
        if($pay_type_id){
            $pay_type = Db::table('pay_type')
                ->where([
                    ['minmoney','<=',$money],
                    ['maxmoney','>=',$money]
                ])
                ->where(['id' => $pay_type_id,'status' => 1])
                ->first();

            if($pay_type){
                return ['code' => 200,'msg' => 'success','data' => $pay_type];
            }

        }

        $pay_before_num = Common::getConfigValue('pay_before_num'); //充值前几次时匹配特定通道

        $where = [['status','=',1],['currency','=',$currency],['type','=',1]];


        $paymentWhere = '';
        if($payment_type_id)$paymentWhere = 'FIND_IN_SET('.$payment_type_id.',payment_ids)';


        $pay_type_ids = Db::connection('readConfig')->table('apppackage_config')->where('package_id',$package_id)->value('pay_type_ids');
        $pay_type_ids = $pay_type_ids ?: '';


        $pay_type_Array = $this->getPayTypeArray($where,$paymentWhere,(string)$money,$pay_type_ids,$pay_before_num > $share_strlog['total_pay_num'] ? 1 : 0);
        if(!$pay_type_Array){
            $pay_type_Array = $this->getPayTypeArray($where,$paymentWhere,(string)$money,$pay_type_ids);
            if(!$pay_type_Array){  //如果还是米有就直接在所有通道里面选择
                $pay_type_Array = $this->getPayTypeArray($where,'',(string)$money,$pay_type_ids);
            }
            if(!$pay_type_Array)return ['code' => 227,'msg' => 'Sorry! No recharge channel has been matched yet','data' => []];
        }

        $order = Db::connection('readConfig')->table('order')->select('pay_status','paytype')->where('uid',$uid)->orderBy('id','desc')->first();

        if(!$order){
            $pay_type = self::getFirstPay($pay_type_Array);
        }elseif ($order['pay_status'] == 1){
            $keyValue = 0;
            foreach ($pay_type_Array as $key => $v){
                if($v['name'] == $order['paytype']){
                    $keyValue = $key;
                    break;
                }
            }
            $pay_type = $pay_type_Array[$keyValue] ?? self::getFirstPay($pay_type_Array);
        }else{
            $keyValue = 0;
            foreach ($pay_type_Array as $key => $v){
                if($v['name'] == $order['paytype']){
                    $keyValue = $key + 1;
                    break;
                }
            }
            $pay_type = $pay_type_Array[$keyValue] ?? $pay_type_Array[0];
        }


        if(!$pay_type) return ['code' => 227,'msg' => 'Sorry! No recharge channel has been matched yet','data' => []];

        return ['code' => 200,'msg' => 'success','data' => $pay_type];
    }

    /**
     * 获取支付通道列表
     * @param $where array 基础的where条件
     * @param $paymentWhere string 支付方式条件
     * @param $money string 充值金额
     * @param $pay_type_ids  string 支付ID
     * @param $is_specific_channel int 是否为特殊通道
     * @return mixed[]
     */
    private function getPayTypeArray(array $where,string $paymentWhere,string $money, string $pay_type_ids,int $is_specific_channel = 0){
        $query = Db::table('pay_type')
            ->where($where)
            ->where([
                ['minmoney','<=',$money],
                ['maxmoney','>=',$money],
            ]);
        if($pay_type_ids) $query->whereIn('id',explode(',',$pay_type_ids));
        if($is_specific_channel == 1)$query->where('is_specific_channel','=',1);
        if($paymentWhere)$query->whereRaw($paymentWhere);
        return $query->orderBy('weight','desc')
            ->get()
            ->toArray();
    }


    /**
     * 按照权重随机一个通通道
     * @param $pay_type_Array  所有满足条件的支付通道
     * @return mixed
     */
    public static function getFirstPay(array $pay_type_Array){
        $keyValue = [];//将每个key生产对应的权重数量存入
        $total_num = 0;//计算总共权重的数量
        foreach ($pay_type_Array as $key => $value){
            for ($i = 0; $i < $value['weight']; $i++) {
                $keyValue[] = $key;
            }
            $total_num  = $total_num + $value['weight'];
        }

        $randNum = rand(0,$total_num - 1);//随机看出现那个支付渠道的索引
        return $pay_type_Array[$keyValue[$randNum]] ?? $pay_type_Array[0];
    }






    /**
     * @param $money 充值金额 用户支付金额
     * @param $pt_pay_count 用户的普通充值次数
     * @param $total_pay_score 用户总充值金额
     * @param $coin 用户余额
     * @param $terminal_type 1=App端 ,2=H5端
     * @return void
     */
    private function getBonus($money,$pt_pay_count,$total_pay_score,$coin,$uid,$package_id,$user_bonus){
        $bonus = 0;
        $cash_bili = 0;
        [$defaultConfig,$shop_id] = $this->getMoneyConfig($pt_pay_count,$total_pay_score,$coin,$uid,$package_id,$user_bonus);
        foreach ($defaultConfig as $v){
            if($v['money'] == $money){
                //比例的时候打开
//                $bonus =  bcmul((string)$v['bonus'],(string)$v['money'],0);
//                $cash_bili = $v['cash_bili'];
                //赠送金额的时候打开
                $bonus =  $v['bonus'];
                $cash_bili = $v['cash_bili'];
                break;
            }
        }
        return [$bonus,$cash_bili,$shop_id];
    }


    /**
     * 获取支付方式赠送比例
     * @param $payment_type_id int|string|array 支付方式ID  或者where 条件
     * @param  $money int|string 充值金额
     * @param $pt_pay_count int|string 玩家支付次数
     * @return string
     */
    private function paymentIdZsBonus(int|string|array $payment_type_id,int|string $money,int|string $pt_pay_count){
        if($pt_pay_count <= 0){
            $zs_bonus_str = is_array($payment_type_id)
                ? $this->PayService->getPaymentType($payment_type_id,'first_zs_bonus_bili',2)['first_zs_bonus_bili']
                : $this->PayService->getPaymentType(['id' => $payment_type_id],'first_zs_bonus_bili',2)['first_zs_bonus_bili'];
        }else{
            $zs_bonus_str = is_array($payment_type_id)
                ? $this->PayService->getPaymentType($payment_type_id,'zs_bonus_bili',2)['zs_bonus_bili']
                : $this->PayService->getPaymentType(['id' => $payment_type_id],'zs_bonus_bili',2)['zs_bonus_bili'];
        }
        if(!$zs_bonus_str)return '0';
        $zs_bonus_array = $this->getPaymentSendMoney($zs_bonus_str);
        $zs_bonus_bili = 0;
        foreach ($zs_bonus_array as $v){
            if($v['min_money'] <= $money && $v['max_money'] >= $money){
                $zs_bonus_bili = $v['zs_bili'];
                break;
            }
        }
        return bcmul((string)$zs_bonus_bili,(string)$money,0);
    }


    private function getHandEnterBonus($money,$pt_pay_count,$uid){
        $user_type = \App\Controller\user\UserController::getUserType($uid); //获取用户类型
        if($pt_pay_count <= 0){
            $marketing_handshop = Db::connection('readConfig')->table('marketing_handshop')->selectRaw('id,zs_cash,zs_bonus')->where([['user_type','=',$user_type],['status','=',1],['type','=',10],['min_money','<=',$money],['max_money','>=',$money]])->first();
        }else{
            $marketing_handshop = Db::connection('readConfig')->table('marketing_handshop')->selectRaw('id,zs_cash,zs_bonus')->where([['user_type','=',$user_type],['status','=',1],['type','=',0],['min_money','<=',$money],['max_money','>=',$money]])->first();
        }
        if(!$marketing_handshop)return [0,0,0];
        return [bcmul((string)$marketing_handshop['zs_bonus'],(string)$money,0),$marketing_handshop['zs_cash'],$marketing_handshop['id']];
    }

    /**
     * 设置订单Ip
     * @param int $uid 用户的UID
     * @param string $info ip或者设备ID数据
     * @param int $type 1 = ip ,2 = device_id
     * @return void
     */
    private function setOrderIp(int $uid,string $ip,string $device_id = ''){
        Db::table('order_ip')->insertOrIgnore([
            'uid' => $uid,
            'ip' => $ip,
            'device_id' => $device_id,
        ]);
    }


    /**
     * 判断是否满足参与首充商场条件
     * @param int $uid
     * @return int
     */
    public function getOrderIp(int $uid):int{
        if($uid <= 0)return 1;

        //查看用户是否参与过充值活动，参与过得就能参加
        $res = Db::table('order_ip')->where('uid',$uid)->value('uid');
        if($res)return 1;
        //检查用户是H5还是App
        $share_strlog = Db::table('share_strlog')->select('is_app','device_id')->where('uid',$uid)->first();
        if(!$share_strlog)return 1;
        $ip = Common::getIp();
        if(!$share_strlog['is_app'] || !$share_strlog['device_id']){
            //查看用户的ip是否已经被别人使用过，使用过就不能参加
            $ipUid = Db::table('order_ip')->where('ip',$ip)->value('uid'); //用户是否不满足参与的条件
            if($ipUid){
                $this->logger->error('用户uid:'.$uid.'参与充值商场的Ip已被使用-uid:'.$ipUid.'使用了');
                return 0;
            }
            //用户没参加，并且IP没被使用过可以继续参加
            $this->setOrderIp($uid,$ip,'');
        }else{
            $deviceUid = Db::table('order_ip')->where('device_id',$share_strlog['device_id'])->value('uid'); //用户是否不满足参与的条件
            if($deviceUid){
                $this->logger->error('用户uid:'.$uid.'参与充值商场的设备ID已被使用-uid:'.$deviceUid.'使用了');
                return 0;
            }
            $this->setOrderIp($uid,$ip,$share_strlog['device_id']);
        }

        return 1;
    }


    /**
     * @return array 获取活动的值
     * @param $active 活动
     */
    private function activeValue(array $active):array
    {
        [$money,$bonus,$get_money,$zs_money,$day] = [$active['price'],$active['get_bonus'],$active['get_cash'],$active['next_get_cash'],1]; //$money = 活动支付金额 , $bonus = 活动赠送的bonus , $get_money = 到账金额 , $zs_money=活动赠送金额

        if($active['type'] == 1){
            $zs_money = $active['get_cash'] + $active['next_get_cash'] + $active['last_get_cash'];
            $bonus = $active['get_bonus'] + $active['next_get_bonus'] + $active['last_get_bonus'];
            $get_money = 0;
            $day = 3;
        }elseif ($active['type'] == 2){
            $day = $active['day'];
            $zs_money = 0;
        }

        return [$money,$bonus,$get_money,$zs_money,$day];
    }

    /**
     * @param $active_id  活动id
     * @param $type 活动类型
     * @param $first_pay_score 首充金额
     * @return void
     */
    private function getActiveId($active_id,$type,$first_pay_score){
        $activeSelfScreening = config('active.selfScreening');
        if(isset($activeSelfScreening[(string)$type]) && $activeSelfScreening[(string)$type] == 1){ //判断是否为自动筛选活动
            $active_id = Db::table('active')->where([['money','>',$first_pay_score],['status','=',1],['type','=',$type]])->orderBy('money')->value('id');
            if(!$active_id) $active_id = Db::table('active')->where([['status','=',1],['type','=',$type]])->orderBy('money','desc')->value('id');
        }
        return $active_id;
    }

    /**
     * 普通充值赠送金额
     * @param $money 充值金额
     * @param $vip vip等级
     * @param $send_bili 渠道额外赠送比例
     * @param $cash_bili 如果赠送比例大于0
     * @param 要求的流水 vip等级
     * @return void
     */
    private function getVipZs($money,$vip,$current_money,$send_bili,$cash_bili){
        $zs_money = 0; //赠送金额


        if($send_bili > 0){ //如果存在额外赠送的金额
            $send_money = bcmul((string)$send_bili,(string)$money,0); //额外赠送的金额
            $zs_money = bcadd($send_money,(string)$zs_money,0); //总赠送金额
//            $current_money = bcadd(bcmul(Common::getConfigValue("pay_flow_multiple"),$send_money,0),$current_money,0);
            $current_money = 0;
        }

        if($cash_bili > 0){  //如果赠送比例大于0
//            //比例的时候打开
//            $zs_money = bcadd((string)$zs_money,bcmul((string)$money,(string)$cash_bili,0),0);
            //赠送固定金额的时候打开
            $zs_money = bcadd((string)$zs_money,(string)$cash_bili,0);
        }

        return [$zs_money,$current_money];
    }


    /**
     * 判断用户是否能参加本次活动
     * @param $uid
     * @param $shop_id
     * @param $shop_type 点控的商场充值活动
     * @param $total_pay_num 点控的商场充值活动
     * @return void
     */
    private static function getUserShopStatus($uid,$shop_id,$total_pay_score,$coin,$shop_type,$total_pay_num){
        $marketing_shop = Db::connection('readConfig')->table('marketing_shop')->selectRaw('type,withdraw_bili,coin_money,num')->where('id',$shop_id)->first();
        if($marketing_shop && $shop_type && $shop_type == $marketing_shop['type']){
            Db::table('share_strlog')->where('uid',$uid)->update(['shop_type' => 0]);  //修改点控状态为0，表示本地已经参与了
            return ['',$marketing_shop['type']];
        }elseif($marketing_shop && $marketing_shop['type'] != 0){//	活动类型:6=新的破产活动,7=客损活动,10=首次充值
            //判断是否超过了参与次数
            $join_num = Db::table('shop_log')->where([['uid','=',$uid],['type','=',$marketing_shop['type']]])->count();
            if($join_num < $marketing_shop['num']) return ['',$marketing_shop['type']];
        }else{
            return ['支付回调时不满足活动商场条件',0];
        }
        return ['支付回调时不满足活动商场条件',$marketing_shop['type']];
    }



    /**
     * 判断用户是否能参加本次活动
     * @param $handshop_id
     * @param $total_pay_score
     * @return void
     */
    private static function getUserHandShopStatus($handshop_id,$total_pay_score){
        $marketing_handshop = Db::connection('readConfig')->table('marketing_handshop')->selectRaw('type')->where('id',$handshop_id)->first();
        if($marketing_handshop  && $marketing_handshop['type'] == 10 && $total_pay_score <= 0){
            return ['',$marketing_handshop['type']];
        }elseif($marketing_handshop){//	活动类型:6=新的破产活动,7=客损活动,10=首次充值
            return ['',$marketing_handshop['type']];
        }else{
            return ['支付回调时不满足活动商场条件',0];
        }
        return ['支付回调时不满足活动商场条件',$marketing_shop['type']];
    }



    /**
     * 判断用户是否能参加本次活动
     * @param $uid
     * @param $shop_id
     * @param $shop_type 点控的商场充值活动
     * @param $total_pay_num 点控的商场充值活动
     * @return void
     */
    private static function getUserActiveStatus($uid,$active_id){
        $order_active = Db::table('order_active')->selectRaw('type,num')->where('id',$active_id)->first();
        if($order_active){
            $order_active_num = Db::table('order_active_log')->where(['uid' => $uid ,'type' => $order_active['type']])->count();
            if($order_active['num'] <= $order_active_num)return '活动类型-Type:'.$order_active['type'].'已经用完了';
            return '';
        }else{
            return '支付回调时不满足活条件';
        }
        return '';
    }

//    /**
//     * 判断用户是否能参加本次活动
//     * @param $uid
//     * @param $shop_id
//     * @param $shop_type 点控的商场充值活动
//     * @param $total_pay_num 点控的商场充值活动
//     * @return void
//     */
//    private static function getUserShopStatus($uid,$shop_id,$total_pay_score,$coin,$shop_type,$total_pay_num){
//        $marketing_shop = Db::table('marketing_shop')->selectRaw('type,pay_money,withdraw_bili,coin_money,num')->where('id',$shop_id)->first();
//        if($shop_type && $shop_type == $marketing_shop['type']){
//            Db::table('share_strlog')->where('uid',$uid)->update(['shop_type' => 0]);  //修改点控状态为0，表示本地已经参与了
//            return ['',$marketing_shop['type']];
//        }elseif($marketing_shop['type'] == 7){//	活动类型:6=新的破产活动,7=客损活动,10=首次充值
//            $withdraw_money = WithdrawlogController::getUserTotalExchangeMoney($uid);  //用户提现的金额，包含待审核和处理中
//
//            $withdraw_bili = $total_pay_score > 0 ? bcdiv((string)$withdraw_money,(string)$total_pay_score,2) : 0;  // 提现 / 充值
//
//            //只能参与一次
//            $day_status = Db::table('shop_log')->select('id')->where([['uid','=',$uid],['type','=',7]])->first();
//            if($total_pay_score > 0 && $total_pay_score >= $marketing_shop['pay_money'] && $coin <= $marketing_shop['coin_money'] && $withdraw_bili <=  $marketing_shop['withdraw_bili'] && !$day_status){
//                return ['',$marketing_shop['type']];
//            }
//        }elseif ($marketing_shop['type'] == 6){
//            $withdraw_money = WithdrawlogController::getUserTotalExchangeMoney($uid);  //用户提现的金额，包含待审核和处理中
//            $withdraw_bili = $total_pay_score > 0 ? bcdiv((string)$withdraw_money,(string)$total_pay_score,2) : 0;  // 提现 / 充值
//
//            //每个用户只能参与一次
//            $pc_shop_log = Db::table('shop_log')->where([['uid','=',$uid],['type','=',6]])->value('id');
//            if(!$pc_shop_log && $total_pay_score > 0 && $coin <= $marketing_shop['coin_money'] && $withdraw_bili <= $marketing_shop['withdraw_bili']){
//                return ['',$marketing_shop['type']];
//            }
//        }elseif ($marketing_shop['type'] >= 10 && $marketing_shop['type'] <= 13){
//            $first_shop_log = Db::table('shop_log')->where([['uid','=',$uid],['type','=',$marketing_shop['type']]])->value('id');
//            if(!$first_shop_log)return ['',$marketing_shop['type']];
//        }else{
//            return ['支付回调时不满足活动商场条件',0];
//        }
//        return ['支付回调时不满足活动商场条件',$marketing_shop['type']];
//    }

    /**处理当日首充用户与当日付费统计
     * @param $uid 用户uid
     * @param $package_id 包id
     * @param $channel 渠道号
     * @param $user_type 用户等级
     * @param $price 支付金额
     * @param $af_status 是否是广告用户
     * @return void
     */
    public static function statisticsRetainedUser($uid,$package_id,$channel){

        //获取当日包和渠道下的首充用户
        $day_user = Db::table('statistics_retainedpaiduser')->where(['time'=> strtotime('00:00:00'), 'package_id' => $package_id, 'channel' => $channel])
            ->update([
                'uids' => Db::raw("concat(uids,',', '$uid')")
            ]);
        if(!$day_user){
            Db::table('statistics_retainedpaiduser')
                ->insert([
                    'time' => strtotime('00:00:00'),
                    'package_id' => $package_id,
                    'channel' => $channel,
                    'uids' => $uid,
                ]);
        }
        $list = ['statistics_retentionpaidlg','statistics_retentionpaid'];
        foreach ($list as $v){
            $res = Db::table($v)->where(['time'=> strtotime('00:00:00'), 'package_id' => $package_id, 'channel' => $channel])
                ->update([
                    'num' => Db::raw('num + 1')
                ]);

            if(!$res){
                Db::table($v)
                    ->insert([
                        'time' => strtotime('00:00:00'),
                        'package_id' => $package_id,
                        'channel' => $channel,
                        'num' => 1,
                    ]);
            }
        }

    }

    /**
     * 获取数字货币充值金额
     * @param $ordersn
     * @param $reallyPayMoney
     * @return string|void
     */
    private function getOrderProtocol($ordersn,$reallyPayMoney){
        $order_protocol = Db::table('order_protocol')->selectRaw('protocol_money,money')->where('ordersn',$ordersn)->first();
        if(!$order_protocol || $order_protocol['protocol_money'] > $reallyPayMoney){
            return '';
        }
        return $order_protocol['money'];
    }


    /**
     * 后台订单完成接口
     * @return void
     */
    #[RequestMapping(path:'htOrderApi')]
    public function htOrderApi(){
        $data = $this->request->all();
        $res = self::Orderhandle($data['ordersn'],$data['price'],2);
        if($res['code'] == 200){
            return ['code' => $res['code'],'msg' => $res['msg'],'data' => []];
        }

        return ['code' => 200,'msg' => 'success','data' => []];
    }


    /**
     *kk_pay 支付回调
     * @return false|string|void
     */
    #[RequestMapping(path:'kkpayNotify')]
    public function kkpayNotify() {
        $data = $this->request->all();

        $this->logger->error('kk_pay充值:'.json_encode($data));


        $custOrderNo=$data['partnerOrderNo'] ?? '';
        $ordStatus= $data['status'] ?? '';
        if(!$custOrderNo)return 0;
        $reallyPayMoney = (string)$data['amount'];
        if($ordStatus == '1'){
            $res = self::Orderhandle($custOrderNo,$reallyPayMoney);
            if($res['code'] == 200){
                return 0;
            }
            $this->logger->error('kk_pay充值事务处理失败==='.$res['msg'].'==ordersn=='.$custOrderNo);

            return 'fail';
        }
        return 0;
    }




    /**
     * 虚拟货币回调
     * @return false|string|void
     */
    #[RequestMapping(path:'cryptocurrencypayNotify')]
    public function cryptocurrencypayNotify()
    {
        $data = $this->request->all();
        $this->logger->error('虚拟币回调充值:'.json_encode($data));
        if(!isset($data['txid']) || !$data['txid'])return 'ok';
        $payData = \App\Service\BlockApi\BlockApiService::getTransactionInfo($data['txid']);
        $this->logger->error('虚拟币回调解析充值数据:'.json_encode($payData));
        $uid = Db::table('user_wallet_address')->where('address', $payData['from_address'])->value('uid');
        if(!$uid){
            $this->logger->error('钱包地址未找到用户:'.json_encode($payData));
            return 'ok';
        }

        $pay_price = bcmul((string)$payData['amount'],'100',0); //支付金额

        if($payData['symbol'] != 'USDT'){  //不是U需要转换金额
            $currency_and_ratio = $this->PayService->getCurrencyAndRatio(['name' => $payData['symbol']],2,'bili',2);
            if(!$currency_and_ratio){
                $this->logger->error('虚拟货币类型暂不支持:'.json_encode($payData));
                return 'ok';
            }
            //转换为U
            $payData['amount'] = $this->PayService->getFiatCryptoConversion($pay_price,$currency_and_ratio['bili']);
        }else{
            $payData['amount'] = $pay_price;
            $currency_and_ratio['bili'] = 1;
        }


        $userinfo = Db::table('userinfo')->selectRaw('total_pay_num,package_id,total_pay_score,channel')->where('uid',$uid)->first();
        //U赠送的赠送Bonus金额
        $payment_id_zs_bonus = $this->paymentIdZsBonus(['status' => 1,'currency' => $payData['symbol']],$pay_price, $userinfo['total_pay_num']);

        $createData = [
            "uid"           => $uid,
            "day"           => 0 ,
            "ordersn"  => $data['txid'],
            "paytype"       => $payData['symbol'],
            "zs_bonus"      => $this->PayService->getFiatCryptoConversion($payment_id_zs_bonus,$currency_and_ratio['bili']),  //转换为U
            "zs_money"      => 0,//转换为U
            "money"      => $this->PayService->getFiatCryptoConversion($payment_id_zs_bonus,$currency_and_ratio['bili']),//转换为U
            'get_money' => $payData['amount'],//转换为U
            'price'    => $payData['amount'],//转换为U
            'email'         => '',
            'phone'        => '',
            'nickname'        => '',
            'createtime' => time(),
            'packname' => '',
            'active_id' => 0,
            'ip' => '', //正式
            'all_price' => $userinfo['total_pay_score'],
            'fee_money' => 0,
            'current_money' => 0,
            'package_id' => $userinfo['package_id'],
            'channel' => $userinfo['channel'],
            'shop_id' => 0,
            'handshop_id' => 0,
            'pay_price' => $pay_price,
            'currency' => $payData['symbol'],
        ];

        Db::table('order')->insert($createData);
        $this->Orderhandle($data['txid'],$pay_price);
        return 'ok';
    }


    /**
     * @return void 订单充值成功处理
     * @param $ordersn  订单号
     * @param $type   1= 订单回调正常处理 ,2 = 后台强制完成
     */
    public function Orderhandle($ordersn,$reallyPayMoney = 0,$type = 1){

        $order = Db::table('order')->where('ordersn',$ordersn)->first();
        if(!$order){
            return ['code' => '201','msg' => '订单获取失败','data' => []];
        }
        if($order['pay_status'] == 1){
            return ['code' => '200','msg' => '支付成功','data' => []];
        }

        if($order['pay_price'] > $reallyPayMoney){
            $this->logger->error('支付金额不对小于实际支付金额直接返回-订单号:'.$ordersn);
            return ['code' => '200','msg' => '支付金额不对小于实际支付金额直接返回','data' => []];
        }

        $res = Db::table('order')->where('id','=', $order['id'])->update(['finishtime' => time(),'pay_status' => 1]);
        if(!$res){
            return ['code' => '202','msg' => '订单状态修改失败','data' => []];
        }


        $share_strlog = Db::table('share_strlog as a')
            ->join('userinfo as b','a.uid','=','b.uid')
            ->selectRaw('br_a.uid,br_a.gpsadid,br_a.adid,br_a.afid,br_a.device_id,br_a.appname,br_a.createtime,br_a.login_ip,
            br_a.phone,br_a.email,br_a.shop_type,br_b.puid,br_b.channel,br_b.package_id,br_b.vip,br_b.first_pay_score,
            br_b.total_pay_num,br_b.regist_time,br_b.total_pay_score,br_b.coin,br_a.af_status,br_a.is_brushgang,br_a.brushgang_pay_status,
            br_a.fbc, br_a.fbp,br_a.city,br_a.ip,br_a.is_agent_user')
            ->where('a.uid',$order['uid'])
            ->first();

        $remark = '';
        $marketing_shop_type = 0;
        $marketing_hand_shop_type = 0;
        if($order['active_id'] > 0){
            $remark = self::getUserActiveStatus($order['uid'],$order['active_id']);
        }elseif ($order['shop_id'] > 0){
            [$remark,$marketing_shop_type] = self::getUserShopStatus($order['uid'],$order['shop_id'],$share_strlog['total_pay_score'],$share_strlog['coin'],$share_strlog['shop_type'],$share_strlog['total_pay_num']);
        }elseif ($order['handshop_id'] > 0){
            [$remark,$marketing_hand_shop_type] = self::getUserHandShopStatus($order['handshop_id'],$share_strlog['total_pay_score']);
        }

        if($remark)Db::table('order')->where('id','=', $order['id'])->update(['remark' => $remark]);


        Db::beginTransaction();

        //修改用户总充值金额，总赠送金额，总充值次数

        $user_day =  [
            'uid' => $order['uid'].'|up',
            'puid' => $share_strlog['puid'].'|up',
            'vip' => $share_strlog['vip'].'|up',
            'channel' => $share_strlog['channel'].'|up',
            'package_id' => $share_strlog['package_id'].'|up',
            'total_pay_score' => $order['price'].'|raw-+',
            'total_pay_num' => '1|raw-+',
        ];

        //user_day表处理
        $user_day = new SqlModel($user_day);
        $res = $user_day->userDayDealWith();

        if(!$res){
            Db::rollback();
            return ['code' => '202','msg' => '玩家每日记录表跟新失败','data' => []];
        }



        $res = Db::table('userinfo')->where('uid',$order['uid'])
            ->update([
                'total_pay_num' => Db::raw('total_pay_num + 1'),
                'updatetime' => time(),
                'total_pay_score' =>Db::raw('total_pay_score + '.$order['price']),
            ]);
        if(!$res){
            Db::rollback();
            return ['code' => 201 ,'msg'=>'修改用户的总充值次数失败','data' => []];
        }

        //更新用户充值时间
        Db::table('share_strlog')->where('uid',$order['uid'])
            ->update([
                'last_pay_time' => time(),
                'last_pay_price' => $order['price'],
            ]);

        $is_first_recharge = false; //是否是第一次充值
        //普通充值判断用户是否是首充
        if($share_strlog['first_pay_score'] <= 0){
            $is_first_recharge = true;
            $res = Db::table('userinfo')->where('uid',$order['uid'])->update(['first_pay_score'=>$order['price'],'first_pay_time' => time(),'updatetime'=>time()]);
            if(!$res){
                Db::rollback();
                return ['code' => '202','msg' => '添加余额报文失败','data' => []];
            }
            //修改是否是首充充值字段
            Db::table('order')->where('id','=', $order['id'])->update(['is_first' => 1]);
            //添加每日用户
            self::statisticsRetainedUser($share_strlog['uid'],$share_strlog['package_id'],$share_strlog['channel']);

        }

        //roi
        $this->setRoi($share_strlog['regist_time'], $share_strlog['package_id'], $share_strlog['channel'], $order['price'], $share_strlog['uid'], $order['fee_money']);

        if($order['active_id'] > 0 && !$remark){
            $order_active = Db::table('order_active')->where('id',$order['active_id'])->first();
            $order_active_log = [
                'uid' => $order['uid'],
                'active_id' => $order['active_id'],
                'type' => $order_active['type'],
                'money' => $order['price'],
                'zs_money' => $order['zs_money'],
                'zs_bonus' => $order['zs_bonus'],
                'num' => $order_active['type'] == 1 ? 3 : 0,
                'expiretime' => $order_active['type'] == 2 ? bcadd(bcmul((string)$order['day'],'86400',0),(string)strtotime(date('Ymd')),0) : 0,
                'createtime' => time(),
                'package_id' => $share_strlog['package_id'],
                'channel' => $share_strlog['channel'],
            ];
            if($order_active['type'] == 2)$order_active_log['collectiontime'] = time();
            $res= Db::table('order_active_log')->insert($order_active_log);
            if(!$res){
                Db::rollback();
                $this->logger->error('uid:'.$order['uid'].'充值修改余额失败,订单号:'.$ordersn);
                return ['code' => '202','msg' => '活动日志存储失败','data' => []];
            }
            if($order_active['type'] == 7){
                Db::table('piggy_bank')->update([
                    'uid' => $order['uid'],
                    'piggy_bank_money' => Db::raw('piggy_bank_money - '.($order_active['get_cash'] + $order_active['get_bonus'] + $order_active['next_get_cash'])),
                    'active_id' => $order['active_id'],
                ]);
            }
            if($order_active['type'] != 1){

                $res = $this->user->userEditCoin($order['uid'],$order['get_money'],1,'玩家:'.$order['uid']."充值".$order["price"]."到账".bcdiv((string)$order["get_money"],'100',2),2);
                if(!$res){
                    Db::rollback();
                    $this->logger->error('uid:'.$order['uid'].'充值修改余额失败,订单号:'.$ordersn);
                    return ['code' => '202','msg' => '充值修改余额失败','data' => []];
                }
                $reason = config('reason.active_type_reason')[$order_active['type']] ?? 2;
                $water_multiple_type = config('reason.active_type_water_multiple')[$order_active['type']] ?? 1;
                if($order['zs_money'] > 0){  //如果不是活动或者商城活动强转的 才赠送Cash
                    $res = $this->user->userEditCoin($order['uid'],$order['zs_money'],$reason,'玩家:'.$order['uid']."充值".$order["price"]."赠送".bcdiv((string)$order["zs_money"],'100',2),2,$water_multiple_type);
                    if(!$res){
                        Db::rollback();
                        $this->logger->error('uid:'.$order['uid'].'充值赠送修改余额失败,订单号:'.$ordersn);
                        return ['code' => '202','msg' => '充值赠送余额失败','data' => []];
                    }
                }
                //赠送砖石
                if($order['zs_bonus'] > 0){  //如果不是活动或者商城活动强转的 才赠送Bonus
                    $res = $this->user->userEditBonus($order['uid'],$order['zs_bonus'],$reason,'玩家:'.$order['uid']."充值:".$order["price"]."赠送:".bcdiv((string)$order["zs_bonus"],'100',2)."Bonus",2,$water_multiple_type);
                    if(!$res){
                        Db::rollback();
                        $this->logger->error('uid:'.$order['uid'].'充值赠送Bonus失败,订单号:'.$ordersn);
                        return ['code' => '202','msg' => '充值赠送Bonus失败','data' => []];
                    }
                }
            }
        }else{
            //增加用户余额
            $res = $this->user->userEditCoin($order['uid'],$order['get_money'],1,'玩家:'.$order['uid']."充值".$order["price"]."到账".bcdiv((string)$order["get_money"],'100',2),2);
            if(!$res){
                Db::rollback();
                $this->logger->error('uid:'.$order['uid'].'充值修改余额失败,订单号:'.$ordersn);
                return ['code' => '202','msg' => '充值修改余额失败','data' => []];
            }
            if(!$remark && $order['zs_money'] > 0){  //如果不是活动或者商城活动强转的 才赠送Cash
                $res = $this->user->userEditCoin($order['uid'],$order['zs_money'],2,'玩家:'.$order['uid']."充值".$order["price"]."赠送".bcdiv((string)$order["zs_money"],'100',2),2,2);
                if(!$res){
                    Db::rollback();
                    $this->logger->error('uid:'.$order['uid'].'充值赠送修改余额失败,订单号:'.$ordersn);
                    return ['code' => '202','msg' => '充值赠送余额失败','data' => []];
                }
            }


            //赠送砖石
            if(!$remark && $order['zs_bonus'] > 0){  //如果不是活动或者商城活动强转的 才赠送Bonus
                $res = $this->user->userEditBonus($order['uid'],$order['zs_bonus'],2,'玩家:'.$order['uid']."充值:".$order["price"]."赠送:".bcdiv((string)$order["zs_bonus"],'100',2)."Bonus",2);
                if(!$res){
                    Db::rollback();
                    $this->logger->error('uid:'.$order['uid'].'充值赠送Bonus失败,订单号:'.$ordersn);
                    return ['code' => '202','msg' => '充值赠送Bonus失败','data' => []];
                }
            }


            //普通充值检查是否是活动商场
            if($order['shop_id'] > 0 && !$remark && $marketing_shop_type){
                $shop_log = Db::table('shop_log')->insert([
                    'uid' => $order['uid'],
                    'shop_id' => $order['shop_id'],
                    'type' => $marketing_shop_type,
                    'money' => $order['price'],
                    'get_money' => $order['get_money'],
                    'zs_money' => $order['zs_money'],
                    'zs_bonus' => $order['zs_bonus'],
                    'package_id' => $share_strlog['package_id'],
                    'channel' => $share_strlog['channel'],
                    'createtime' => time(),
                ]);
                if(!$shop_log){
                    Db::rollback();
                    $this->logger->error('uid:'.$order['uid'].'普通充值商场记录存储失败,订单号:'.$ordersn);
                    return ['code' => '202','msg' => '普通充值商场活动失败存储','data' => []];
                }
            }elseif ($order['handshop_id'] > 0 && !$remark && $marketing_hand_shop_type){
                $hand_shop_log = Db::table('handshop_log')->insert([
                    'uid' => $order['uid'],
                    'handshop_id' => $order['handshop_id'],
                    'type' => $marketing_hand_shop_type,
                    'money' => $order['price'],
                    'get_money' => $order['get_money'],
                    'zs_money' => $order['zs_money'],
                    'zs_bonus' => $order['zs_bonus'],
                    'package_id' => $share_strlog['package_id'],
                    'channel' => $share_strlog['channel'],
                    'createtime' => time(),
                ]);
                if(!$hand_shop_log){
                    Db::rollback();
                    $this->logger->error('uid:'.$order['uid'].'普通手动输入充值商城记录存储失败,订单号:'.$ordersn);
                    return ['code' => '202','msg' => '普通手动输入充值商场活动失败存储','data' => []];
                }
            }
        }



        //查询刷子帮是否能跟改支付通道
        if ($share_strlog['is_brushgang'] == 1 && $share_strlog['brushgang_pay_status'] == 1){
            $brushgang_pay_config = Common::getMore('brushgang_pay_money,brushgang_pay_num');
            $brushgang_pay_status = true;
            if($brushgang_pay_config['brushgang_pay_money'] && bcadd((string)$share_strlog['total_pay_score'],(string)$order['price'],0) < $brushgang_pay_config['brushgang_pay_money'])$brushgang_pay_status = false;
            if($brushgang_pay_status && $brushgang_pay_config['brushgang_pay_num'] && bcadd((string)$share_strlog['total_pay_num'],'1',0) < $brushgang_pay_config['brushgang_pay_num'])$brushgang_pay_status = false;
            if($brushgang_pay_status)Db::table('share_strlog')->where('uid',$order['uid'])->update(['brushgang_pay_status' => 0]);
        }


        Db::commit();


        //处理支付方式数据
        $order_tage_payment = Db::table('order_tage_payment')->where('order_id',$order['id'])->first();
        if($order_tage_payment){
            $this->setTwoOrderPaymentData($order_tage_payment['payment_type_id'],$order_tage_payment['pay_type_id'],$order['price']);
            Db::table('order_tage_payment')->where('order_id',$order_tage_payment['order_id'])->delete();
        }

        if($share_strlog['adid'] && $share_strlog['gpsadid'])$this->adjust->adjustUploadEvent($share_strlog['appname'],$share_strlog['gpsadid'],$share_strlog['adid'],(float)bcdiv((string)$order["price"],'100',2),$is_first_recharge,$ordersn,$share_strlog);



        if ($share_strlog['afid'])$this->adjust->afUploadEvent($share_strlog['appname'],$share_strlog['gpsadid'],$share_strlog['afid'],(float)bcdiv((string)$order["price"],'100',2),$is_first_recharge,$ordersn,$share_strlog);


        if($share_strlog['fbc']) $this->adjust->fbUploadEvent($share_strlog['appname'],(float)bcdiv((string)$order["price"],'100',2),$is_first_recharge,$ordersn,$order['uid'],$share_strlog);

        //统计无限代用户数据
        if($share_strlog['is_agent_user'] == 1)Common::agentTeamWeeklog($order['uid'],$order['price'],$order['fee_money']);
//        if(Common::getConfigValue('is_tg_send') == 1) {
//            //发送充值成功消息to Tg
//            \service\TelegramService::rechargeSuc($order);
//        }


        return ['code' => '200','msg' => '','data' => []];
    }

    /**
     * 用户roi数据添加
     * @param $regist_time
     * @param $package_id
     * @param $channel
     * @param $price
     * @return void
     */
    public function setRoi($regist_time, $package_id, $channel, $price, $uid, $fee_money){
        //$this->logger->error((string)$package_id);
        //$this->logger->error((string)$channel);

        try {
            co(function ()use($regist_time,$package_id,$channel,$price,$uid,$fee_money){
                $reg_day = date('Y-m-d',$regist_time);
                $reg_time = strtotime(date('Y-m-d',$regist_time));
                $new_time = strtotime('00:00:00');
                $i = ($new_time - $reg_time)/86400;
                $day = 'day'.($i + 1);

                if ($i <= 29) {
                    $res = Db::table('statistics_roi')->where(['time' => $reg_time, 'package_id' => $package_id, 'channel' => $channel])
                        ->update([
                            "recharge" => Db::raw("recharge + $price"),
                            "$day" => Db::raw("$day + $price"),
                        ]);

                    if (!$res) {
                        Db::table('statistics_roi')
                            ->insert([
                                'time' => $reg_time,
                                'num' => 1,
                                'package_id' => $package_id,
                                'channel' => $channel,
                                "recharge" => Db::raw("recharge + $price"),
                                "$day" => Db::raw("$day + $price"),
                            ]);
                    }
                }

                //data数据
                /*$day_data_channel = Db::table('day_data')
                    ->where(['date' => $reg_day, 'package_id' => $package_id, 'channel' => $channel])
                    ->select('new_total_recharge_users')
                    ->first();*/
                $day_data = Db::table('day_data')
                    ->where(['date' => $reg_day, 'package_id' => 0, 'channel' => 0])
                    ->select('new_total_recharge_users')
                    ->first();
                /*$users1 = $uid;
                if (!empty($day_data_channel) && !empty($day_data_channel['new_total_recharge_users'])){
                    $users_arr = explode(',',$day_data_channel['new_total_recharge_users']);
                    if (in_array($uid,$users_arr)){
                        $users1 = $day_data_channel['new_total_recharge_users'];
                    }else{
                        $users1 = $day_data_channel['new_total_recharge_users'].','.$uid;
                    }
                }*/
                $users2 = $uid;
                if (!empty($day_data) && !empty($day_data['new_total_recharge_users'])){
                    $users_arr2 = explode(',',$day_data['new_total_recharge_users']);
                    if (in_array($uid,$users_arr2)){
                        $users2 = $day_data['new_total_recharge_users'];
                    }else{
                        $users2 = $day_data['new_total_recharge_users'].','.$uid;
                    }
                }

                /*$res = Db::table('day_data')
                    ->where(['date' => $reg_day, 'package_id' => $package_id, 'channel' => $channel])
                    ->update([
                        "new_total_recharge" => Db::raw("new_total_recharge + $price"),
                        "new_total_recharge_users" => $users1,
                        "new_total_recharge_fee" => Db::raw("new_total_recharge_fee + $fee_money"),
                    ]);
                if (!$res && empty($day_data_channel)){
                    Db::table('day_data')
                        ->insert([
                            'date' => $reg_day,
                            'new_total_recharge' => $price,
                            'new_total_recharge_users' => $users1,
                            'new_total_recharge_fee' => $fee_money,
                            'package_id' => $package_id,
                            'channel' => $channel,
                        ]);
                }*/
                //$this->logger->error('res==>'.$res);

                $res = Db::table('day_data')
                    ->where(['date' => $reg_day, 'package_id' => 0, 'channel' => 0])
                    ->update([
                        "new_total_recharge" => Db::raw("new_total_recharge + $price"),
                        "new_total_recharge_users" => $users2,
                        "new_total_recharge_fee" => Db::raw("new_total_recharge_fee + $fee_money"),
                    ]);
                if (!$res && empty($day_data)){
                    Db::table('day_data')
                        ->insert([
                            'date' => $reg_day,
                            'new_total_recharge' => $price,
                            'new_total_recharge_users' => $users2,
                            'new_total_recharge_fee' => $fee_money,
                            'package_id' => 0,
                            'channel' => 0,
                        ]);
                }
            });
        }catch (\Exception $e){
            $this->logger->error('充值setRoi失败:'.$e->getMessage());
        }
    }
}
