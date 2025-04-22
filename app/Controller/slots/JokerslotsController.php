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
#[Controller(prefix:"jokerslots")]
class JokerslotsController extends AbstractController{


    #[Inject]
    protected Common $common;
    #[Inject]
    protected Guzzle $guzzle;

    /**效验
     * @return
     */
    #[RequestMapping(path: 'authenticate-token')]
    public function authenticate(){
        $param = $this->request->all();
//        $this->logger->error('jocker-authenticate:'.json_encode($param));

        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);

        $userUid = $this->common->getUserUid($param['token']);
        if(!$userUid){
            $this->logger->error("Jocker用户获取效验失败");
            return $this->response->json(['status' => 'Error']);
        }

        $res = $this->common->setUserMoney($userUid,2);
        if($res['code'] != 200){
            $this->logger->error("jocker验证账号接口uid错误--uid:".$userUid);
            return $this->response->json(['status' => 'Error']);
        }
        $data = [
            'Username' => (string)$userUid,
            'Balance' => (float)bcdiv((string)$res['data'],'100',2),
            'Message' => 'Success',
            'Status' => 0
        ];
        return $this->response->json($data);
    }


    /**获取用户余额
     * @return
     */
    #[RequestMapping(path: 'balance')]
    public function balance(){
        $param = $this->request->all();
//        $this->logger->error('jocker-balance:'.json_encode($param));

        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);

        $res = $this->common->setUserMoney($param['username'],2);
        if($res['code'] != 200){
            $this->logger->error("jocker验证账号接口uid错误--uid:".$param['username']);
            return $this->response->json(['status' => 'Error']);
        }
        return $this->response->json(['Balance' => (float)bcdiv((string)$res['data'],'100',2),'Message' => 'Success','Status' => 0]);
    }


    /**
     * 下注
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'bet')]
    public function bet(){
        $param = $this->request->all();
        $this->logger->error('joker-debit:'.json_encode($param));



        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);


        $userinfo = $this->common->getUserInfo($param['username'],2);
        if(!$userinfo){
            $this->logger->error("joker验证账号接口uid错误--uid:".$param['username']);
            return $this->response->json(['status' => 'Error']);
        }
        //用户余额
        $money = $userinfo['coin'] + $userinfo['bonus'];


        //下注不能为负数
        if($param['amount'] < 0){
            $this->logger->error("joker下注金额错误--betAmount:".$param['amount']);
            return $this->response->json(['status' => 'Error']);
        }

        //正式环境打开
        $res = $this->common->getUserRunGameTage($param['username'],'joker');
        if(!$res)return $this->response->json(['status' => 'Error']);


        $debitAmount = bcmul((string)$param['amount'],'100',0);
        if($money < $debitAmount){
            $this->logger->error('joker玩家-UID:'.$param['userId'].'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$debitAmount);
            return $this->response->json(['status' => 'INSUFFICIENT_FUNDS']);
        }

        $roundId = $param['id'];


        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($roundId);
        if($slots_log){
            $this->logger->error('joker该笔订单已经通知过-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }


        $slots_game = Db::table('slots_game')->select('id','englishname')->where(['terrace_id' => 16,'slotsgameid' => $param['gamecode']])->first();

        $slotsData = [
            'betId' => $roundId,
            'parentBetId' => $param['roundid'],
            'uid' => $param['username'],
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['gamecode'],
            'englishname' => $slots_game['englishname'],
            'game_id' => $slots_game['id'],
            'terrace_name' => 'joker',
            'transaction_id' => $roundId,
            'betTime' => time(),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
            'is_settlement' => 0,
        ];
        $res = $this->common->slotsLog($slotsData,$userinfo['coin'],$userinfo['bonus'],$debitAmount,'0',2);
        if($res['code'] != 200)return $this->response->json(['status' => 'Error']);
        $data = [
            'Balance' => (float)bcdiv((string)$res['data'],'100',2),
            'Message' =>  'OK',
            'Status' => 0
        ];
        return $this->response->json($data);
    }



    /**
     * returnRrason = 1 表示下单失败，退回订单 returnRrason = 2 表示三方游戏维护、断电，中断，导致的问题，进行补偿
     * 结算
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'settle-bet')]
    public function settleBet(){
        $param = $this->request->all();
        $this->logger->error('joker-settleBet:'.json_encode($param));

        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);



        $userinfo = $this->common->getUserInfo($param['username'],2);
        if(!$userinfo){
            $this->logger->error("joker验证账号接口uid错误--uid:".$param['username']);
            return $this->response->json(['status' => 'Error']);
        }



        $roundId = $param['roundid'];
        $slots_log = $this->getRoundIdSlotsLog($roundId);
        if(!$slots_log){
            $this->logger->error('"joker该笔订单不存在-betID:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }

        $winAmount = max(bcmul((string)$param['amount'],'100',2), '0');


        if(!in_array($slots_log['is_settlement'],[0,4])){
            $this->logger->error('joker该笔订单已经通知过-betID:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }
        $this->common->resultDealWith($slots_log,$userinfo,$winAmount,2);



        $res = $this->common->setUserMoney($param['username'],2);
        if($res['code'] != 200){
            $this->logger->error('joker数据库-userinfo不存在用户-UID:'.$param['username']);
            return $this->response->json(['status' => 'Error']);
        }


        $data = [
            'Balance' => (float)bcdiv((string)$res['data'],'100',2),
            'Message' =>  'OK',
            'Status' => 0
        ];
        return $this->response->json($data);
    }



    /**
     * 回滚
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'cancel-bet')]
    public function cancelBet(){
        $param = $this->request->all();
        $this->logger->error('joker-cancelBet:'.json_encode($param));

        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);

        //获取用户金额

        $roundId = $param['betid'];

        $slots_log = $this->common->SlotsLogView($roundId);
        if(!$slots_log){
            $this->logger->error('joker该笔回滚订单不存在-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }
        if($slots_log['is_settlement'] == 2){
            $this->logger->error('joker该笔订单已经通知过-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }

        //如果回滚金额大于了下注金额
        $rollbackAmount = bcadd((string)$slots_log['cashBetAmount'],(string)$slots_log['bonusBetAmount'],0);
        if(bcadd((string)$slots_log['cashBetAmount'],(string)$slots_log['bonusBetAmount'],0) < $rollbackAmount){
            $this->logger->error('joker该笔订单已经通知过-回滚金额大于下注金额-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }

        $this->common->setRefundSlotsLog($slots_log,$rollbackAmount);
        $res = $this->common->setUserMoney($param['username'],2);
        if($res['code'] != 200){
            $this->logger->error('joker数据库-userinfo不存在用户-UID:'.$param['username']);
            return $this->response->json(['status' => 'Error']);
        }


        $data = [
            'Balance' => (float)bcdiv((string)$res['data'],'100',2),
            'Message' =>  'OK',
            'Status' => 0
        ];
        return $this->response->json($data);

    }


    /**
     * bonusWin
     * @return void
     */
    #[RequestMapping(path: 'bonus-win')]
    public function bonusWin(){
        $param = $this->request->all();
        $this->logger->error('joker-bonusWin:'.json_encode($param));


        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);




        $uid = $param['username'];
        $res = $this->common->setUserMoney($uid,2);
        if($res['code'] != 200){
            $this->logger->error('joker玩家-UID:'.$uid.'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }
        $money = $res['data'];

        $slots_bonus = Db::table('slots_bonus')->where(['terrace_name' => 'joker','transactionId' => $param['id']])->first();
        if($slots_bonus){
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }

        $amount =  bcmul((string)$param['amount'],'100',0);


        $slots_bonus_data = [
            'uid' => $param['username'],
            'terrace_name' => 'joker',
            'roundId' => $param['roundid'],
            'transactionId' => $param['id'],
            'amount' => $amount,
            'type' => 1,
            'bonustime' => time(),
        ];

        $res = $this->common->slotsBonus($slots_bonus_data,(int)$amount);
        if(!$res)return $this->response->json(['error' => 100,'description' => 'Internal server error. Casino Operator will return this error code if their system has internal problem and cannot process the request at the moment and Operator logic requires a retry of the request. Request will follow Reconciliation process']);

        $data = [
            'Balance' => (float)bcdiv(bcadd((string)$money,$amount,0),'100',2),
            'Message' =>  'OK',
            'Status' => 0
        ];
        return $this->response->json($data);




    }


    /**
     * bonusWin
     * @return void
     */
    #[RequestMapping(path: 'jackpot-win')]
    public function jackpotWin(){
        $param = $this->request->all();
        $this->logger->error('joker-jackpotWin:'.json_encode($param));


        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);




        $uid = $param['username'];
        $res = $this->common->setUserMoney($uid,2);
        if($res['code'] != 200){
            $this->logger->error('joker玩家-UID:'.$uid.'不存在');
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }
        $money = $res['data'];

        $slots_bonus = Db::table('slots_bonus')->where(['terrace_name' => 'joker','transactionId' => $param['id']])->first();
        if($slots_bonus){
            return $this->response->json(['error' => 2,'description' => 'Player not found or is logged out. Should be returned in the response on any request sent by Pragmatic Play if the player can’t be found or is logged out at Casino Operator’s side.']);
        }

        $amount =  bcmul((string)$param['amount'],'100',0);


        $slots_bonus_data = [
            'uid' => $param['username'],
            'terrace_name' => 'joker',
            'roundId' => $param['roundid'],
            'transactionId' => $param['id'],
            'amount' => $amount,
            'type' => 1,
            'bonustime' => time(),
        ];

        $res = $this->common->slotsBonus($slots_bonus_data,(int)$amount);
        if(!$res)return $this->response->json(['error' => 100,'description' => 'Internal server error. Casino Operator will return this error code if their system has internal problem and cannot process the request at the moment and Operator logic requires a retry of the request. Request will follow Reconciliation process']);

        $data = [
            'Balance' => (float)bcdiv(bcadd((string)$money,$amount,0),'100',2),
            'Message' =>  'OK',
            'Status' => 0
        ];
        return $this->response->json($data);




    }


    /**
     * 事务
     * @return void
     */
    #[RequestMapping(path: 'transaction')]
    public function transaction(){

        $param = $this->request->all();
        $this->logger->error('joker-transaction:'.json_encode($param));


        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);


        $uid = $param['username'];
        $userinfo = $this->common->getUserInfo($uid,2);
        if(!$userinfo){
            $this->logger->error("joker验证账号接口uid错误--uid:".$uid);
            return $this->response->json(['status' => 'Error']);
        }

        $roundId = $param['id'];
        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($roundId);
        if($slots_log){
            $this->logger->error('joker该笔订单已经通知过-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }


        $slots_game = Db::table('slots_game')->select('id','englishname')->where(['terrace_id' => 16,'slotsgameid' => $param['gamecode']])->first();

        $betAmount = bcmul((string)$param['amount'],'100',0);
        $winAmount = bcmul((string)$param['result'],'100',0);

        $slotsData = [
            'betId' => $param['id'],
            'parentBetId' => $param['roundid'],
            'uid' => $param['username'],
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['gamecode'],
            'englishname' => $slots_game['englishname'],
            'game_id' => $slots_game['id'],
            'terrace_name' => 'joker',
            'transaction_id' => $roundId,
            'betTime' => time(),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
            'is_settlement' => 1,
        ];
        $this->common->logDealWith($slotsData,0,0,$betAmount,$winAmount,2);

        $data = [
            'Balance' => (float)bcdiv(bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2),
            'Message' =>  'OK',
            'Status' => 0
        ];

        return $this->response->json($data);

    }


    /**
     * 提现
     * @return void
     */
    #[RequestMapping(path: 'withdraw')]
    public function withdraw(){

        $param = $this->request->all();
        $this->logger->error('joker-withdraw:'.json_encode($param));


        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);


        $uid = $param['username'];
        $res = $this->common->setUserMoney($uid,2);
        if($res['code'] != 200){ //
            $this->logger->error('joker玩家-UID:'.$uid.'不存在');
            return $this->response->json(['error' =>['code' => '3004','message' => 'PlayerNotFound'] ,'data' => null]);
        }
        $money = $res['data'];


        $slots_bonus = Db::table('slots_bonus')->where(['terrace_name' => 'joker','transactionId' => $param['id']])->first();
        if($slots_bonus){
            $data = [
//                'adjust_amount' => (float)$param['transfer_amount'],
//                'balance_before' =>(float)bcdiv((string)$money,'100',2),
//                'balance_after' =>(float)bcdiv((string)$money,'100',2),
//                'updated_time' => $param['adjustment_time'],
            ];
            return $this->response->json(['error' =>null,'data' =>$data]);
        }
        if($param['amount'] > 0)$param['amount'] = bcsub('0',(string)$param['amount'],2);
        $amount =  bcmul((string)$param['amount'],'100',0);


        $slots_bonus_data = [
            'uid' =>$uid,
            'terrace_name' => 'joker',
            'roundId' => 0,
            'transactionId' => $param['id'],
            'amount' => $amount,
            'type' => 3,
            'bonustime' => time(),
        ];

        $res = $this->common->slotsBonus($slots_bonus_data,(int)$amount);
        if(!$res)return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);

        $data = [
            'Balance' => (float)bcdiv(bcadd((string)$money,$amount,0),'100',2),
            'Message' =>  'OK',
            'Status' => 0
        ];
        return $this->response->json($data);

    }



    /**
     * 充值
     * @return void
     */
    #[RequestMapping(path: 'deposit')]
    public function deposit(){

        $param = $this->request->all();
        $this->logger->error('joker-deposit:'.json_encode($param));


        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200)return $this->response->json($resBasicEfficacy['data']);


        $uid = $param['username'];
        $res = $this->common->setUserMoney($uid,2);
        if($res['code'] != 200){ //
            $this->logger->error('joker玩家-UID:'.$uid.'不存在');
            return $this->response->json(['error' =>['code' => '3004','message' => 'PlayerNotFound'] ,'data' => null]);
        }
        $money = $res['data'];


        $slots_bonus = Db::table('slots_bonus')->where(['terrace_name' => 'joker','transactionId' => $param['id']])->first();
        if($slots_bonus){
            $data = [
//                'adjust_amount' => (float)$param['transfer_amount'],
//                'balance_before' =>(float)bcdiv((string)$money,'100',2),
//                'balance_after' =>(float)bcdiv((string)$money,'100',2),
//                'updated_time' => $param['adjustment_time'],
            ];
            return $this->response->json(['error' =>null,'data' =>$data]);
        }

        $amount =  bcmul((string)$param['amount'],'100',0);


        $slots_bonus_data = [
            'uid' =>$uid,
            'terrace_name' => 'joker',
            'roundId' => 0,
            'transactionId' => $param['id'],
            'amount' => $amount,
            'type' => 3,
            'bonustime' => time(),
        ];

        $res = $this->common->slotsBonus($slots_bonus_data,(int)$amount);
        if(!$res)return $this->response->json(['error' =>['code' => '1034','message' => 'Invalid request'] ,'data' => null]);

        $data = [
            'Balance' => (float)bcdiv(bcadd((string)$money,$amount,0),'100',2),
            'Message' =>  'OK',
            'Status' => 0
        ];
        return $this->response->json($data);

    }
    /**
     * @param string|int $uid 用户UID
     * @param string|int $gameID 游戏ID
     * @return string
     */
    public function getGameUrl(string|int $uid,string|int $gameID):string{
        $token = $this->common->getUserToken($uid);
        $gamEuRL = config('jokerslots.game_url')."/playGame?token=$token&appID=".config('jokerslots.appId')."&gameCode=$gameID&language=".config('jokerslots.language')."&mobile=true";
        $this->logger->error('joker-getGameUrl:'.$gamEuRL);
        return $gamEuRL;


    }

    /**
     * 通过回合ID获取三方数据
     * @param string $roundId
     */
    private function getRoundIdSlotsLog(string $roundId){
        $slots_log = Db::table('slots_log_'.date('Ymd'))->where('parentBetId',$roundId)->first();
        if(!$slots_log) $slots_log = Db::table('slots_log_'.date('Ymd',strtotime( '-1 day')))->where('parentBetId',$roundId)->first();
        return $slots_log;
    }

    /**
     * @param array $param 三方数据
     * @return array
     */
    private function basicEfficacy(array $param):array{

        if($param['appid'] != config('jokerslots.appId')){
            $this->logger->error("joker运营商信息错误--我方appid:".config('jokerslots.appId')."--三方传入我方appid:".$param['appid']);
            return ['code' => 201,'data' => ['status' => 'INVALID_TOKEN_ID']];
        }

        $getHash = $this->getHash($param);
        if($getHash != $param['hash']){
            $this->logger->error("joker验证错误--我方的Hash:".$getHash."--三方传入的Hash:".$param['hash']);
            return ['code' => 201,'data' => ['status' => 'INVALID_TOKEN_ID']];
        }
        return ['code' => 200,'data' => ['status' => 'OK']];
    }

    /**
     * @param array $body
     * @return string
     */
    private function getHash(array $body):string{
        unset($body['hash']);
        $array = array_filter($body);
        $array = array_change_key_case($array, CASE_LOWER);
        ksort($array);

        $rawData = '';
        foreach ($array as $Key => $Value)
            $rawData .=  $Key . '=' . $Value . '&' ;

        $rawData = substr($rawData,0, -1);
        $rawData .= config('jokerslots.appSecret');
        return md5($rawData);

    }
}








