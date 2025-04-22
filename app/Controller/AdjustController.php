<?php
declare(strict_types=1);
namespace App\Controller;

use App\Common\Guzzle;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;
use  App\Common\Curl;

class AdjustController extends AbstractController
{


    #[Inject]
    protected Guzzle $guzzle;



    private array $needAdjustPayTwoPackageId = ['com.win3377.mtg'];//需要打二次次数的包
    private array $needAdjustPayPackageId = ['com.win3377.gb'];  //需要打累充200，累充500，累计充值3次，累计充值5次
    private array $firstChargeAdjustPayPackageId = ['com.win3377.youmi'];  //首充100和300以上的单独打点
    /**
     *
     * @param $packname
     * @param $gps_adid
     * @param $adid 这里是adid
     * @param $totalfee 支付金额/游戏次数
     * @param $is_first_recharge
     * @param $orderinfo
     * @param $shareinfo
     * @param $type 1 = 充值 ， 2= 注册 , 3 =游戏次数打点
     * @return void
     */
    public function adjustUploadEvent($packname, $gps_adid, $adid, $totalfee, $is_first_recharge, $ordersn, $share_strlog,$type = 1)
    {
        co(function () use ($packname,$gps_adid,$adid,$totalfee,$is_first_recharge,$ordersn,$type,$share_strlog){
            try {
                //如果是渠道打点,包名就变为渠道
                $channel_point = Db::table('channel_point')->selectRaw('ad_first_purchase,ad_purchase,ad_todayfirst_purchase,ad_complete_registration,ad_key')->where('channel',$packname)->first();
                if(!$channel_point || !$channel_point['ad_key'])return 111;

                if($type == 1){  //只打当天用户的点
                    if ($is_first_recharge && date('Ymd',$share_strlog['regist_time']) == date('Ymd')) {
                        $response = $this->guzzle->get("https://s2s.adjust.com/event?s2s=1&event_token=" . $channel_point["ad_todayfirst_purchase"] . "&app_token=" . $channel_point['ad_key'] . "&gps_adid=" . $gps_adid . "&revenue=" . $totalfee . "&currency=INR&adid=" . $adid);
                            Db::table("log")->insert(['out_trade_no' => 'Adjust打点注册当日首次付费回调'.$ordersn, 'type' => 5,'createtime' => time(), "log" => json_encode($response)]);
                    }elseif ($is_first_recharge){
                         $response = $this->guzzle->get("https://s2s.adjust.com/event?s2s=1&event_token=" . $channel_point["ad_first_purchase"] . "&app_token=" . $channel_point['ad_key'] . "&gps_adid=" . $gps_adid . "&revenue=" . $totalfee . "&currency=INR&adid=" . $adid);
                            Db::table("log")->insert(['out_trade_no' => 'Adjust打点首次付费回调'.$ordersn, 'type' => 5,'createtime' => time(), "log" => json_encode($response)]);

                    }else{
                        $response = $this->guzzle->get("https://s2s.adjust.com/event?s2s=1&event_token=" . $channel_point["ad_purchase"] . "&app_token=" .$channel_point['ad_key'] . "&gps_adid=" . $gps_adid . "&revenue=" . $totalfee . "&currency=INR&adid=" . $adid);
                        Db::table("log")->insert(['out_trade_no' => 'Adjust打点付费回调'.$ordersn, 'type' => 5,'createtime' => time(),"log" => json_encode($response)]);
                    }


                }elseif($type == 2){
                    $response = $this->guzzle->get("https://s2s.adjust.com/event?s2s=1&event_token=" . $channel_point["ad_complete_registration"] . "&app_token=" . $channel_point['ad_key'] . "&gps_adid=" . $gps_adid . "&adid=" . $adid);
                    Db::table("log")->insert(['out_trade_no' => 'Adjust注册打点'.$packname, 'type' => 5,'createtime' => time(),"log" => json_encode($response)]);
                }

            }catch (\Throwable $e){
                $this->logger->error('Adjust打点系统发送错误:'.$e->getMessage());
            }
            return 1;
        });



    }




    /**
     * Fb打点
     * @return void
     * @param $type 类型: 1 = 支付点 ，2 = 注册点 , 3= 自定义事件名称,4=购物车点
     * @param $fbEventName 事件名称
     *
     */
    public function fbUploadEvent($packname, $totalfee, $is_first_recharge, $ordersn,$uid,$share_strlog = [],$type = 1,$fbEventName = ''){
        // 定义 Pixel ID 和 Access Token
        [$ip,$userAgent]  = $this->getIpAndUserAgent();

         co(function () use ($packname,$totalfee,$is_first_recharge,$ordersn,$uid,$type,$fbEventName,$share_strlog,$userAgent){
             try {
                 //如果是渠道打点,包名就变为渠道

                 $channel_point = Db::table('channel_point')->selectRaw('fb_token,fb_pixel_id')->where('channel',$share_strlog['channel'])->first();
                 if(!$channel_point){
                     $channel_point = Db::table('channel_point')->selectRaw('fb_token,fb_pixel_id')->where('channel',$packname)->first();
                     if(!$channel_point)return 111;
                 }else{
                     $packname = $share_strlog['channel'];
                 }
                 if(!$channel_point['fb_pixel_id'])return 111;


                 if($type == 2){
                     $eventName = 'CompleteRegistration';
                     $eventParams = [];
                     $remark = 'FB注册打点'.$packname;
                 }elseif ($type == 3){
                     $eventName = $fbEventName;
                     $eventParams = [];
                     $remark = "FB".$eventName."打点".$packname;
                 }else{
                     if($type == 4){
                         $eventName = 'AddToCart';
                     }else{
                         $eventName = $is_first_recharge  ? 'Purchase' : 'AddPaymentInfo';
                     }
                     $eventParams = [
                         'currency' => 'INR',
                         'value' => $totalfee,
                         // 添加其他自定义参数
                     ];
                     $remark = 'FB'.$eventName.'打点付费回调'.$ordersn;
                 }

                 $url = 'https://graph.facebook.com/v20.0/' . $channel_point['fb_pixel_id'] . '/events';


                 $provincial = explode('/',$share_strlog['city']);
                 $data = [
                     'data' => [
                         [
                             'event_name' => $eventName,
                             'event_time' => time(),
                             'user_data' => [
                                 'client_ip_address' => $share_strlog['ip'],
                                 'client_user_agent' => $userAgent,
                                 'fbc' => $share_strlog['fbc'],
//                                 'fbp' => $share_strlog['fbp'],
                                 'st' => isset($provincial[0]) ? hash('sha256', strtolower($provincial[0])) : '',
                                 'external_id' => $share_strlog['gpsadid'] ?: (string)$uid ,
                                 'country' => hash('sha256', 'in'),
                             ],
                             'custom_data' => $eventParams,
                         ],
                     ],
                 ];
                if($share_strlog['fbp'])$data['data'][0]['user_data']['fbp'] = $share_strlog['fbp'];

//                 $response =  $this->guzzle->post($url,$data,[ 'Content-Type' => 'application/json', 'Authorization' =>  'Bearer ' .  $fBConfig[$packname]['accessToken']]);
                 $herder = [
                     'Content-Type: application/json',
                     'Authorization: Bearer ' .  $channel_point['fb_token'],
                 ];
                 $response = Curl::post($url,$data,$herder);

                 $response = json_decode($response,true);

 // 处理响应
                 if ($response) {
                     Db::table("log")->insert(['out_trade_no' => $remark, 'type' => 5,'createtime' => time(),"log" => json_encode($response)]);
//                     if($packname == '10290')Db::table("log")->insert(['out_trade_no' => $remark, 'type' => 5,'createtime' => time(),"log" => json_encode($response)]);
                     if (isset($response['events_received'])) {
                         $eventCount = $response['events_received'];

 //                $this->logger->error('成功发送 ' . $eventCount . ' 个事件。');

                     } else {
                         Db::table("log")->insert(['out_trade_no' => $remark, 'type' => 5,'createtime' => time(),"log" => json_encode($response)]);
                         $this->logger->error('Fb发送事件失败'.json_encode($response));
                     }
                 } else {
                     Db::table("log")->insert(['out_trade_no' => $remark, 'type' => 5,'createtime' => time(),"log" => json_encode($response)]);
                     $this->logger->error('Fb发送请求失败.'.json_encode($response));

                 }
             }catch (\Throwable $e){

                 $this->logger->error('Fb打点系统发送错误:'.$e->getMessage());
             }
             return 1;
         });


    }


    /** AF打点
     * @param $packname
     * @param $gps_adid
     * @param $adid 这里是afid
     * @param $totalfee
     * @param $is_first_recharge
     * @param $ordersn
     * @param $share_strlog
     * @param $type 1 = 支付 ，2 = 注册
     * @return void
     */
    public function afUploadEvent($packname, $gps_adid, $adid, $totalfee, $is_first_recharge, $ordersn, $share_strlog,$type = 1){
        co(function () use($packname,$gps_adid,$adid,$totalfee,$is_first_recharge,$ordersn,$share_strlog,$type){
            try {
                $array = [
                    //2023-6-15投放
                    "com.mzfasfk.lffutxovqmeaq"  => 'rxC9SU7YVKVyQRuDXfAiNU',
                ];
                if(!isset($array[$packname])){  //直接返回
                    return 1111;
                }

                $header = [
                    "Content-Type" => "application/json",
                    "authentication" => $array[$packname],
                ];
                //1 = 支付 , 2 = 注册
                if($type == 1){
                    $eventValue = array(
                        "af_revenue"=> $totalfee,
                        "af_currency"=>"INR",
                        "af_content_type"=> "wallets",
                        "af_content_id"=>"15854",
                        "af_quantity"=>"1"
                    );
                    $eventName = 'af_purchase';
                }else{
                    $eventValue = [];
                    $eventName = 'CompleteRegistration';
                }

                $data =array(
                    "appsflyer_id"=>$adid,
                    "ip"=>$share_strlog["login_ip"],
                    "customer_user_id"=>$share_strlog['uid'],
                    "app_version_name"=>"1.0",
                    "eventTime"=>date("Y-m-d H:i:s"),
                    "eventName"=>$eventName,
                    "eventCurrency"=>"INR",
                    "os"=>"14.5.1",
                    "att"=>3,
                    "eventValue"=> $eventValue,
                );
                $response =  $this->guzzle->post("https://api2.appsflyer.com/inappevent/".$packname,$data,$header);

                Db::table("log")->insert(['out_trade_no' => $type == 1 ? 'AF打点付费回调'.$ordersn : 'AF注册打点'.$packname, 'type' => 5,'createtime' => time(), "log" => json_encode($response)]);

            }catch (\Throwable $e){
                $this->logger->error('Af打点系统异常:'.$e->getMessage());
            }
            return 1;
        });

    }

    /**
     * 获取用户的ip和userAgent
     * @return array
     */
    private function getIpAndUserAgent(){

        $request = ApplicationContext::getContainer()->get(RequestInterface::class);
        $headers = $request->getHeaders();
        $ip = '';
        if(isset($headers['x-forwarded-for'][0]) && !empty($headers['x-forwarded-for'][0])) {
            $ip =  $headers['x-forwarded-for'][0];
        } elseif (isset($headers['x-real-ip'][0]) && !empty($headers['x-real-ip'][0])) {
            $ip = $headers['x-real-ip'][0];
        }
        $userAgent = $headers['user-agent'][0] ?? '';
        return  [$ip,$userAgent];
    }
}


