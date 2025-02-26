<?php
declare(strict_types=1);
namespace App\Controller;

use Hyperf\DbConnection\Db;
use App\Common\Common;
use Hyperf\Di\Annotation\Inject;
use App\Common\User;
use App\Common\pay\Withdraw;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Controller\slots\Common as SlotsCommon;
use App\Controller\slots\DealWithController;
use App\Service\PayService;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;
use function Hyperf\Support\value;

#[Controller(prefix:'Withdrawlog')]
class WithdrawlogController extends AbstractController {


    #[Inject]
    protected User $user;
    #[Inject]
    protected SlotsCommon $slotsCommon;
    #[Inject]
    protected Withdraw $withdraw;
    #[Inject]
    protected DealWithController $DealWithController;
    #[Inject]
    protected PayService $PayService;



    private array $riskControlPackageId = []; //直接风控的包

    /**
     * @return void 提现历史记录
     */
    #[RequestMapping(path:'list')]
    public function list(){
        $uid = $this->request->post('uid');
        $page = $this->request->post('page') ?? 1;
        $limit = 20;
        $date = $this->request->post('date') ?? date('Ymd');
        if(!$date)$date =  date('Ymd');
        $date = str_replace('-', '', (string)$date);
        $start = strtotime($date);
        $end = $start + 86400;
        $withdraw_log = Db::connection('readConfig')
            ->table('withdraw_log')->selectRaw("FROM_UNIXTIME(createtime,'%Y/%m/%d %H:%i') as createtime,status,money,finishtime")
            ->where([['uid','=',$uid],['createtime','>=',$start],['createtime','<',$end]])
            ->orderBy('createtime','desc')
            ->forPage($page,$limit)
            ->get()
            ->toArray();

        foreach ($withdraw_log as &$v){
            if($v['finishtime'])$v['finishtime'] = date('Y/m/d H:i',$v['finishtime']);
        }

//        return json(['code' => 200 ,'msg'=>'','data' =>$withdraw_log ]);
        return $this->ReturnJson->successFul(200, $withdraw_log);
    }



    /**
     * @return void 用户提现页面
     */
    #[RequestMapping(path:'index')]
    public function index(){

        $uid = $this->request->post('uid');

        //统一处理三方流水输赢情况
        $this->DealWithController->setUserData($uid);

        $data = Db::table('userinfo')
            ->selectRaw('withdraw_money,coin,need_cash_score_water,now_cash_score_water,withdraw_money_other')
            ->where('uid',$uid)
            ->first();

        if(!$data)return $this->ReturnJson->failFul(226);

        //退款额度(退款额度需求)
//        if($data['withdraw_money'] > $data['coin']){
//            Db::table('userinfo')->where('uid',$uid)->update(['withdraw_money' => $data['coin']]);
//            $data['withdraw_money'] = $data['coin'];
//        }
        //退款额度
        if($data['withdraw_money_other'] > $data['coin']) Db::table('userinfo')->where('uid',$uid)->update(['withdraw_money_other' => $data['coin']]);

        $data['withdraw_money'] = $data['now_cash_score_water'] >= $data['need_cash_score_water'] ? $data['coin'] : $data['withdraw_money_other'];

        $withConfig = Common::getMore("with_min_money,with_max_money,with_money_config,withdraw_fee_bili,withdraw_fee_amount"); //退款最小金额
        $data['with_min_money'] = $withConfig['with_min_money'];  //系统最小退款金额
        $data['with_max_money'] = $withConfig['with_max_money'];  //系统最大退款金额
        $data['with_money_config'] = explode('|',$withConfig['with_money_config']); //系统最大退款金额
        $data['withdraw_fee_bili'] = $withConfig['withdraw_fee_bili']; //退款手续费比例
        $data['withdraw_fee_amount'] = $withConfig['withdraw_fee_amount']; //退款手续费固定值


        $data['withdraw_info'] = $this->withdrawFiatCurrencyInfo($uid);

//        return json(['code' => 200,'msg' => '成功','data' => $data]);

        return $this->ReturnJson->successFul(200, $data);
    }


    /**
     * 客户端获取不同国家的退款方式与地址
     * @param
     *
     */
    #[RequestMapping(path:'getWithDrawConfiguration')]
    public function getWithDrawConfiguration(){
        return $this->ReturnJson->successFul(200, $this->withdrawFiatCurrencyInfo($this->request->post('uid') ?? 0),2);
    }

    /**
     * 得到提现类型与用户退款信息
     * @param string|int $uid
     * @param int $type 1= 查看法币与所有的虚拟币的退款方式 ， 2 = 查看指定货币的退款方式
     * @return void
     */
    private function withdrawFiatCurrencyInfo(string|int $uid,int $type = 1){
        $currency = $this->request->post('currency') ?? 'VND';
        $refundmethod_type = match ($type){
            1 => $this->PayService->getWithRefundMethodType(selectType: 3,currency: $currency),
            2 => $this->PayService->getWithRefundMethodType(['status' => 1,'currency' => $currency]),
        };
        //数字货币协议
        $digital_currency_protocol = $this->PayService->getDigitalCurrencyProtocol();
        foreach($refundmethod_type as $value){
            if($value['type'] == 3){
                $value['video_url'] = '';
            }elseif ($value['type'] == 4){
                $refundmethod_type_array = explode(',',$value['protocol_ids']);
                foreach ($digital_currency_protocol as $digital_currency){
                    if(in_array($digital_currency['id'],$refundmethod_type_array)){
                        if($digital_currency['icon'])$digital_currency['icon'] = Common::domain_name_path((string)$digital_currency['icon']);
                        if($digital_currency['digital_currency_url'])$digital_currency['digital_currency_url'] = Common::domain_name_path((string)$digital_currency['digital_currency_url']);
                        $value['refundmethod_type_array'][] = $digital_currency;
                    }
                }
                $value['video_url'] = '';
            }else{
                $value['video_url'] = '';
            }
            $data['refundmethod_type'][] = $value;
        }


        //获取用户提现的信息
        $user_withinfo = $this->PayService->getUserWithdrawInfo(['uid' => $uid,'status' => 1,'currency' => $currency]);
        $data['user_withinfo'] = [];
        foreach ($user_withinfo as &$v){
            if(!isset($data['user_withinfo'][$v['type']]))$data['user_withinfo'][$v['type']] = $v;
        }
        $user_wallet_address =  $this->PayService->getWithdrawWalletAddressInfo(['uid' => $uid]);
        if($user_wallet_address)foreach ($user_wallet_address as $wallet){
            $data['user_withinfo'][$wallet['type']] = $wallet;
        }
        return $data;
    }


    /**
     * 获取提现渠道
     * @param $money  用户提现金额
     * @param $withdraw_id_type_id  提现通道ID
     * @param $package_id  包名ID
     * @param $uid  用户UID
     * @param $user_withdraw_type 退款类型:1=银行卡,2=upi,	3=钱包,4=数字货币
     */
    public function getWithdrawType($money,$withdraw_id_type_id,$package_id,$uid,$user_withdraw_type = 1){
        $currency = $this->request->post('currency') ?? 'VND';
        //如果客户端传入了支付通道，直接拿取使用
        if($withdraw_id_type_id){
            $withdraw_type = Db::table('withdraw_type')
                ->where([
                    ['minmoney','<=',$money],
                    ['maxmoney','>=',$money]
                ])
                ->where(['id' => $withdraw_id_type_id,'status' => 1])
                ->first();
            if(!$withdraw_type) return ['code' => 244,'msg' => 'Sorry! No recharge channel has been matched yet','data' => []];
            if($user_withdraw_type == 2 && !$withdraw_type['upi_status'])return ['code' => 265,'msg' => 'Sorry! This channel currently only supports bank card refunds','data' => []];
            return ['code' => 200,'msg' => 'success','data' => $withdraw_type];
        }
        $withdraw_type_ids = Db::table('apppackage_config')->where('package_id',$package_id)->value('withdraw_type_ids');


        $pay_before_num = Common::getConfigValue('pay_before_num'); //充值前几次时匹配特定通道

        $withCreatetime = Db::table('withdraw_log')->where([['uid','=',$uid]])->whereIn('status',[0,3,1])->orderBy('createtime','desc')->value('createtime');

        $withCount = Db::table('userinfo')->where([['uid','=',$uid]])->value('total_exchange_num');


        $paymentWhere = '';
        if($user_withdraw_type)$paymentWhere = 'FIND_IN_SET('.$user_withdraw_type.',refundmethod_ids)';

        //处理WDD退款评率订单
        $Redis = Common::Redis('RedisMy6379_1');
        $wddWithdrawStatus = $this->getWddWithdrawUidTime($Redis,$uid);
        $where = [['currency','=',$currency]];
        if($wddWithdrawStatus)$where[] = ['name','<>','wdd_pay'];

        $withdraw_type = $this->getWithDrawTypeArray($money,$where,$paymentWhere,$withdraw_type_ids,$user_withdraw_type,$withCreatetime,$pay_before_num > $withCount ? 1 : 0);
        if(!$withdraw_type){
            if($pay_before_num > $withCount){  //特殊通道没匹配到直接换
                $withdraw_type = $this->getWithDrawTypeArray($money,$where,$paymentWhere,$withdraw_type_ids,$user_withdraw_type,$withCreatetime);
                if(!$withdraw_type) return ['code' => 278,'msg' => 'Sorry! No refund channel has been matched yet','data' => []];
            }else{
                return ['code' => 278,'msg' => 'Sorry! No refund channel has been matched yet','data' => []];
            }
        }

        if($user_withdraw_type == 1 && !$withdraw_type)return ['code' => 244,'msg' => 'Sorry! No refund channel has been matched yet','data' => []];
        if($user_withdraw_type == 2 && !$withdraw_type)return ['code' => 266,'msg' => 'Sorry! There is no available UIP refund channel. Please submit a bank card refund attempt','data' => []];

        if($uid){
            $withdraw_log = Db::table('withdraw_log')->selectRaw('status,withdraw_type')->where('uid',$uid)->orderBy('id','desc')->first();
            if($withdraw_log && $withdraw_log['status'] == 2){
                $keyValue = 0;
                foreach ($withdraw_type as $key => $v){
                    if($v['name'] == $withdraw_log['withdraw_type']){
                        $keyValue = $key + 1;
                        break;
                    }
                }
                $withdraw_type = $withdraw_type[$keyValue] ?? $withdraw_type[0];
            }elseif ($withdraw_log && $withdraw_log['status'] == 1){
                $keyValue = 0;
                foreach ($withdraw_type as $key => $v){
                    if($v['name'] == $withdraw_log['withdraw_type']){
                        $keyValue = $key;
                        break;
                    }
                }
                $withdraw_type = $withdraw_type[$keyValue] ?? OrderController::getFirstPay($withdraw_type);
            }else{
                $withdraw_type = OrderController::getFirstPay($withdraw_type);
            }
        }else{
            $withdraw_type = OrderController::getFirstPay($withdraw_type);
        }
        //存储Wdd订单
        if($withdraw_type['name'] == 'wdd_pay')$this->setWddWithdrawUidTime($Redis,$uid);

        return ['code' => 200,'msg' => 'success','data' => $withdraw_type];
    }


    /**
     * @param $money 退款金额
     * @param $where 自定义条件
     * @param $paymentWhere 退款方式
     * @param $withdraw_type_ids 退款类型ID
     * @param $user_withdraw_type
     * @param $withCreatetime
     * @param int $is_specific_channel
     * @return mixed[]
     */
    private function getWithDrawTypeArray($money,$where, $paymentWhere,$withdraw_type_ids,$user_withdraw_type,$withCreatetime,int $is_specific_channel = 0){
        $query = Db::table('withdraw_type')
            ->selectRaw("id,name,IF(".self::getFreeStatus().",0,`fee_bili`) as fee_bili,IF(".self::getFreeStatus().",0,`fee_money`) as fee_money,weight")
            ->where($where)
            ->where([
                ['status' ,'=', '1'],
                ['minmoney','<=',$money],
                ['maxmoney','>=',$money]
            ]);
        if($withdraw_type_ids)$query->whereIn('id',explode(',',(string)$withdraw_type_ids));
        if($user_withdraw_type == 2)$query->where('upi_status',1);
        if($is_specific_channel == 1)$query->where('is_specific_channel','=',1);
        if($paymentWhere)$query->whereRaw($paymentWhere);
        //tm_pay 2分钟只能拉一笔
        if($withCreatetime && ($withCreatetime + 120) > time())$query->where('name','<>','tm_pay');
        return $query->orderBy('weight','desc')
            ->get()
            ->toArray();
    }



    private function setWddWithdrawUidTime($Redis,$uid){
        $Redis->set('wdd_withdraw_' . $uid, '1', 150);
    }


    private function getWddWithdrawUidTime($Redis,$uid){
        return $Redis->get('wdd_withdraw_' . $uid);
    }


    /**
     * @return void 用户提现
     */
    #[RequestMapping(path:'add')]
    public function add(){

        $uid = $this->request->getAttribute('uid');
        $money = (string)$this->request->post('money');  //U的金额
        $user_withinfo_id = $this->request->post('user_withinfo_id'); //用户退款ID
        $withdraw_type_id   = $this->request->post('withdraw_id_type_id') ?? 0;  //提现通道ID
        $refundmethod_type_id = $this->request->post('refundmethod_type_id') ?? 1; //退款方式ID:1=银行卡,2=UPI,3=钱包,4=数字货币
        $currency = $this->request->post('currency') ?? 'VND'; //货币
        $protocol_name = $this->request->post('protocol_name') ?? ''; //数字货币协议



        $userinfo = Db::table('userinfo as a')
            ->join('share_strlog as b','a.uid','=','b.uid')
            ->selectRaw('br_a.coin,br_a.bonus,(br_a.cash_total_score + br_a.bonus_total_score) as total_score,
            br_a.total_pay_score,br_a.total_give_score,br_a.total_exchange,br_a.package_id,br_a.channel,br_a.withdraw_money,
            br_b.phone,br_b.email,br_b.af_status,br_b.city,(br_a.total_cash_water_score + br_a.total_bonus_water_score) as total_water_score,
            br_b.jiaphone,br_b.jiaemail,br_b.jianame,br_b.tag,br_a.now_cash_score_water,br_a.need_cash_score_water,br_a.withdraw_money_other,
            br_a.vip,br_b.createtime,br_b.is_brushgang,br_a.total_exchange_num,br_b.puid')
            ->where('a.uid',$uid)
            ->first();

        if(!$userinfo)return $this->ReturnJson->failFul(239);
        if($userinfo['total_pay_score'] <= 0 && $userinfo['withdraw_money_other'] <= 0) return $this->ReturnJson->failFul(239);


        //获取货币比例
        $currency_and_ratio = $this->PayService->getCurrencyAndRatio(['name' => $currency,'status' => 1],2,'bili',2);
        if(!$currency_and_ratio)return $this->ReturnJson->failFul(280);  //抱歉,该区域暂不支持充值!


        $getConfig = Common::getMore('withdraw_fee_bili,withdraw_fee_amount,special_package_ids');


        //平台退款手续费
        if($money <= 10000){
            $pt_fee_momey = '0';
        }elseif ($money <= 20000 && $refundmethod_type_id == 3){
            $pt_fee_momey = '0';
        }else{
            $pt_fee_momey = bcmul((string)$getConfig['withdraw_fee_bili'],$money,0);
            $pt_fee_momey = bcadd($pt_fee_momey,(string)$getConfig['withdraw_fee_amount'],0);

        }


//        $really_money = bcadd($money ,$pt_fee_momey,0); //用户真实扣除的金额
        $really_money = bcsub($money ,$pt_fee_momey,0); //用户真实得到的金额
        $really_withdraw_money = $this->PayService->getFiatCryptoConversion($really_money,$currency_and_ratio['bili'],2);


        if(!$money || $money <= 0 || !$user_withinfo_id)return $this->ReturnJson->failFul(219);


        //可用余额是否满足
        if($userinfo['coin'] < $money) return $this->ReturnJson->failFul(240);

        //抱歉!退款额度不足(退款额度需求)
//        if($userinfo['withdraw_money'] <= 0 || $userinfo['withdraw_money'] < $money)return $this->ReturnJson->failFul(241);
        //抱歉!退款额度不足(打流水需求)
        if(($userinfo['need_cash_score_water'] <= 0 || $userinfo['now_cash_score_water'] < $userinfo['need_cash_score_water']) && $userinfo['withdraw_money_other'] < $money)return $this->ReturnJson->failFul(241);


        //用户总退款金额
        $new_total_exchange = (string)$userinfo['total_exchange'];

        //获取用户退款最大额度
        $withdraw_multiple = Common::getConfigValue("withdraw_multiple");
        $withdraw_multiple_money = bcmul((string)$withdraw_multiple,(string)$userinfo['total_pay_score'],0);
        if($withdraw_multiple_money && $withdraw_multiple_money < bcadd($new_total_exchange,$money,0)) return $this->ReturnJson->failFul(272);//抱歉！您的VIP等级不够,请提升Vpi等级



        $with_min_money = Common::getConfigValue("with_min_money");
        if($new_total_exchange > 0 && $with_min_money > $money) return $this->ReturnJson->failFul(242);//抱歉!提现小于了最低提现金额

        $with_max_money = Common::getConfigValue("with_max_money");
        if($with_max_money < $money) return $this->ReturnJson->failFul(253);//抱歉!提现大于了最高提现金额

        //钱包与数字货币
        if(in_array($refundmethod_type_id,[3,4])){
            $user_withinfo = $this->PayService->getUserWithdrawInfo(['uid' => $uid,'id' => $user_withinfo_id],selectType: 2);
            if(!$user_withinfo)return $this->ReturnJson->failFul(260);
            $user_withinfo['email'] = '';
            $user_withinfo['phone'] = '';
        }else{
            //用户提现信息判断
            $user_withinfo = $this->PayService->getWithdrawWalletAddressInfo(['uid' => $uid,'id' => $user_withinfo_id],selectType: 2);
            if(!$user_withinfo)return $this->ReturnJson->failFul(260);
        }

        //用户提现平台判断
        $res = $this->getWithdrawType($really_money,$withdraw_type_id,$userinfo['package_id'],$uid,$user_withinfo['type']);
        if($res['code'] != 200)return $this->ReturnJson->failFul($res['code']);
        $withdraw_type = $res['data'];


        //获取用户今日退款的金额
        $user_day_withdraw_log = Db::table('user_day_'.date('Ymd'))->selectRaw('total_exchange,total_exchange_num')->where('uid',$uid)->first();


        if($user_day_withdraw_log){
            $day_with_moeny = (string)$user_day_withdraw_log['total_exchange'];//用户今日提现金额
            $day_withdraw_num = $user_day_withdraw_log['total_exchange_num'];//用户今日提现次数
        }else{
            $day_with_moeny = '0';//用户今日提现金额
            $day_withdraw_num = 0;//用户今日提现次数
        }


        //判断每日退款次数是否已到达上线
        $withdraw_vip = Db::table('vip')->select('day_withdraw_money','day_withdraw_num','order_pay_money','withdraw_max_money')->where('vip',$userinfo['vip'])->first();
        if($withdraw_vip){
            //判断用户次数是否已达上限
            if($withdraw_vip['day_withdraw_num'] &&  $withdraw_vip['day_withdraw_num'] <= $day_withdraw_num) return $this->ReturnJson->failFul(268);
            //判断用户金额是否已达上限
            if($withdraw_vip['day_withdraw_money'] && $withdraw_vip['day_withdraw_money'] < bcadd($money,$day_with_moeny,0))return $this->ReturnJson->failFul(270);

        }



        //关联用户数据
        $gl_data = $this->getGlDataArray($uid);


        [$auditdesc,$user_withdraw_bili,$firstmoney,$lastmoney,$dataList] = $this->subControl($money,$day_with_moeny,(string)$userinfo['coin'],(string)$userinfo['total_pay_score'],(string)$userinfo['total_give_score'],$new_total_exchange,(string)$userinfo['total_score'],(string)$userinfo['total_water_score'],$day_withdraw_num,(int)$userinfo['af_status'],(int)$uid,(string)$userinfo['city'],$userinfo,$gl_data);

        //手续费
        $fee = 0;

        if($withdraw_type['fee_bili'] && $withdraw_type['fee_bili'] > 0){  //比例手续费
            $fee = bcmul((string)$withdraw_type['fee_bili'],$money,0);
        }
        if($withdraw_type['fee_money'] && $withdraw_type['fee_money'] > 0){ //固定手续费
            $fee = bcadd((string)$withdraw_type['fee_money'],(string)$fee,0);
        }

        $withdraw_money_other = 0;
        if($userinfo['withdraw_money_other'] > $money){
            $withdraw_money_other = $money;
        }elseif ($userinfo['withdraw_money_other'] > 0){
            $withdraw_money_other = $userinfo['withdraw_money_other'];
        }

        //扣除用户余额
        $res = User::userEditCoin($uid,bcsub('0',$money,0),4,'玩家:'.$uid."退款扣除:".bcdiv($money,'100',2),3,1,bcsub('0',(string)$withdraw_money_other,0));

        if(!$res){

            $this->logger->error('uid:'.$uid.'退款修改余额失败');
            return $this->ReturnJson->failFul(245);
        }



        $data = [
            'uid' => $uid,
            'withdraw_type' => $withdraw_type['name'],
            'before_money' => $userinfo['coin'],  //用户之前的余额
            'ordersn' => Common::doOrderSn(000),
            'fee_money' => $pt_fee_momey,  //平台手续费(分为单位)
            'fee' => $fee,  //三方手续费
            'money' => $money, //提现金额
//            'really_money' => $money, //实际到账金额
            'really_money' => $really_money, //实际到账金额
            'backname' => $user_withinfo['backname'] ?? '', //实际到账金额
            'bankaccount' => $user_withinfo['account'] ?? $user_withinfo['address'],
            'ifsccode' => $user_withinfo['ifsccode'] ?? '',
            'createtime' => time(),
            'packname' => $this->request->getAttribute('packname'),
            'ip' => Common::getIp($this->request->getServerParams()), //正式
            'type' => $user_withinfo['type'],
            'package_id' => $userinfo['package_id'],
            'channel' => $userinfo['channel'],
            'auditdesc' => $auditdesc,
            'control' => $firstmoney.','.$lastmoney,
            'status' =>  $auditdesc == 0 ? 0 : 3,
            'email' =>  $user_withinfo['email'] ?:  $userinfo['jiaemail'],
            'phone' => $user_withinfo['phone'] ?:  $userinfo['jiaphone'],
            'withdraw_money_other' => $withdraw_money_other,
            'really_withdraw_money' => $really_withdraw_money,
            'currency' => $currency,
        ];


        $withdraw_log_id = Db::table('withdraw_log')->insertGetId($data);
        if(!$withdraw_log_id) return $this->ReturnJson->failFul(246);


        //处理提现中间表
        $res = $this->withdrawLogCenter($withdraw_log_id,$userinfo['total_pay_score'],$userinfo['total_exchange'],$user_withdraw_bili,$uid,$userinfo['withdraw_money'],$dataList,$gl_data);

        if(!$res) return $this->ReturnJson->failFul(246);

        if($auditdesc != 0){
            $userinfo['uid'] = $uid;
            $this->setUserWithdrawLogInfo($userinfo,(int)$money);
            return $this->ReturnJson->successFul();
        }
        $data['protocol_name'] = $protocol_name;
        $data['jianame'] = $userinfo['jianame'];
        $apInfo = $this->withdraw->withdraw($data,$data['withdraw_type'],2);
        if($apInfo['code'] != 200) return $this->ReturnJson->failFul(246);


        $res = Db::table('withdraw_log')->where('id','=',$withdraw_log_id)->update(['platform_id'=> $apInfo['data'],'updatetime' => time()]);

        if(!$res) return $this->ReturnJson->failFul(246);
        //(迁移)
        $userinfo['uid'] = $uid;
        $this->setUserWithdrawLogInfo($userinfo,(int)$money);


//        return json(['code' => 200 ,'msg'=>'success','data' =>[] ]);
        return $this->ReturnJson->successFul();
    }



    /**
     * 首次退款弹窗
     * @return void
     */
    #[RequestMapping(path:'getFirstWithdrawStatus')]
    public function getFirstWithdrawStatus(){
        $uid = $this->request->post('uid');
        $userinfo = Db::table('userinfo')
            ->select('total_pay_score','now_cash_score_water','need_cash_score_water','coin')
            ->where('uid',$uid)
            ->first();
        if(!$userinfo || $userinfo['total_pay_score'] <= 0 || $userinfo['need_cash_score_water'] <= 0 || $userinfo['need_cash_score_water'] > $userinfo['now_cash_score_water']) return $this->ReturnJson->successFul(200,['status' => 0]);
        //退款弹窗金额
        $pop_withdraw_money = Common::getConfigValue('pop_withdraw_money');
        if($pop_withdraw_money <= 0) return $this->ReturnJson->successFul(200,['status' => 0]);
        //获取用户总退款金额
        $withDrawMonty = $this->getUserTotalExchangeMoney($uid);
        //检查是否已过款
        if($withDrawMonty > 0) return $this->ReturnJson->successFul(200,['status' => 0]);
        //检查我的退款金额是否已经满足
        if($userinfo['coin'] < $pop_withdraw_money)return $this->ReturnJson->successFul(200,['status' => 0]);
        $user_withinfo = Db::table('user_withinfo')->where(['uid' => $uid])->orderBy('id','desc')->first();
        return $this->ReturnJson->successFul(200,['status' => 1,'money' => $pop_withdraw_money,'user_withinfo' => $user_withinfo]);

    }



    /**
     * 后台拉起退款接口
     * @return void
     */
    #[RequestMapping(path:'htWithdrawApi')]
    public function htWithdrawApi(){
        $data = $this->request->all();
        if($data['status'] == 0){
            $this->Withdrawhandle($data['ordersn'],1,[],2);
        }else{
            $apInfo = $this->withdraw->withdraw($data,$data['withdraw_type'],4);
            if($apInfo['code'] == 300){
                Db::table('withdraw_log')->where('id',$data['id'])->update(['status' => 3]);
                return $this->response->json(['code' => 201,'msg' => '服务器与三方网络冲突,请过1分钟左右再尝试该通道','data' => []]);
            }

            if($apInfo['code'] != 200 ){
                return $this->response->json(['code' => 201,'msg' => '第三方提现失败','data' => []]);
            }

            Db::table('withdraw_log')->where('id','=',$data['id'])->update(['platform_id'=> $apInfo['data'],'updatetime' => time(),'remark' => $data['remark'],'admin_id' => $data['admin_id']]);
        }



        return $this->response->json(['code' => 200,'msg' => 'success','data' => []]);
    }



    private function getGlDataArray($uid){
        //关联用户数据
        [$gl_data['phoneUid'],$gl_data['emailUid'],$gl_data['ipUid'],$gl_data['bankaccountUid'],$gl_data['upiUid']] = \App\Common\My::glTypeUid($uid);
        $allgluid = array_merge($gl_data['phoneUid'],$gl_data['emailUid'],$gl_data['ipUid'],$gl_data['bankaccountUid']);
        $gl_data['glUid'] = array_unique($allgluid); //去重

        $gl_data['gl_user'] = 0;
        $gl_data['gl_order'] = 0;
        $gl_data['gl_withdraw'] = 0;
        $gl_data['gl_refund_bili'] = 0;
        if(count($gl_data['glUid']) > 0){
            $gl_data['gl_user'] = count($gl_data['glUid']); //关联用户数量
            $gl_data['gl_order'] = Db::table('userinfo')->whereIn('uid',$gl_data['glUid'])->sum('total_pay_score'); //关联用户充值金额
            $gl_data['gl_withdraw'] = Db::table('userinfo')->whereIn('uid',$gl_data['glUid'])->sum('total_exchange'); //关联用户提现金额
            $gl_data['gl_refund_bili'] = $gl_data['gl_order'] == 0 ? 0 : bcdiv((string)$gl_data['gl_withdraw'],(string)$gl_data['gl_order'],2); //关联用户提现率
        }
        return $gl_data;
    }

    /**
     * 风控处理
     * @param string $money 用户的提现金额
     * @param string $day_with_moeny 今日提现金额
     * @param string $coin 用户余额
     * @param string $total_pay_score 	总充值金额
     * @param string $total_give_score 总赠送余额
     * @param string $total_exchange 总提现余额
     * @param string $total_score 总输赢
     * @param string $total_water_score 总流水
     * @param int $day_withdraw_num 今日退款次数
     * @param int $af_status 是否是广告用户
     * @param int $uid 用户uid
     * @param string $city 用户的城市
     * @param array $userinfo 用户信息
     * @param array $glUid 关联用户uid
     * @return void
     */
    private function subControl(string $money, string $day_with_moeny, string $coin, string $total_pay_score, string $total_give_score, string $total_exchange, string $total_score, string $total_water_score,int $day_withdraw_num, int $af_status,int $uid,string $city,array $userinfo,array $glData):array{


        $firstmoney = bcadd($coin,$total_exchange,0); //用户余额 + 用户提现成功的金额 + 用户提现处理中的金额

        $lastmoney = bcadd(bcadd($total_pay_score,$total_give_score,0),$total_score,0); //总充值金额 + 总赠送金额 + 总输赢

        $user_withdraw_bili = $total_pay_score == 0 ? bcdiv(bcadd($total_exchange,$money,0),'10000',2) : bcdiv(bcadd($total_exchange,$money,0),$total_pay_score,2); //总提现提现比例

        $special_gang_money = 0; //每次充值金额达到多少进入风控，0表示不判断
        $special_gang_withdrwmoney = 0; //每次提现金额达到多少进入风控，0表示不判断
        $day_withdrw_money = 0; //特殊帮今日提现金额
        $all_withdraw = 0; //玩家累计退款金额进入风控
        $withdraw_max_coin = 0; //玩家单笔最大退款金额进入风控
        $gl_user_count = Common::getConfigValue('gl_user_count');  //关联用户数量
        $gl_user_withdraw_bili = Common::getConfigValue('gl_user_withdraw_bili'); //关联用户退款比例
        $gl_user_count = $gl_user_count ?: 0;
        $gl_user_withdraw_bili = $gl_user_withdraw_bili ?: 0;
        if($af_status == 1){
            $recharge_amount_bili = Common::getConfigValue('af_recharge_amount_bili'); //广告量打码量充值比
            $customer_loss = Common::getConfigValue('af_customer_loss');//广告客损金额
        }else{
            $recharge_amount_bili = Common::getConfigValue('recharge_amount_bili');//普通用户打码量充值比
            $customer_loss = Common::getConfigValue('customer_loss');//普通用户客损金额
            $all_withdraw = Common::getConfigValue('all_withdraw');//普通用户累计退款金额
            $withdraw_max_coin = $userinfo['is_brushgang'] == 1 ? Common::getConfigValue('brushgang_user_single_coin') : Common::getConfigValue('withdraw_max_coin');//普通用户笔最大退款金额
            if($city){
                $getSpecialGangStatus = $this->getSpecialGangStatus($city);
                if($getSpecialGangStatus){

                    $day_withdrw_money = $day_with_moeny;

                    $special_gang_money = Common::getConfigValue('special_gang_money');
                    $special_gang_withdrwmoney = Common::getConfigValue('special_gang_withdrwmoney');
                }
            }

        }

        $user_customer_loss = bcsub($total_pay_score,$total_pay_score,0); //客损金额
        $user_recharge_amount_bili = bcadd($total_pay_score,$total_give_score,0) <= 0 ? bcdiv($total_water_score,'10000',2) : bcdiv($total_water_score,bcadd($total_pay_score,$total_give_score,0),2); //打码量比例

        if(in_array($userinfo['package_id'],$this->riskControlPackageId)){ //判断是否直接进入风控
            $auditdesc = 8;
        }elseif($money <= 20000){
            $auditdesc = 0;
        }else{
            if($userinfo['tag'])$tag_Array = explode(',', $userinfo['tag']);
            if($userinfo['tag'] && in_array('1', $tag_Array)) {
                $auditdesc = 7;  //标签需要审核的用户
            }elseif($user_customer_loss < $customer_loss){//  客损金额小于了配置
                $auditdesc = 1;
            }elseif ($user_recharge_amount_bili < $recharge_amount_bili){  //打码量充值比小于配置
                $auditdesc = 2;
            }elseif ($special_gang_money && $special_gang_money <= (Db::table('user_day_'.date('Ymd'))->where('uid','=',$uid)->value('total_pay_score') ?? 0)){
                $auditdesc = 3;  //非广告用户特殊地区今日总充值大于配置
            }elseif ($special_gang_withdrwmoney && $special_gang_withdrwmoney <= bcadd($money,(string)$day_withdrw_money,0)){
                $auditdesc = 4;  //非广告用户特殊地区今日总提现大于配置
            }elseif ($all_withdraw && $all_withdraw <= bcadd($money,$total_exchange,0)){
                $auditdesc = 5;  //非广告用户累计退款最大金额
            }elseif ($withdraw_max_coin && $withdraw_max_coin <= $money){
                $auditdesc = 6;  //非广告用户单笔最大退款金额
            }elseif ($gl_user_count < $glData['glUid'] && $gl_user_withdraw_bili < $glData['gl_refund_bili']){//关联用户数量超过了且退款比例过大
                $auditdesc = 9;
            }else{
                //风控状态:0=正常提现,1=客损金额小于了配置,2=打码量充值比小于配置,3=非广告用户特殊地区今日总充值大于配置,4=非广告用户特殊地区今日总提现大于配置,5=非广告用户累计退款最大金额,6=非广告用户单笔最大退款金额,7=网红私域订单,8=直接风控的包,9=关联用户退款率过高
                $auditdesc = 0;
            }

        }


        $dataList['water_multiple'] = $user_recharge_amount_bili;
        return [$auditdesc,$user_withdraw_bili,$firstmoney,$lastmoney,$dataList];

    }


    /**
     * @param  $city 玩家的city
     * @return void 判断用户是否在每日充值大于配置进入风控的帮众
     */
    private function getSpecialGangStatus(string $city){

        $black = [
            'Mumbai','Hyderabad','Delhi'
        ];

        foreach ($black as $vo)
        {
            if (strpos(strtolower($city), $vo) !== false)
            {
                return 1;
            }
        }

        return 0;

    }


    /**
     * 获取用户今日提现金额
     * @param int $uid uid
     * @return int
     * @return void
     */
    public function getUserDayTotalExchangeMoney(int $uid){
        //(迁移)
        $total_exchange = Db::table('user_day_'.date('Ymd'))->where('uid',$uid)->value('total_exchange');
        return $total_exchange ?: 0;
    }


    /**
     * @param $withdraw_log_id  提现id
     * @param $ordersn  提现订单号
     * @param $total_pay_score  总充值金额
     * @param $total_exchange 总提现金额
     * @param $user_withdraw_bili 用户总提现率
     * @param $uid 用户uid
     * @param $now_withdraw_money 用户当前可退款金额
     * @param $dataList 用户当前可退款金额
     * @param $gl_data 关联数据
     * @return void
     */
    private function withdrawLogCenter($withdraw_log_id,$total_pay_score,$total_exchange,$user_withdraw_bili,$uid,$now_withdraw_money,$dataList,$gl_data){




        $data = [
            'withdraw_id' => $withdraw_log_id,
            'order_coin' => $total_pay_score,
            'withdraw_coin' => $total_exchange,
            'withdraw_bili' => $user_withdraw_bili,
            'water_multiple' => $dataList['water_multiple'],
            'gl_user' => $gl_data['gl_user'],
            'gl_order' => $gl_data['gl_order'],
            'gl_withdraw' => $gl_data['gl_withdraw'],
            'gl_refund_bili' => $gl_data['gl_refund_bili'],
            'gl_device' =>0,
            'gl_phone' => count($gl_data['phoneUid']),
            'gl_email' => count($gl_data['emailUid']),
            'gl_upi' => count($gl_data['upiUid']),
            'gl_bankaccount' => count($gl_data['bankaccountUid']),
            'gl_ip' => count($gl_data['ipUid']),
            'now_withdraw_money' => $now_withdraw_money, //用户当前可退款金额
        ];

        $res = Db::table('withdraw_logcenter')->insert($data);
        if(!$res){
            return false;
        }
        return true;
    }

    /**
     * 获取有效流水和需求流水字段
     * @param $type 1 = CASH ,2=BONUS
     * @return void
     *
     */
    private function getWaterField($type = 1){
        return $type == 1 ? ['now_cash_score_water','need_cash_score_water'] : ['now_bonus_score_water','need_bonus_score_water'];
    }


    /**
     * 用户提现处理
     * @param $uid 用户UID
     * @param $account 提现信息
     * @return void
     */
    private function treatWithinfo($uid,$account){
        $user_withinfo = Db::table('user_withinfo')->where(['uid' => $uid,'account' => $account])->first();
        if(!$user_withinfo){
            $user_withinfo = [
                'uid' => $uid,
                'account' => $account,
                'type' => 1,
                'cpf' => $account,
                'packname' => $this->request->getAttribute('packname'),
                'createtime' => time(),
            ];
            $user_withinfo_id = Db::table('user_withinfo')->insertGetId($user_withinfo);
            $user_withinfo['id'] = $user_withinfo_id;
        }
        return $user_withinfo;

    }

    /**
     * @return void 判断今日手续费是否免费
     */
    private static function getFreeStatus(){
        $zhouji = date('w') == 0 ? 7 : date('w');//获取今天是周几

        $withdrawal_fee_waived = explode("|",Common::getConfigValue('withdrawal_fee_waived'));
        $freeStatus = 0;
        if($withdrawal_fee_waived && in_array($zhouji,$withdrawal_fee_waived)){
            $freeStatus = 1;
        }
        return $freeStatus;
    }

    /**
     * 获取用户提现金额
     * @param $uid uid
     * @return int
     * @return void
     */
    public static function getUserTotalExchangeMoney($uid){
        //(迁移)
        $total_exchange = Db::table('userinfo')->where('uid',$uid)->value('total_exchange');
        return $total_exchange ?: 0;
    }



    /**处理当日充过值的用户首次退款留存
     * @param $uid 用户uid
     * @param $package_id 包id
     * @param $channel 渠道号
     * @param $user_type 用户等级
     * @param $price 支付金额
     * @param $af_status 是否是广告用户
     * @return void
     */
    private function statisticsRetainedWithdraw($uid,$package_id,$channel){

        //获取当日包和渠道下的首充用户
        $day_user = Db::table('statistics_retainedwith')->where(['time'=> strtotime('00:00:00'), 'package_id' => $package_id, 'channel' => $channel])
            ->update([
                'uids' => Db::raw("concat(uids,',', '$uid')")
            ]);
        if(!$day_user){
            Db::table('statistics_retainedwith')
                ->insert([
                    'time' => strtotime('00:00:00'),
                    'package_id' => $package_id,
                    'channel' => $channel,
                    'uids' => $uid,
                ]);
        }

        $res = Db::table('statistics_retainedwithlg')->where(['time'=> strtotime('00:00:00'), 'package_id' => $package_id, 'channel' => $channel])
            ->update([
                'num' => Db::raw('num + 1')
            ]);

        if(!$res){
            Db::table('statistics_retainedwithlg')
                ->insert([
                    'time' => strtotime('00:00:00'),
                    'package_id' => $package_id,
                    'channel' => $channel,
                    'num' => 1,
                ]);
        }


    }



    /**
     * 处理退款用户信息
     * @param $userinfo
     * @param int $money
     * @param  $daySuffix
     * @return void
     */
    private function setUserWithdrawLogInfo($userinfo,int $money,$daySuffix = ''){
        if($money == 0)return;
        $user_day =  [
            'uid' => $userinfo['uid'].'|up',
            'puid' => $userinfo['puid'].'|up',
            'vip' => $userinfo['vip'].'|up',
            'channel' => $userinfo['channel'].'|up',
            'package_id' => $userinfo['package_id'].'|up',
            'total_exchange' => $money.'|raw-+',
            'total_exchange_num' => ($money > 0 ? 1 : -1).'|raw-+',
        ];
        //user_day表处理
        $user_day = new SqlModel($user_day,$daySuffix);
        $user_day->userDayDealWith();


        if($userinfo['total_exchange'] <= 0){ //第一次退款成功
            if($money < 0)return ;
            Db::table('userinfo')->where('uid',$userinfo['uid'])
                ->update([
                    'total_exchange' => Db::raw('total_exchange + '.$money),
                    'total_exchange_num' => Db::raw('total_exchange_num + 1'),
                    'first_withdraw_time' => time(),
                    'updatetime' => time(),
                ]);
        }elseif ($userinfo['total_exchange_num'] == 1 && $money < 0){ //如果已经有一次退款了,看第二次金额是否是退款失败
            Db::table('userinfo')->where('uid',$userinfo['uid'])
                ->update([
                    'total_exchange' => Db::raw('total_exchange + '.$money),
                    'total_exchange_num' => Db::raw('total_exchange_num - 1'),
                    'first_withdraw_time' => 0,
                    'updatetime' =>time(),
                ]);
        }else{
            Db::table('userinfo')->where('uid',$userinfo['uid'])
                ->update([
                    'total_exchange' => Db::raw('total_exchange + '.$money),
                    'total_exchange_num' => $money > 0 ? Db::raw('total_exchange_num + 1') : Db::raw('total_exchange_num - 1'),
                    'updatetime' => time(),
                ]);
        }


    }

    /**
     * no_pay提现回调
     * @return string|void
     */
    #[RequestMapping(path:'nopayNotify')]
    public function nopayNotify() {
        $data = $this->request->all();

        $this->logger->error('no_pay提现:'.json_encode($data,JSON_UNESCAPED_UNICODE));  //防止乱码

        $ordersn=$data['merchantOrderNo'] ?? '';
        $ordStatus= $data["state"] ?? '';


        $status = $ordStatus == '3' ? 1 : 2;
        $res = $this->Withdrawhandle($ordersn,$status,$data);

        if($res['code'] == 200){
            return 'SUCCESS';
        }
        $this->logger->error('no_pay提现事务处理失败==='.$res['msg'].'==ordersn=='.$ordersn);
        return 'SUCCESS';
    }


    /**
     * qf888_pay提现回调
     * @return false|string|void
     */
    #[RequestMapping(path:'qf888payNotify')]
    public function qf888payNotify() {
        $data = $this->request->all();

        $this->logger->error('qf888_pay提现:'.json_encode($data,JSON_UNESCAPED_UNICODE));
        $ordersn =$data['mchOrderId'];
        $status = $data["isPaid"];  // 1 成功 2 失败
        if(!$ordersn)return 'success';//订单信息错误
        $status = $status == '1' ? 1 : 2;
        $res = $this->Withdrawhandle($ordersn,$status,$data);

        if($res['code'] == 200){
            return "success";
        }
        $this->logger->error('qf888_pay提现事务处理失败==='.$res['msg'].'==ordersn=='.$ordersn);
        return 'success';
    }





    /**
     * 提现统一处理
     * @param $ordersn 订单号
     * @param $status 提现状态:1=成功,2=失败
     * @param $data 第三方数据
     * @param $type 1 = 正常流程 ,2 = 后台处理
     * @return void
     */
    public function Withdrawhandle($ordersn,$status,$data,$type = 1){
        $withdraw_log = Db::table('withdraw_log')->where('ordersn',$ordersn)->first();
        if(!$withdraw_log){
            return ['code' => 200 ,'msg'=>'','data' => []];
        }

        if($withdraw_log['status'] == 1 || $withdraw_log['status'] == -1 || $withdraw_log['status'] == 2){
            return ['code' => 200 ,'msg'=>'','data' => []];
        }

        //(迁移)
        $userinfo = Db::table('userinfo')->selectRaw('uid,puid,channel,package_id,vip,total_exchange,total_pay_score,regist_time,total_exchange_num')->where('uid',$withdraw_log['uid'])->first();
        if($status == 1){ //成功
            $res = Db::table('withdraw_log')->where('ordersn',$ordersn)->update(['finishtime' => time(),'status' => 1]);

            if(!$res){
                return ['code' => 201 ,'msg'=>'提现状态修改失败1','data' => []];
            }


            Db::beginTransaction();

            //(迁移)
            if($userinfo['regist_time'] >= strtotime('2024-12-17 07:30:00') && $userinfo['total_exchange_num'] == 1 && $userinfo['total_pay_score'] > 0)$this->statisticsRetainedWithdraw($userinfo['uid'],$userinfo['package_id'],$userinfo['channel']);

            $this->setRoi($userinfo['regist_time'], $userinfo['package_id'], $userinfo['channel'], $withdraw_log['money'], $userinfo['uid']);

            $share_strlog = Db::table('share_strlog')
                ->selectRaw('is_agent_user')
                ->where('uid',$withdraw_log['uid'])->first();
            //统计无限代用户数据
            if($share_strlog['is_agent_user'] == 1)Common::agentTeamWeeklog($withdraw_log['uid'], $withdraw_log['money'], $withdraw_log['fee'], 2);
            //到账发送消息
            $s1 = date('Y-m-d',$withdraw_log['createtime']);

        }else{ //失败
            //修改订单状态
            $res = Db::table('withdraw_log')->where('ordersn',$ordersn)->update(['finishtime' => time(),'status' => 2]);
            if(!$res){
                return ['code' => 201 ,'msg'=>'提现状态修改失败2','data' => []];
            }

            Db::beginTransaction();

            Db::table('log')->insert(['out_trade_no'=> $ordersn,'log' => json_encode($data,JSON_UNESCAPED_UNICODE),'type'=>8,'createtime' => time()]);

            //返回用户的提现金额  退款的reason
            User::userEditCoin($withdraw_log['uid'],$withdraw_log['money'],5, "玩家三方回调" . $withdraw_log['uid'] . "退还提现金额" . bcdiv((string)$withdraw_log['money'],'100',2),3,1,$withdraw_log['withdraw_money_other']);

            //(迁移)
            $this->setUserWithdrawLogInfo($userinfo,0 - $withdraw_log['money'],date('Ymd',$withdraw_log['createtime']));

            //将三方错误日志，存储到第三张表中,是查询速度快一点
            Db::table('withdraw_logcenter')->where('withdraw_id',$withdraw_log['id'])->update([
                'log_error' => json_encode($data,JSON_UNESCAPED_UNICODE),
            ]);




        }

        Db::commit();
//        if(Common::getConfigValue('is_tg_send') == 1) {
//            //发送提现成功消息to Tg
//            $status == 1 ? \service\TelegramService::withdrawSuc($withdraw_log) : \service\TelegramService::withdrawFail($withdraw_log, $data);
//        }

        return ['code' => 200 ,'msg'=>'','data' => []];
    }

    /**
     * roi数据添加
     * @param $regist_time
     * @param $package_id
     * @param $channel
     * @param $money
     * @return void
     */
    private function setRoi($regist_time, $package_id, $channel, $money, $uid){
        $reg_day = date('Y-m-d',$regist_time);
        $reg_time = strtotime(date('Y-m-d',$regist_time));

        try {
            co(function ()use($regist_time,$package_id,$channel,$money,$uid,$reg_day,$reg_time){
                $res = Db::table('statistics_roi')->where(['time'=> $reg_time, 'package_id' => $package_id, 'channel' => $channel])
                    ->update([
                        'withdraw' => Db::raw("withdraw + $money")
                    ]);

                if(!$res){
                    Db::table('statistics_roi')
                        ->insert([
                            'time' => $reg_time,
                            'package_id' => $package_id,
                            'channel' => $channel,
                            'withdraw' => $money,
                            'num' => 1,
                        ]);
                }

                /*$day_data_channel = Db::table('day_data')
                    ->where(['date' => $reg_day, 'package_id' => $package_id, 'channel' => $channel])
                    ->select('new_total_withdraw_users')
                    ->first();*/
                $day_data = Db::table('day_data')
                    ->where(['date' => $reg_day, 'package_id' => 0, 'channel' => 0])
                    ->select('new_total_withdraw_users')
                    ->first();
                /*$users1 = $uid;
                if (!empty($day_data_channel) && !empty($day_data_channel['new_total_withdraw_users'])){
                    $users_arr = explode(',',$day_data_channel['new_total_withdraw_users']);
                    if (in_array($uid,$users_arr)){
                        $users1 = $day_data_channel['new_total_withdraw_users'];
                    }else{
                        $users1 = $day_data_channel['new_total_withdraw_users'].','.$uid;
                    }
                }*/
                $users2 = $uid;
                if (!empty($day_data) && !empty($day_data['new_total_withdraw_users'])){
                    $users_arr2 = explode(',',$day_data['new_total_withdraw_users']);
                    if (in_array($uid,$users_arr2)){
                        $users2 = $day_data['new_total_withdraw_users'];
                    }else{
                        $users2 = $day_data['new_total_withdraw_users'].','.$uid;
                    }
                }

                /*$res = Db::table('day_data')
                    ->where(['date' => $reg_day, 'package_id' => $package_id, 'channel' => $channel])
                    ->update([
                        "new_total_withdraw" => Db::raw("new_total_withdraw + $money"),
                        "new_total_withdraw_users" => $users1,
                        "new_total_withdraw_count" => Db::raw("new_total_withdraw_count + 1"),
                    ]);
                if (!$res && empty($day_data_channel)){
                    Db::table('day_data')
                        ->insert([
                            'date' => $reg_day,
                            'new_total_withdraw' => $money,
                            'new_total_withdraw_users' => $users1,
                            'new_total_withdraw_count' => 1,
                            'package_id' => $package_id,
                            'channel' => $channel,
                        ]);
                }*/
                //$this->logger->error('res==>'.$res);

                $res = Db::table('day_data')
                    ->where(['date' => $reg_day, 'package_id' => 0, 'channel' => 0])
                    ->update([
                        "new_total_withdraw" => Db::raw("new_total_withdraw + $money"),
                        "new_total_withdraw_users" => $users2,
                        "new_total_withdraw_count" => Db::raw("new_total_withdraw_count + 1"),
                    ]);
                if (!$res && empty($day_data)){
                    Db::table('day_data')
                        ->insert([
                            'date' => $reg_day,
                            'new_total_withdraw' => $money,
                            'new_total_withdraw_users' => $users2,
                            'new_total_withdraw_count' => 1,
                            'package_id' => 0,
                            'channel' => 0,
                        ]);
                }
            });
        }catch (\Exception $e){
            $this->logger->error('代付setRoi失败:'.$e->getMessage());
        }



        //$new_time = strtotime('00:00:00');
//        $i = ($new_time - $reg_time)/86400;
//        $day = 'day'.($i + 1);
        //$this->logger->error('roi++++++++++');



    }

}

