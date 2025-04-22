<?php
/**
 * 游戏
 */
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\slots;


use App\Common\Sign;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use function Hyperf\Config\config;
use Hyperf\Di\Annotation\Inject;
use App\Common\Guzzle;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use function Hyperf\Coroutine\co;
use  App\Common\Curl;
#[Controller(prefix:"weslots")]
class WeslotsController extends AbstractController{


    #[Inject]
    protected Common $common;
    #[Inject]
    protected Guzzle $guzzle;

    /**
     * 验证玩家
     * @return void
     */
    #[RequestMapping(path: 'validate')]
    public function validate(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'token' => 'required',
                'operatorID' => 'required',
                'appSecret' => 'required',
            ],
            [
                'token.required' => 'token is required',
                'operatorID.required' => 'operatorID is required',
                'appSecret.required' => 'appSecret is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("WE验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }
        if($param['operatorID'] != config('weslots.operatorID') || $param['appSecret'] != config('weslots.appSecret')){
            $this->logger->error("WE运营商信息错误--我方operatorID:".config('weslots.operatorID')."--三方传入我方operatorID:".$param['operatorID']."--我方appSecret:".config('weslots.appSecret')."--三方传入我方appSecret:".$param['appSecret']);
            return $this->errorData(2);
        }

        $uid = $this->common->getUserUid($param['token']);
        if(!$uid){
            $this->logger->error("WE验证账号接口token错误--token:".$param['token']);
            return $this->errorData(3);
        }
        $data = [
            'playerID' => (string)$uid,
            'nickname' => (string)$uid,
            'currency' => config('weslots.currency'),
            'time' => time(),
        ];
        return $this->response->json($data);
    }

    /**
     * 获取玩家余额
     * @return void
     */
    #[RequestMapping(path: 'balance')]
    public function balance(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'token' => 'required',
                'operatorID' => 'required',
                'appSecret' => 'required',
                'playerID' => 'required',
            ],
            [
                'token' => 'token is required',
                'operatorID.required' => 'operatorID is required',
                'appSecret.required' => 'appSecret is required',
                'playerID.required' => 'playerID is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("WE验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }

        //检查供应商是不是自己平台
        if($param['operatorID'] != config('weslots.operatorID') || $param['appSecret'] != config('weslots.appSecret')){
            $this->logger->error("WE运营商信息错误--我方operatorID:".config('weslots.operatorID')."--三方传入我方operatorID:".$param['operatorID']."--我方appSecret:".config('weslots.appSecret')."--三方传入我方appSecret:".$param['appSecret']);
            return $this->errorData(2);
        }

        //效验UID与三方的UID是否一致
        $uid = $this->common->getUserUid($param['token']);
        if(!$uid){
            $this->logger->error("WE验证账号接口token错误--token:".$param['token']);
            return $this->errorData(3);
        }

        if($uid != $param['playerID']){
            $this->logger->error("WE验证账号接口UID错误--token获取的UID:".$uid."--三方传入的playerID:".$param['playerID']);
            return $this->errorData(3);
        }

        $res = $this->common->setUserMoney($uid);
        if($res['code'] != 200){
            $this->logger->error("WE验证账号接口token错误--token:".$param['token']);
            return $this->errorData(3);
        };

        $data = [
            'balance' => (int)$res['data'],
            'currency' => config('weslots.currency'),
            'time' => time(),
        ];
        return $this->response->json($data);
    }




    /**
     * 余额扣款(投注、免费投注、转入游戏、小费)
     * @return false|string
     */
    #[RequestMapping(path: 'debit')]
    public function debit(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'token' => 'required',
                'operatorID' => 'required',
                'appSecret' => 'required',
                'playerID' => 'required',
                'gameID' => 'required',
                'betID' => 'required',
                'gameRoundID' => 'required',
                'parentBetID' => 'required',
                'betType' => 'required',
                'amount' => 'required',
                'currency' => 'required',
                'type' => 'required',
            ],
            [
                'token.required' => 'token is required',
                'operatorID.required' => 'operatorID is required',
                'appSecret.required' => 'appSecret is required',
                'playerID.required' => 'playerID is required',
                'gameID.required' => 'gameID is required',
                'betID.required' => 'betID is required',
                'gameRoundID.required' => 'gameRoundID is required',
                'parentBetID.required' => 'parentBetID is required',
                'betType.required' => 'betType is required',
                'amount.required' => 'amount is required',
                'currency.required' => 'currency is required',
                'type.required' => 'type is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("WE验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }

        //检查供应商是不是自己平台
        if($param['operatorID'] != config('weslots.operatorID') || $param['appSecret'] != config('weslots.appSecret')){
            $this->logger->error("WE运营商信息错误--我方operatorID:".config('weslots.operatorID')."--三方传入我方operatorID:".$param['operatorID']."--我方appSecret:".config('weslots.appSecret')."--三方传入我方appSecret:".$param['appSecret']);
            return $this->errorData(2);
        }


        //效验UID与三方的UID是否一致
        $uid = $this->common->getUserUid($param['token']);
        if(!$uid){
            $this->logger->error("WE验证账号接口token错误--token:".$param['token']);
            return $this->errorData(3);
        }

        if($uid != $param['playerID']){
            $this->logger->error("WE验证账号接口UID错误--token获取的UID:".$uid."--三方传入的playerID:".$param['playerID']);
            return $this->errorData(3);
        }


        $userinfo = $this->common->getUserInfo($uid);
        if(!$userinfo){
            $this->logger->error("WE玩家UID不存在--UID:".$uid);
            return $this->errorData(3);
        }

        $res = $this->common->getUserRunGameTage($uid,'we');
        if(!$res)return $this->errorData(5);

        $money = $userinfo['coin'] + $userinfo['bonus'];
        if($money < $param['amount']){
            $this->logger->error('WE玩家-UID:'.$uid.'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$param['amount']);
            return $this->errorData(6);
        }



        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($param['betID']);
        if($slots_log){
            $this->logger->error('WE该笔订单已经通知过-roundId:'.$param['betID']);
            return $this->errorData(4);
        }


        $slots_game = Db::table('slots_game')->select('id','englishname')->where(['terrace_id' => 10,'slotsgameid' => $param['gameID']])->first();
        $slotsData = [
            'betId' => $param['betID'],
            'parentBetId' => $param['parentBetID'],
            'uid' => $uid,
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['gameID'],
            'englishname' => $slots_game['englishname'],
            'game_id' => $slots_game['id'],
            'terrace_name' => 'we',
            'transaction_id' => 0,
            'betTime' => time(),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
            'is_settlement' => 0,
        ];
        $res = $this->common->slotsLog($slotsData,$userinfo['coin'],$userinfo['bonus'],$param['amount'],'0',2);
        if($res['code'] != 200)return $this->errorData(5);
        $data = [
            'balance' => (int)$res['data'],
            'currency' =>config('weslots.currency'),
            'time' => time(),
            'refID' => $param['betID'],
        ];
        return $this->response->json($data);

    }


    /**
     * 增加余额
     * @return void
     */
    #[RequestMapping(path: 'credit')]
    public function  credit(){

        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'data' => 'required',
            ],
            [
                'data.required' => 'data is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("WE验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }
        $WeData = json_decode($param['data'],true);
        $uid = 0;
        foreach ($WeData as $value){
            $validator2 = $this->validatorFactory->make(
                $value,
                [
                    'operatorID' => 'required',
                    'appSecret' => 'required',
                    'playerID' => 'required',
                    'gameID' => 'required',
                    'betID' => 'required',
                    'amount' => 'required',
                ],
                [
                    'operatorID.required' => 'operatorID is required',
                    'appSecret.required' => 'appSecret is required',
                    'playerID.required' => 'playerID is required',
                    'gameID.required' => 'gameID is required',
                    'betID.required' => 'betID is required',
                    'amount.required' => 'amount is required',
                ]
            );
            if ($validator2->fails()) {
                $errorMessage = $validator2->errors()->first();
                $this->logger->error("WE验证账号接口数据验证失败===>".$errorMessage);
                return $this->errorData();
            }

            //检查供应商是不是自己平台
            if($value['operatorID'] != config('weslots.operatorID') || $value['appSecret'] != config('weslots.appSecret')){
                $this->logger->error("WE运营商信息错误--我方operatorID:".config('weslots.operatorID')."--三方传入我方operatorID:".$value['operatorID']."--我方appSecret:".config('weslots.appSecret')."--三方传入我方appSecret:".$value['appSecret']);
                return $this->errorData(2);
            }
            $userinfo = $this->common->getUserInfo($value['playerID']);
            if(!$userinfo){
                $this->logger->error('we数据库-userinfo不存在用户-UID:'.$value['playerID']);
                return $this->errorData(3);
            }
            $uid = $value['playerID'];
            $slots_log = $this->common->SlotsLogView($value['betID']);
            if(!$slots_log){
                $this->logger->error('WE该笔订单不存在-betID:'.$value['betID']);
                return $this->errorData(7);
            }
            if(!in_array($slots_log['is_settlement'],[0,4])){
                $this->logger->error('WE该笔订单已经通知过-betID:'.$value['betID']);
                return $this->errorData(4);
            }
            $winAmount = max($value['amount'], '0');

            $this->common->resultDealWith($slots_log,$userinfo,$winAmount,2);

        }
        $res = $this->common->setUserMoney($uid);
        if($res['code'] != 200){
            $this->logger->error('we数据库-userinfo不存在用户-UID:'.$uid);
            return $this->errorData(3);
        };


        $data = [
            'balance' => (int)$res['data'],
            'currency' =>config('weslots.currency'),
            'time' => time(),
            'refID' => rand(7777,9999).time(),
        ];
        return $this->response->json($data);
    }


    /**
     * 取消注单
     * @return void
     */
    #[RequestMapping(path: 'rollback')]
    public function rollback(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'operatorID' => 'required',
                'appSecret' => 'required',
                'playerID' => 'required',
                'betID' => 'required',
                'amount' => 'required',
                'currency' => 'required',
            ],
            [
                'operatorID.required' => 'operatorID is required',
                'appSecret.required' => 'appSecret is required',
                'playerID.required' => 'playerID is required',
                'betID.required' => 'betID is required',
                'amount.required' => 'amount is required',
                'currency.required' => 'currency is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("WE验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }

        //检查供应商是不是自己平台
        if($param['operatorID'] != config('weslots.operatorID') || $param['appSecret'] != config('weslots.appSecret')){
            $this->logger->error("WE运营商信息错误--我方operatorID:".config('weslots.operatorID')."--三方传入我方operatorID:".$param['operatorID']."--我方appSecret:".config('weslots.appSecret')."--三方传入我方appSecret:".$param['appSecret']);
            return $this->errorData(2);
        }

        $userinfo = $this->common->getUserInfo($param['playerID']);
        if(!$userinfo){
            $this->logger->error("WE玩家UID不存在--UID:".$param['playerID']);
            return $this->errorData(3);
        }
        $slots_log = $this->common->SlotsLogView($param['betID']);
        if(!$slots_log || $slots_log['is_settlement'] == 2){
            $this->logger->error('WE该笔订单已经通知过-roundId:'.$param['betID']);
            return $this->errorData(4);
        }
        $this->common->setRefundSlotsLog($slots_log,$param['amount']);
        $res = $this->common->setUserMoney($param['playerID']);
        if($res['code'] != 200){
            $this->logger->error('we数据库-userinfo不存在用户-UID:'.$param['playerID']);
            return $this->errorData(3);
        }


        $data = [
            'balance' => (int)$res['data'],
            'currency' =>config('weslots.currency'),
            'time' => time(),
            'refID' => rand(7777,9999).time(),
        ];
        return $this->response->json($data);

    }


    public function getGameUrl(int $uid,string $gameId):array{
        $url = config('weslots.api_url').'/player/launch';
        $body = [
            'operatorID' => config('weslots.operatorID'),
            'requestTime' => time(),
        ];
        $data = $this->post($url,$body);
        if(!isset($data['url']) || !$data['url']){
            $this->logger->error('WeGetGameUrl获取失败:'.json_encode($data).'--UID:'.$uid);
            return ['code' => 201,'msg' => '获取失败'];
        }
        $token = $this->common->getUserToken($uid);
        $gameUrl = $data['url']."?token=$token&operator=".config('weslots.operatorID')."&lang=".config('weslots.language')."&tableid=$gameId";

        return ['code' => 200,'msg' => '获取成功','data' => $gameUrl];
    }

    /**
     * post请求
     * @param $url string 请求地址
     * @param $body array 请求体
     * @return null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function post(string $url,array $body){
        $herder = [
            'Content-Type: application/x-www-form-urlencoded',
            'signature: '.$this->sign($body),
        ];
        $response = Curl::post($url,$body,$herder,[],2);
        if($response){
            return json_decode($response,true);
        }else{
            return [];
        }
//        return $this->guzzle->post($url,$body,$herder);
    }

    /**
     * 签名
     * @param $body array 请求体
     * @return string
     */
    private function sign(array $body):string{
        ksort($body);
        return md5(config('weslots.appSecret').implode('',$body));
    }

    /**
     * 错误返回
     * @param int $type
     * @return
     */
    private function errorData(int $type = 1){
        if($type == 1){ //非法请求缺少必填参数等状况
            $code = 400;
            $data = ['error' => 'Bad Request'];
        }elseif ($type == 2){ //HTTP状态码:401, 金钥凭证错误等状况
            $code = 401;
            $data = ['error' => 'Credential error'];
        }elseif ($type == 3){// 玩家令牌无效、玩家令牌过期等状况
            $code = 404;
            $data = ['error' => 'Token is invalid'];
        }elseif ($type == 4){// 重复交易等状况
            $code = 409;
            $data = ['error' => 'Repeat transactions'];
        }elseif ($type == 5){// 内部存取错误等状况
            $code = 500;
            $data = ['errorcode' => 11013,'error' => 'User account is locked'];
        }elseif ($type == 6){// 玩家余额不足
            $code = 402;
            $data = ['error' => 'Insufficient balance'];
        }elseif ($type == 7){// 无法派彩 / 无法取消交易
            $code = 410;
            $data = ['error' => 'Unable to payout'];
        }else{
            $code = 500;
            $data = ['errorcode' => 11013,'error' => 'User account is locked'];
        }
        return $this->response->json($data)->withStatus($code);
    }

}







