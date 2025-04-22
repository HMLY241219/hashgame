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
use function Hyperf\Config\config;
use Hyperf\Di\Annotation\Inject;
use App\Common\Guzzle;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Common\Common as appCommon;
#[Controller(prefix:"ezugislots")]
class EzugislotsController extends AbstractController{


    #[Inject]
    protected Common $common;
    #[Inject]
    protected Guzzle $guzzle;


    private  array $gameID = [1,46,12,3,7,48,54,29,15,16,50,53,38,55,39,2,27,20,21,25,24,45,13,19,14,52,17];

    private array $debitBetTypeId = [1,3,4,5,6,24,8,9,11,10];


    /**
     * 效验玩家
     * @return void
     */
    #[RequestMapping(path: 'authentication')]
    public function authentication(){
        $param = $this->request->all();
//        $this->logger->error('ezugiauthentication:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'operatorId' => 'required',
                'token' => 'required',
                'platformId' => 'required',
                'timestamp' => 'required',
            ],
            [
                'operatorId.required' => 'operatorId is required',
                'token.required' => 'token is required',
                'platformId.required' => 'platformId is required',
                'timestamp.required' => 'timestamp is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("Ezugi验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }

        //检验hash是否正确
        $res = $this->hashEffect($param);
        if(!$res) return $this->InvalidHash($param);

        if($param['operatorId'] != (int)config('ezugislots.operatorID')){
            $this->logger->error("Ezugi运营商信息错误--我方operatorID:".(int)config('ezugislots.operatorID')."--三方传入我方operatorID:".$param['operatorID']);
            return $this->errorData();
        }

        //效验登录token是否已经使用 测试用的，正式环境需要删除
//        $res =$this->getLoginToken($param['token']);
//        if($res){
//            $this->logger->error('Token已经效验了一下');
//            $data = [
//                'operatorId' => (int)config('ezugislots.operatorID'),
//                'errorCode' => 6,
//                'errorDescription' => 'Token not found',
//                'timestamp' => (int)round(microtime(true) * 1000),
//            ];
//            return $this->response->json($data);
//        }

        $uid = $this->common->getUserUid($param['token']);
        if(!$uid){
            $this->logger->error("Ezugi验证账号接口token错误--token:".$param['token']);
            return $this->errorData(6);
        }

        $res = $this->common->setUserMoney($uid);
        if($res['code'] != 200){
            $this->logger->error("Ezugi验证账号接口uid错误--uid:".$uid);
            return $this->errorData(4);
        }

        //设置登录token是否已经使用 测试用的，正式环境需要删除
//        $this->setLoginToken($param['token']);

        //设置下注游戏使用的token
        $token = $this->setToken((string)$uid);
        $data = [
            'operatorId' => (int)config('ezugislots.operatorID'),
            'uid' => (string)$uid,
            'nickName' => (string)$uid,
            'token' => $token,
            'playerTokenAtLaunch' => $param['token'],
            'balance' => (float)bcdiv((string)$res['data'],'100',2),
            'currency' => config('ezugislots.currency'),
            'errorCode' => 0,
            'errorDescription' => 'OK',
            'timestamp' => (int)round(microtime(true) * 1000),
        ];
        return $this->response->json($data);
    }

    /**
     * 下注
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'debit')]
    public function debit(){
        $param = $this->request->all();
//        $this->logger->error('ezugi-debit:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'serverId' => 'required',
                'operatorId' => 'required',
                'token' => 'required',
                'uid' => 'required',
                'transactionId' => 'required',
                'roundId' => 'required',
                'gameId' => 'required',
                'tableId' => 'required',
                'currency' => 'required',
                'debitAmount' => 'required',
                'betTypeID' => 'required',
            ],
            [
                'serverId.required' => 'serverId is required',
                'operatorId.required' => 'operatorId is required',
                'token.required' => 'token is required',
                'uid.required' => 'uid is required',
                'transactionId.required' => 'transactionId is required',
                'roundId.required' => 'roundId is required',
                'gameId.required' => 'gameId is required',
                'tableId.required' => 'tableId is required',
                'currency.required' => 'currency is required',
                'debitAmount.required' => 'debitAmount is required',
                'betTypeID.required' => 'betTypeID is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("Ezugi验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }

        //检验hash是否正确
        $res = $this->hashEffect($param);
        if(!$res) return $this->InvalidHash($param);


        //检查供应商是不是自己平台
        if($param['operatorId'] != (int)config('ezugislots.operatorID')){
            $this->logger->error("Ezugi运营商信息错误--我方operatorID:".(int)config('ezugislots.operatorID')."--三方传入我方operatorID:".$param['operatorID']);
            return $this->errorData();
        }

        //检查供应商是不是自己平台
        if($param['currency'] != config('ezugislots.currency')){
            $this->logger->error("Ezugi运营商信息错误--我方operatorID:".(int)config('ezugislots.operatorID')."--三方传入我方operatorID:".$param['operatorID']);
            return $this->errorData();
        }


        //效验UID与三方的UID是否一致
        $uid = $this->getToken((string)$param['token']);
        if(!$uid){
            $this->logger->error("Ezugi验证账号接口token错误--token:".$param['token']);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $param['uid'],
                'token' => $param['token'],
                'balance' => 0.00,
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 6,
                'errorDescription' => 'Token not found',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }

        if($uid != $param['uid']){
            $this->logger->error("Ezugi验证账号接口UID错误--token获取的UID:".$uid."--三方传入的playerID:".$param['uid']);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => 0.00,
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 7,
                'errorDescription' => 'User not found',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }


        $userinfo = $this->common->getUserInfo($uid);
        if(!$userinfo){
            $this->logger->error("Ezugi玩家UID不存在--UID:".$uid);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => 0.00,
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 7,
                'errorDescription' => 'User not found',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }

        //用户余额
        $money = $userinfo['coin'] + $userinfo['bonus'];


        //检查游戏ID 测试时使用
//        if(!in_array($param['gameId'],$this->gameID)){
//            $data = [
//                'operatorId' => $param['operatorId'],
//                'roundId' => $param['roundId'],
//                'uid' => $uid,
//                'token' => $param['token'],
//                'balance' => (float)bcdiv((string)$money,'100',2),
//                'transactionId' => $param['transactionId'],
//                'currency' =>config('ezugislots.currency'),
//                'bonusAmount' => 0.00,
//                'errorCode' => 1,
//                'errorDescription' => 'Unknown Game ID',
//                'timestamp' => (int)round(microtime(true) * 1000),
//            ];
//            return $this->response->json($data);
//        }
//
//        if(!in_array($param['betTypeID'],$this->debitBetTypeId)){
//            $data = [
//                'operatorId' => $param['operatorId'],
//                'roundId' => $param['roundId'],
//                'uid' => $uid,
//                'token' => $param['token'],
//                'balance' => (float)bcdiv((string)$money,'100',2),
//                'transactionId' => $param['transactionId'],
//                'currency' =>config('ezugislots.currency'),
//                'bonusAmount' => 0.00,
//                'errorCode' => 1,
//                'errorDescription' => 'Invalid Bet Type',
//                'timestamp' => (int)round(microtime(true) * 1000),
//            ];
//            return $this->response->json($data);
//        }

        //下注不能为负数
        if($param['debitAmount'] < 0){
            $this->logger->error("Ezugi下注金额错误--debitAmount:".$param['debitAmount']);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv((string)$money,'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 1,
                'errorDescription' => 'Negative amount',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }

        //正式环境必须开启
        $res = $this->common->getUserRunGameTage($uid,'ezugi');
        if(!$res)return $this->errorData();


        $debitAmount = bcmul((string)$param['debitAmount'],'100',0);
        if($money < $debitAmount){
            $this->logger->error('Ezugi玩家-UID:'.$uid.'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$debitAmount);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv((string)$money,'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 3,
                'errorDescription' => 'Insufficient funds',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }

        $roundId = $param['transactionId'];

        $res = $this->getNotGetBetOrder($param['transactionId']);
        if($res){
            $this->logger->error('Ezugi扣款前回滚订单-transactionId:'.$param['transactionId']);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv((string)$money,'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 1,
                'errorDescription' => 'Debit after rollback',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];

            return $this->response->json($data);
        }


        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($roundId);
        if($slots_log){
            $this->logger->error('Ezugi该笔订单已经通知过-roundId:'.$roundId);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv((string)$money,'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 0,
                'errorDescription' => 'Transaction has already processed',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }


        $slotsData = [
            'betId' => $roundId,
            'parentBetId' => $roundId,
            'uid' => $uid,
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['gameId'],
            'englishname' => 'ezugi'.$param['gameId'],
            'game_id' => 0,
            'terrace_name' => 'ezugi',
            'transaction_id' => $param['transactionId'],
            'betTime' => time(),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
            'is_settlement' => 0,
        ];
        $res = $this->common->slotsLog($slotsData,$userinfo['coin'],$userinfo['bonus'],$debitAmount,'0',2);
        if($res['code'] != 200)return $this->errorData();
        $data = [
            'operatorId' => $param['operatorId'],
            'roundId' => $param['roundId'],
            'uid' => $uid,
            'token' => $param['token'],
            'balance' => (float)bcdiv((string)$res['data'],'100',2),
            'transactionId' => $param['transactionId'],
            'currency' =>config('ezugislots.currency'),
            'bonusAmount' => 0.00,
            'errorCode' => 0,
            'errorDescription' => 'OK',
            'timestamp' => (int)round(microtime(true) * 1000),
        ];
        return $this->response->json($data);
    }


    /**
     * returnRrason = 1 表示下单失败，退回订单 returnRrason = 2 表示三方游戏维护、断电，中断，导致的问题，进行补偿
     * 结算
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'credit')]
    public function credit(){
        $param = $this->request->all();
//        $this->logger->error('ezugi-credit:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'serverId' => 'required',
                'operatorId' => 'required',
                'token' => 'required',
                'uid' => 'required',
                'transactionId' => 'required',
                'roundId' => 'required',
                'gameId' => 'required',
                'tableId' => 'required',
                'betTypeID' => 'required',
                'currency' => 'required',
                'creditAmount' => 'required',
            ],
            [
                'serverId.required' => 'serverId is required',
                'operatorId.required' => 'operatorId is required',
                'token.required' => 'token is required',
                'uid.required' => 'uid is required',
                'transactionId.required' => 'transactionId is required',
                'roundId.required' => 'roundId is required',
                'gameId.required' => 'gameId is required',
                'tableId.required' => 'tableId is required',
                'betTypeID.required' => 'betTypeID is required',
                'currency.required' => 'currency is required',
                'creditAmount.required' => 'creditAmount is required',
            ]
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("Ezugi验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }

        //检验hash是否正确
        $res = $this->hashEffect($param);
        if(!$res) return $this->InvalidHash($param);

        //检查供应商是不是自己平台
        if($param['operatorId'] != (int)config('ezugislots.operatorID')){
            $this->logger->error("Ezugi运营商信息错误--我方operatorID:".(int)config('ezugislots.operatorID')."--三方传入我方operatorID:".$param['operatorID']);
            return $this->errorData();
        }

        //检查供应商是不是自己平台
        if($param['currency'] != config('ezugislots.currency')){
            $this->logger->error("Ezugi运营商信息错误--我方operatorID:".(int)config('ezugislots.operatorID')."--三方传入我方operatorID:".$param['operatorID']);
            return $this->errorData();
        }

        //效验UID与三方的UID是否一致
        $uid = $this->getToken((string)$param['token']);
        if(!$uid){
            $this->logger->error("Ezugi验证账号接口token错误--token:".$param['token']);
            return $this->errorData(6);
        }


        if($uid != $param['uid']){
            $this->logger->error("Ezugi验证账号接口UID错误--token获取的UID:".$uid."--三方传入的playerID:".$param['uid']);
            return $this->errorData(4);
        }

        $userinfo = $this->common->getUserInfo($param['uid']);
        if(!$userinfo){
            $this->logger->error('"Ezugi数据库-userinfo不存在用户-UID:'.$param['uid']);
            return $this->errorData(4);
        }

        //检查下注的流水号是否有值
        if(!isset($param['debitTransactionId']) || !$param['debitTransactionId']){
            $this->logger->error("Ezugi结算时不存在debitTransactionId");
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv(bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 9,
                'errorDescription' => 'Debit transaction ID not found',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }

        //检查下注的流水号是否存在订单
//        $slots_log = $this->getTransactionIdSlotsLog($param['debitTransactionId']);
        $slots_log = $this->common->SlotsLogView($param['debitTransactionId']);
        if(!$slots_log){
            $this->logger->error("Ezugi结算时下注流水号找不到订单-debitTransactionId:".$param['debitTransactionId']);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv(bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'bonusAmount' => 0.00,
                'errorCode' => 9,
                'errorDescription' => 'Debit transaction ID not found',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }


        $roundId = $param['debitTransactionId'];


        $winAmount = max(bcmul((string)$param['creditAmount'],'100',2), '0');

        if(isset($param['returnReason']) && $param['returnReason'] == 1){
            if($slots_log['is_settlement'] == 2){
                $this->logger->error('Ezugi该笔订单已经通知过-roundId:'.$roundId);
                return  $this->errorData();
            }
            $this->common->setRefundSlotsLog($slots_log,$winAmount);
        }else{
            if(!in_array($slots_log['is_settlement'],[0,4])){
                $this->logger->error('Ezugi该笔订单已经通知过-betID:'.$roundId);
                $transactionId = $this->getCreditTransactionId($roundId);
                $data = [
                    'operatorId' => $param['operatorId'],
                    'roundId' => $param['roundId'],
                    'uid' => $uid,
                    'token' => $param['token'],
                    'balance' => (float)bcdiv(bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2),
                    'transactionId' => $param['transactionId'],
                    'currency' =>config('ezugislots.currency'),
                    'bonusAmount' => 0.00,
                    'errorCode' => $transactionId == $param['transactionId'] ? 0 : 1,
                    'errorDescription' => $transactionId == $param['transactionId'] ? 'Transaction already processed' : 'Debit transaction already processed',
                    'timestamp' => (int)round(microtime(true) * 1000),
                ];
                return $this->response->json($data);
            }
            $this->common->resultDealWith($slots_log,$userinfo,$winAmount,2);
        }

        $this->setCreditTransactionId($roundId,$param['transactionId']);


        $res = $this->common->setUserMoney($uid);
        if($res['code'] != 200){
            $this->logger->error('we数据库-userinfo不存在用户-UID:'.$uid);
            return $this->errorData(3);
        };


        $data = [
            'operatorId' => $param['operatorId'],
            'roundId' => $param['roundId'],
            'uid' => $uid,
            'token' => $param['token'],
            'balance' => (float)bcdiv((string)$res['data'],'100',2),
            'transactionId' => $param['transactionId'],
            'currency' =>config('ezugislots.currency'),
            'bonusAmount' => 0.00,
            'errorCode' => 0,
            'errorDescription' => 'OK',
            'timestamp' => (int)round(microtime(true) * 1000),
        ];
        return $this->response->json($data);
    }

    /**
     * 回滚
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'rollback')]
    public function rollback(){
        $param = $this->request->all();
//        $this->logger->error('ezugi-rollback:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'serverId' => 'required',
                'operatorId' => 'required',
                'token' => 'required',
                'uid' => 'required',
                'transactionId' => 'required',
                'roundId' => 'required',
                'gameId' => 'required',
                'tableId' => 'required',
                'currency' => 'required',
                'rollbackAmount' => 'required',
                'betTypeID' => 'required',
            ],
            [
                'serverId.required' => 'serverId is required',
                'operatorId.required' => 'operatorId is required',
                'token.required' => 'token is required',
                'uid.required' => 'uid is required',
                'transactionId.required' => 'transactionId is required',
                'roundId.required' => 'roundId is required',
                'gameId.required' => 'gameId is required',
                'tableId.required' => 'tableId is required',
                'currency.required' => 'currency is required',
                'rollbackAmount.required' => 'rollbackAmount is required',
                'betTypeID.required' => 'betTypeID is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("Ezugi验证账号接口数据验证失败===>".$errorMessage);
            return $this->errorData();
        }


        //检验hash是否正确
        $res = $this->hashEffect($param);
        if(!$res) return $this->InvalidHash($param);

        //检查供应商是不是自己平台
        if($param['operatorId'] != (int)config('ezugislots.operatorID')){
            $this->logger->error("Ezugi运营商信息错误--我方operatorID:".(int)config('ezugislots.operatorID')."--三方传入我方operatorID:".$param['operatorID']);
            return $this->errorData();
        }

        //检查供应商是不是自己平台
        if($param['currency'] != config('ezugislots.currency')){
            $this->logger->error("Ezugi运营商信息错误--我方operatorID:".(int)config('ezugislots.operatorID')."--三方传入我方operatorID:".$param['operatorID']);
            return $this->errorData();
        }

        //效验UID与三方的UID是否一致
        $uid = $this->getToken((string)$param['token']);
        if(!$uid){
            $this->logger->error("Ezugi验证账号接口token错误--token:".$param['token']);
            return $this->errorData(6);
        }


        if($uid != $param['uid']){
            $this->logger->error("Ezugi验证账号接口UID错误--token获取的UID:".$uid."--三方传入的playerID:".$param['uid']);
            return $this->errorData(4);
        }
        //获取用户金额
        $res = $this->common->setUserMoney($uid);

        $roundId = $param['transactionId'];
//        $slots_log = $this->getTransactionIdSlotsLog($param['transactionId']);
        $slots_log = $this->common->SlotsLogView($roundId);
        if(!$slots_log){
            $this->setNotGetBetOrder($param['transactionId']);
            $this->logger->error('Ezugi该笔回滚订单不存在-roundId:'.$roundId);

            if($res['code'] != 200){
                $this->logger->error("Ezugi验证账号接口uid错误--uid:".$uid);
                return $this->errorData(4);
            }
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv((string)$res['data'],'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'errorCode' => 9,
                'errorDescription' => 'Transaction not found',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];

            return $this->response->json($data);
        }
        if($slots_log['is_settlement'] == 2){
            $this->logger->error('Ezugi该笔订单已经通知过-roundId:'.$roundId);
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv((string)$res['data'],'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'errorCode' => 0,
                'errorDescription' => 'Transaction already processed',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }

        //如果回滚金额大于了下注金额
        $rollbackAmount = bcmul((string)$param['rollbackAmount'],'100',0);
        if(bcadd((string)$slots_log['cashBetAmount'],(string)$slots_log['bonusBetAmount'],0) < $rollbackAmount){
            $data = [
                'operatorId' => $param['operatorId'],
                'roundId' => $param['roundId'],
                'uid' => $uid,
                'token' => $param['token'],
                'balance' => (float)bcdiv((string)$res['data'],'100',2),
                'transactionId' => $param['transactionId'],
                'currency' =>config('ezugislots.currency'),
                'errorCode' => 1,
                'errorDescription' => 'Invalid amount',
                'timestamp' => (int)round(microtime(true) * 1000),
            ];
            return $this->response->json($data);
        }

        $this->common->setRefundSlotsLog($slots_log,$rollbackAmount);
        $res = $this->common->setUserMoney($param['uid']);
        if($res['code'] != 200){
            $this->logger->error('Ezugi数据库-userinfo不存在用户-UID:'.$param['uid']);
            return $this->errorData(10);
        }


        $data = [
            'operatorId' => $param['operatorId'],
            'roundId' => $param['roundId'],
            'uid' => $uid,
            'token' => $param['token'],
            'balance' => (float)bcdiv((string)$res['data'],'100',2),
            'transactionId' => $param['transactionId'],
            'currency' =>config('ezugislots.currency'),
            'bonusAmount' => 0.00,
            'errorCode' => 0,
            'errorDescription' => 'OK',
            'timestamp' => (int)round(microtime(true) * 1000),
        ];
        return $this->response->json($data);

    }
    /**
     * @param string|int $uid 用户UID
     * @param string $tableId 启动的桌号ID
     * @return string
     */
    public function getGameUrl(string|int $uid, string $tableId = ''):string{
        $token = $this->common->getUserToken($uid);
        return $tableId
            ? config('ezugislots.game_url')."?token=$token&operatorId=".(int)config('ezugislots.operatorID')."&openTable=$tableId"
            : config('ezugislots.game_url')."?token=$token&operatorId=".(int)config('ezugislots.operatorID');
//        return $game_url;
    }


    private function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * 效验hash
     * @param $body array 请求体
     * @return int
     */
    private function hashEffect(array $body):int{
        $hash = $this->request->getHeaderLine('hash');
        $hashReally = base64_encode(hash_hmac('sha256', $this->getHashJson($body), config('ezugislots.appSecret'), true));
        if($hashReally != $hash){
            $this->logger->error("hash效验失败:得到三方的--hash:$hash,计算出来的--hash:".$hashReally);
            return 0;
        }
        return 1;
    }

    private function getHashJson(array $data){
        foreach ($data as $key => $v){
            if(in_array($key, ['creditAmount', 'debitAmount', 'rollbackAmount'])){
                $value = explode('.',(string)$v);
                if(!isset($value[1]))return $this->custom_json_encode($data);
            }
        }
        return json_encode($data);
    }

    /**
     * 防止下注金额出现0.00 的时候变为0这种情况
     * @param array $data
     * @return array|string|string[]|null
     */
    private function custom_json_encode(array $data) {
        return preg_replace_callback('/"(\w+)":([0-9\.]+)/', function($matches) {
            if (in_array($matches[1], ['creditAmount', 'debitAmount', 'rollbackAmount'])) {
                return '"' . $matches[1] . '":' . sprintf("%.2f", $matches[2]);
            }
            return $matches[0];
        }, json_encode($data));
    }

    /**
     * 设置token
     * @param string $uid
     * @param string $token
     */
    private function setToken(string $uid){
        $token = md5(time().$uid);
        Db::table('ezugi_token')->updateOrInsert(
            ['uid' => $uid],
            ['token' => $token]
        );
        return $token;
//        $Redis = appCommon::Redis('Redis5501');
//        $token = md5(time().$uid);
//        $Redis->hSet('ezugi_token',$token,$uid);
//        return $token;
    }

    /**
     * 获取token
     * @param string $token
     * @return string
     */
    private function getToken(string $token){
        return Db::table('ezugi_token')->where('token',$token)->value('uid');
//        $Redis = appCommon::Redis('Redis5501');
//        return $Redis->hGet('ezugi_token',$token);
    }


    /**
     * 设置未获取到的订单
     * @param string $roundId
     * @param string $transactionId
     */
    private function setNotGetBetOrder(string $transactionId){
        $Redis = appCommon::Redis('Redis5501');
        return $Redis->hSet('ezugi_not_bet',$transactionId,'1');
    }

    /**
     * 获取token
     * @param string $roundId
     * @return string
     */
    private function getNotGetBetOrder(string $transactionId){
        $Redis = appCommon::Redis('Redis5501');
        return $Redis->hGet('ezugi_not_bet',$transactionId);
    }


    /**
     * 设置登录效验token是否已使用
     * @param string $token
     */
    private function setLoginToken(string $token){
        $Redis = appCommon::Redis('Redis5501');
        return $Redis->hSet('ezugi_login_token',$token,'1');
    }

    /**
     * 获取登录效验token
     * @param string $token
     * @return string
     */
    private function getLoginToken(string $token){
        $Redis = appCommon::Redis('Redis5501');
        return $Redis->hGet('ezugi_login_token',$token);
    }


    /**
     * 通过流水号获取订单
     * @param string $transactionId 流水号
     * @return \Hyperf\Database\Model\Model|\Hyperf\Database\Query\Builder|object|null
     */
    private function getTransactionIdSlotsLog(string $transactionId){
        $slots_log = Db::table('slots_log_'.date('Ymd'))->where('transaction_id',$transactionId)->first();
        if(!$slots_log) $slots_log = Db::table('slots_log_'.date('Ymd',strtotime( '-1 day')))->where('transaction_id',$transactionId)->first();
        return $slots_log;
    }


    /**
     * 无效hash
     * @param array $param
     * @return void
     */
    private function InvalidHash(array $param){
        return [
            'operatorId' => $param['operatorId'] ?? '',
            'roundId' => $param['roundId'] ?? '',
            'uid' => $param['uid'] ?? '',
            'token' => $param['token'] ?? '',
            'balance' => 0.0,
            'transactionId' => $param['transactionId'] ?? '',
            'currency' =>config('ezugislots.currency'),
            'bonusAmount' => 0.00,
            'errorCode' => 1,
            'errorDescription' => 'Invalid Hash',
            'timestamp' => time(),
        ];
    }

    /**
     * 错误返回
     * @param int $type
     * @return
     */
    private function errorData(int $type = 1){
        if($type == 1){ //一般错误
            $data = ['errorCode' => 1,'errorDescription' => 'General error'];
        }elseif ($type == 2){ //余额不足
            $data = ['errorCode' => 3,'errorDescription' => 'Insufficient funds'];
        }elseif ($type == 3){// TOKEN无效
            $data = ['errorCode' => 6,'errorDescription' => 'Token not found'];
        }elseif ($type == 4){// 没找到用户
            $data = ['errorCode' => 7,'errorDescription' => 'User not found'];
        }elseif ($type == 5){// 未找到交易
            $data = ['errorCode' => 9,'errorDescription' => 'Transaction not found'];
        }elseif ($type == 6){// 真实余额不足以支付小费
            $data = ['errorCode' => 11,'errorDescription' => 'Real balance is not enough for tipping'];
        }elseif ($type == 7){//交易超时
            $data = ['errorCode' => 10,'errorDescription' => 'Transaction timed out'];
        }else{
            $data = ['errorCode' => 10,'errorDescription' => 'Transaction timed out'];
        }
        return $this->response->json($data);
    }


    /**
     * 设置结算下注流水号
     * @param string $betId
     * @param string $TransactionId
     */
    private function setCreditTransactionId(string $betId,string $TransactionId){
        $Redis = appCommon::Redis('Redis5501');
        $Redis->hSet('ezugi_CreditTrans',$betId,$TransactionId);
    }


    /**
     * 获取结算下注流水号
     * @param string $betId
     */
    private function getCreditTransactionId(string $betId){
        $Redis = appCommon::Redis('Redis5501');
        return $Redis->hGet('ezugi_CreditTrans',$betId);
    }
}







