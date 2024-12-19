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
#[Controller(prefix:"evolutionslots")]
class EvoController extends AbstractController{


    #[Inject]
    protected Common $common;
    #[Inject]
    protected Guzzle $guzzle;

    /**效验
     * @return
     */
    #[RequestMapping(path: 'check')]
    public function check(){
        $param = $this->request->all();
//        $this->logger->error('evo-check:'.json_encode($param));
        if($param['authToken'] != config('evoslots.authToken')){
            $this->logger->error("Evo运营商信息错误--我方authToken:".config('evoslots.authToken')."--三方传入我方authToken:".$param['authToken']);
            return $this->response->json(['status' => 'Error']);
        }
        $userUid = $this->getApiToken($param['sid']);
        if(!$userUid || $userUid != $param['userId']){
            $this->logger->error("用户获取效验失败");
            return $this->response->json(['status' => 'Error']);
        }
        return $this->response->json(['status' => 'OK','sid' => $this->setApiToken($param['userId']),'uuid' => $param['uuid']]);
    }


    /**获取用户余额
     * @return
     */
    #[RequestMapping(path: 'balance')]
    public function balance(){
        $param = $this->request->all();
//        $this->logger->error('evo-balance:'.json_encode($param));
        if($param['authToken'] != config('evoslots.authToken')){
            $this->logger->error("Evo运营商信息错误--我方authToken:".config('evoslots.authToken')."--三方传入我方authToken:".$param['authToken']);
            return $this->response->json(['status' => 'Error']);
        }
        $userUid = $this->getApiToken($param['sid']);
        if(!$userUid || $userUid != $param['userId']){
            $this->logger->error("用户获取效验失败");
            return $this->response->json(['status' => 'Error']);
        }
        $res = $this->common->setUserMoney($userUid);
        if($res['code'] != 200){
            $this->logger->error("Evo验证账号接口uid错误--uid:".$userUid);
            return $this->response->json(['status' => 'Error']);
        }
        $data = [
            'status' => 'OK',
            'balance' => (float)bcdiv((string)$res['data'],'100',2),
            'uuid' => $param['uuid']
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
//        $this->logger->error('evo-debit:'.json_encode($param));


        //基础效验
        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200) return $this->response->json($resBasicEfficacy['data']);


        $userinfo = $this->common->getUserInfo($param['userId']);

        //用户余额
        $money = $userinfo['coin'] + $userinfo['bonus'];



        //下注不能为负数
        if($param['transaction']['amount'] < 0){
            $this->logger->error("Evo下注金额错误--debitAmount:".$param['debitAmount']);
            return $this->response->json(['status' => 'Error']);
        }

        //正式环境打开
        $res = $this->common->getUserRunGameTage($param['userId'],'evo');
        if(!$res)return $this->response->json(['status' => 'Error']);


        $debitAmount = bcmul((string)$param['transaction']['amount'],'100',0);
        if($money < $debitAmount){
            $this->logger->error('evo玩家-UID:'.$param['userId'].'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$debitAmount);
            return $this->response->json(['status' => 'INSUFFICIENT_FUNDS']);
        }

        $roundId = $param['transaction']['refId'];


        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($roundId);
        if($slots_log){
            $this->logger->error('evo该笔订单已经通知过-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }


        $slotsData = [
            'betId' => $roundId,
            'parentBetId' => $roundId,
            'uid' => $param['userId'],
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['game']['id'],
            'englishname' => $param['game']['type'],
            'game_id' => 0,
            'terrace_name' => 'evo',
            'transaction_id' => $param['transaction']['id'],
            'betTime' => time(),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
            'is_settlement' => 0,
        ];
        $res = $this->common->slotsLog($slotsData,$userinfo['coin'],$userinfo['bonus'],$debitAmount,'0',2);
        if($res['code'] != 200)return $this->response->json(['status' => 'Error']);
        $data = [
            'status' => 'OK',
            'balance' =>  (float)bcdiv((string)$res['data'],'100',2),
            'uuid' => $param['uuid']
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
//        $this->logger->error('evo-credit:'.json_encode($param));

        //基础效验
        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200) return $this->response->json($resBasicEfficacy['data']);

        $userinfo = $this->common->getUserInfo($param['userId']);





        $roundId = $param['transaction']['refId'];
        $slots_log = $this->common->SlotsLogView($roundId);
        if(!$slots_log){
            $this->logger->error('"Evo该笔订单不存在-betID:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }

        $winAmount = max(bcmul((string)$param['transaction']['amount'],'100',2), '0');


        if(!in_array($slots_log['is_settlement'],[0,4])){
            $this->logger->error('Evo该笔订单已经通知过-betID:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }
        $this->common->resultDealWith($slots_log,$userinfo,$winAmount,2);
        

        
        $res = $this->common->setUserMoney($param['userId']);
        if($res['code'] != 200){
            $this->logger->error('Evo数据库-userinfo不存在用户-UID:'.$param['userId']);
            return $this->response->json(['status' => 'Error']);
        }


        $data = [
            'status' => 'OK',
            'balance' =>  (float)bcdiv((string)$res['data'],'100',2),
            'uuid' => $param['uuid']
        ];
        return $this->response->json($data);
    }



    /**
     * 回滚
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'cancel')]
    public function cancel(){
        $param = $this->request->all();
//        $this->logger->error('evo-cancel:'.json_encode($param));

        //基础效验
        $resBasicEfficacy = $this->basicEfficacy($param);
        if($resBasicEfficacy['code'] != 200) return $this->response->json($resBasicEfficacy['data']);

        //获取用户金额

        $roundId = $param['transaction']['refid'];

        $slots_log = $this->common->SlotsLogView($roundId);
        if(!$slots_log){
            $this->logger->error('Evo该笔回滚订单不存在-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }
        if($slots_log['is_settlement'] == 2){
            $this->logger->error('Evo该笔订单已经通知过-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }

        //如果回滚金额大于了下注金额
        $rollbackAmount = bcmul((string)$param['transaction']['amount'],'100',0);
        if(bcadd((string)$slots_log['cashBetAmount'],(string)$slots_log['bonusBetAmount'],0) < $rollbackAmount){
            $this->logger->error('Evo该笔订单已经通知过-回滚金额大于下注金额-roundId:'.$roundId);
            return $this->response->json(['status' => 'Error']);
        }

        $this->common->setRefundSlotsLog($slots_log,$rollbackAmount);
        $res = $this->common->setUserMoney($param['userId']);
        if($res['code'] != 200){
            $this->logger->error('Evo数据库-userinfo不存在用户-UID:'.$param['userId']);
            return $this->response->json(['status' => 'Error']);
        }


        $data = [
            'status' => 'OK',
            'balance' =>  (float)bcdiv((string)$res['data'],'100',2),
            'uuid' => $param['uuid']
        ];
        return $this->response->json($data);

    }


    /**
     * @param string|int $uid 用户UID
     * @param string $tableId 启动的桌号ID
     * @return string
     */
    public function getGameUrl(string|int $uid,string $tableId = ''):string{
        $url = config('evoslots.api_url').'/ua/v1/'.config('evoslots.casinoKey').'/'.config('evoslots.apiToken');
        $token = $this->common->getUserToken($uid);
        $body = [
            'uuid' => $token,
            'player' => [
                'id' => (string)$uid,
                'update' => true,
                'firstName' => substr((string)$uid,0,3),
                'lastName' => substr((string)$uid,3),
                'country' => config('evoslots.country'),
                'language' => config('evoslots.language'),
                'currency' => config('evoslots.currency'),
                'session' => [
                    'id' => $this->setApiToken($uid),
                    'ip' => appCommon::getIp(),
                ],
            ],
            'config' => [
                'channel' => [
                    'wrapped' => false,
                    'mobile' => true,
                ],
            ],
        ];

        if($tableId) $body['config']['game']['table']['id'] = $tableId;
        $data = $this->post($url,$body);
        if(isset($data['entry']) || isset($data['entryEmbedded'])){
            return $data['entry'] ?? $data['entryEmbedded'];
        }
        return '';

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
            'Content-Type' => 'application/json',
        ];
        return $this->guzzle->post($url,$body,$herder);
    }


    /**
     * 设置用户的apiToken
     * @param $uid 用户UID
     * @return void
     */
    private function setApiToken($uid){
//        $token = md5(time().$uid);
//        Db::table('evo_token')->updateOrInsert(
//            ['uid' => $uid],
//            ['token' => $token]
//        );
//        return $token;
        return $this->common->getUserToken((int)$uid);


    }

    /**
     * 获取用户的UID
     * @param $token
     * @return false|mixed|null
     */
    private function getApiToken($token){
//        return Db::table('evo_token')->where('token',$token)->value('uid');
        return $this->common->getUserUid((string)$token);
    }

    /**
     * @param array $param 三方数据
     * @return array
     */
    private function basicEfficacy(array $param):array{

        if($param['authToken'] != config('evoslots.authToken')){
            $this->logger->error("Evo运营商信息错误--我方authToken:".config('evoslots.authToken')."--三方传入我方authToken:".$param['authToken']);
            return ['code' => 201,'data' => ['status' => 'INVALID_TOKEN_ID']];
        }

        //检查供应商是不是自己平台
        if($param['currency'] != config('evoslots.currency')){
            $this->logger->error("Evo运营商信息错误--我方currency:".config('evoslots..currency')."--三方传入我方currency:".$param['currency']);
            return ['code' => 201,'data' => ['status' => 'Error']];
        }

        //效验UID与三方的UID是否一致
        $uid = $this->getApiToken((string)$param['sid']);
        if(!$uid){
            $this->logger->error("Evo验证账号接口token错误--token:".$param['sid']);
            return ['code' => 201,'data' => ['status' => 'INVALID_TOKEN_ID']];
        }


        if($uid != $param['userId']){
            $this->logger->error("Evo验证账号接口UID错误--token获取的UID:".$uid."--三方传入的playerID:".$param['userId']);
            return ['code' => 201,'data' => ['status' => 'INVALID_TOKEN_ID']];
        }
        return ['code' => 200,'data' => ['status' => 'OK']];
    }
}








