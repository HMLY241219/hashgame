<?php
declare(strict_types=1);
namespace App\Controller;

use App\Common\Common;
use App\Common\Guzzle;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;

#[Controller(prefix: 'Common')]
class CommonController extends AbstractController
{


    #[Inject]
    protected Guzzle $guzzle;
    #[Inject]
    protected AdjustController $adjust;
    #[Inject]
    protected AuthController $auth;

    #[RequestMapping(path: 'common')]
    public function common(){
        //红包雨时间配置
        $data['red_envelopes_time'] = Db::connection('readConfig')
            ->table('red_envelopes_time')
            ->select('time','type','startdate','day')
            ->where('status',1)
            ->orderBy('startdate')
            ->get()
            ->toArray();
        [$data['red_envelopes_week'],$data['red_envelopes_month'],$data['red_envelopes_time']] = $this->red_envelopes_time($data['red_envelopes_time']);
        $data['is_carry_bonus'] = config('slots.is_carry_bonus'); //进入三方游戏是否携带Bonus,1=是,0=否
        $data['is_zy_carry_bonus'] = config('slots.is_zy_carry_bonus');//进入自研游戏是否携带Bonus,1=是,0=否
        $sysConfig = Common::getMore('vipbetrayal_max_amount,customer_url,cash_back_max_bili,cashback_type,zy_cashbak_cash_bili,zy_cashbak_bonus_bili,is_open_sign,new_cash_back_max_bili,order_active_close_num,marquee_time,is_withdraw_hand_input');

        $data['vipbetrayal_max_amount'] = $sysConfig['vipbetrayal_max_amount'];//Vip反水领取最大金额
        $data['customer_url'] = $sysConfig['customer_url'];//客服链接
        $data['cash_back_max_bili'] = $sysConfig['cash_back_max_bili'];//Cash最高返利客户端使用
        $data['cashback_type'] = $sysConfig['cashback_type'];//1= 是新的反水需求和定时任务 ，2=  老版本反水定时任务,这个值判断客户端调取老的反水还是新的反水接口
        $data['zy_cashbak_cash_bili'] = $sysConfig['zy_cashbak_cash_bili'];//自研游戏Cash反水比例
        $data['zy_cashbak_bonus_bili'] = $sysConfig['zy_cashbak_bonus_bili'];//自研游戏Bonus反水比例
//        $data['bonus_pay_zs_water_multiple'] = $sysConfig['bonus_pay_zs_water_multiple'];//充值赠送Bonus流水倍数
//        $data['cash_pay_water_multiple'] = $sysConfig['cash_pay_water_multiple'];//充值Cash流水倍数
        $data['avatar_url'] = $this->getAvatarUrl();
        $data['is_open_sign'] = $sysConfig['is_open_sign'];
        $data['new_cash_back_max_bili'] = $sysConfig['new_cash_back_max_bili'];//新包Cash最高返利客户端使用
        $data['order_active_close_num'] = $sysConfig['order_active_close_num'];//活动日活动关闭多少次以后不再弹出
        $data['marquee_time'] = $sysConfig['marquee_time'];//跑马灯间隔时间
        $data['is_withdraw_hand_input'] = $sysConfig['is_withdraw_hand_input'];//是否开启退款手动输入
        return $this->ReturnJson->successFul(200,$data);
    }

    /**
     * 获取系统配置头像
     * @return array
     */
    private function getAvatarUrl():array{
        $avatar_url = config('avatar');
        $data = [];
        foreach ($avatar_url as $v){
            $data[] = Common::domain_name_path($v);
        }
        return $data;
    }

    #[RequestMapping(path: 'packAgeInfo')]
    public function packAgeInfo(){
        $app_package = Db::table('app_package')->select('app_version','file_url','is_forced_update')->where('pkg_name',$this->request->getAttribute('packname'))->orderBy('id','desc')->first();
        return $this->ReturnJson->successFul(200,$app_package);
    }


    /**
     * 获取登录状态，查看该的地区用户是否能登录
     * @return void
     */
    #[RequestMapping(path: 'getLoginStatus')]
    public function getLoginStatus(){
        return $this->ReturnJson->successFul(200,1);
        $ip = Common::getIp();
        $ip_login_white = Db::table('ip_login_white')->pluck('ip')->toArray();
        if(in_array($ip,$ip_login_white)) return $this->ReturnJson->successFul(200,1);


        $packname = $this->request->getAttribute('packname');
        $gps_status = Db::table('apppackage')->where('appname',$packname)->value('gps_status');
        if($gps_status == 1){
            $adminArea = $this->request->post('adminArea');
            $res = $this->gpsDetection($adminArea ?? '');
            if(!$res)return $this->ReturnJson->successFul(200,0);
        }



        $netoperator = $this->request->post('netoperator');
        $simoperator = $this->request->post('simoperator');
        $is_h5 = $this->request->post('is_h5') ?? 1;
        if($is_h5 == 1){
            $response =  $this->guzzle->get("https://pro.ip-api.com/json/".$ip."?key=IKEH1fFMW5o1qKY");
            if(!$response || !isset($response['countryCode']) || $response['countryCode'] == 'CN' ) return $this->ReturnJson->successFul(200,0);
        }elseif(substr($netoperator, 0 , 3) == "460"  || substr($simoperator, 0 , 3) =="460"){
            return $this->ReturnJson->successFul(200,0);
        }

        return $this->ReturnJson->successFul(200,1);
    }



    /**
     * 客户端打点
     * @return void
     */
    #[RequestMapping(path: 'setNewFbPoint')]
    public function setNewFbPoint(){
        $fbEventName = $this->request->post('fbEventName');
        $uid = $this->request->post('uid') ?? '';
//        $fbc = $this->request->post('fbc') ?? '';
//        $fbp = $this->request->post('fbp') ?? '';
        $gpsadid = $this->request->post('gpsadid') ?? '';
        $packname = $this->request->getAttribute('packname');
        $adid = $this->request->post('adid') ? str_replace('-','',(string)$this->request->post('adid')) :  '';
        $ip = Common::getIp();
        if($uid){
            $share_strlog_data = Db::table('share_strlog')->select('fbc','fbp','ip','city','gpsadid','channel')->where('uid',$uid)->first();
            if(!$share_strlog_data)return;
        }else{
            //默认渠道
            $mrChanel = Db::table('chanel')->select('package_id','channel')->where([['appname','=',$packname],['type','=',1]])->first();
            if(!$mrChanel)return '';
            [$fbc,$fbp,$chanelid,$af_status] = $this->auth->useWebFbpFbc($adid,$ip,$mrChanel['channel'],$mrChanel['package_id']);
            $share_strlog_data['ip'] = $ip;
            $share_strlog_data['fbc'] = $fbc;
            $share_strlog_data['fbp'] = $fbp;
            $share_strlog_data['gpsadid'] = $gpsadid;
            $share_strlog_data['city'] = $this->auth->getUserCity($share_strlog_data['ip']);
            $share_strlog_data['channel'] = $chanelid;
        }
        $this->adjust->fbUploadEvent($packname,0,false,'',$uid,$share_strlog_data,3,$fbEventName);
    }



    /**
     * 客户端打点
     * @return void
     */
    #[RequestMapping(path: 'setFbPoint')]
    public function setFbPoint(){
        $fbEventName = $this->request->post('fbEventName');
        $packname = $this->request->getAttribute('packname');
        $uid = $this->request->post('uid') ?? '';
        $fbc = $this->request->post('fbc') ?? '';
        $fbp = $this->request->post('fbp') ?? '';
        $gpsadid = $this->request->post('gpsadid') ?? '';
        $trackerName = $this->request->post('trackerName') ?? '';

        if($uid){
            $share_strlog_data = Db::table('share_strlog')->select('fbc','fbp','ip','city','gpsadid','channel')->where('uid',$uid)->first();
            if(!$share_strlog_data)return;
        }else{
            $chanelid = 0;
            if($trackerName){
                $adjust_tage = explode('::',$trackerName)[0];
                $package_id = Db::table('apppackage')->where('appname',$packname)->value('id');
                if(!$package_id)return;
                $chanelinfo = Db::table('chanel')->where([['package_id','=',$package_id],['ctag','like',$adjust_tage.'%']])->first();
                if($chanelinfo) $chanelid = $chanelinfo['channel'];

            }
            $share_strlog_data['ip'] = Common::getIp();
            $share_strlog_data['fbc'] = $fbc;
            $share_strlog_data['fbp'] = $fbp;
            $share_strlog_data['gpsadid'] = $gpsadid;
            $share_strlog_data['city'] = $this->auth->getUserCity($share_strlog_data['ip']);
            $share_strlog_data['channel'] = $chanelid;
        }
        $this->adjust->fbUploadEvent($packname,0,false,'',$uid,$share_strlog_data,3,$fbEventName);
    }




    /**
     * 存储FBP和FBC
     * @return void
     */
    #[RequestMapping(path: 'setWebFbpFbc')]
    public function setWebFbpFbc(){
        $fbc = $this->request->post('fbc');
        $fbp = $this->request->post('fbp');
        $campaign = $this->request->post('campaign');
        if(!$fbc  || !$campaign)return '';
        $fb_web = Db::table('fb_web')->where(['fbc' => $fbc,'fbp' => $fbp])->value('id');
        if($fb_web)return '';
        $ip = Common::getIp();
        Db::table('fb_web')->insert([
            'fbp' => $fbp ?: '',
            'fbc' => $fbc,
            'ip' => $ip,
            'campaign' => $campaign,
        ]);
        return 'success';
    }


    /**
     * @return string 手动打充值点
     */
    #[RequestMapping(path: 'fbManualManagement')]
    public function fbManualManagement(){
        $orersn = $this->request->input('ordersn');
        $is_first_recharge = (bool)$this->request->input('is_first_recharge');

        $order = Db::table('order')->where('ordersn',$orersn)->first();
        if(!$order)return '没有查到订单';
        $share_strlog_data = Db::table('share_strlog')->select('fbc','fbp','ip','city','gpsadid','appname')->where('uid',$order['uid'])->first();
        $this->adjust->fbUploadEvent($share_strlog_data['appname'],bcdiv((string)$order['price'],'100',0),$is_first_recharge,$order['ordersn'],$order['uid'],$share_strlog_data);
        return 'FB手动打点完成';
    }


    /**
     * @return string 获取跑马灯配置
     */
    #[RequestMapping(path: 'getMarqueeConfig')]
    public function getMarqueeConfig(){

        $marquee_config = Db::connection('readConfig')->table('marquee_config')->select('title')->where('status',1)->orderBy('weight','desc')->get()->toArray();

        return $this->ReturnJson->successFul(200,$marquee_config);
    }

    /**
     * GPS检测
     * @return void
     */
    private function gpsDetection($adminArea){
        // $this->logger->error('获取到adminArea:'.$adminArea);
        if(!$adminArea){
            $response =  $this->guzzle->get("https://pro.ip-api.com/json/".Common::getIp()."?key=IKEH1fFMW5o1qKY");
            if(isset($response['regionName']) || !$response['regionName']){
                $this->logger->error('Gps检测时IP获取地区失败'.json_encode($response));
                return 0;
            }
            $adminArea = $response['regionName'];
        }
        // $this->logger->error('确定的adminArea:'.$adminArea);
        if($this->gps_not_login($adminArea)){
            $this->logger->error('Gps检测到玩家属于禁止登录的邦');
            return 0;
        }
        return 1;
    }


    /**
     * @return void gps禁止登录的帮
     */
    private function gps_not_login($city){
        $black = [//上面是中文名称，下面是英文名称
            '阿萨姆','奥里萨邦','锡金','那加兰邦','特伦甘纳','安得拉邦','古吉拉特邦',
            'assam','odisha','sikkim','nagaland','telangana','andhra pradesh','gujrat',
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
     * 处理红包雨配置数据
     * @param array $red_envelopes_time
     * @return void
     */
    private function red_envelopes_time(array $red_envelopes_time){
        $key = [];
        $week = '';
        $month = '';
        foreach ($red_envelopes_time as &$v){
            [$start,$end] = explode(' - ',$v['time']);
            $v['time'] = substr($start,0,5).'-'.substr($end,0,5);
            if(in_array($v['type'],[2,3]) && !in_array($v['type'],$key)){
                $key[] = $v['type'];
                if($v['type'] == 2){
                    $week = explode('、',$v['day']);
                }else{
                    $dayArray = explode('、',$v['day']);
                    $max = max($dayArray);
                    $min = min($dayArray);
                    $month = [$min,$max];
                }
            }
        }
        return [$week,$month,$red_envelopes_time];
    }

}



