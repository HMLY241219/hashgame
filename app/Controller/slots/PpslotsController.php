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

#[Controller(prefix:"ppslots")]
class PpslotsController extends AbstractController{


    #[Inject]
    protected Common $common;
    #[Inject]
    protected Guzzle $guzzle;

    private array $herder = ["Content-Type" => "application/x-www-form-urlencoded"];


    /**
     * 用户切换游戏场景时请求
     * @return false|string
     */
    #[RequestMapping(path: 'authenticate')]
    public function authenticate(){
        $param = $this->request->all();

        $validator = $this->validatorFactory->make(
            $param,
            [
                'token' => 'required',
                'providerId' => 'required',
            ],
            [
                'token.required' => 'token is required',
                'providerId.required' => 'providerId is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }
        
        if($param['providerId'] != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$param['providerId'].'我方-providerId:'.config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        $user_token = Db::table('user_token')->select('uid')->where('token',$param['token'])->first();
        if(!$user_token){
            $this->logger->error('PP用户验证失败:三方-token:'.$param['token']);
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }

        $res = $this->common->setUserMoney($user_token['uid']);
        if($res['code'] != 200){
            $this->logger->error('PP用户获取失败:用户-UID:'.$user_token['uid'].'未找到用户');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }

        $data = [
            'userId' => $user_token['uid'],
            'currency' =>config('ppslots.currency'),
            'cash' => (float)bcdiv((string)$res['data'],'100',2),
            'bonus' => 0,
            'error' => 0,
            'description' => 'Success',
        ];
        return $this->response->json($data);

    }


    /**
     * 获取用户余额
     * @return false|string
     */
    #[RequestMapping(path: 'balance')]
    public function  balance(){
        $param = $this->request->all();

        $validator = $this->validatorFactory->make(
            $param,
            [
                'providerId' => 'required',
                'userId' => 'required',
            ],
            [
                'providerId.required' => 'providerId is required',
                'userId.required' => 'userId is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        $providerId = $param['providerId'];
        $userId = $param['userId'];
        //检查供应商是不是自己平台
        if($providerId != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$providerId.'我方-providerId:'.config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        $res = $this->common->setUserMoney($userId);
        if($res['code'] != 200){
            $this->logger->error('PP玩家-UID:'.$userId.'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        };

        $data = [
            'error' => 0,
            'description' => 'Success',
            'currency' =>config('ppslots.currency'),
            'cash' => (float)bcdiv((string)$res['data'],'100',2),
            'bonus' => 0
        ];
        return $this->response->json($data);
    }



    /**
     * 投付
     * @return false|string
     */
    #[RequestMapping(path: 'bet')]
    public function bet(){

        $param = $this->request->all();

        $validator = $this->validatorFactory->make(
            $param,
            [
                'userId' => 'required',
                'gameId' => 'required',
                'roundId' => 'required',
                'amount' => 'required',
                'reference' => 'required',
                'providerId' => 'required',
                'timestamp' => 'required',
            ],
            [
                'userId.required' => 'userId is required',
                'gameId.required' => 'gameId is required',
                'roundId.required' => 'roundId is required',
                'amount.required' => 'amount is required',
                'reference.required' => 'reference is required',
                'providerId.required' => 'providerId is required',
                'timestamp.required' => 'timestamp is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        $res = $this->common->getUserRunGameTage($param['userId'],'pp');
        if(!$res)return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);

        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        //检查供应商是不是自己平台
        if($param['providerId'] != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$param['providerId'].'我方-providerId:'. config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        $userinfo = $this->common->getUserInfo($param['userId']);
        if(!$userinfo){
            $this->logger->error('PP玩家-UID:'.$param['userId'].'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }

        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',2);

        if($money < $param['amount']){
            $this->logger->error('PP玩家-UID:'.$param['userId'].'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$param['amount']);
            return $this->response->json(['error' => 1,'description' => 'Insufficient balance. The error should be returned in the response on the Bet request..']);
        }


        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($param['roundId']);
        if($slots_log){
            $this->logger->error('PP该笔订单已经通知过-roundId:'.$param['roundId']);
            $data = [
                'transactionId' => $param['reference'],
                'currency' =>config('ppslots.currency'),
                'cash' => (float)$money,
                'bonus' => 0,
                'usedPromo' => 0,
                'error' => 0,
                'description' => 'Success'
            ];
            return $this->response->json($data);
        }




        $slots_game = Db::table('slots_game')->select('id','englishname')->where(['slotsgameid' => $param['gameId']])->first();

        $slotsData = [
            'betId' => $param['roundId'],
            'parentBetId' => $param['roundId'],
            'uid' => $param['userId'],
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['gameId'],
            'englishname' => $slots_game['englishname'],
            'game_id' => $slots_game['id'],
            'terrace_name' => 'pp',
            'transaction_id' => $param['reference'],
            'betTime' => mb_substr($param['timestamp'],0,-3),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
            'is_settlement' => 0,
        ];

        $res = $this->common->slotsLog($slotsData,$userinfo['coin'],$userinfo['bonus'],bcmul((string)$param['amount'],'100',0),'0',2);
        if($res['code'] != 200)return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        $data = [
            'transactionId' => $param['reference'],
            'currency' =>config('ppslots.currency'),
            'cash' => (float)bcdiv((string)$res['data'],'100',2),
            'bonus' => 0,
            'usedPromo' => 0,
            'error' => 0,
            'description' => 'Success'
        ];
        return $this->response->json($data);

    }


    /**
     * 普通结算
     * @return void
     */
    #[RequestMapping(path: 'result')]
    public function  result(){

        $param = $this->request->all();

        $validator = $this->validatorFactory->make(
            $param,
            [
                'userId' => 'required',
                'gameId' => 'required',
                'roundId' => 'required',
                'amount' => 'required',
                'reference' => 'required',
                'providerId' => 'required',
                'timestamp' => 'required',
                'roundDetails' => 'required',
            ],
            [
                'userId.required' => 'userId is required',
                'gameId.required' => 'gameId is required',
                'roundId.required' => 'roundId is required',
                'amount.required' => 'amount is required',
                'reference.required' => 'reference is required',
                'providerId.required' => 'providerId is required',
                'timestamp.required' => 'timestamp is required',
                'roundDetails.required' => 'roundDetails is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }


        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        //检查供应商是不是自己平台
        if($param['providerId'] != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$param['providerId'].'我方-providerId:'. config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }


        $userinfo = $this->common->getUserInfo($param['userId']);
        if(!$userinfo){
            $this->logger->error('PP数据库userinfo不存在用户-UID:'.$param['userId']);
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }

        $slots_log = $this->common->SlotsLogView($param['roundId']);
        if(!$slots_log || $slots_log['is_settlement']){
            $money = bcdiv(bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2);
            $data = [
                'transactionId' => $param['reference'],
                'currency' =>config('ppslots.currency'),
                'cash' => (float)$money,
                'bonus' => 0,
                'error' => 0,
                'description' => 'Success'
            ];
            return $this->response->json($data);
        }

//        $RoundDetails = $this->getRoundDetails($param['roundDetails'],['totalBet','totalWin']);

        $res = $this->common->resultDealWith($slots_log,$userinfo,bcmul((string)$param['amount'],'100',0));
        if($res['code'] !== 200){
            $this->logger->error('PP事务处理失败-UID:'.$param['userId'].'三方游戏-betId:'.$slots_log['betId']);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        $data = [
            'transactionId' => $param['reference'],
            'currency' =>config('ppslots.currency'),
            'cash' => (float)bcdiv((string)$res['data'],'100',2),
            'bonus' => 0,
            'error' => 0,
            'description' => 'Success'
        ];
        return $this->response->json($data);
    }


    /**
     * 返还
     * @return void
     */
    #[RequestMapping(path: 'refund')]
    public function refund(){


        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'userId' => 'required',
                'reference' => 'required',
                'providerId' => 'required',
                'amount' => 'required', //要退还的金额
                'roundId' => 'required',
            ],
            [
                'userId.required' => 'userId is required',
                'reference.required' => 'reference is required',
                'providerId.required' => 'providerId is required',
                'amount.required' => 'amount is required',
                'roundId.required' => 'roundId is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }



        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        //检查供应商是不是自己平台
        if($param['providerId'] != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$param['providerId'].'我方-providerId:'. config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        $data = [
            'transactionId' => $param['reference'],
            'error' => 0,
            'description' => 'Success'
        ];

        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($param['roundId']);
        if(!$slots_log || $slots_log['is_settlement'] == 2){

            return $this->response->json($data);
        }

        $this->common->setRefundSlotsLog($slots_log,bcmul((string)$param['money'],'100',0));
        return $this->response->json($data);
    }


    /**
     * 免费旋转派彩
     * @return $this->response->json
     */
    #[RequestMapping(path: 'bonusWin')]
    public function bonusWin(){

        $param = $this->request->all();

        $validator = $this->validatorFactory->make(
            $param,
            [
                'userId' => 'required',
                'reference' => 'required',
            ],
            [
                'userId.required' => 'userId is required',
                'reference.required' => 'reference is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }


        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        $uid = $param['userId'];
        $res = $this->common->setUserMoney($uid);
        if($res['code'] != 200){
            $this->logger->error('PP玩家-UID:'.$uid.'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        };
        $data = [
            'transactionId' => $param['reference'],
            'currency' =>config('ppslots.currency'),
            'cash' => (float)bcdiv((string)$res['data'],'100',2),
            'bonus' => 0,
            'error' => 0,
            'description' => 'Success'
        ];
        return $this->response->json($data);
    }


    /**
     * jackpotwin 累计奖池中奖
     * @return void
     */
    #[RequestMapping(path: 'jackpotWin')]
    public function jackpotWin(){
        $param = $this->request->all();

        $validator = $this->validatorFactory->make(
            $param,
            [
                'userId' => 'required',
                'providerId' => 'required',
                'roundId' => 'required',
                'jackpotId' => 'required',
                'jackpotDetails' => 'required',
                'amount' => 'required',
                'reference' => 'required',
                'timestamp' => 'required',
            ],
            [
                'userId.required' => 'userId is required',
                'providerId.required' => 'providerId is required',
                'roundId.required' => 'roundId is required',
                'jackpotId.required' => 'jackpotId is required',
                'jackpotDetails.required' => 'jackpotDetails is required',
                'amount.required' => 'amount is required',
                'reference.required' => 'reference is required',
                'timestamp.required' => 'timestamp is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }


        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        //检查供应商是不是自己平台
        if($param['providerId'] != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$param['providerId'].'我方-providerId:'. config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }


        $uid = $param['userId'];
        $res = $this->common->setUserMoney($uid);
        if($res['code'] != 200){
            $this->logger->error('PP玩家-UID:'.$uid.'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }
        $money = $res['data'];

        $slots_bonus = Db::table('slots_bonus')->where(['terrace_name' => 'pp','transactionId' => $param['reference']])->first();
        if($slots_bonus){
            $data = [
                'transactionId' => $param['reference'],
                'currency' =>config('ppslots.currency'),
                'cash' => (float)bcdiv((string)$money,'100',2),
                'bonus' => 0,
                'error' => 0,
                'description' => 'Success'
            ];
            return $this->response->json($data);
        }

        $amount =  bcmul((string)$param['amount'],'100',0);


        $slots_bonus_data = [
            'uid' => $param['userId'],
            'terrace_name' => 'pp',
            'roundId' => $param['roundId'],
            'transactionId' => $param['reference'],
            'amount' => $amount,
            'type' => 1,
            'bonustime' => mb_substr($param['timestamp'],0,-3),
        ];

        $res = $this->common->slotsBonus($slots_bonus_data,(int)$amount);
        if(!$res)return $this->response->json(['error' => 100,'description' => 'Internal server error. Casino Operator will return this error code if their system has internal problem and cannot process the request at the moment and Operator logic requires a retry of the request. Request will follow Reconciliation process']);
        $data = [
            'transactionId' => $param['reference'],
            'currency' =>config('ppslots.currency'),
            'cash' => (float)bcdiv(bcadd((string)$money,$amount,0),'100',2),
            'bonus' => 0,
            'error' => 0,
            'description' => 'Success'
        ];
        return $this->response->json($data);




    }

    /**
     * 锦标赛派彩
     * @return void
     */
    #[RequestMapping(path: 'promoWin')]
    public function promoWin(){
        $param = $this->request->all();
        $this->logger->error('pp-promoWin:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'userId' => 'required',
                'providerId' => 'required',
                'amount' => 'required',
                'reference' => 'required',
                'timestamp' => 'required',
            ],
            [
                'userId.required' => 'userId is required',
                'providerId.required' => 'providerId is required',
                'amount.required' => 'amount is required',
                'reference.required' => 'reference is required',
                'timestamp.required' => 'timestamp is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }


        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        //检查供应商是不是自己平台
        if($param['providerId'] != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$param['providerId'].'我方-providerId:'. config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        $uid = $param['userId'];
        $res = $this->common->setUserMoney($uid);
        if($res['code'] != 200){
            $this->logger->error('PP玩家-UID:'.$uid.'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }
        $money = $res['data'];

        $slots_bonus = Db::table('slots_bonus')->where(['terrace_name' => 'pp','transactionId' => $param['reference']])->first();
        if($slots_bonus){
            $data = [
                'transactionId' => $param['reference'],
                'currency' =>config('ppslots.currency'),
                'cash' => (float)bcdiv((string)$money,'100',2),
                'bonus' => 0,
                'error' => 0,
                'description' => 'Success'
            ];
            return $this->response->json($data);
        }

        $amount =  bcmul((string)$param['amount'],'100',0);


        $slots_bonus_data = [
            'uid' => $param['userId'],
            'terrace_name' => 'pp',
            'roundId' => 0,
            'transactionId' => $param['reference'],
            'amount' => $amount,
            'type' => 2,
            'bonustime' => mb_substr($param['timestamp'],0,-3),
        ];

        $res = $this->common->slotsBonus($slots_bonus_data,(int)$amount);
        if(!$res)return $this->response->json(['error' => 100,'description' => 'Internal server error. Casino Operator will return this error code if their system has internal problem and cannot process the request at the moment and Operator logic requires a retry of the request. Request will follow Reconciliation process']);
        $data = [
            'transactionId' => $param['reference'],
            'currency' =>config('ppslots.currency'),
            'cash' => (float)bcdiv(bcadd((string)$money,$amount,0),'100',2),
            'bonus' => 0,
            'error' => 0,
            'description' => 'Success'
        ];
        return $this->response->json($data);

    }


    /**
     * 结束游戏回合
     * @return void
     */
    #[RequestMapping(path: 'endRound')]
    public function endRound(){
        $param = $this->request->all();

        $validator = $this->validatorFactory->make(
            $param,
            [
                'userId' => 'required',
                'roundId' => 'required',
                'providerId' => 'required',
            ],
            [
                'userId.required' => 'userId is required',
                'roundId.required' => 'roundId is required',
                'providerId.required' => 'providerId is required',
            ]
        );


        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        //检查供应商是不是自己平台
        if($param['providerId'] != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$param['providerId'].'我方-providerId:'. config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }


        $res = $this->common->setUserMoney($param['userId']);
        if($res['code'] != 200){
            $this->logger->error('PP玩家-UID:'.$param['userId'].'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }

        $data = [
            'cash' => (float)bcdiv((string)$res['data'],'100',2),
            'bonus' => 0,
            'error' => 0,
            'description' => 'Success'
        ];

        $SlotsLog = $this->common->SlotsLogView($param['roundId']);
        if(!$SlotsLog || $SlotsLog['is_settlement'] == 1){
            $this->logger->error('PP玩家-UID:'.$param['userId'].'游戏不存在或已结算');
            return $this->response->json($data);
        }


        //将订单标记为完成
        co(function ()use ($SlotsLog){
            $this->common->updateSlotsLog($SlotsLog['betId'],['is_settlement' => 1]);
        });

        //处理mq消息
        $this->common->mqDealWith($SlotsLog);

        return $this->response->json($data);

    }


    /**
     * 玩家需要调整的余额金额
     * 只在真人游戏使用
     * @return void
     */
    #[RequestMapping(path: 'adjustment')]
    public function adjustment(){

        $param = $this->request->all();
        $this->logger->error('pp-adjustment:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'hash' => 'required',
                'userId' => 'required',
                'gameId' => 'required',
                'roundId' => 'required',
                'amount' => 'required', //需要调整的钱
                'reference' => 'required',
                'providerId' => 'required',
                'validBetAmount' => 'required',
                'timestamp' => 'required',
            ],
            [
                'hash.required' => 'hash is required',
                'userId.required' => 'userId is required',
                'gameId.required' => 'gameId is required',
                'roundId.required' => 'roundId is required',
                'amount.required' => 'amount is required',
                'reference.required' => 'reference is required',
                'providerId.required' => 'providerId is required',
                'validBetAmount.required' => 'validBetAmount is required',
                'timestamp.required' => 'timestamp is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("PP-adjustment接口数据验证失败===>".$errorMessage);
            return $this->response->json(['error' => 7,'description' => 'Bad parameters in the request, please check post parameters.']);
        }


        //效验hash是否正确
        $res = $this->effectivenessHash($param);
        if(!$res){
            $this->logger->error('PP-Hash效验失败:三方传入参数'.json_encode($param));
            return $this->response->json(['error' => 5,'description' => 'Invalid hash code. Should be returned in the response on any request sent by Pragmatic Play if the hash code validation is failed.']);
        }

        //检查供应商是不是自己平台
        if($param['providerId'] != config('ppslots.ProviderId')){
            $this->logger->error('PP商户验证失败:三方-providerId:'.$param['providerId'].'我方-providerId:'. config('ppslots.ProviderId'));
            return $this->response->json(['error' => 4,'description' => 'Player authentication failed due to invalid, not found or expired token.']);
        }

        //$res = $this->common->setUserMoney($param['userId']);
        $userinfo = Db::table('userinfo')
            ->where('uid',$param['userId'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();
        if (empty($userinfo)){
            $this->logger->error('PP玩家ad-UID:'.$param['userId'].'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }
        //bonus不带入
        $userinfo['bonus'] = config('slots.is_carry_bonus') == 1 ? $userinfo['bonus'] : 0;
        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);

        if ($param['amount'] < 0) {
            if (bcadd((string)$old_balance, (string)$param['amount'], 2) < 0) {
                $this->logger->error('PP玩家-UID:' . $param['userId'] . 'ad余额不足,玩家余额:' . $old_balance . '调整金额:' . $param['amount']);
                return $this->response->json(['error' => 1, 'description' => 'Insufficient balance']);
            }
        }

        try {
            Db::beginTransaction();

            $res = $this->common->userFundChange($param['userId'], $param['amount']*100, 0, bcadd((string)$old_balance, (string)$param['amount'],2)*100, 0, $userinfo['channel'], $userinfo['package_id']);
            if (!$res) {
                $this->logger->error('uid:' . $userinfo['uid'] . '三方游戏ad调整余额修改失败');
                Db::rollback();
                return $this->response->json(['error' => 7,'description' => 'Bad parameters in the request, please check post parameters.']);
            }

            Db::commit();

            $data = [
                'transactionId' => $param['reference'],
                'currency' =>config('ppslots.currency'),
                'cash' => (float)bcadd((string)$old_balance, (string)$param['amount'],2),
                'bonus' => 0,
                'error' => 0,
                'description' => 'Success'
            ];
            return $this->response->json($data);
        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("ad，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['error' => 7,'description' => 'Bad parameters in the request, please check post parameters.']);
        }

    }


    /**
     * 解析PP roundDetails
     * @param $roundDetails
     * @param $filedArray array 需要那些字段
     * @return void
     */
    private function getRoundDetails($roundDetails,array $filedArray){
        $roundDetailsArray = explode(',',$roundDetails);
        $data = [];
        foreach ($roundDetailsArray as $v){
            $detailsArray = explode(':',$v);
            $filed = $detailsArray[0];
            $value = $detailsArray[1] ?? '';
            if(in_array($filed,$filedArray))$data[$filed] = $value;
        }
        return $data;
    }

//下面转入转出获取为转账钱包

    public  function GetGame(){


        $url = config('ppslots.api_url').'/IntegrationService/v3/http/CasinoGameAPI/getCasinoGames';
        $data = [
            'secureLogin' => config('ppslots.secureLogin'),
            'options' => 'GetFrbDetails,GetLines,GetDataTypes,GetFeatures'
        ];
        $data['hash'] = $this->getHash($data);
        return $this->guzzle->post($url,$data,$this->herder);


    }


    /**
     * @return void 充值
     * @param  $ordersn 订单号
     * @param  $amount 充值金额 正数代表充值，负数代表提现
     *  @param  $player 玩家的在uid
     */
    public  function Cash($ordersn,$amount,$uid){
        $amount = bcdiv((string)$amount,'100',2);
        $url  = config('ppslots.api_url').'/IntegrationService/v3/http/CasinoGameAPI/balance/transfer/';
        $body = [
            'secureLogin' => config('ppslots.secureLogin'),
            'externalPlayerId' => $uid,
            'externalTransactionId' => $ordersn,
            'amount' => $amount,
        ];
        $body['hash'] = $this->getHash($body);

        $data = $this->senPostCurl($url,$body);
        if($amount > 0){
            $this->logger->error('PP==Cash====uid===='.$uid.'======res==='.json_encode($data));
        }else{
            $this->logger->error('PP==Transaction====uid===='.$uid.'======res==='.json_encode($data));
        }

        if(!isset($data['transactionId']) || !isset($data['error']) || $data['error'] !== '0'){//充值获取转出失败
            //给用户加钱
            return ['code' => 201,'msg' => $data['description'] ?? '' ,'data' =>$data ];
        }

        return ['code' => 200,'msg' => $data['description'] ,'data' =>$data['transactionId']];

    }



    /**
     * 转出所有余额
     *
     * @param  $ordersn 订单号
     *  @param  $uid 玩家的uid
     * @return array
     */
    public  function transferAllOut($ordersn,$uid) {

        $res = $this->getPlayerWallet($uid);

        if($res['code'] != 200){
            return ['code' => 201,'msg' => $res['msg'],'data' => $res['data']];
        }
        if($res['data'] <= 0){
            return ['code' => 200,'msg' => '','data' => ['money' => 0]];
        }
        $money = bcsub('0',bcmul((string)$res['data'],'100',2),2);
        $res1 =  $this->Cash($ordersn,$money,$uid);
        if($res1['code'] != 200){
            return ['code' => 201,'msg' => $res1['msg'],'data' => $res1['data']];
        }

        return ['code' => 200,'msg' => '','data' => ['transactionId' => $res1['data'],'money' => $res['data']]];
    }

    /**
     * 创建玩家
     * @param $nickname  玩家昵称
     * @param $player_name  玩家uid
     * @param $trace_id trace_id
     * @return bool|string
     */
    public  function playerCreated($uid){

        $url  = config('ppslots.api_url').'/IntegrationService/v3/http/CasinoGameAPI/player/account/create/';
        $body = [
            'secureLogin' => config('ppslots.secureLogin'),
            'externalPlayerId' => $uid,
            'currency' => config('ppslots.currency'),
        ];

        $body['hash'] = $this->getHash($body);

        $data = $this->senPostCurl($url,$body);

        if(!isset($data['playerId']) || !isset($data['error']) || $data['error'] !== '0'){
            return ['code' => 201,'msg' => $data['description'] ?? '' ,'data' =>$data ];
        }

        return ['code' => 200,'msg' => $data['description'] ,'data' =>$data['playerId']];

    }



    /**
     * 获取玩家第三方游戏余额
     * @param $player_name  玩家uid
     * @return bool|string
     */
    public  function getPlayerWallet($uid){

        $url  = config('ppslots.api_url').'/IntegrationService/v3/http/CasinoGameAPI/balance/current/';
        $body = [
            'secureLogin' => config('ppslots.secureLogin'),
            'externalPlayerId' => $uid,
        ];

        $body['hash'] = $this->getHash($body);
        $data = $this->senPostCurl($url,$body);

        if(!isset($data['balance']) || !isset($data['error']) || $data['error'] !== '0'){
            $this->logger->error('PP==getPlayerWallet====uid===='.$uid.'======res==='.json_encode($body).'===url==='.$url);
            Common::log('Ppslots获取玩家余额'.$uid,json_encode($data),3);
            return ['code' => 201,'msg' => $data['description'] ?? '' ,'data' =>$data ];
        }

        return ['code' => 200,'msg' => $data['description'] ,'data' =>$data['balance']];

    }

    /**
     * 获取游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public  function getGameUrl($uid,$gameid){
        //单一钱包启动
        $user_token = Db::table('user_token')->select('token')->where('uid',$uid)->first();
        if(!$user_token){
            $this->logger->error('用户获取PP游戏链接时-uid:'.$uid.'获取token失败');
            return ['code' => 201,'msg' => '用户获取PP游戏链接时-uid:'.$uid.'获取token失败' ,'data' =>[] ];
        }
        $url  = config('ppslots.api_url').'/IntegrationService/v3/http/CasinoGameAPI/game/url/';
        $body = [
            'secureLogin' => config('ppslots.secureLogin'),
            'symbol' => $gameid,
            'language' => config('ppslots.language'),
            'token' => $user_token['token'],
            'externalPlayerId' => $uid,
            'currency' => config('ppslots.currency'),
            'rcCloseUrl' => config('host.gameurl'),//指向运营商网站页面的链接，如果玩家选择关闭游戏，他们将被重定向到该页面。
            'lobbyUrl' => config('host.gameurl'),//用于返回娱乐场运营商网站上的大厅页面的URL。此链接用于移动版游戏中的返回大厅（主页）按钮
            'cashierUrl' => '',//当玩家没有资金时，用于在娱乐场运营商网站上打开收银台的URL
        ];
        $body['hash'] = $this->getHash($body);
        $data = $this->senPostCurl($url,$body);

        if(!isset($data['gameURL']) || !isset($data['error']) || $data['error'] !== '0'){
            $this->logger->error('用户获取PP游戏链接时-uid:'.$uid.'获取游戏启动链接失败,返回数据：'.json_encode($data));
            return ['code' => 201,'msg' => 'Ppslots玩家获取游戏链接' ,'data' =>[] ];
        }
//        $this->logger->error('用户获取PP链接:'.$data['gameURL']);
        return ['code' => 200,'msg' => 'success' ,'data' =>$data['gameURL']];

        //转账模式启动
        $url  = config('ppslots.api_url').'/IntegrationService/v3/http/CasinoGameAPI/game/start/';
        $body = [
            'secureLogin' => config('ppslots.secureLogin'),
            'externalPlayerId' => $uid,
            'gameId' => $gameid,
            'language' => config('ppslots.language'),
            'lobbyURL' => 'https://closewebview/',
        ];

        $body['hash'] = $this->getHash($body);

        $data = $this->senPostCurl($url,$body);

        if(!isset($data['gameURL']) || !isset($data['error']) || $data['error'] !== '0'){
            return ['code' => 201,'msg' => $data['description'] ?? '' ,'data' =>$data ];
        }

        return ['code' => 200,'msg' => $data['description'] ,'data' =>$data['gameURL']];
    }

    /**
     * 获取游戏历史记录
     * @param $timepoint 拉取开始时间
     * @param $dataType 产品组合类 RNG 视频老虎机、经典老虎机等 ,LC 真人娱乐场产品组合,VSB 虚拟体育博彩产品组合,BNG 宾果 产品
     * @return bool|string
     */
    public  function getHistory($timepoint,$dataType = 'RNG') {

        $url  = config('ppslots.api_url').'/IntegrationService/v3/DataFeeds/gamerounds/finished/';
        $body = [
            'login' => config('ppslots.secureLogin'),
            'password' => config('ppslots.SecretKey'),
            'timepoint' => $timepoint,
            'dataType' => $dataType,
            'options' => 'addBalance',
        ];

        $data = $this->senGetCurl($url,$body);

        if(!$data || $data == 'null'){
            return [];
        }
        $data = explode("\n",$data);

        $timepointSrting = explode('=',$data[0])[1] ?? 0;
        if(!$timepointSrting){
            return [];
        }
        if(count($data) <= 2){ //表示只有时间和数据格式
            return ['timepoint' => $timepointSrting,'data' => []];
        }
        $timezone = new \DateTimeZone('GMT');

        $list = [];
        for ($i= 2;$i < count($data); $i++){
            if($data[$i]){
                $valueArray = explode(',',$data[$i]);
                $startDate = $valueArray[5] ?? 0;
                $endDate =$valueArray[6] ?? 0;

                $startDateObj = \DateTime::createFromFormat('Y-m-d H:i:s', $startDate, $timezone);
                $endDateObj = \DateTime::createFromFormat('Y-m-d H:i:s', $endDate, $timezone);
                $startDateObj->setTimezone(new \DateTimeZone('Asia/Shanghai')); // 设置为中国时区
                $endDateObj->setTimezone(new \DateTimeZone('Asia/Shanghai')); // 设置为中国时区
//                $startDateObj->setTimezone(new \DateTimeZone('America/Sao_Paulo')); // 设置为巴西时区
//                $endDateObj->setTimezone(new \DateTimeZone('America/Sao_Paulo')); // 设置为巴西时区
                $startDateLocal = $startDateObj->format('Y-m-d H:i:s');
                $endDateLocal = $endDateObj->format('Y-m-d H:i:s');
                $list[] = [
                    'playerID' => $valueArray[0] ?? 0,     //三方的用户id
                    'extPlayerID' => $valueArray[1] ?? 0,  //我们平台用户uid
                    'gameID' => $valueArray[2] ?? 0,    //游戏id
                    'playSessionID' => $valueArray[3] ?? 0,   //子账单
                    'parentSessionID' => $valueArray[4] ?? 0,  //母账单
                    'startDate' => strtotime($startDateLocal),   //开始时间
                    'endDate' =>  strtotime($endDateLocal),    //结束时间
                    'status' => $valueArray[7] ?? 0,    //游戏状态 ： C = 已完成 , I = 未完成
                    'type' => $valueArray[8] ?? 0,    //游戏类型 ： R = 游戏回合 , F = 免费旋转在游戏回合中触发
                    'bet' => $valueArray[9] ?? 0,     //投注金额
                    'win' => $valueArray[10] ?? 0,    //结算金额
                    'currency' => $valueArray[11] ?? 0,  //交易货币
                    'jackpot' => $valueArray[12] ?? 0,    //累积奖金赢得的数量
                    'balance' => $valueArray[13] ?? 0,    //这把打完的余额
                ];
            }
        }


        return ['timepoint' => $timepointSrting,'data' => $list];

    }

    /**
     * 效验hash是否正确
     * @param array $body 三方传入的所有参数
     * @return void
     */
    private function effectivenessHash(array $body):int{
        $hash = $body['hash'] ?? '';
        if(!$hash)return 0;
        unset($body['hash']);
        $bodyHash = $this->getHash($body);
        var_dump($bodyHash);
        if($bodyHash != $hash)return 0;
        return 1;
    }


    /**
     * @param array $body 请求体
     * @return string 获取hash
     */
    private  function getHash(array $body){
        return Sign::asciiSignNotKey($body,config('ppslots.SecretKey'));
    }

    /** 发送Curl
     * @param $url
     * @param $body
     * @return mixed
     */
    private  function senPostCurl($url,$body)
    {
        return $this->guzzle->post($url,$body,$this->herder);

    }

    /** 发送Curl
     * @param $url
     * @param $body
     * @return mixed
     */
    private  function senGetCurl($url,$body){
        return $this->guzzle->get($url,$body);
    }



}






