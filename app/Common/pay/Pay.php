<?php
declare(strict_types=1);
namespace App\Common\pay;

use Hyperf\Di\Annotation\Inject;
use App\Common\Common;
use App\Common\Guzzle;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;
use function Hyperf\Support\env;
use  App\Common\Curl;
class Pay{


    #[Inject]
    protected LoggerInterface $logger; //存储日志

    #[Inject]
    protected Common $Common;
    #[Inject]
    protected Guzzle $guzzle;



    //qf888_pay
    private string $qf888pay_url = "https://www.qf888.vip/api/pay";//下单地址
    private string $qf888pay_mchid = "1637576";//商户编码
//    private string $qf888pay_mchid = "1693915";//正式
    private string $qf888pay_key = "647188ab09b5875d452b46e6310abe76";//商户密钥的值
//    private string $qf888pay_key = "d94d1b3f92b5ddcdb91f74469522d761";//正式
    private string $qf888pay_backUrl = "qf888payNotify";//回调地址



    //no_pay
    private string $nopay_url  = "https://icw891o.nopay.app/order/depositOrderCoinCreate";                 //下单地址
    private string $nopay_merchantNo = "CBQ3DK9CN67LEKPX"; //正式
    private string $nopay_notifyUrl  = "nopayNotify";  //回调地址
    private string $nopay_Key        = "CBKX2EpjQikiiqK6Bd9zIhkXoOVK7aOB";//正式




    private array $header = ["Content-Type" => "application/x-www-form-urlencoded"];


    private array $zr_header = ["Content-Type" => "application/json"];

    /**
     * @return void 统一支付渠道
     * @param $paytype 支付渠道
     * @param $createData 创建订单信息
     */
    public function pay($paytype,$createData,$baseUserInfo){

        return $this->$paytype($createData,$baseUserInfo);
    }




    /**
     * uper_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function qf888_pay($createinfo, $baseUserInfo) {
        $data = [
            "mchId" => $this->qf888pay_mchid,
            "mchOrderId" => $createinfo['ordersn'],
            "amount" => bcdiv((string)$createinfo['pay_price'],'100',0),
            "payMethod" => 'VNBANKQR',  //VNBANKQR2  //越南ZALO pay VNZALO 已开启 越南MOMO pay VNMOMO 已开启 越南ViettelPay VNVTPAY 已开启
            "notifyUrl" => $this->getNotifyUrl($this->qf888pay_backUrl),
        ];
        $data['sign'] = Sign::asciiKeyStrtolowerSign($data,$this->qf888pay_key,'sign');
        $response = $this->guzzle->post($this->qf888pay_url,$data,$this->zr_header);
        if (isset($response['code']) && $response['code'] == '200') {
            $paydata = $this->getPayData( 'suc',$response['data']['payUrl']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }
        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];
    }



    /**
     * no_pay
     *
     * @param $createinfo 创建订单信息
     * @param $baseUserInfo 基本用户信息
     *
     * @return array
     */
    public function no_pay($createinfo, $baseUserInfo) {
        $data   = [
            "merchantOrderNo" => $createinfo['ordersn'],
            "merchantMemberNo" => $baseUserInfo['uid'],
            "amount"  =>  bcdiv((string)$createinfo['protocol_money'],'100',2),
            "language"  =>  'en',
            "coin" => 'USDT',
            "rateType" => 1,
            "protocol" => $createinfo['protocol_name'],
            "notifyUrl"  =>$this->getNotifyUrl($this->nopay_notifyUrl),
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

        // $response = $this->guzzle->post('https://cuisine.teenpatticlub.shop/api/Text/testCurlUrl',['data' => $data,'url' => $this->nopay_url,'header' => $herder],$this->header);
        $response = Curl::post($this->nopay_url,$data,$herder);
        $response = json_decode($response,true);

        if (isset($response['code'])&&$response['code'] == 0) {
            $paydata = $this->getPayData($response['data']['orderNo'],$response['data']['url']);
            return ['code' => 200 , 'msg' => '' , 'data' => $paydata];
        }

        Common::log($createinfo['ordersn'],$response,1);
        return ['code' => 201 , 'msg' => '' , 'data' => []];

    }



    /**
     * 得到回调地址
     * @param string $notify_url 回调地址名称
     * @return string
     */
    private function getNotifyUrl(string $notify_url):string{
        return config('host.apiDomain').'/Order/'.$notify_url;
    }


    /**
     * 随机生产几位字母
     * @param $length
     * @return string
     */
    private function generateRandomString($uid= '',$length = 6){

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
        $shuff = '@gmail.com';
        $RandomString = self::generateRandomString(rand(1000,9999),6);
        return $RandomString.$shuff;
    }

    /**
     * 得到支付数据
     * @param $tradeodersn 三方订单号
     * @param $payurl 三方h5支付链接
     * @param $appPayUrl 三方APP支付链接
     * @return void
     */
    private function getPayData($tradeodersn,$payurl,$appPayUrl = ''){
        return ['tradeodersn' => $tradeodersn,'payurl'=>$payurl,'appPayUrl' => $appPayUrl];
    }

}