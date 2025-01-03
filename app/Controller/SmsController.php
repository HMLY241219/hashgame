<?php
declare(strict_types=1);

namespace App\Controller;
use App\Common\Common;
use App\Common\Guzzle;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
#[Controller(prefix:"Sms")]
class SmsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    /**
     * 发送验证码
     * @return void
     */
    #[RequestMapping(path: "code", methods: "get,post")	]
    public function code(){

        $phone = $this->request->post('phone');  //这里手机号需要带区号 84是越南
        $randnum = mt_rand(1000,9999);

        //检查手机号是否正确
        if (!Common::PregMatch($phone,'phone'))return $this->ReturnJson->failFul(206);

        $result  = $this->onbukaSendCode($phone, $randnum);

        if($result)
        {
            $this->savecode($phone,(string)$randnum);
            return $this->ReturnJson->successFul();
        }else{

            return $this->ReturnJson->failFul(215);
        }
    }



    /**
     * @param $phone 电话
     * @param $randnum 验证码
     * @return void
     * @throws \RedisException
     */
    private function savecode($phone,string $randnum){
        $redis = Common::Redis('Redis5501');
        $redis->hSet("PHONE_ACCOUNT_CODE", $phone, $randnum);
    }

    /**
     * 效验验证码是否正确
     * @return
     */
    #[RequestMapping(path: "effectivenessCode", methods: "get,post")	]
    public function effectivenessCode(){
        $phone = $this->request->post('phone');
        $code = $this->request->post('code');
        $redis = Common::Redis('Redis5501');
        $redisCode = $redis->hGet("PHONE_ACCOUNT_CODE", (string)$phone);
        if($redisCode != $code)return $this->ReturnJson->failFul(209);
        return $this->ReturnJson->successFul();
    }

    #[RequestMapping(path: "getcode")	]
    public function getcode(){

        $phone = $this->request->post('phone');
        $redis = Common::Redis('Redis5501');
        return $redis->hGet("PHONE_ACCOUNT_CODE", (string)$phone);
    }



//    /**
//     * @return bool 天哄一短信
//     */
//    private function onbukaSendCode($phone,$content,$appId = "Btv7LMEW"){
//        return true;
//        header('content-type:text/html;charset=utf8');
//
//        $apiKey = "NTKyaG0g";
//        $apiSecret = "y2wzGNtL";
//
//        $url = "https://api.itniotech.com/sms/sendSms";
//
//        $timeStamp = time();
//        $sign = md5($apiKey.$apiSecret.$timeStamp);
//
//        $dataArr['appId'] = $appId;
//        $dataArr['numbers'] = '91'.$phone;
//        $dataArr['content'] = $content;
//        $dataArr['senderId'] = '';
//
//
//
//         $headers = [
//             "Content-Type" => "application/json;charset=UTF-8",
//             "Sign" => $sign,
//             "Timestamp" => $timeStamp,
//             "Api-Key" => $apiKey,
//         ];
//         $data = $this->guzzle->post($url,$dataArr,$headers);
//
//
//        if(!isset($data['status']) || $data['status'] != 0){
//            Common::log(0,json_encode($data),6);
//            return false;
//        }
//        return true;
//
//    }

    /**
     * @return bool 天哄一短信
     */
    private function onbukaSendCode($phone,$content,$appId = "Btv7LMEW"){
        return true;
        header('content-type:text/html;charset=utf8');

        $apiKey = "NTKyaG0g";
        $apiSecret = "y2wzGNtL";

        $url = "https://api.itniotech.com/sms/sendSms";

        $timeStamp = time();
        $sign = md5($apiKey.$apiSecret.$timeStamp);

        $dataArr['appId'] = $appId;
//        $dataArr['numbers'] = '84'.$phone;
        $dataArr['numbers'] = $phone;
        $dataArr['content'] = $content;
        $dataArr['senderId'] = '';

        $data = json_encode($dataArr);
        $headers = array('Content-Type:application/json;charset=UTF-8',"Sign:$sign","Timestamp:$timeStamp","Api-Key:$apiKey");

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 600);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS , $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//       {"status":"0","reason":"success","success":"1","fail":"0","array":[{"msgId":"2304252117461165117","number":"918066778800","orderId":null}]}
        $output = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($output,true);

        if(!isset($data['status']) || $data['status'] != 0){
//            \customlibrary\Common::log(0,$output,6);
            Common::log(0,$output,6);
            return false;
        }
        return true;

    }


}

