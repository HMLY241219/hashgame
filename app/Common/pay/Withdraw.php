<?php
declare(strict_types=1);
namespace App\Common\pay;

use http\Env;
use Psr\Log\LoggerInterface;
use App\Common\Common;
use App\Common\Guzzle;
use Hyperf\Di\Annotation\Inject;
use  App\Common\Curl;
use function Hyperf\Config\config;
use function Hyperf\Support\env;
class Withdraw{

    #[Inject]
    protected Common $Common;
    #[Inject]
    protected Guzzle $guzzle;
    #[Inject]
    protected LoggerInterface $logger; //存储日志

    //no_pay
    private string $nopay_url  = "https://icw891o.nopay.app/order/withdrawOrderCoinCreate";                 //下单地址
    private string $nopay_merchantNo = "TBIK6NOXLXUJQ7R1"; //正式
    private string $nopay_notifyUrl  = "nopayNotify";  //回调地址
    private string $nopay_Key        = "TBKqifMFIOeud0rSNzIkW6iIFOmrbTVv";//正式



    //qf888_pay
    private string $qf888pay_url = "https://www.qf888.vip/api/behalfPay/bill";//下单地址
    private string $qf888pay_mchid = "1637576";//商户编码
    private string $qf888pay_key = "647188ab09b5875d452b46e6310abe76";//商户密钥的值
    private string $qf888pay_backUrl = "qf888payNotify";//回调地址



    private array $header = ["Content-Type" => "application/x-www-form-urlencoded"];


    private array $zr_header = ["Content-Type" => "application/json"];

    /**
     * @return void 统一提现接口
     * @param $paytype 提现渠道
     * @param $withdrawdata 创建提现订单信息
     */
    public function withdraw($withdrawdata,$withtype,$type){

        return $this->$withtype($withdrawdata,$type);
    }



    /**
     * no_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function no_pay($withdrawlog,$type){

        $data   = [
            "merchantOrderNo" => $withdrawlog['ordersn'],
            "merchantMemberNo" => $withdrawlog['uid'],
            "amount"  =>  bcdiv((string)$withdrawlog['really_withdraw_money'],'100',2),
            "coin" => 'USDT',
            "language"  =>  'en',
            "rateType" => 1,
            "protocol" => $withdrawlog['protocol_name'],
            "notifyUrl" => $this->getNotifyUrl($this->nopay_notifyUrl) ,
            "toAddress"  =>  $withdrawlog['bankaccount'],
            "timestamp" => time(),
            "appId" => $this->nopay_merchantNo,
        ];

        $signStr = Sign::dataString($data);
        $data['sign'] =  hash('sha256', $signStr.'&key='.$this->nopay_Key);

        $herder = array(
            "Content-Type: application/json",
            "version: v1",
            "appId: ".$this->nopay_merchantNo,
        );



        try {
            // $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->nopay_url,'header' => $herder],$this->header);
            $response = Curl::post($this->nopay_url,$data,$herder);
            $response = json_decode($response,true);


        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 0){

            return ['code' => 200 , 'msg' => '' , 'data' => $response['data']['orderNo']];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }


    /**
     * qf888_pay
     * @param $withdrawlog 用户提现数据
     * @return array
     */
    public function qf888_pay($withdrawlog,$type){
        if($withdrawlog['type'] != 1){
            return ['code' => 201 , 'msg' => 'Sorry! Only bank cards are supported' , 'data' => []];
        }
        $bankCode = config('withdrawbankcode.vnd.qf888_pay')[$withdrawlog['ifsccode']] ?? '';
        if(!$bankCode){
            $this->logger->error('qf888_pay代付-BackCode获取失败-ordersn：'.$withdrawlog['ordersn']);
            return ['code' => 201 , 'msg' => 'BackCode获取失败' , 'data' => []];
        }
        $data = [
            "mchId"      => $this->qf888pay_mchid,
            "mchOrderId" => $withdrawlog['ordersn'],
            "bankCode" => $bankCode,
            'bankAccount' => $withdrawlog['bankaccount'],
            'bankOwner' => str_replace(' ', '', $withdrawlog['backname']),
            "amount"  =>  bcdiv((string)$withdrawlog['really_withdraw_money'],'100',2),
            'notifyUrl' =>  $this->getNotifyUrl($this->qf888pay_backUrl)  ,
        ];
        $data['sign'] = Sign::asciiKeyStrtolowerSign($data,$this->qf888pay_key,'sign');

        try {
            $response = $this->guzzle->post($this->qf888pay_url,$data,$this->zr_header);
        }catch (\Exception $e){
            $response = ['Request timed out'];
        }

        if (isset($response['code']) && $response['code'] == 200){

            return ['code' => 200 , 'msg' => '' , 'data' => 'suc'];
        }

        Common::log($withdrawlog['ordersn'],$response,$type);
        $msg = $this->sendWithdrawFail($withdrawlog,$response); //通知群并返回错误信息

        return ['code' => 201 , 'msg' => $msg , 'data' => []];
    }




    /**
     * 得到回调地址
     * @param string $notify_url 回调地址名称
     * @return string
     */
    private function getNotifyUrl(string $notify_url):string{
        return config('host.apiDomain').'/Withdrawlog/'.$notify_url;
    }

    private function sendWithdrawFail($withdrawlog,$response){
        $msg = '';
//        if(Common::getConfigValue('is_tg_send') == 1) {
//            //发送提现失败消息to Tg
//            $msg = \service\TelegramService::withdrawFail($withdrawlog, $response);
//        }
        return $msg;
    }


    /**
     * 随机生产几位字母
     * @param $length
     * @return string
     */
    private function generateRandomString($uid = '',$length = 6){

        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString.$uid;

    }
}
