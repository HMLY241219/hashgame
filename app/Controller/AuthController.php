<?php
declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller;
use App\Amqp\Producer\TaskProgressProducer;
use App\Common\Guzzle;
use App\Common\Message;
use App\Enum\EnumType;
use Hyperf\Amqp\Producer;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use App\Common\Common;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;
use function Hyperf\Support\env;


#[Controller(prefix:"Auth")]
class AuthController extends AbstractController
{

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected AdjustController $Adjust;

    //Fb打点的包
    private array $FbLoginPackageId = [1,7,11,12,14,15];
    //Adjust打点的包
    private array $AdjustPackageId = [1,13,11,14,15,12]; //Adjust打点的包
    //AF打点的包
    private array $AfLoginPackageId = [];
    //代理渠道
    private array $agentChannel = [5030,5020,5010];


    private string $googleClientId = '262294838288-srsnim6tq5e06c45vfjh3kn1v0t53567.apps.googleusercontent.com';
    #[RequestMapping(path: "Login", methods: "get,post")	]
    public function login(){
        $logintype = $this->request->post('logintype');  //1=手机,2=谷歌,3=账号
        $device_id = str_replace('-','',(string)$this->request->post('deviceid')); //把-替换为空字符串设备号
        $packname = $this->request->getAttribute('packname');
        $param = [];
        if($this->request->post('param')) $param = json_decode(base64_decode(str_replace(' ', '+', (string)$this->request->post('param'))), true);
//        $afid = $this->request->post('afid');
//        $param['afid'] = $afid;
        $adid = $param['adid'] ?? '';  //	adjust的设备ID
        $gpsid = $param['gps_adid'] ?? ''; //	原始谷歌的广告ID
        $package_id = Db::table('apppackage')->where('appname',$packname)->value('id');
//        $this->logger->error('packname'.$packname);
        if(!$package_id){
            $this->logger->info("包名传入有误!");
            return $this->ReturnJson->failFul(206);
        }
//        $this->logger->error('packname'.$packname);
        if($logintype == 1){ //手机登录
            $phone = $this->request->post('phone');
            $password = $this->request->post('password');
            $type = $this->request->post('type') ?? 1; //1=登录,2=注册

            //检查手机号是否正确
//            if (!Common::PregMatch($phone,'phone'))return $this->ReturnJson->failFul(207);
//            if(!Common::PregMatch($password,'account'))return $this->ReturnJson->failFul(263);
            if($type == 1){  //登录先检查账号密码是否正确
                $userData = Db::table('share_strlog')
                    ->select('uid', 'code','phone','email','nickname','avatar','package_id','channel','status','jiaemail','last_login_time')
                    ->where([['phone','=',$phone],['password','=', md5($password)],['package_id','=',$package_id]])
                    ->orderBy('uid','desc')
                    ->first();
                if(!$userData){
                    //用户删除检测
                    $userData = $this->getUserBackup($phone,$package_id,1,$password);
                    if(!$userData)return $this->ReturnJson->failFul(210);
                }
                if($userData['status'] != 1)return $this->ReturnJson->failFul(261);

                //召回统计
                $this->recallolUser($userData['uid'], $package_id, $userData['last_login_time'], $userData['channel']);


            }else{
                $res = Db::table('share_strlog')
                    ->select('uid')
                    ->where([['phone' ,'=',$phone],['package_id','=',$package_id]])
                    ->orderBy('uid','desc')
                    ->first();
                if($res)return $this->ReturnJson->failFul(220);//抱歉!手机号已被注册

                $res = $this->get_channel((array)$param,(string)$phone,(int)$package_id,(int)$logintype,(string)$packname,(string)$device_id,(string)$password);
                if($res['code'] != 200)return $this->ReturnJson->failFul($res['code']);
                $userData = $res['data'];
            }


        }elseif($logintype == 2){ //谷歌登录
            $credential =$this->request->post('credential');
            $sub =$this->request->post('sub');
            if($sub){
                $userData = Db::table('share_strlog')
                    ->selectRaw('uid,code,phone,email,nickname,avatar,googlesub as sub,package_id,channel,1 as status,status as login_status,1 as registorlogin,jiaemail,last_login_time')
                    ->where([['googlesub','=',$sub],['package_id','=',$package_id]])
                    ->orderBy('uid','desc')
                    ->first();
                //用户删除检测
                if(!$userData){
                    $userData = $this->getUserBackup($sub,$package_id,2);
                    if(!$userData)return $this->ReturnJson->failFul(210);
                }
                if($userData['login_status'] != 1)return $this->ReturnJson->failFul(261);

                //召回统计
                $this->recallolUser($userData['uid'], $package_id, $userData['last_login_time'], $userData['channel']);
            }else{
                //正式服代码
//            $client = new \Google_Client(['client_id' => $this->googleClientId]);  // Specify the CLIENT_ID of the app that accesses the backend
//            $googleInfo = $client->verifyIdToken($credential);
                //测试服代码
                $googleInfo = $this->guzzle->post("http://googlelogin.teenpatticlub.shop/api/Auth/login",['credential' => $credential]);
                if(!$googleInfo || !isset($googleInfo['sub'])){
                    $this->logger->error('谷歌登录失败:'.json_encode($googleInfo));
                    return $this->ReturnJson->failFul(214);
                }
                $userinfo = [
                    'sub' => $googleInfo['sub'],
                    'name' => $googleInfo['name'] ?? '',
                    'avatar' => $googleInfo['picture'] ?? '',
                ];

                $userData = Db::table('share_strlog')
                    ->selectRaw('uid,code,phone,email,nickname,avatar,googlesub as sub,package_id,channel,1 as status,status as login_status,1 as registorlogin,jiaemail')
                    ->where([['googlesub','=',$sub],['package_id','=',$package_id]])
                    ->orderBy('uid','desc')
                    ->first();
                if(!$userData){
                    $res = $this->get_channel((array)$param,$googleInfo['sub'],(int)$package_id,(int)$logintype,$packname,$device_id,'',(string)$userinfo['name'],(string)$userinfo['avatar'],(string)$googleInfo['email']);
                    if($res['code'] != 200)return $this->ReturnJson->failFul($res['code']);
                    $userData = $res['data'];
                    $userData['sub'] = $userinfo['sub'];
                    $userData['status'] = 2;
                    $userData['registorlogin'] = 2;
                }elseif ($userData['login_status'] != 1) return $this->ReturnJson->failFul(261);

            }


        }elseif ($logintype == 3){  //账号登录
            $account = $this->request->post('account');
            if(!Common::PregMatch($account,'account'))return $this->ReturnJson->failFul(262);
            $password = $this->request->post('password');
            if(!Common::PregMatch($password,'account'))return $this->ReturnJson->failFul(263);
            $type = $this->request->post('type') ?? 1; //1=登录,2=注册
            if($type == 2){
                $res = Db::table('share_strlog')
                    ->select('uid')
                    ->where([['account' ,'=',$account],['package_id','=',$package_id]])
                    ->orderBy('uid','desc')
                    ->first();
                if($res)return $this->ReturnJson->failFul(264);//抱歉!账号已被注册
                $res = $this->get_channel((array)$param,(string)$account,(int)$package_id,(int)$logintype,$packname,$device_id,$password);
                if($res['code'] != 200)return $this->ReturnJson->failFul($res['code']);
                $userData = $res['data'];
            }else{
                $userData = Db::table('share_strlog')
                    ->select(Db::raw('uid,code,phone,email,nickname,avatar,package_id,channel,status,jiaemail,last_login_time'))
                    ->where([['account','=',$account],['password','=',md5($password)],['package_id','=',$package_id]])
                    ->orderBy('uid','desc')
                    ->first();
                //用户删除检测
                if(!$userData){
                    $userData = $this->getUserBackup($account,$package_id,3,$password);
                    if(!$userData)return $this->ReturnJson->failFul(210);
                }
                if($userData['status'] != 1)return $this->ReturnJson->failFul(261);

                //召回统计
                $this->recallolUser($userData['uid'], $package_id, $userData['last_login_time'], $userData['channel']);
            }

        }elseif ($logintype == 4){  //游客登录
            $account = $this->request->post('account');
            if(!Common::PregMatch($account,'account'))return $this->ReturnJson->failFul(262);
            $account = $device_id ?: $account;
            $userData = Db::table('share_strlog')
                ->select(Db::raw('uid,code,phone,email,nickname,avatar,package_id,channel,status,jiaemail,last_login_time'))
                ->where([['account','=',$account],['package_id','=',$package_id]])
                ->orderBy('uid','desc')
                ->first();
            //用户删除检测
            if(!$userData)$userData = $this->getUserBackup($account,$package_id,4);
            if($userData){
                if($userData['status'] != 1)return $this->ReturnJson->failFul(261);
                $userData['is_login'] = 1;

                //召回统计
                $this->recallolUser($userData['uid'], $package_id, $userData['last_login_time'], $userData['channel']);
            }else{
                $res = $this->get_channel((array)$param,(string)$account,(int)$package_id,(int)$logintype,$packname,$device_id,'');
                if($res['code'] != 200)return $this->ReturnJson->failFul($res['code']);
                $userData = $res['data'];
                $userData['is_login'] = 0;
            }


        }else{
            $this->logger->error('暂不支持其它方式登录');
            return $this->ReturnJson->failFul(211);
        }

        //修改用户登录时间
        Db::table('userinfo')->where('uid',$userData['uid'])->update(['login_time' => time()]);

        //处理每日登录
        $this->setLogin($userData['uid'],$userData['channel'],$userData['package_id']);

        $userData['token'] = $this->setUserToken((int)$userData['uid']);
        $userData['tawkHash'] = hash_hmac('sha256',$userData['jiaemail'],'89553b729f0570a1c75248f8b02f8986cd20f5a9');
        $userData['avatar'] = $userData['avatar'] ? Common::domain_name_path($userData['avatar']) : '';
        if(!isset($userData['register_send_status']))$userData['register_send_status'] = 0; //注册赠送默认不弹出


        //判断是否可以领取马甲包下载APK奖励
        $isnotmjb = $this->request->post('isnotmjb') ?? 0; //是否不是马甲包:1=是,0=否
        if(in_array($package_id,config('my.sendBonusMjbPackageId')))$userData['mjbSendBonus'] = $this->mjbSendBonusLog($userData['uid'],$isnotmjb);


        return $this->ReturnJson->successFul(200,$userData);
    }


    /**
     * 用户修改密码
     * @return void
     */
    #[RequestMapping(path: "BindingPhone", methods: "get,post")	]
    public function BindingPhone(){
        $uid = $this->request->post('uid');
        $phone = $this->request->post('phone'); //手机号
        $packname = $this->request->getAttribute('packname');
        $package_id = Db::table('apppackage')->where('appname',$packname)->value('id');
        if(!$package_id)return $this->ReturnJson->failFul(206);

        //检查手机号是否正确
        if (!Common::PregMatch($phone,'phone'))return $this->ReturnJson->failFul(206);

//
//        $res = Db::table('share_strlog')->where('uid',$uid)->value('phone');
//        if($res)return $this->ReturnJson->failFul(222);//抱歉!该用户已经绑定了手机;

        $share_strlog = Db::table('share_strlog')->select('uid')->where([['phone','=',$phone],['package_id','=',$package_id]])->first();
        if($share_strlog)return $this->ReturnJson->failFul(221);//抱歉!手机号已被占用;
        //用户删除检测
        $share_strlog_backup = Db::table('share_strlog_backup')->select('uid')->where([['phone','=',$phone],['package_id','=',$package_id]])->first();
        if($share_strlog_backup)return $this->ReturnJson->failFul(221);//抱歉!手机号已被占用;

//        Db::table('share_strlog')->where('uid',$uid)->update(['phone' => $phone,'password' => md5($password)]);
        Db::table('share_strlog')->where('uid',$uid)->update(['phone' => $phone]);
        return $this->ReturnJson->successFul();
    }


    /**
     * 用户修改密码
     * @return void
     */
    #[RequestMapping(path: "editPassword", methods: "get,post")	]
    public function editPassword(){
        $uid = $this->request->getAttribute('uid');
        $password = $this->request->post('password'); //新密码
        $oldpassword = $this->request->post('oldpassword'); //老密码
        $phone = $this->request->post('phone'); //手机号
        $smstype = $this->request->post('smstype'); //验证码
        $packname = $this->request->getAttribute('packname');
        $package_id = Db::table('apppackage')->where('appname',$packname)->value('id');
        if(!$package_id)return $this->ReturnJson->failFul(206);

        if(!$password)return $this->ReturnJson->failFul(218);

        if($smstype == 2){
            $share_strlog = Db::table('share_strlog')->select('uid')->where([['phone','=',$phone],['package_id','=',$package_id]])->first();
            if(!$share_strlog)return $this->ReturnJson->failFul(216);//手机号用户不存在!请先注册
        }else{
            $share_strlog = Db::table('share_strlog')->select('uid')->where([['uid','=',$uid],['password','=',md5($oldpassword)]])->first();
            if(!$share_strlog)return $this->ReturnJson->failFul(217);//抱歉!老密码输入错误!
        }
        Db::table('share_strlog')->where('uid',$share_strlog['uid'])->update(['password' => md5($password)]);

        //发送消息
        Message::messageSelect(10, ['uid'=>$uid]);
        return $this->ReturnJson->successFul();
    }

    /**
     *
     * 获取归因
     * @param $param array  归因参数
     * @param $key string 用户的唯一标识
     * @param $package_id int 包名
     * @param $logintype int 登录方式  注册 1=手机,2=谷歌,3=账号
     * @param $packname string 包名
     * @param $device_id string 设备ID
     * @param $password string 登录密码
     * @param $nickname string 昵称
     * @param $password string 密码
     * @param $email string 邮箱
     * @return int[]
     */
    private function get_channel(array $param,string $key,int $package_id,int $logintype,string $packname,string $device_id,string $password = '', $nickname = '', $avatar = '', $email = '')
    {
        $apppackage = Db::table('apppackage')->select('hide','is_genuine_gold','gps_status')->where('id',$package_id)->first();
        $chanel = Db::table('chanel')->where([['package_id','=',$package_id],['type','=',1]])->value('channel');
        $chanelid = $chanel ?: 10000;
        $phone = $logintype == 1 ? $key : '';
        $account = in_array($logintype,[3,4])  ? $key : '';
        $pchanelid = 0;
        $puid = 0;
        $isgold = 1;
        $ip = Common::getIp($this->request->getServerParams()); //正式
//        $ip = '66.249.66.41'; //测试
        $nickname = $nickname ?: substr($key,0,6).'***';
        $googlesub = $logintype == 2 ? $key : '';
        $afid = $param['afid'] ?? '';  //	appsflyer_id的设备ID
        $adid = $param['adid'] ?? '';  //	adjust的设备ID
        $gpsid = $param['gps_adid'] ?? ''; //	原始谷歌的广告ID
        $is_app = $param['is_app'] ?? 0; //	是否是APP
        $fbp = $param['fbp'] ?? ''; //
        $fbc = $param['fbc'] ?? ''; //
        $af_status = 0;
        $avatar = $avatar ?: $this->getAvatar();
        $is_agent_user = 0;

        //判断是否开启gps检测
//        if($apppackage['gps_status'] == 1){
//            $res = $this->gpsDetection($param['gps'] ?? '');
//            if(!$res)return ['code' => 212,'msg' => 'Sorry! The service is temporarily unavailable','data' => []];
//        }


        //获取UID同时处理shrlog
        $uid = $this->GetUid();

        if($logintype == 4){ //黑名单处理
            $filed_array = ['phone' =>$phone,'ip' => $ip];
        }else{
            $filed_array = ['ip' => $ip];
        }



        $getIpLoginStatus = $this->getIpLoginStatus($package_id,$ip);
        $ip_login_status = $getIpLoginStatus['ip_login_status'];//0 = A面  ， 1 = B面  ,-1 = 不允许登录
        $city = $getIpLoginStatus['city'];



        //判断用户是否是刷子帮
        $is_brushgang = $this->is_black_city($city);

        //不管是不是真金包都不允许拉贾拾坦邦进入
        if($ip_login_status === -1){
            $this->logger->error('非印度地区无法进入系统:'.$key.'ip:'.$ip.'城市:'.$city);
            return ['code' => 212,'msg' => 'Sorry! The service is temporarily unavailable','data' => []];
        }

        $is_agent = 0;
        if(!$apppackage['hide']){  //闪退或者不投放，直接A
            $chanelid = 0;
        }else{
            if($apppackage['is_genuine_gold'] == 1){
                $isgold = 0;
                $ip_login_status = 1;
                $af_status = isset($param['af_status']) && !isset($param['bindData']) && $param['af_status'] == 'Non-organic' ? 1 : 0;
                $chanelid = $this->getGgChanelid($param,$chanelid,$package_id,$af_status);
            }elseif ((isset($param['af_status']) && $param['af_status'] != 'share-link' &&  !isset($param['bindData'])) || isset($param['trackerName'])) {  //不是分享来的
                $trackerName = $param['af_status'] ?? $param['trackerName'];
                if ($trackerName) {
                    $isgold = $trackerName == 'Organic' ? 1 : 0;
                    //是否开启自然量进入B面
                    if($this->isNatureStatus()){
                        if ($isgold == 1 && $ip_login_status !== 1) {  //金币玩家同时设备ip等检测不能通过就为金币
                            $chanelid = 0;
                        }elseif ($isgold == 1 && $ip_login_status === 1) {  //金币玩家同时设备ip等检测能通过就转为真金
                            $isgold = 0;
                        }else {  //真金玩家且有有归因参数
                            //广告渠道
                            $chanelid = $this->getGgChanelid($param,$chanelid,$package_id,1);
                            $af_status = 1;
                            if (strpos($trackerName, "Youmi") !== false) $af_status = 0;

                        }
                    }else{
                        if($isgold == 1){
                            $chanelid = 0;
                        }else {  //真金玩家且有有归因参数
                            //广告渠道
                            $chanelid = $this->getGgChanelid($param,$chanelid,$package_id,1);
                            $af_status = 1;
                            if (strpos($trackerName, "Youmi") !== false) $af_status = 0;
                        }
                    }


                }
            }elseif (((isset($param['bindData']) && $param['bindData']) || (isset($param['af_status']) && $param['af_status'] == 'share-link')) && !isset($param['h5uid']) ){

                $bindData = isset($param['bindData']) ? json_decode($param['bindData'],true) : $param;

//                $bindData = json_decode($param['bindData'],true);
                if(isset($bindData['af_status']) && $bindData['af_status']){

                    $isgold = $bindData['af_status'] == 'share-link' ? 0 : 1;
                    if(isset($bindData['agent']) && $bindData['agent']){
                        $agent_cid = Db::table('chanel')->where([['package_id','=',$package_id],['ctag','=',$bindData['agent']]])->value('channel');
                        $chanelid = $agent_cid ?: $chanelid;
                    }
                    $bindData['clickLabel'] = isset($bindData['clickLabel']) ? base64_decode($bindData['clickLabel']) : 0;
                    $chanelinfo = Db::table('share_strlog')->where('uid',$bindData['clickLabel'])->first();
                    $agent_info = $chanelinfo;
                    if ($chanelinfo && $bindData['clickLabel'] != $uid) {
                        $puid = $bindData['clickLabel'];  //上级用户uid
                        $pchanelid = $chanelinfo['channel'];  //上级用户渠道id
                        $pchan =  Db::table('chanel')->where('channel',$pchanelid)->first();//获取上级渠道的等级
                        $agentstatus = true;
                        //本次修改
                        if(in_array($pchanelid,$this->agentChannel) || $pchanelid >= 100000000)$agentstatus = false;  //所以用户都给代理 理
                        if($pchan && $pchan['level'] <= 2 && $agentstatus) {
                            $chanelinfo = Db::table('chanel')->where('channel',$pchanelid + 1)->first();  //获取分享用户理想状态的渠道 上级渠道 + 1
                            if (!$chanelinfo || !$pchan['channel']) {
                                $leave = $pchan['level'] + 1; //用户等级等级
                                $chanelid = $pchanelid + 1; //下级渠道id
                                //添加
                                $chanel_data = [
                                    'channel' => $chanelid,
                                    'pchannel' => $pchanelid,
                                    'cname' => $pchan['cname'] . $leave . '级',
                                    'appname' => $packname,
                                    'level' => $leave,
                                    'package_id' => $package_id,
                                    'ppackage_id' => $pchan['ppackage_id'],
                                ];
                                Db::table('chanel')->insert($chanel_data);
                            } else {
                                $chanelid = $chanelinfo['channel'];
                            }
                        } elseif ($pchan) {
                            $chanelid = $chanelinfo['channel'];
                            //本次修改
//                            if($pchanelid == 40012 && $chanelinfo['agent'] != 1){
//                                $chanelid = 9000;
//                            }else{
//                                $chanelid = in_array($chanelid,[40012,9160]) ? $chanelid : $chanelinfo['channel']; //落地页包的分享包用户单独区分渠道
//                            }

                        }


                    }


                }

            } else { //parm为空的时候
                if($this->isNatureStatus() && $ip_login_status === 1) $isgold = 0;
            }
        }

        if(!$ip_login_status && !$af_status){
            $isgold = 1;
            $chanelid = 0;
        }

        //没有A面的包禁止登录
//        if($isgold == 1){
//            $this->logger->error('金币玩家无法登陆包id:'.$package_id.'登录信息：'.$key);
//            return ['code' => 212,'msg' => 'Sorry! The service is temporarily unavailable','data' => []];
//        }

        //如果有设备号，检查设备是否唯一，如果不唯一，则把上级用户uid变为0
//        if($puid > 0 && $device_id != '00000000000000000000000000000000'){
//            $puid_status = Db::table('share_strlog')->select('uid')->where('device_id',$device_id)->first();
//            if($puid_status)$puid = 0;
//        }

        $fb_login_status = false;

        //ip与设备号检测
        if($apppackage['is_genuine_gold'] != 1 && $device_id != '00000000000000000000000000000000'){ //不是真金包检测ip 13为支付包不检查这个,000为未获取到设备号用户
            $device_count = Db::table('share_strlog')->where('device_id',$device_id)->where('package_id',$package_id)->count(); //获取注册用户的设备号数量
            if(!$device_count )$fb_login_status = true;
//            if($device_count >= 5){ //如果ip已经注册了3个直接进A面
//                $this->logger->error('设备号注册了超过了5个禁止注册:'.$device_id.'包id：'.$package_id.'登录信息:'.$key);
//                return ['code' => 271,'msg' => 'Sorry! A device can only register up to 5 accounts.','data' => []];
//            }
        }


        //黑名单处理
        $res = \App\Common\Black::is_block($filed_array);
        if($res){
            $this->logger->error('黑名单用户禁止注册===登录信息:'.$key.'ip:'.$ip);
            return ['code' => 201,'msg' => 'Sorry! The service is temporarily unavailable','data' => []];
        }

        //如果没得Fbp
        if(!$fbp && !$fbc && in_array($package_id,$this->FbLoginPackageId)){
            [$fbc,$fbp,$chanelid,$af_status] = $this->useWebFbpFbc($device_id,$ip,$chanelid,$package_id);
        }


        Db::beginTransaction();
        $code = $this->code();
        $share_strlog_data = [
            'uid' => $uid,
            'puid' => $puid,
            'account' => $account,
            'password' => md5($password),
            'nickname' => $nickname,
            'googlesub' => $googlesub,
            'avatar' => $avatar,
            'ip' =>$ip,
            'strlog' => json_encode($param),
            'isgold' => 0,
            'pchannel' => $pchanelid,
            'channel' => $chanelid,
            'appname' => $packname,
            'device_id' => $device_id,
            'last_login_time' => time(),
            'createtime' => time(),
            'gpsadid' => $gpsid,
            'adid' => $adid,
            'code' => $code,
            'phone' => $phone,
            'package_id' => $package_id,
            'login_ip' => $ip,
            'afid' => $afid,
            'jiaemail' => $this->getEmail(),
            'jiaphone' => $this->getPhone(),
            'jianame' => $this->getName(),
            'af_status' => $af_status,
            'city' => $city,
            'is_brushgang' => $is_brushgang,
            'brushgang_pay_status' => $is_brushgang,
            'is_app' => $is_app,
            'fbp' => $fbp,
            'fbc' => $fbc,
            'is_agent_user' => $is_agent_user,
            'agent' => $is_agent,
        ];

        $res = Db::table('share_strlog')->insert($share_strlog_data);
        if(!$res){
            Db::rollBack();
            $this->logger->error('注册用户信息:'.$key.'数据表share_strlog添加失败');
            return ['code' => 213 ,'msg'=>'注册用户失败','data' => []];
        }

        $userinfo = [
            'uid' => $uid,
            'puid' => $puid,
            'channel' => $chanelid,
            'vip' => $this->getVip($uid),
            'ip' => $ip,
            'regist_time' => time(),
            'acc_type' => $logintype,
            'package_id' => $package_id,
        ];

        $userinfo = Db::table('userinfo')->insert($userinfo);
        if(!$userinfo){
            Db::rollBack();
            $this->logger->error('注册用户信息:'.$key.'数据表userinfo添加失败');
            return ['code' => 213 ,'msg'=>'注册用户失败','data' => []];
        }


        $this->setTeamLevel($uid,$puid,$nickname,$avatar,$chanelid,$package_id,0,$param); //团队数据处理

        Db::commit();

        if($isgold == 0){
            $this->statisticsRetainedUser($uid,$package_id,$chanelid);
        }

        //处理每日注册
        $this->setRegist($uid,$chanelid,$package_id);

        //处理注册赠送
        $register_send_status = $this->registerSend($uid);//1=可以弹出领取，0=不能弹出领取


        //fb注册打点
        if($fb_login_status && $fbc)$this->Adjust->fbUploadEvent($packname,0,false,'',$uid,$share_strlog_data,2);
        //AF注册打点
        if($fb_login_status && in_array($package_id,$this->AfLoginPackageId) && $afid)$this->Adjust->afUploadEvent($packname,'',$afid,0,false,'',$share_strlog_data,2);
        //Adjust注册打点
        if($fb_login_status)$this->Adjust->adjustUploadEvent($packname,$gpsid,$adid,0,false,'',$share_strlog_data,2);


        return ['code' => 200,'msg' => '成功','data' => ['uid' => $uid,'code'=>$code,'phone' => $phone,'email' => $email,'nickname' => $nickname,'avatar' => $avatar,'package_id' => $package_id,'channel' => $chanelid,'jiaemail' => $share_strlog_data['jiaemail'],'register_send_status' => $register_send_status]];

    }


    /**
     * 马甲包下载Apk赠送
     * @param $uid
     * @param $isnotmjb
     * @return int
     */
    private function mjbSendBonusLog($uid,$isnotmjb):int{
        $mjb_cash_send_log = Db::table('mjb_bonus_send_log')
            ->selectRaw('bonus,status,isnotmjb')
            ->where('uid',$uid)
            ->first();
        if(!$mjb_cash_send_log && $isnotmjb == 0){
            Db::table('mjb_bonus_send_log')
                ->insert([
                    'isnotmjb' => $isnotmjb,
                    'uid' => $uid,
                    'createtime' => time(),
                ]);
        }elseif($mjb_cash_send_log && $isnotmjb == 1){
            if($mjb_cash_send_log['isnotmjb'] == 0) Db::table('mjb_bonus_send_log')->where('uid',$uid)->update(['isnotmjb' => 1]);
            if($mjb_cash_send_log['status'] != 0 || $mjb_cash_send_log['isnotmjb'] != 0 || $mjb_cash_send_log['bonus'] <= 0)return 0;
            return $mjb_cash_send_log['bonus'];
        }
        return 0;
    }


    /**
     * 注册赠送
     * @param $uid 用户uid
     * @return int
     */
    private function registerSend($uid):int{
        //注册赠送Cash
        $register_send_cash = Common::getConfigValue('register_send_cash');
        if($register_send_cash){
            $glUidArray = \App\Common\My::glUid($uid); //获取我的关联用户
            $register_send_log_count = Db::table('register_send_log')->whereIn('uid',$glUidArray)->count(); //获取关联数量
            if(!$register_send_log_count){
                Db::table('register_send_log')->insert([
                    'uid' => $uid,
                    'cash' => $register_send_cash,
                    'createtime' => time(),
                ]);
                return 1;
            }
        }

        return 0;
    }




    /**
     * 获取用户的默认VIP等级
     * @return
     */
    private function getVip($uid){
        $vip = Db::table('vip')->where('need_water',0)->orderBy('vip','desc')->value('vip');
//        if($vip) Db::table('vip_log')->insertOrIgnore([
//                'uid' => $uid,
//                'vip' => $vip,
//                'status' => 1,
//                'createtime' => time(),
//            ]);

        return $vip ?: 0;
    }

    /**获取用户默认头像
     * @return mixed
     */
    private function getAvatar(){
        $key = rand(1,16);
        return config('avatar')[$key] ?? config('avatar')[1];
    }

    /**
     * GPS检测
     * @return void
     */
    private function gpsDetection($gps){
        $gpsArray = json_decode($gps,true);
        if(!$gpsArray || !isset($gpsArray['adminArea']) || !$gpsArray['adminArea']){
            $this->logger->error('未检测到Gps数据禁止登录');
            return 0;
        }
        if($this->gps_not_login($gpsArray['adminArea'])){
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
            '阿萨姆','奥里萨邦','锡金邦','那加兰邦','特伦甘纳','安得拉邦','古吉拉特邦',
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
     *
     * @return void
     */
    private function setIpSieveBehaviorLog($uid,$ip){

    }


    /**
     * 设置用户token
     * @param $uid
     * @return void
     */
    private function setUserToken(int $uid){
        $token = Common::setToken($uid);
        Db::table('user_token')->updateOrInsert(
            ['uid' => $uid],
            ['token' => $token]
        );
        $Redis = Common::Redis('Redis5501');
        $Redis->hSet('php_login_1_1', (string)$uid, (string)$token);
        return $token;
    }

    /**
     * 设置用户团队层级数据
     * @param $uid 用户UID
     * @param $chanelid 渠道ID
     * @param $package_id   包名
     * @return void
     */
    private function setLogin($uid,$chanelid,$package_id){
        co(function () use ($uid,$chanelid,$package_id){
            try{
                Db::table('login_'.date('Ymd'))->updateOrInsert(
                    ['uid' => $uid],
                    ['channel' => $chanelid, 'package_id' => $package_id, 'createtime' => time()]
                );
            }catch (\Throwable $e){
                $this->logger->error('设置用户每日登录系统发送错误:'.$e->getMessage());
            }

        });
    }


    /**
     * 设置用户团队层级数据
     * 设置用户团队层级数据
     * @param $uid 用户UID
     * @param $chanelid 渠道ID
     * @param $package_id   包名
     * @return void
     */
    private function setRegist($uid,$chanelid,$package_id){
        //处理每日注册数据表检查数据表是否存在，不存在就创建
        co(function () use ($uid,$package_id,$chanelid) {
            try {
                Db::table('regist_'.date('Ymd'))->insertOrIgnore([
                    'uid' => $uid,
                    'channel' => $chanelid,
                    'package_id' => $package_id,
                    'createtime' => time(),
                ]);
            }catch (\Throwable $e){
                $this->logger->error('记录用户注册系统发送错误:'.$e->getMessage());
            }
        });
    }


    /**
     * 设置用户团队层级数据
     * @param $uid 用户UID
     * @param $puid 上级代理用户UID
     * @param $level 上级用户层级 默认是自己 0
     * @return void
     */
    private function setTeamLevel($uid,$puid,$nickname,$avatar,$channel,$package_id,$level = 0,$param=[]){

        //第一次进来，如果有团队详细数据就直接返回。
        if(!$level){
            $this->installTeamLevel($uid,$uid,0);
            //存储团队信息
            if($puid > 0){
                //转盘邀请用户处理
                $turntable_id = 0;
                $money = 0;
                $bindData = isset($param['bindData']) ? json_decode($param['bindData'],true) : $param;
                if (isset($bindData['turntable_id']) && $bindData['turntable_id'] > 0){
                    $turntable_id = $bindData['turntable_id'];
                    //$turntable_user_count = Db::table('user_team')->where('turntable_id',$turntable_id)->count();
                    //$puid_info = Db::table('userinfo')->where('uid',$uid)->first();

                    //$user_water = Db::table('user_water')->where('uid', $puid)->first();
                    /*$user_water = Db::table('commission_log')->where('puid', $puid)->where('level',1)
                        ->selectRaw('IFNULL(SUM(BetAmount),0) as total_cash_water_score')
                        ->first();
                    $turntable_config = config('turntable');

                    $start = 0;
                    $end = 0;
                    if (!empty($user_water)){
                        if ($user_water['total_cash_water_score']/100 < $turntable_config['range_money'][0]){
                            $start = $turntable_config['range_zs_money'][0][0] * 100;
                            $end = $turntable_config['range_zs_money'][0][1] * 100;
                        }elseif ($user_water['total_cash_water_score']/100 >= $turntable_config['range_money'][0] && $user_water['total_cash_water_score']/100 < $turntable_config['range_money'][1]){
                            $start = $turntable_config['range_zs_money'][1][0] * 100;
                            $end = $turntable_config['range_zs_money'][1][1] * 100;
                        }elseif ($user_water['total_cash_water_score']/100 >= $turntable_config['range_money'][1] && $user_water['total_cash_water_score']/100 < $turntable_config['range_money'][2]){
                            $start = $turntable_config['range_zs_money'][2][0] * 100;
                            $end = $turntable_config['range_zs_money'][2][1] * 100;
                        }elseif ($user_water['total_cash_water_score']/100 >= $turntable_config['range_money'][2] && $user_water['total_cash_water_score']/100 < $turntable_config['range_money'][3]){
                            $start = $turntable_config['range_zs_money'][3][0] * 100;
                            $end = $turntable_config['range_zs_money'][3][1] * 100;
                        }elseif ($user_water['total_cash_water_score']/100 >= $turntable_config['range_money'][3]){
                            $start = $turntable_config['range_zs_money'][4][0] * 100;
                            $end = $turntable_config['range_zs_money'][4][1] * 100;
                        }else{
                            $start = $turntable_config['range_zs_money'][0][0] * 100;
                            $end = $turntable_config['range_zs_money'][0][1] * 100;
                        }
                    }else{
                        $start = $turntable_config['range_zs_money'][0][0] * 100;
                        $end = $turntable_config['range_zs_money'][0][1] * 100;
                    }
                    $money = mt_rand((int)$start, (int)$end) / 100;*/

                    /*if ($turntable_user_count <= 5){
                        $money = mt_rand(5, 15) / 100;
                    }else{
                        $probability = 0.2;  // 控制概率，范围为 0 到 1 之间
                        $randomNumber = mt_rand(1, 100) / 100;  // 生成一个介于 0.01 和 1 之间的随机数
                        if ($randomNumber < $probability) {
                            $money = 0.01;
                        } else {
                            $money = 0;
                        }
                    }*/
                    //Db::table('turntable')->where('id',$turntable_id)->increment('money', $money);
                }

                Db::table('user_team')->insert([
                    'uid' => $uid,
                    'nickname' => $nickname,
                    'avatar' => $avatar,
                    'puid' => $puid,
                    'createtime' => time(),
                    'channel' => $channel,
                    'package_id' => $package_id,
                    'turntable_id' => $turntable_id,
                    'money' => $money,
                ]);
            }else{
                return 1;
            }
        }

        //防止层级过多出问题，这里最多设置10级
        if($level >= 10)return 1;

        $this->installTeamLevel($uid,$puid,$level + 1);

        //获取上级用户是否还有上级用户
        $user_team = Db::table('user_team')->select('puid')->where('uid',$puid)->first();
        if(!$user_team)return 1;

        //如果推荐代理有上级用户，同时上级用户不是代理自己
        if($user_team['puid'] > 0 && $user_team['puid'] != $puid) self::setTeamLevel($uid,$user_team['puid'],$nickname,$avatar,$channel,$package_id,$level + 1);
        return 1;
    }


    /**
     * 储存用户团队层级数据
     * @return void 存储
     */
    private function installTeamLevel($uid,$puid,$level = 0){
        Db::table('teamlevel')->insert([
            'uid' => $uid,
            'puid' => $puid,
            'level' => $level,
            'createtime' => time(),
        ]);
    }




    /**
     * @return bool|mixed 自然量是否开启进入B面,1=是,0=否
     */
    private function isNatureStatus(){
        return Common::getConfigValue('is_nature_status');
    }

    /**
     * 这里每次更新服务器需要把强制加10000的关闭掉
     * 设置首次登录用户的uid
     * @param $package_id 包名
     * @param $logintype 登录类型
     * @param $key  key
     * @return void
     */
    private function GetUid()
    {
        $Redis = Common::Redis('Redis5501');
        $olduid = $Redis->hGet("D_TEXAS_INDEX", 'UID');
        $randnum = rand(2, 9); //取随机数
        $uid = $olduid + $randnum;
        $Redis->hSet("D_TEXAS_INDEX", 'UID', $olduid + $randnum);

        return $uid;
    }

    /**
     * @return void 生成邀请码
     */
    private function code(){
        return time().rand(1000,9999);
        $english = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"),0,4);
        $num = rand(1000,9999);
        $code = str_shuffle($num.$english);
        $user_team = Db::table('share_strlog')->select('uid')->where('code','=',$code)->first();
        if($user_team){
            $this->code();
        }
        return $code;
    }



    /**
     * 随机生产几位字母
     * @param $length
     * @return string
     */
    private static function generateRandomString($uid,$length = 6){

        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString.$uid;

    }


    /**
     * 给充值用户随机生产email
     * @param $length
     * @return string
     */
    private function getEmail(){
        $shuff = '@gmail.com' ;
        $RandomString = self::generateRandomString(rand(1000,9999));
        return $RandomString.$shuff;
    }



    /**
     * 给充值用户随机生产生成手机号
     * @param $length
     * @return string
     */
    private function getPhone(){
        return rand(7777777777,9999999999);
    }


    /**
     * 给充值用户随机生产生成手机号
     * @param $length
     * @return string
     */
    private function getName(){
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $randomString = '';
        for ($i = 0; $i < 6; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    /**
     * 获取自然用户的登录状态
     * @param $device_id 设备号
     * @param $device 设备
     * @param $package_id 分享包
     * @param $isApp 是否是APP  'true' 是 ， 'false' 否
     * 返回值代表 0 = A面  ， 1 = B面  ,-1 = 不允许登录
     */
    private function getIpLoginStatus($package_id,$ip){
        //获取用户城市
        $response = $this->getUserCity($ip,2,$package_id);
        $city = $response['regionName'].'/'.$response['city'];

//        if($response['regionName'] == 'Rajasthan')return ['city' => $city,'ip_login_status' => -1];  //如果是拉贾斯坦直接禁止登录

        // 帮 regionName/城市 city/国家编码 countryCode  例子 Rajasthan/Jodhpur/IN
//        if($response['countryCode'] != 'IN')return ['city' => $city,'ip_login_status' => -1]; //如果不在印度就直接A面

        return ['city' => $city,'ip_login_status' => 1];
    }

    /**
     * @param $type 类型: 1= 直接返回帮和城市的字符串 ， 2 = 获取所有数据
     * @return mixed 获取用户城市
     * @param $ip  用户ip
     */
    public function getUserCity($ip,$type = 1,$package_id = 0){
        $response =  $this->guzzle->get("https://pro.ip-api.com/json/".$ip."?key=IKEH1fFMW5o1qKY");
        return  $type == 1 ? $response['regionName'].'/'.$response['city'] : $response;

    }





    /**
     * 获取广告渠道id
     * @param $param param 参数
     * @param $chanelid 渠道号
     * @param $package_id 包名
     * @return string
     */
    private function getGgChanelid($param,$chanelid,$package_id,$af_status){

        if((isset($param['bindData'])  && $param['bindData'])  || (isset($param['af_status']) && $param['af_status'] == 'share-link')){  //如果是特殊的话就单独处理
            $bindData = isset($param['bindData']) ? json_decode($param['bindData'],true) : $param;
            if(isset($bindData['agent']) && $bindData['agent']){
                $agent_cid = Db::table('chanel')->where([['package_id','=',$package_id],['ctag','=',$bindData['agent']]])->value('channel');
                return $agent_cid ?: $chanelid;
            }

        }
        //修改广告用户的默认渠道
        if($af_status == 1){
            $ggchanelid = Db::table('chanel')->where([['package_id','=',$package_id],['type','=',2]])->value('channel');
            $chanelid = $ggchanelid ?: $chanelid;
        };

        $fb_tage = $param['campaign'] ?? ''; //fb的广告标记
        if($fb_tage){
            $fb_tage = explode('_',$fb_tage)[0]; //可能是下划线
            $chanelinfo = Db::table('chanel')->where([['package_id','=',$package_id],['ctag','like',$fb_tage.'%']])->first();
            if($chanelinfo){
                return $chanelinfo['channel'];
            }
            $fb_tage = explode('-',$fb_tage)[0]; //可能是中横线
            $chanelinfo = Db::table('chanel')->where([['package_id','=',$package_id],['ctag','like',$fb_tage.'%']])->first();
            if($chanelinfo) return $chanelinfo['channel'];
        }

        $adjust_tage = $param['trackerName'] ?? ''; //adjust的广告标记
        if($adjust_tage){
            $adjust_tage = explode('::',$adjust_tage)[0];
            $chanelinfo = Db::table('chanel')->where([['package_id','=',$package_id],['ctag','like',$adjust_tage.'%']])->first();
            if($chanelinfo){
                $chanelid = $chanelinfo['channel'];
            }
        }
        return $chanelid;
    }


    /**
     * 检查是否是刷子帮
     * @param $city
     * @return int
     */
    private function is_black_city($city)
    {
        $black = [//上面是中文名称，下面是英文名称
            '马哈拉施特拉', '拉贾斯坦', '阿萨姆', '奥里萨', '特朗加纳', '查谟和克什米尔', '北方邦',
            'maharashtra','rajasthan','assam','odisha','telangana','jammu and kashmir','uttar pradesh'
        ];

        foreach ($black as $vo)
        {
            if (strpos(strtolower($city), $vo) !== false)
            {
//                return $city . ' <span class="layui-font-red">[刷子邦]</span>';
                return 1;
            }
        }

        return 0;
    }


    /**处理当日注册用户与当日注册统计
     * @param $uid 用户uid
     * @param $package_id 包id
     * @param $channel 渠道号
     * @param $af_status  是否是广告
     * @return void
     */
    private function statisticsRetainedUser($uid,$package_id,$channel){

        co(function () use ($uid,$package_id,$channel) {
            try {
                //获取当日包和渠道下的首充用户
                $day_user = Db::table('statistics_retaineduser')->where([['time','=',strtotime('00:00:00')],['package_id','=',$package_id],['channel','=',$channel]])
                    ->update([
                        'uids' => Db::raw("concat(uids,',', '$uid')")
                    ]);

                if(!$day_user){
                    Db::table('statistics_retaineduser')
                        ->insert([
                            'time' => strtotime('00:00:00'),
                            'package_id' => $package_id,
                            'channel' => $channel,
                            'uids' => $uid,
                        ]);
                }

                $res = Db::table('statistics_retained')->where([['time','=',strtotime('00:00:00')],['package_id','=',$package_id],['channel','=',$channel]])
                    ->update([
                        'num' => Db::raw('num + 1')
                    ]);

                if(!$res){
                    Db::table('statistics_retained')
                        ->insert([
                            'time' => strtotime('00:00:00'),
                            'package_id' => $package_id,
                            'channel' => $channel,
                            'num' => 1,
                        ]);
                }

                $roi = Db::table('statistics_roi')->where([['time','=',strtotime('00:00:00')],['package_id','=',$package_id],['channel','=',$channel]])
                    ->update([
                        'num' => Db::raw('num + 1')
                    ]);

                if(!$roi){
                    Db::table('statistics_roi')
                        ->insert([
                            'time' => strtotime('00:00:00'),
                            'package_id' => $package_id,
                            'channel' => $channel,
                            'num' => 1,
                        ]);
                }

                $ltv = Db::table('statistics_ltv')->where([['time','=',strtotime('00:00:00')],['package_id','=',$package_id],['channel','=',$channel],['type','=',1]])
                    ->update([
                        'num' => Db::raw('num + 1')
                    ]);

                if(!$ltv){
                    Db::table('statistics_ltv')
                        ->insert([
                            'time' => strtotime('00:00:00'),
                            'package_id' => $package_id,
                            'channel' => $channel,
                            'type' => 1,
                            'num' => 1,
                        ]);
                }

            } catch (\Throwable $e) {
                // 捕获异常并处理
                $this->logger->error('注册处理用户留存数据系统发送错误:' . $e->getMessage());
            }

        });

    }

    /**
     * 获取用户的
     * @param string $device_id
     * @param string $ip
     * @param  $chanelid 渠道
     * @param  $package_id 包id
     * @return void
     */
    public function useWebFbpFbc($device_id,$ip,$chanelid,$package_id){


        try {
            $fb_web = [];
            $fb_web_not_id = 0; //修改使用的ID
            if($device_id)$fb_web = Db::table('fb_web')->select('fbc','fbp','campaign')->where('device_id',$device_id)->first();
            if($fb_web){
                $fbc = $fb_web['fbc'];
                $fbp = $fb_web['fbp'];
                $campaign = $fb_web['campaign'];
            }else{
                $fb_web_not_use = Db::table('fb_web')->select('id','fbc','fbp','campaign','status')->where(['ip' => $ip,'status' => 0])->first();
                if(!$fb_web_not_use){
                    $fb_web_not_use = Db::table('fb_web')->select('fbc','fbp','campaign')->where(['ip' => $ip])->first();
                    if(!$fb_web_not_use)return ['','',$chanelid,0];

                }else{
                    $fb_web_not_id = $fb_web_not_use['id'];
                }
                $fbc = $fb_web_not_use['fbc'];
                $fbp = $fb_web_not_use['fbp'];
                $campaign = $fb_web_not_use['campaign'];
            }



            $chanelinfo = Db::table('chanel')->select('channel','type')->where([['ctag','=',$campaign],['package_id','=',$package_id]])->first();
            if($chanelinfo) $chanelid = $chanelinfo['channel'];
            if($device_id && $fb_web_not_id)Db::table('fb_web')->where('id',$fb_web_not_id)->update([
                'device_id' => $device_id,
                'channel' => $chanelid,
                'status' => 1
            ]);

            return [$fbc,$fbp,$chanelid,1];
        }catch (\Throwable $e){
            $this->logger->error('useWebFbpFbc处理失败:'.$e->getMessage());
        }

    }

    /**
     * 召回统计
     * @param $uid
     * @param $package_id
     * @param $last_login_time
     * @return void
     */
    private function recallolUser($uid,$package_id,$last_login_time, $channel){
//        $this->logger->error('packid==>'.$package_id);
        //获取多久的用户算召回玩家
        $recallol_day = Common::getConfigValue('recallol_day');

        //判断判断是否开始召回，当老玩家设置天数以后开启，0代表不开启;判断包是否支持召回活动;判断回归时候是否大于配置;判断是否参与了回归活动
        if(!$recallol_day || time() <= bcadd((string)$last_login_time,bcmul((string)$recallol_day,'86400',0),0))return;

        //获取用户总充值
        $userinfo = Db::table('userinfo')->select('total_pay_score','total_exchange')->where('uid',$uid)->first();

        //判断是否参加过
        $res = Db::table('active_recallolduser_log')->select('id')->where('uid',$uid)->first();
        if($res)return;

        //存储参加日志
        Db::table('active_recallolduser_log')->insert([
            'uid' => $uid,
            'recallolduser_id' => 0,
            'old_price' => $userinfo['total_pay_score'],
            'old_exchange' => $userinfo['total_exchange'],
            'zs_bonus' => 0,
            'package_id' => $package_id,
            'channel' => $channel,
            'createtime' => time(),
        ]);

        $recallold_statistics = Db::table('recallold_statistics')->select('id')->where('package_id',$package_id)->orderBy('id','desc')->first();
        if($recallold_statistics)Db::table('recallold_statistics')->where('id',$recallold_statistics['id'])->update([
            'recallold_num' => Db::raw('recallold_num + 1')
        ]);

    }


    /**
     * 判断用户是否已经存在
     * @param $uid
     * @param $login_type 1=手机,2=谷歌,3=账号,4=游客
     * @return void
     */
    private function getUserBackup($info,$package_id,$login_type = 1,$password = ''){
        $query = Db::connection('readConfig')
            ->table('share_strlog_backup');
        match ($login_type){
            1 => $query->where([['phone','=',$info],['password','=', md5($password)],['package_id','=',$package_id]]),
            2 => $query->where([['googlesub','=',$info],['package_id','=',$package_id]]),
            3 => $query->where([['account','=',$info],['password','=', md5($password)],['package_id','=',$package_id]]),
            default => $query->where([['account','=',$info],['package_id','=',$package_id]])
        };

        $share_strlog = $query->orderBy('uid','desc')
            ->first();
        if(!$share_strlog)return [];
        $userinfo = Db::connection('readConfig')
            ->table('userinfo_backup')
            ->where('uid',$share_strlog['uid'])
            ->first();
        if(!$userinfo)return [];
        try {
            Db::table('share_strlog')->insert($share_strlog);
            Db::table('userinfo')->insert($userinfo);
        }catch (\Throwable $e){
            $this->logger->error('用户数据回滚失败:'.$e->getMessage());
        }

        Db::table('share_strlog_backup')->where('uid',$share_strlog['uid'])->delete();
        Db::table('userinfo_backup')->where('uid',$share_strlog['uid'])->delete();
        return $share_strlog;

    }
}


