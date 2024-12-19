<?php
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\slots;

use App\Common\Guzzle;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Swoole\Coroutine;
use function Hyperf\Config\config;
use Hyperf\Coroutine\Exception\ParallelExecutionException;
use Hyperf\Coroutine\Parallel;
/**
 * 这里沙巴体育请求我们所有的接口都是gzip，手动修改底层代码解压，这里就不需要解压了
 */
#[Controller(prefix:"sbsslots")]
class SbsslotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Common $common;


    /**
     * 获取玩家余额
     * @return void
     */
    #[RequestMapping(path: 'getbalance')]
    public function getBalance(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取getbalance时解压失败');
            return $this->response->json($this->errorData(4));
        }
        [$key,$param] = $this->getData($requestData);

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }

        //获取系统UID
        $param['userId'] = $this->getUid($param['userId']);

        $res = $this->common->setUserMoney($param['userId']);
        if($res['code'] != 200){
            $this->logger->error('sbs玩家-UID:'.$param['userId'].'不存在');
            return $this->response->json($this->errorData(5));
        }
        $data = [
            'status' => 0,
            'userId' => $param['userId'],
            'balance' => (float)bcdiv((string)$res['data'],'100',2),
            'balanceTs' => $this->common->getDate(),
            'msg' => null
        ];
        return $this->response->json($data);
    }


    /**
     * 商户提供此方法，沙巴体育通过呼叫此方法提供下注细节给商户
     当在沙巴系统下注失败时，沙巴体育将呼叫 Cancel Bet API 方法以取消注单
     正常情况下，在 10 分钟内如果没有收到 ConfirmBet 或 CancelBet，可以呼叫 CheckTicket
    Status API 来确认注单状况。
     如果没有收到商户的回复，沙巴体育将呼叫 Cancel Bet API 方法以取消注单
     当下注单状态为未结算

     * @return void
     */
    #[RequestMapping(path: 'placebet')]
    public function placeBet(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取placeBet时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'userId' => 'required',
                'betAmount' => 'required',//注单金额
                'actualAmount' => 'required',//实际注单金额
                'leagueName_en' => 'required',
                'refId' => 'required',
                'debitAmount' => 'required',//需从玩家扣除的金额。
                'creditAmount' => 'required',//需增加在玩家的金额。
                'matchId' => 'required',//需增加在玩家的金额。
            ],
            [
                'operationId.required' => 'operationId is required',
                'userId.required' => 'userId is required',
                'betAmount.required' => 'betAmount is required',
                'actualAmount.required' => 'actualAmount is required',
                'leagueName_en.required' => 'leagueName_en is required',
                'refId.required' => 'refId is required',
                'debitAmount.required' => 'debitAmount is required',
                'creditAmount.required' => 'creditAmount is required',
                'matchId.required' => 'matchId is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-placebet-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }

        //获取系统UID
        $param['userId'] = $this->getUid($param['userId']);

        $res = $this->common->getUserRunGameTage($param['userId'],'sbs');
        if(!$res)return $this->response->json($this->errorData(2));

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }


        $userinfo = $this->common->getUserInfo($param['userId']);
        if(!$userinfo){
            $this->logger->error('sbs玩家-UID:'.$param['userId'].'不存在');
            return $this->response->json($this->errorData(2));
        }



        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',2);

        if($money < $param['actualAmount']){
            $this->logger->error('sbs玩家-UID:'.$param['userId'].'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$param['actualAmount']);
            return $this->response->json($this->errorData(3));
        }

        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($param['refId']);
        if($slots_log){
            return $this->response->json($this->errorData());
        }


        $slotsData = [
            'betId' => $param['refId'],
            'parentBetId' => $param['refId'],
            'really_betAmount' =>  bcmul((string)$param['betAmount'],'100',0),
            'uid' => $param['userId'],
            'puid' => $userinfo['puid'],
            'slotsgameid' => '',
            'englishname' => 'Saba Sports',
            'game_id' => 0,
            'terrace_name' => 'sbs',
            'transaction_id' => $param['operationId'],
            'betTime' => time(),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
            'is_settlement' => 0,
            'is_sports' => 1,
        ];
        $res = $this->common->installSlotsLog($slotsData,2);
        if(!$res)return $this->response->json($this->errorData(5));


        $data = [
            'status' => 0,
            'refId' => $param['refId'],
            'licenseeTxId' => rand(00000000,9999999999).time(),
            'msg' => 'Success',
        ];
        return $this->response->json($data);
    }



    /**
     * 商户提供此方法给沙巴体育呼叫
     当沙巴体育通过 PlaceBet 方法收到成功结果，沙巴体育将会呼叫 ConfirmBet
     当呼叫失败时，将会持续呼叫 Confirm Bet 直到成功或达到重试最大次数上限。详情可参阅
    附录:请求重试机制。
     当赔率变得更佳时，request.isOddsChanged 值应该为 true 且 request.odds 以及
    request.actualAmount 将会和 PlaceBet 里面的值有所不同
     * @return void
     */
    #[RequestMapping(path: 'confirmbet')]
    public function confirmbet(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取confirmBet时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'userId' => 'required',
                'txns' => 'required',
            ],
            [
                'operationId.required' => 'operationId is required',
                'userId.required' => 'userId is required',
                'txns.required' => 'txns is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-confirmbet-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }

        //获取系统UID
        $param['userId'] = $this->getUid($param['userId']);


        $userinfo = $this->common->getUserInfo($param['userId']);
        if(!$userinfo){
            $this->logger->error('sbs玩家-UID:'.$param['userId'].'不存在');
            return $this->response->json($this->errorData(2));
        }

//        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',2);
        $txns = $param['txns'];
        //订单不存在
        $slots_log = $this->common->SlotsLogView($txns[0]['refId'],0,2);
        if(!$slots_log){
            $this->logger->error('sbs-refId未找到-refId:'.$txns[0]['refId']);
            return $this->response->json($this->errorData(4));
        }


        $res = $this->common->slotsLog($slots_log,$userinfo['coin'],$userinfo['bonus'],bcmul((string)$txns[0]['actualAmount'],'100',0),'0',3,2);
        if($res['code'] != 200)return $this->response->json($this->errorData(5));

        $data = [
            'status' => 0,
            'balance' => bcdiv((string)$res['data'],'100',2),
        ];
        return $this->response->json($data);
    }


    /**
     * 商户提供此方法，沙巴体育通过呼叫此方法提供下注细节给商户
     当在沙巴系统下注失败时，沙巴体育将呼叫 Cancel Bet API 方法以取消注单
     正常情况下，在 10 分钟内如果没有收到 ConfirmBet 或 CancelBet，可以呼叫 CheckTicket
    Status API 来确认注单状况。
     如果没有收到商户的回复，沙巴体育将呼叫 Cancel Bet API 方法以取消注单
     当下注单状态为未结算

     * @return void
     */
    #[RequestMapping(path: 'placebetparlay')]
    public function placeBetParlay(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取placeBetParlay时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'userId' => 'required',
                'txns' => 'required',
                'ticketDetail' => 'required',
                'totalBetAmount' => 'required',
            ],
            [
                'operationId.required' => 'operationId is required',
                'userId.required' => 'userId is required',
                'txns.required' => 'txns is required',
                'ticketDetail.required' => 'ticketDetail is required',
                'totalBetAmount.required' => 'totalBetAmount is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-placebet-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }


        //获取系统UID
        $param['userId'] = $this->getUid($param['userId']);

        $res = $this->common->getUserRunGameTage($param['userId'],'sbs');
        if(!$res)return $this->response->json($this->errorData(2));

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }


        $userinfo = $this->common->getUserInfo($param['userId']);
        if(!$userinfo){
            $this->logger->error('sbs玩家-UID:'.$param['userId'].'不存在');
            return $this->response->json($this->errorData(2));
        }



        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',2);

        if($money < $param['totalBetAmount']){
            $this->logger->error('sbs玩家-UID:'.$param['userId'].'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$param['totalBetAmount']);
            return $this->response->json($this->errorData(3));
        }
        $refIdArray = [];
        $parallel = new Parallel(10);
        foreach ($param['txns'] as $v){
            $parallel->add(function () use($v,$param,$userinfo){
                $slots_log = $this->common->SlotsLogView($v['refId']);
                if($slots_log) return $v['refId'];
                $slotsData = [
                    'betId' => $v['refId'],
                    'parentBetId' => $v['refId'],
                    'really_betAmount' =>  bcmul((string)$v['betAmount'],'100',0),
                    'uid' => $param['userId'],
                    'puid' => $userinfo['puid'],
                    'slotsgameid' => '',
                    'englishname' => 'Saba Sports',
                    'game_id' => 0,
                    'terrace_name' => 'sbs',
                    'transaction_id' => $param['operationId'],
                    'betTime' => time(),
                    'channel' => $userinfo['channel'],
                    'package_id' => $userinfo['package_id'],
                    'createtime' => time(),
                    'is_settlement' => 0,
                    'is_sports' => 1,
                ];
                $this->common->installSlotsLog($slotsData,2);
                return $v['refId'];
            });

        }

        try{
            $refIdArray = $parallel->wait();
        } catch(ParallelExecutionException $e){
            $this->logger->error('sbs-placebetparlay-失败协程中-betId:'.json_encode($e->getResults()));
            $this->logger->error('sbs-placebetparlay-失败协程中出现的异常:'.json_encode($e->getThrowables()));
        }

        $data = [];
        foreach ($refIdArray as $value){
            $data[] = [
                'refId' => $value,
                'licenseeTxId' => rand(00000000,9999999999).time(),
            ];
        }

        return $this->response->json(['status' => 0,'txns' => $data]);
    }


    /**
     * 商户提供此方法给沙巴体育呼叫
     当沙巴体育通过 PlaceBet 方法收到成功结果，沙巴体育将会呼叫 ConfirmBet
     当呼叫失败时，将会持续呼叫 Confirm Bet 直到成功或达到重试最大次数上限。详情可参阅
    附录:请求重试机制。
     当赔率变得更佳时，request.isOddsChanged 值应该为 true 且 request.odds 以及
    request.actualAmount 将会和 PlaceBet 里面的值有所不同
     * @return void
     */
    #[RequestMapping(path: 'confirmbetparlay')]
    public function confirmBetParlay(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取confirmBetParlay时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'userId' => 'required',
                'txns' => 'required',
            ],
            [
                'operationId.required' => 'operationId is required',
                'userId.required' => 'userId is required',
                'txns.required' => 'txns is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-confirmBetParlay-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }

        //获取系统UID
        $param['userId'] = $this->getUid($param['userId']);


        $userinfo = $this->common->getUserInfo($param['userId']);
        if(!$userinfo){
            $this->logger->error('sbs玩家-UID:'.$param['userId'].'不存在');
            return $this->response->json($this->errorData(2));
        }



        foreach ($param['txns'] as  $key => $v){
            //第二次就要重新计算数据
            if($key>0)$userinfo = $this->common->getUserInfo($param['userId']);

            $slots_log = $this->common->SlotsLogView($v['refId'],0,2);
            if(!$slots_log) continue;
            $res = $this->common->slotsLog($slots_log,$userinfo['coin'],$userinfo['bonus'],bcmul((string)$v['actualAmount'],'100',0),'0',3,2);
            if(!$res)$this->logger->error('sbs-confirmbetparlay-slotsLog-失败-refId:'.$v['refId']);
        }


        $res = $this->common->setUserMoney($param['userId']);

        $data = [
            'status' => 0,
            'balance' => bcdiv((string)$res['data'],'100',2),
        ];
        return $this->response->json($data);
    }


    /**
    ⚫ 描述
    商户提供此方法给沙巴体育呼叫
     当在沙巴下注失败，或是非预期问题造成沙巴没有收到来自商户的 placebet 响应导致逾
    时，沙巴体育将会呼叫 Cancel Bet
     当呼叫失败时，将会持续呼叫 Cancel Bet 直到成功或达到重试最大次数上限。详情可参阅
    附录:请求重试机制。
     支持针对单一玩家同时取消多笔交易
     * @return void
     */
    #[RequestMapping(path: 'cancelbet')]
    public function cancelbet(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取cancelBet时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'userId' => 'required',
                'txns' => 'required',
            ],
            [
                'operationId.required' => 'operationId is required',
                'userId.required' => 'userId is required',
                'txns.required' => 'txns is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-cancelbet-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }

        //获取系统UID
        $param['userId'] = $this->getUid($param['userId']);


        $userinfo = $this->common->getUserInfo($param['userId']);
        if(!$userinfo){
            $this->logger->error('sbs玩家-UID:'.$param['userId'].'不存在');
            return $this->response->json($this->errorData(2));
        }

        //删除历史数据
        $txns = $param['txns'];

        $parallel = new Parallel(10);
        foreach ($txns as $v){
            $parallel->add(function () use($v){
                $this->common->deleteSlotsLog($v['refId'],2);
                return $v['refId'];
            });
        }
        try{
            $parallel->wait();
        } catch(ParallelExecutionException $e){
            $this->logger->error('删除sbs游戏记录失败协程中-betId:'.json_encode($e->getResults()));
            $this->logger->error('删除sbs游戏记录失败协程中出现的异常:'.json_encode($e->getThrowables()));
        }
        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',2);

        $data = [
            'status' => 0,
            'balance' => $money,
        ];
        return $this->response->json($data);
    }



    /**
    ⚫ 描述 结算
    商户提供此方法给沙巴体育呼叫
     当赛事结束后，沙巴体育会通过此方法传输交易，或是 Cash Out 交易被接受后沙巴体育将
    会通过此方法传输交易。
     当呼叫失败时，将会持续呼叫 Settle 直到成功或达到重试最大次数上限。详情可参阅附录:
    请求重试机制
     * @return void
     */
    #[RequestMapping(path: 'settle')]
    public function settle(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取Settle时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'txns' => 'required',
            ],
            [
                'operationId.required' => 'operationId is required',
                'txns.required' => 'txns is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-settle-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }

        $txns = $param['txns'];



        foreach ($txns as $v){

            //获取系统UID
            $v['userId'] = $this->getUid($v['userId']);

            $userinfo = $this->common->getUserInfo($v['userId']);
            if(!$userinfo){
                $this->logger->error('sbs数据库settle协程时-userinfo不存在用户-UID:'.$v['userId']);
                continue;
            }
            $slots_log = $this->common->SlotsLogView($v['refId'],0,2);
            if(!$slots_log || !in_array($slots_log['is_settlement'],[0,4])){
                $this->logger->error('sbs-refId未找到或者已经执行了-refId:'.$v['refId']);
                continue;
            }
            $winAmount = max($v['payout'], '0');
            if($slots_log['is_settlement'] == 0){
                $this->common->resultDealWith($slots_log,$userinfo,bcmul((string)$winAmount,'100',0),2,2);
            }else{
                $this->common->updateSlotsLog($v['refId'],['is_settlement' => 1],2);
                $this->common->resettlementDealWith($slots_log,$userinfo,bcmul((string)$winAmount,'100',0),2);
            }


        }


        return $this->response->json(['status' => '0']);
    }


    /**
     * 赛事重新计算
     *
    ⚫ 描述
    商户提供此方法给沙巴体育呼叫
     当赛事结束后发生因重新结算，导致 winlostDate、status、creditAmount、debitAmount 或
    payout 其中一个参数异动时，沙巴体育会通过此方法传输交易
     当呼叫失败时，将会持续呼叫 Resettle 直到成功或达到重试最大次数上限。详情可参阅附
    录:请求重试机制
    ⚫ 方法 URL
     * @return void
     */
    #[RequestMapping(path: 'resettle')]
    public function resettle(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取Resettle时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'txns' => 'required',
            ],
            [
                'operationId.required' => 'operationId is required',
                'txns.required' => 'txns is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-resettle-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }

        $txns = $param['txns'];

        foreach ($txns as $v){
            $v['userId'] = $this->getUid($v['userId']);

            $userinfo = $this->common->getUserInfo($v['userId']);
            if(!$userinfo){
                $this->logger->error('sbs数据库resettle协程时-userinfo不存在用户-UID:'.$v['userId']);
                continue;
            }
            $winAmount = max($v['payout'], '0');
            $slots_log = $this->common->SlotsLogView($v['refId'],0,2);
            if(!$slots_log || $slots_log['is_settlement'] != 1){
                $this->logger->error('sbs-resettle-协程未找到或者为满足需要执行条件-refId:'.$v['refId']);
                continue;
            }
            $res = $this->common->resettlementDealWith($slots_log,$userinfo,bcmul((string)$winAmount,'100',0),2);
            if(!$res){
                $this->logger->error('sbs-resettle-协程事务处理失败-UID:'.$v['userId'].'三方游戏-betId:'.$v['refId']);
            }
        }

        return $this->response->json(['status' => '0']);
    }



    /**
     *赛事取消派彩，重新进入进行中
    ⚫ 描述
     商户提供此方法给沙巴体育呼叫
     当呼叫失败时，将会持续呼叫 Unsettle 直到成功或达到重试最大次数上限。详情可参阅附
    录:请求重试机制
    ⚫ 方法 URL
     * @return void
     */
    #[RequestMapping(path: 'unsettle')]
    public function unsettle(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取Unsettle时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'txns' => 'required',
            ],
            [
                'operationId.required' => 'operationId is required',
                'txns.required' => 'txns is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-resettle-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }

        foreach ($param['txns'] as $txns){
            $txns['userId'] = $this->getUid($txns['userId']);
            //处理每一笔重试订单
            $slots_log = $this->common->SlotsLogView($txns['refId'],0,2);
            if(!$slots_log || $slots_log['is_settlement'] != 1)continue;
            $userinfo = $this->common->getUserInfo($txns['userId']);
            if(!$userinfo){
                $this->logger->error('sbs数据库unsettle协程-userinfo不存在用户-UID:'.$txns['userId']);
                continue;
            }
            if($txns['debitAmount'] > 0){
                $res = $this->common->unresettlementDealWith($slots_log,$userinfo,2);
                if(!$res) $this->logger->error('sbs数据库unsettle协程-userinfo不存在用户-UID:'.$txns['userId']);
            }else{
                $this->common->updateSlotsLog($txns['refId'],['is_settlement' => 4],2);
            }

        }

        return $this->response->json(['status' => '0']);
    }



    /**
     *赛事取消派彩，重新进入进行中
    ⚫ 描述
     商户提供此方法给沙巴体育呼叫
     当呼叫失败时，将会持续呼叫 Unsettle 直到成功或达到重试最大次数上限。详情可参阅附
    录:请求重试机制
    ⚫ 方法 URL
     * @return void
     */
    #[RequestMapping(path: 'adjustbalance')]
    public function AdjustBalance(){
        $requestData = $this->request->all();

        if(!$requestData){
            $this->logger->error('sbs获取AdjustBalance时解压失败');
            return $this->response->json($this->errorData(4));
        }

        [$key,$param] = $this->getData($requestData);

        $validator = $this->validatorFactory->make(
            $param,
            [
                'operationId' => 'required',
                'refId' => 'required',
                'userId' => 'required',
                'balanceInfo' => 'required',
            ],
            [
                'operationId.required' => 'operationId is required',
                'refId.required' => 'refId is required',
                'userId.required' => 'userId is required',
                'balanceInfo.required' => 'balanceInfo is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbs-AdjustBalance-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(4));
        }

        if($key != config('sbsslots.vendor_id')){
            $this->logger->error('sbs-vendor_id验证失败三方-vendor_id:'.$key.'我方-vendor_id:'.config('sbsslots.vendor_id'));
            return $this->response->json($this->errorData(4));
        }

        $param['userId'] = $this->getUid($param['userId']);


        $res = $this->common->setUserMoney( $param['userId']);
        if($res['code'] != 200){
            $this->logger->error('SBS玩家-UID:'. $param['userId'].'不存在');
            return $this->response->json($this->errorData(2));
        }

        $slots_bonus = Db::table('slots_bonus')->where(['terrace_name' => 'sbs','transactionId' => $param['operationId']])->first();
        if($slots_bonus)return $this->response->json(['status' => '0']);

        $amountArray = $param['balanceInfo'];

        $amount = $amountArray['creditAmount'] > 0 ? bcmul((string)$amountArray['creditAmount'],'100',0) : bcmul(bcsub('0',(string)$amountArray['debitAmount'],0),'100',0);
        if(!$amount ) return $this->response->json(['status' => '0']);




        $slots_bonus_data = [
            'uid' => $param['userId'],
            'terrace_name' => 'sbs',
            'roundId' => $param['refId'],
            'transactionId' => $param['operationId'],
            'amount' => $amount,
            'type' => 3,
            'bonustime' => time(),
        ];

        $res = $this->common->slotsBonus($slots_bonus_data,(int)$amount);
        if(!$res) return $this->response->json($this->errorData(5));

        return $this->response->json(['status' => '0']);
    }


    /**
     * 创建玩家
     * @param $player_name  玩家uid
     * @return bool|string
     */
    #[RequestMapping(path: 'playerCreated')]
    public function playerCreated($uid){
        $url  = config('sbsslots.api_url').'/CreateMember';
        $body = [
            'vendor_id' => config('sbsslots.vendor_id'),
            'vendor_member_id' =>  config('sbsslots.uid_prefix').$uid.config('sbsslots.uid_suffix'),
            'operatorId' => config('sbsslots.operatorId'),
            'username' => config('sbsslots.uid_prefix').$uid.config('sbsslots.uid_suffix'),
            'oddstype' => config('sbsslots.oddstype'),
            'currency' => config('sbsslots.currency'),
            'maxtransfer' => config('sbsslots.maxtransfer'),
            'mintransfer' => config('sbsslots.mintransfer'),
        ];

        $data = $this->guzzle->post($url,$body);
        if(!isset($data['error_code']) || !in_array($data['error_code'],[0,6,2])){
            $this->logger->error('Sbs用户创建失败-uid:'.$uid.'三方返回:'.json_encode($data));
            return ['code' => 201,'msg' => $data['message'] ?? '' ,'data' =>'' ];
        }
        return ['code' => 200,'msg' => $data['message'] ,'data' =>[]];

    }


    /**
     * 获取游戏启动url
     * @param $gameid  游戏的id
     * @param $TraceID  用户的TraceID
     * @return array
     */
    #[RequestMapping(path: 'getGameUrl')]
    public function getGameUrl($uid){

        $url  = config('sbsslots.api_url').'/GetSabaUrl';
        $body = [
            'vendor_id' => config('sbsslots.vendor_id'),
            'vendor_member_id' => config('sbsslots.uid_prefix').$uid.config('sbsslots.uid_suffix'),
            'platform' => 2,//2= h5 , 1= 桌机
        ];
        $data = $this->guzzle->post($url,$body);

        if(!isset($data['error_code']) || $data['error_code'] != 0){
            $this->logger->error('Sbs启动游戏链接失败-uid:'.$uid.'三方返回:'.json_encode($data));
            return ['code' => 201,'msg' => $data['message'] ?? '' ,'data' =>'' ];
        }
//        $gameurl = $data['Data'].'&forcedarkmode=true';
        $gameurl = $data['Data'].'&sportid=50'; //手机
//        $gameurl = $data['Data'].'&act=50'; //电脑

        return ['code' => 200,'msg' => $data['message'] ,'data' =>$gameurl];

    }

    /**
     * 检查指定的注单状态。这支 API 提供查询注单的完整历程纪录。
     * @param $gameid  游戏的id
     * @param $TraceID  用户的TraceID
     * @return array
     */
    #[RequestMapping(path: 'checkTicketStatus')]
    public function checkTicketStatus(){
        $refId = $this->request->post('refId');
        $url  = config('sbsslots.api_url').'/checkticketstatus';
        $body = [
            'vendor_id' => config('sbsslots.vendor_id'),
            'refId' => $refId,
        ];
        $data = $this->guzzle->post($url,$body);
        if(!isset($data['error_code']) || $data['error_code'] != 0){
            $this->logger->error('Sbs获取checkTicketStatus失败-三方返回:'.json_encode($data));
            return ['code' => 201,'msg' => $data['message'] ?? '' ,'data' =>'' ];
        }
        return ['code' => 200,'msg' => $data['message'] ,'data' =>$data];
    }


    /**
     *  请求重试指定的注单。
     注单最后交易状态为失败和搁置时，可以使用这支 API 重试交易，以利后续交易能继续进
    行。
     此 API 不适用在 PlaceBet, PlaceBetParlay , PlaceBet3rd 与 PlaceBetEnt。
     針對 ConfirmBetParlay/ConfirmBet 只能取得 30 天内的注单资料，但其餘可取得 100 天内的
    注单资料。
     如果注单在七日内 confirmbet 请求都没有回复成功，就无法收到 settle 请求。请先使用
    retry operation API 重送 confirmbet 并且回复成功后，再联系技术客服手动为您处理结算。
     * @param $operationId string  交易纪录 id
     * @return array
     */
    #[RequestMapping(path: 'retryOperation')]
    public function retryOperation(){
        $operationId = $this->request->post('operationId');
        $url  = config('sbsslots.api_url').'/retryoperation';
        $body = [
            'vendor_id' => config('sbsslots.vendor_id'),
            'operationId' => $operationId,
        ];
        $data = $this->guzzle->post($url,$body);
        if(!isset($data['error_code']) || $data['error_code'] != 0){
            $this->logger->error('Sbs请求重试指定的注单失败:'.json_encode($data));
            return ['code' => 201,'msg' => $data['message'] ?? '' ,'data' =>'' ];
        }
        return ['code' => 200,'msg' => $data['message'] ,'data' =>$data];
    }

    /**
     *
    沙巴体育提供此方法给商户呼叫
     取得所有已达重试上限的注单。
     只能取得 30 天内的注单资料。
     建议实作每日排程来取得重试上限注单清单，并可后续进行确认(CheckTicketStatus)和重试
    请求(RetryOperation)，已确保流程完整。
     * @param string $start_Time
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getReachLimitTrans(string $start_Time = ''){
        $url  = config('sbsslots.api_url').'/getreachlimittrans';
        $body = [
            'vendor_id' => config('sbsslots.vendor_id'),
            'start_Time' => $start_Time ?: date('Y/m/d'),
        ];
        $data = $this->guzzle->post($url,$body);
        if(!isset($data['error_code']) || $data['error_code'] != 0){
            $this->logger->error('Sbs取得所有已达重试上限的注单失败:'.json_encode($data));
            return ['code' => 201,'msg' => $data['message'] ?? '' ,'data' =>'' ];
        }
        return ['code' => 200,'msg' => $data['message'] ,'data' =>$data];
    }


    /**
     * 请求数据
     * @param $requestData
     * @return void
     */
    private function getData($requestData){
        return [$requestData['key'],$requestData['message']];
    }

    /**
     * 获取UID
     * @return void
     */
    private function getUid($sbsUid){
        $uidPrefix = config('sbsslots.uid_prefix');
        $uidArray = explode("_",$sbsUid);
        if($uidPrefix){ //有前缀就用中间的
            return $uidArray[1];
        }else{//没有前缀就用第一个
            return $uidArray[0];
        }
    }

    /**
     * 错误返回
     * @param int $type
     * @return array
     */
    private function errorData(int $type = 1):array{
        if($type == 1){ //重复的交易
            $code = '1';
            $message = 'duplicate transactions';
        }elseif ($type == 2){ //账户不存在
            $code = '203';
            $message = 'Account does not exist';
        }elseif ($type == 3){//餘額不足時回傳該編碼
            $code = "502";
            $message = "Insufficient Balance";
        }elseif ($type == 4){
            $code = "101";
            $message = "Parameter error";
        }else { //系统错误
            $code = "999";
            $message = "system error";
        }
        return [
            "status" => $code,
            "msg" => $message,
        ];
    }

}








