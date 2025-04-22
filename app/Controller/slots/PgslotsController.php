<?php
/**
 * 游戏
 */
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\slots;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use function Hyperf\Config\config;
use Hyperf\Di\Annotation\Inject;
use App\Common\Guzzle;
use App\Common\Common as ggCommon;
use  App\Common\Curl;
#[Controller(prefix:"pgslots")]
class PgslotsController extends AbstractController{


    #[Inject]
    protected Common $common;
    #[Inject]
    protected Guzzle $guzzle;


    private array $herder1 = ["Content-Type" => "application/x-www-form-urlencoded"];
    private array $herder2 = ["Content-Type" => "application/x-www-form-urlencoded;charset='utf-8'"];

    //单一钱包
    /**
     * 获取用户钱包
     * @return void
     */
    #[RequestMapping(path: 'Cash/Get')]
    public function Get(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'operator_token' => 'required',
                'secret_key' => 'required',
                'operator_player_session' => 'required',
                'player_name' => 'required',
            ],
            [
                'reqId.required' => 'reqId is required',
                'token.required' => 'token is required',
                'operator_player_session.required' => 'operator_player_session is required',
                'player_name.required' => 'player_name is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PG-GET验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }
        $operator_token = $param['operator_token'];
        $secret_key     = $param['secret_key'];
        $session        = $param['operator_player_session'];
        $player_name = $param['player_name'];
        //检查供应商是不是自己平台
        if($operator_token != config('pgslots.OperatorToken') || $secret_key != config('pgslots.SecretKey')){
            $this->logger->error("PG检查供应商是不是自己平台");
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }
        //效验UID与三方的UID是否一致
        $uid = $this->getUserUid($session);
        if($uid != $player_name) {
            $this->logger->error("PG传入玩家与实际玩家不匹配:-传入uid:".$player_name."-实际玩家uid:".$uid);
            return $this->response->json(['error' =>['code' => '3004','message' => 'Invalid request'] ,'data' => null]);
        }

        $res = $this->common->setUserMoney($uid);
        if($res['code'] != 200){
            $this->logger->error("玩家不存在-不存在玩家uid:".$uid);
            return $this->response->json(['error' =>['code' => '3004','message' => 'Invalid request'] ,'data' => null]);
        };

        $data = [
            'currency_code' => config('pgslots.currency'),
            'balance_amount' => bcdiv((string)$res['data'],'100',2),
            'updated_time' => time().'000',
        ];
        return $this->response->json(['error' =>null,'data' =>$data]);
    }

    /**
     * @return void
     */
    #[RequestMapping(path: 'Cash/TransferInOut')]
    public function TransferInOut(){

        $param = $this->request->all();

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operator_token' => 'required',
                'secret_key' => 'required',
                'player_name' => 'required',
                'currency_code' => 'required',
                'bet_amount' => 'required',
                'transaction_id' => 'required',
                'bet_id' => 'required',
                'parent_bet_id' => 'required',
                'game_id' => 'required',
                'win_amount' => 'required',
                'transfer_amount' => 'required',
                'create_time' => 'required',
                'updated_time' => 'required',
            ],
            [
                'reqId.required' => 'reqId is required',
                'token.required' => 'token is required',
                'player_name.required' => 'player_name is required',
                'currency_code.required' => 'currency_code is required',
                'bet_amount.required' => 'bet_amount is required',
                'transaction_id.required' => 'transaction_id is required',
                'bet_id.required' => 'bet_id is required',
                'parent_bet_id.required' => 'parent_bet_id is required',
                'game_id.required' => 'game_id is required',
                'win_amount.required' => 'win_amount is required',
                'transfer_amount.required' => 'transfer_amount is required',
                'create_time.required' => 'create_time is required',
                'updated_time.required' => 'updated_time is required',
            ]
        );


        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PG-GET验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }

        $res = $this->common->getUserRunGameTage($param['player_name'],'pg');
        if(!$res)return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);

        $param['is_validate_bet'] = $param['is_validate_bet'] ?? 'False';
        $param['is_adjustment'] = $param['is_adjustment'] ?? 'False';

        //检查供应商是不是自己平台
        if($param['operator_token'] != config('pgslots.OperatorToken') || $param['secret_key'] != config('pgslots.SecretKey')){
            $this->logger->error('商家信息验证失败-operator_token:'.$param['operator_token'].'-secret_key:'.$param['secret_key']);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }

        //效验UID与三方的UID是否一致
        $uid = $this->getUserUid($param['operator_player_session']);
        if($param['is_validate_bet'] != 'True' && $param['is_adjustment'] != 'True' && $uid != $param['player_name']){
            $this->logger->error('效验UID等数据不对-is_validate_bet:'.$param['is_validate_bet'].'-is_adjustment:'.$param['is_adjustment'].'-实际UID:'.$uid.'三方传入UID:'.$param['player_name']);
            return $this->response->json(['error' =>['code' => '3004','message' => 'Invalid request'] ,'data' => null]);
        }


        //效验货币是否正确
        if(config('pgslots.currency') != $param['currency_code']){
            $this->logger->error('货币效验失败-currency_code:'.$param['currency_code']);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }

        if(bcsub($param['win_amount'],$param['bet_amount'],2) != $param['transfer_amount']){
            $this->logger->error('效验订单金额失败-win_amount:'.$param['win_amount'].'-bet_amount:'.$param['bet_amount'].'-transfer_amount:'.$param['transfer_amount']);
            return $this->response->json(['error' =>['code' => '3073','message' => 'Invalid request'] ,'data' => null]);
        }

        $userinfo = $this->common->getUserInfo($param['player_name']);
        if(!$userinfo){
            $this->logger->error('数据库userinfo不存在用户-UID:'.$param['player_name']);
            return $this->response->json(['error' =>['code' => '3004','message' => 'Invalid request'] ,'data' => null]);
        }

        //效验下注金额是否足够
        $money = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0);
        if($money  < bcmul((string)$param['bet_amount'],'100',0)){
            $this->logger->error('本次下注余额不足-实际余额:'.bcdiv($money,'100',2).'-用户下注金额:'.$param['bet_amount']);
            return $this->response->json(['error' =>['code' => '3202','message' => 'Invalid request'] ,'data' => null]);
        }


        $slots_log = $this->common->SlotsLogView($param['bet_id']);
        //效验订单是否已经使用过
//        $slots_log = Db::table('slots_log_'.date('Ymd'))->select('betId')->where('transaction_id',$param['transaction_id'])->first();
        if($slots_log){
            $this->logger->error('该笔订单已经通知过-transaction_id:'.$param['transaction_id']);
            $data = [
                'currency_code' => config('pgslots.currency'),
                'balance_amount' => bcdiv($money,'100',2),
                'updated_time' => $param['updated_time'],
            ];
            return $this->response->json(['error' =>null ,'data' => $data]);
//            return $this->response->json(['error' =>null ,'data' => ['currency_code' => config('pgslots.currency'),'balance_amount' => bcdiv($money,'100',2),'updated_time' => $param['updated_time']]]);
        }


        $slots_game = Db::table('slots_game')->select('id','englishname')->where(['terrace_id' => 1,'slotsgameid' => $param['game_id']])->first();


        $slotsData = [
            'betId' => $param['bet_id'],
            'parentBetId' => $param['parent_bet_id'],
            'uid' => $param['player_name'],
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['game_id'],
            'englishname' => $slots_game['englishname'],
            'game_id' => $slots_game['id'],
            'terrace_name' => 'pg',
            'transaction_id' => $param['transaction_id'],
            'betTime' => mb_substr($param['create_time'],0,-3),
            'channel' => $userinfo['channel'],
            'is_settlement' => 1,
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
        ];

        $res = $this->common->slotsLog($slotsData,$userinfo['coin'],$userinfo['bonus'],bcmul((string)$param['bet_amount'],'100',0),bcmul((string)$param['win_amount'],'100',0));
        if($res['code'] != 200)return $this->response->json(['error' =>['code' => '1200','message' => 'Invalid request'] ,'data' => null]);

        $data = [
            'currency_code' => config('pgslots.currency'),
            'balance_amount' => bcdiv((string)$res['data'],'100',2),
            'updated_time' => $param['updated_time'],
        ];

        return  $this->response->json(['error' =>null,'data' =>$data]);
    }



    /**
     * 余额调整、锦标赛派彩
     * @return void
     */
    #[RequestMapping(path: 'Cash/Adjustment')]
    public function Adjustment(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'operator_token' => 'required',
                'secret_key' => 'required',
                'player_name' => 'required',
                'transfer_amount' => 'required',
                'adjustment_transaction_id' => 'required',
                'currency_code' => 'required',
                'adjustment_time' => 'required',

            ],
            [
                'operator_token.required' => 'operator_token is required',
                'secret_key.required' => 'secret_key is required',
                'player_name.required' => 'player_name is required',
                'transfer_amount.required' => 'transfer_amount is required',
                'adjustment_transaction_id.required' => 'adjustment_transaction_id is required',
                'currency_code.required' => 'currency_code is required',
                'adjustment_time.required' => 'adjustment_time is required',
            ]
        );


        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PG-Adjust验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }


        //检查供应商是不是自己平台
        if($param['operator_token'] != config('pgslots.OperatorToken') || $param['secret_key'] != config('pgslots.SecretKey')){
            $this->logger->error('商家信息验证失败-operator_token:'.$param['operator_token'].'-secret_key:'.$param['secret_key']);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }

        //效验货币是否正确
        if(config('pgslots.currency') != $param['currency_code']){
            $this->logger->error('货币效验失败-currency_code:'.$param['currency_code']);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }


        $uid = $param['player_name'];
        $res = $this->common->setUserMoney($uid);
        if(!ctype_digit((string)$uid) || $res['code'] != 200){ //判断一下用户ID是否全是数字
            $this->logger->error('PG玩家-UID:'.$uid.'不存在');
            return $this->response->json(['error' =>['code' => '3004','message' => 'PlayerNotFound'] ,'data' => null]);
        }
        $money = $res['data'];
        if($param['transfer_amount'] < 0 && $money < bcsub( '0',bcmul((string)$param['transfer_amount'],'100',0),0)){
            $this->logger->error('本次下注余额不足-实际余额:'.$money.'-用户下注金额:'.$param['transfer_amount']);
            return $this->response->json(['error' =>['code' => '3202','message' => 'Invalid request'] ,'data' => null]);
        }


        $slots_bonus = Db::table('slots_bonus')->where(['terrace_name' => 'pg','transactionId' => $param['adjustment_transaction_id']])->first();
        if($slots_bonus){
            $data = [
                'adjust_amount' => (float)$param['transfer_amount'],
                'balance_before' =>(float)bcdiv((string)$money,'100',2),
                'balance_after' =>(float)bcdiv((string)$money,'100',2),
                'updated_time' => $param['adjustment_time'],
            ];
            return $this->response->json(['error' =>null,'data' =>$data]);
        }

        $amount =  bcmul((string)$param['transfer_amount'],'100',0);


        $slots_bonus_data = [
            'uid' =>$uid,
            'terrace_name' => 'pg',
            'roundId' => 0,
            'transactionId' => $param['adjustment_transaction_id'],
            'amount' => $amount,
            'type' => 3,
            'bonustime' => time(),
        ];

        $res = $this->common->slotsBonus($slots_bonus_data,(int)$amount);
        if(!$res)return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        $data = [
            'adjust_amount' => (float)$param['transfer_amount'],
            'balance_before' =>(float)bcdiv((string)$money,'100',2),
            'balance_after' =>(float)bcdiv(bcadd((string)$money,$amount,0),'100',2),
            'updated_time' => $param['adjustment_time'],
        ];
        return $this->response->json(['error' =>null,'data' =>$data]);

    }


    /**
     * 令牌验证 以及充值
     *  接口文档对应位置：5.2.4.1 充值
     *
     * @url api/pgslots.Verify/VerifySession
     */
    #[RequestMapping(path: 'VerifySession')]
    public function VerifySession() {

        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'operator_token' => 'required',
                'secret_key' => 'required',
                'operator_player_session' => 'required',
            ],
            [
                'reqId.required' => 'reqId is required',
                'token.required' => 'token is required',
                'operator_player_session.required' => 'operator_player_session is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PG-GET验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }

        $operator_token = $param['operator_token'];
        $secret_key     = $param['secret_key'];
        $session        = $param['operator_player_session'];

        //检查供应商是不是自己平台
        if($operator_token != config('pgslots.OperatorToken') || $secret_key != config('pgslots.SecretKey')){
            $this->logger->error('PG商家信息验证失败-operator_token:'.$param['operator_token'].'-secret_key:'.$param['secret_key']);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }

        //效验是否有UID
        $uid = $this->getUserUid($session);
        if(!$uid) {
            $this->logger->error('PG用户不存在-operator_player_session:'.$param['operator_player_session']);
            return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);
        }

        $pgslots = [
            'player_name' => $uid,
            'nickname' => $uid,
            'currency' => config('pgslots.currency')
        ];

        return $this->response->json(['error' =>null ,'data' => $pgslots]);

    }


    //转账钱包

    public function GetGame(){

        $url = config('pgslots.api_url').'/Game/v2/Get?trace_id='.$this->guid();;
        $body = [
            'operator_token' => config('pgslots.OperatorToken'),
            'secret_key' => config('pgslots.SecretKey'),
            'currency' => config('pgslots.currency'),
            'language' => 'en-us',
            'status' => 1,
        ];
        // post请求
        return $this->guzzle->post($url,$body,$this->herder1);
    }

    /**
     * @return void 充值
     * @param  $ordersn 订单号
     * @param  $amount 充值金额
     *  @param  $player_name 玩家的uid
     */
    public function Cash($ordersn,$amount,$player_name){
        $amount = bcdiv((string)$amount,'100',2);
        $url  = config('pgslots.api_url').'/Cash/v3/TransferIn?trace_id='.$this->guid();
        $body = [
            'operator_token' => config('pgslots.OperatorToken'),
            'secret_key' => config('pgslots.SecretKey'),
            'currency' => config('pgslots.currency'),
            'player_name'        => $player_name,
            'amount'             => $amount,
            'transfer_reference' => $ordersn,
        ];

        // post请求
        return $this->guzzle->post($url,$body,$this->herder1);
    }



    /**
     * 转出所有余额
     *
     * @param  $ordersn 订单号
     *  @param  $player_name 玩家的uid
     * @return array
     */
    public  function transferAllOut($ordersn,$player_name) {

        $url  = config('pgslots.api_url') . '/Cash/v3/TransferAllOut?trace_id=' .$this->guid();
        $body = [
            'operator_token' => config('pgslots.OperatorToken'),
            'secret_key' => config('pgslots.SecretKey'),
            'currency' => config('pgslots.currency'),
            'player_name'        => (string) $player_name,
            'transfer_reference' => $ordersn,
        ];



        // post请求
        return $this->guzzle->post($url,$body,$this->herder1);


    }

    /**
     * 创建玩家
     * @param $nickname  玩家昵称
     * @param $uid  玩家uid
     * @return bool|string
     */
    public  function playerCreated($uid,$trace_id){

        $url  = config('pgslots.api_url').'/v3/Player/Create?trace_id='.$trace_id;
        $body = [
            'operator_token' => config('pgslots.OperatorToken'),
            'secret_key' => config('pgslots.SecretKey'),
            'currency' => config('pgslots.currency'),
            'player_name'  => (string) $uid,
            'nickname' => (string)$uid,
        ];

        // post请求
        return $this->guzzle->post($url,$body,$this->herder1);


    }

    /**
     * 获取玩家第三方游戏状态
     * @param $player_name  玩家uid
     * @return bool|string
     */
    public  function getPlayersOnlineStatus($player_name){

        $url  = config('pgslots.api_url').'/Player/v3/GetPlayersOnlineStatus?trace_id='.$this->guid();
        $body = [
            'operator_token' => config('pgslots.OperatorToken'),
            'secret_key' => config('pgslots.SecretKey'),
            'player_names' => $player_name,
        ];

        // 签名头数据
        return $this->guzzle->post($url,$body,$this->herder1);

    }


    /**
     * 获取玩家第三方游戏余额
     * @param $player_name  玩家uid
     * @return bool|string
     */
    public  function getPlayerWallet($player_name){

        $url  = config('pgslots.api_url').'/Cash/v3/GetPlayerWallet?trace_id='.$this->guid();
        $body = [
            'operator_token' => config('pgslots.OperatorToken'),
            'secret_key' => config('pgslots.SecretKey'),
            'player_name' => $player_name,
        ];

        return $this->guzzle->post($url,$body,$this->herder1);
    }

    /**
     * @param $count 获取条数 1500 - 5000
     * @param $row_version   row_version 第三方的row_version 首次填写1
     * @return bool|string
     */
    public  function getHistory($count,$row_version) {

        $url  = config('pgslots.history_url').'/Bet/v4/GetHistory?trace_id='.$this->guid();

        $body = [
            'operator_token' => config('pgslots.OperatorToken'),
            'secret_key' => config('pgslots.SecretKey'),
            'count' =>(int)$count,
            'bet_type'  => 1,
            'row_version' => (int)$row_version,
        ];

        return $this->guzzle->post($url,$body,$this->herder1);

    }



    /**
     * 获取游戏启动url
     * @param $gameid  游戏的id
     * @param $TraceID  用户的TraceID
     * @return array
     */
    public function getGameUrl($gameid,$TraceID){
        $domain =  str_replace('/external','',config('pgslots.api_url'));
        $url  = $domain.'/external-game-launcher/api/v1/GetLaunchURLHTML?trace_id='.$this->guid();
        $body = [
            'operator_token' => config('pgslots.OperatorToken'),
            'path' =>"/$gameid/index.html",
            'extra_args' =>"btt=1&ops=$TraceID&l=".config('pgslots.language')."&f=".urlencode(config('host.gameurl')),
            'url_type'  => 'game-entry',
            'client_ip' => \App\Common\Common::getIp($this->request->getServerParams()),
        ];
        $herder2 = ["Content-Type: application/x-www-form-urlencoded;charset='utf-8'"];
        return Curl::post($url,$body,$herder2,[],2);
        return $this->guzzle->post($url,$body,$this->herder2,2);
    }



    /**
     * 获取用户的TraceId
     * @param $uid 用户UID
     * @return void
     */
    #[RequestMapping(path: 'getUserTraceId')]
    public function getUserTraceId($uid){
        return Db::table('user_token')->where('uid',$uid)->value('token');
    }


    /**
     * 通过TraceId获取用户的Uid
     * @param $token  token
     * @return void
     */
    public function getUserUid($token){
        return Db::table('user_token')->where('token',$token)->value('uid');
    }


    public function guid() {
        mt_srand((int) microtime() * 10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid((string)rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid   = substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);

        return $uuid;
    }


}





