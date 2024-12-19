<?php
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\slots;

use App\Common\Common;
use App\Controller\slots\Common as slotsCommon;
use App\Common\Curl;
use App\Common\Guzzle;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use function Hyperf\Config\config;

#[Controller]
class JlslotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Curl $curl;

    #[Inject]
    protected slotsCommon $slotsCommon;

    /**
     * 游戏厂商 验证账号
     * @param Request $request
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    #[RequestMapping(path: '/api/auth')]
    public function userAuth(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'reqId' => 'required',
                'token' => 'required',
            ],
            [
                'reqId.required' => 'reqId is required',
                'token.required' => 'token is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("验证账号接口数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219);
        }

        $userinfo = Db::table('user_token as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->where('a.token',$param['token'])
            ->selectRaw('br_a.uid,IFNULL(br_b.coin,0) AS coin,IFNULL(br_b.bonus,0) AS bonus,IFNULL(br_b.total_pay_score,0) AS total_pay_score')
            ->first();
        //dd($userinfo);
        if (empty($userinfo)){
            $this->logger->error("验证账号token无效");
            return json_encode(['errorCode'=>4, 'message'=>'Token expired']);
        }
        //bonus不带入
        $carry_bonus_config = Common::getMore('recharge_and_get_bonus,bonus_and_get_bonus');
        $userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && $carry_bonus_config['recharge_and_get_bonus'] >= $userinfo['total_pay_score']) ? $userinfo['bonus'] : 0;

        $data = [
            'errorCode' => 0,
            'message' => 'Success',
            'username' => (string)$userinfo['uid'],
            'currency' => config('jlslots.currency'),
//            'balance' => ($userinfo['coin'] + $userinfo['bonus'])/100,
            'balance' =>  (float)bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100, 2),
        ];

        return json_encode($data);
    }

    #[RequestMapping(path: '/api/bet')]
    public function userBet(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'reqId' => 'required',
                'token' => 'required',
                'currency' => 'required',
                'game' => 'required',
                'round' => 'required',
                'wagersTime' => 'required',
                'betAmount' => 'required',
                'winloseAmount' => 'required',
            ],
            [
                'reqId.required' => 'reqId is required',
                'token.required' => 'token is required',
                'currency.required' => 'currency is required',
                'game.required' => 'game is required',
                'round.required' => 'round is required',
                'wagersTime.required' => 'wagersTime is required',
                'betAmount.required' => 'betAmount is required',
                'winloseAmount.required' => 'winloseAmount is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("注单接口数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[],1);
        }

        /*$userinfo = Db::table('user_token as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->where('a.token',$param['token'])
            ->selectRaw('br_a.uid,br_b.puid,IFNULL(br_b.coin,0) AS coin,IFNULL(br_b.bonus,0) AS bonus,br_b.channel,br_b.package_id')
            ->first();*/

        //$uid = $this->slotsCommon->getUserUid($param['token']);
        //$userinfo = $this->slotsCommon->getUserInfo($uid);
        $userinfo = $this->slotsCommon->getUserInfoToken($param['token']);
        //$userinfo = $this->slotsCommon->getUserInfo($userinfo['uid']);
        //dd($userinfo);
        if (empty($userinfo)){
            $this->logger->error("注单token无效");
            return $this->response->json(['errorCode'=>4, 'message'=>'Token expired']);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;

        //单点登录
        $res = $this->slotsCommon->getUserRunGameTage($userinfo['uid'],'jl');
        if(!$res) return $this->response->json(['errorCode'=>4, 'message'=>'Token expired']);

        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);

        $data = [
            'username' => (string)$userinfo['uid'],
            'currency' => config('jlslots.currency'),
            'balance' =>  (float)$old_balance,
        ];

        $log = $this->slotsCommon->SlotsLogView((string)$param['round']);
        //已承认
        if (!empty($log) && $log['transaction_id'] != ''){
            $data['errorCode'] = 1;
            $data['message'] = 'Already accepted';
            $data['txId'] =  (int)$log['transaction_id'];
            return $this->response->json($data);
        }

        //检查余额是否充足
        if ($old_balance < $param['betAmount']){
            $data['errorCode'] = 2;
            $data['message'] = 'Not enough balance';
            return $this->response->json($data);
        }

        /*$game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','jl')
            ->where('b.slotsgameid',$param['game'])
            ->select('a.name','a.type',
            'b.englishname','b.id as slots_game_id')
            ->first();*/
        $game_info = Db::connection('readConfig')->table('slots_game')
            ->where('terrace_id',9)
            ->where('slotsgameid',$param['game'])
            ->select('englishname','id as slots_game_id')
            ->first();
        if (empty($game_info)){
            return $this->response->json(['errorCode'=>5, 'message'=>'Game does not exist']);
        }

        $ordersn = Common::doOrderSn(333);
        try {
            Db::beginTransaction();

            //下注记录
            if (!empty($log)) {
                //Db::table($table)->where('betId',$param['round'])->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$param['round'], ['transaction_id'=>$ordersn]);
                $game_log = $log;
            } else {
                $game_log = [
                    'betId' => $param['round'],
                    'parentBetId' => $param['round'],
                    'uid' => $userinfo['uid'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => 'jl',
                    'slotsgameid' => $param['game'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $ordersn,
                    'package_id' => $userinfo['package_id'],
                    'channel' => $userinfo['channel'],
                    'betTime' => $param['wagersTime'],
                    'createtime' => time(),
                    'is_settlement' => 1,
                ];
                //Db::name($table)->insert($game_log);
            }

            //资金变化
            //$res = slotsCommon::userFundChange($userinfo['uid'], $game_log['cashTransferAmount'], $game_log['bonusTransferAmount'], $new_cash*100, $new_bouns*100, $userinfo['channel'], $userinfo['package_id']);
            $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $param['betAmount']*100, $param['winloseAmount']*100);
            if ($balance['code'] != 200){
                $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏记录存储失败');
                Db::rollback();
                return $this->response->json(['errorCode'=>5, 'message'=>'Other error']);
            }
            Db::commit();

            //发送消息队列
            /*if ($res) {
                $amqpDetail = config('rabbitmq.slots_queue');
                MqProducer::pushMessage($game_log, $amqpDetail);
            }*/

            //回复
            $data = [
                'errorCode' => 0,
                'message' => 'Success',
                'username' => (string)$userinfo['uid'],
                'currency' => config('jlslots.currency'),
                'balance' =>  (float)bcdiv((string)$balance['data'], (string)100, 2),
                'txId' =>  (int)$ordersn,
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("注单，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['errorCode'=>5, 'message'=>'Other error']);
        }

    }

    /**
     * 取消注单
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    #[RequestMapping(path: '/api/cancelBet')]
    public function cancelBet(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'reqId' => 'required',
                'token' => 'required',
                'currency' => 'required',
                'game' => 'required',
                'round' => 'required',
                'betAmount' => 'required',
                'winloseAmount' => 'required',
                'userId' => 'required',
            ],
            [
                'reqId.required' => 'reqId is required',
                'token.required' => 'token is required',
                'currency.required' => 'currency is required',
                'game.required' => 'game is required',
                'round.required' => 'round is required',
                'betAmount.required' => 'betAmount is required',
                'winloseAmount.required' => 'winloseAmount is required',
                'userId.required' => 'userId is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("取消注单接口数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[],1);
        }

        /*$userinfo = Db::table('user_token as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->where('a.token',$param['token'])
            ->selectRaw('br_a.uid,IFNULL(br_b.coin,0) AS coin,IFNULL(br_b.bonus,0) AS bonus,br_b.channel,br_b.package_id')
            ->first();*/
//        $uid = $this->slotsCommon->getUserUid($param['token']);
//        $userinfo = $this->slotsCommon->getUserInfo($uid);
        $userinfo = $this->slotsCommon->getUserInfoToken($param['token']);
        //$userinfo = $this->slotsCommon->getUserInfo($userinfo['uid']);
        //dd($userinfo);
        if (empty($userinfo)){
            $this->logger->error("取消注单token无效");
            return $this->response->json(['errorCode'=>3, 'message'=>'Invalid parameter']);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;

        //$log = Db::table($table)->where('betId',$param['round'])->first();//旧
        $log = $this->slotsCommon->SlotsLogView((string)$param['round']);
        if (empty($log)){
            $this->logger->error("取消注单 ，注单不存在");
            return $this->response->json(['errorCode'=>2, 'message'=>'Round not found']);
        }else{
            $this->logger->error("取消注单 ，注单已成立");
            return $this->response->json(['errorCode'=>6, 'message'=>'Already accepted and cannot be canceled']);
        }

    }


    #[RequestMapping(path: '/api/sessionBet')]
    public function sessionBet(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'reqId' => 'required',
                'token' => 'required',
                'currency' => 'required',
                'game' => 'required',
                'round' => 'required',
                'wagersTime' => 'required',
                'betAmount' => 'required',
                'winloseAmount' => 'required',
                'sessionId' => 'required',
                'type' => 'required',
                'turnover' => 'required',
                'preserve' => 'required',
            ],
            [
                'reqId.required' => 'reqId is required',
                'token.required' => 'token is required',
                'currency.required' => 'currency is required',
                'game.required' => 'game is required',
                'round.required' => 'round is required',
                'wagersTime.required' => 'wagersTime is required',
                'betAmount.required' => 'betAmount is required',
                'winloseAmount.required' => 'winloseAmount is required',
                'sessionId.required' => 'sessionId is required',
                'type.required' => 'type is required',
                'turnover.required' => 'turnover is required',
                'preserve.required' => 'preserve is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("牌局注单接口数据验证失败===>".$errorMessage);
            $this->logger->error("牌局注单接口数据验证失败pam===>".json_encode($param));
            return $this->ReturnJson->failFul(219,[],1);
        }
        $this->logger->error("牌局注单接口数据==param=>".json_encode($param));

        /*$userinfo = Db::table('user_token as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->where('a.token',$param['token'])
            ->selectRaw('br_a.uid,br_b.puid,IFNULL(br_b.coin,0) AS coin,IFNULL(br_b.bonus,0) AS bonus,br_b.channel,br_b.package_id')
            ->first();*/
//        $uid = $this->slotsCommon->getUserUid($param['token']);
//        $userinfo = $this->slotsCommon->getUserInfo($uid);
        $userinfo = $this->slotsCommon->getUserInfoToken($param['token']);
        //$userinfo = $this->slotsCommon->getUserInfo($userinfo['uid']);
        //dd($userinfo);
        if (empty($userinfo)){
            $this->logger->error("注单token无效");
            return $this->response->json(['errorCode'=>4, 'message'=>'Token expired']);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;

        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);
        $data = [
            'username' => (string)$userinfo['uid'],
            'currency' => config('jlslots.currency'),
            'balance' =>  (float)$old_balance,
        ];

        //$log = Db::table($table)->where('betId',$param['round'])->first();//旧
        $log = $this->slotsCommon->SlotsLogView((string)$param['round']);
        //已承认
        if (!empty($log) && ($log['is_settlement'] == 2 || $log['is_settlement'] == 1)){
            $data['errorCode'] = 1;
            $data['message'] = 'Already accepted';
            $data['txId'] =  (int)$log['transaction_id'];
            return $this->response->json($data);
        }

        //回合内所有投注
        //$bet = Db::table($table)->where('parentBetId',$param['sessionId'])->selectRaw('IFNULL(SUM(cashBetAmount),0) as cashBetAmount')->first();
        $bet = 0;

        //检查余额是否充足
        /*if ($old_balance < (bcdiv((string)($bet['cashBetAmount']), (string)100,2) + $param['betAmount'] + $param['preserve'])){
            $data['errorCode'] = 2;
            $data['message'] = 'Not enough balance';
            return $this->response->json($data);
        }*/

        /*$game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','jl')
            ->where('b.slotsgameid',$param['game'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();*/
        $game_info = Db::connection('readConfig')->table('slots_game')
            ->where('terrace_id',9)
            ->where('slotsgameid',$param['game'])
            ->select('englishname','id as slots_game_id')
            ->first();
        if (empty($game_info)){
            return $this->response->json(['errorCode'=>5, 'message'=>'Game does not exist']);
        }

        $ordersn = Common::doOrderSn(333);
        try {
            Db::beginTransaction();

            //下注记录
            if (!empty($log)) {
                //Db::table($table)->where('betId',$param['round'])->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$param['round'], ['transaction_id'=>$ordersn]);
                $game_log = $log;
                $balance['data'] = $userinfo['coin'];
            } else {

                $game_log = [
                    'betId' => $param['round'],
                    'parentBetId' => $param['sessionId'],
                    'uid' => $userinfo['uid'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => 'jl',
                    'slotsgameid' => $param['game'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $ordersn,
                    'package_id' => $userinfo['package_id'],
                    'channel' => $userinfo['channel'],
                    'betTime' => $param['wagersTime'],
                    'createtime' => time(),
                ];
                if ($param['type'] == 1) {
                    $game_log['is_settlement'] = 0;
                    $game_log['other'] = 1;//1-下注  2-结算

                    $game_log['cashBetAmount'] = $param['betAmount']*100;
                    $game_log['cashWinAmount'] = $param['winloseAmount']*100;
                    $game_log['cashTransferAmount'] = bcsub((string)$param['winloseAmount'],(string)$param['betAmount'],4) * 100;
                    //Db::table($table)->insert($game_log);

                    //修改余额
                    $balance = [];
                    $balance['data'] = $userinfo['coin'] - $bet['cashBetAmount'] - $game_log['cashBetAmount'] - ($param['preserve']*100);
                    //Db::table('userinfo')->where('uid',$userinfo['uid'])->update(['coin'=>$balance['data']]);

                }else{
                    $game_log['is_settlement'] = 1;
                    $game_log['other'] = 2;//1-下注  2-结算
                    $betAmount = $param['sessionTotalBet']*100;
                    $balance = $this->slotsCommon->slotsLog($game_log, bcadd((string)$userinfo['coin'],(string)($param['preserve']*100)), $userinfo['bonus'], $betAmount, $param['winloseAmount']*100);
                    if ($balance['code'] != 200){
                        $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏记录存储失败');
                        Db::rollback();
                        return $this->response->json(['errorCode'=>5, 'message'=>'Other error']);
                    }
                    //$balance['data'] = bcadd((string)$balance['data'],(string)($param['preserve']*100));

                    //修改其他注单状态
                    //Db::table($table)->where(['parentBetId'=>$param['sessionId'],'is_settlement'=>0])->update(['is_settlement'=>1]);
                }
            }

            //资金变化
            Db::commit();

            //回复
            $data = [
                'errorCode' => 0,
                'message' => 'Success',
                'username' => (string)$userinfo['uid'],
                'currency' => config('jlslots.currency'),
                'balance' =>  (float)bcdiv((string)$balance['data'], (string)100, 2),
                'txId' =>  (int)$ordersn,
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("注单，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['errorCode'=>5, 'message'=>'Other error']);
        }
    }

    #[RequestMapping(path: '/api/cancelSessionBet')]
    public function cancelSessionBet(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'reqId' => 'required',
                'token' => 'required',
                'currency' => 'required',
                'game' => 'required',
                'round' => 'required',
                'betAmount' => 'required',
                'winloseAmount' => 'required',
                'userId' => 'required',
                'sessionId' => 'required',
                'type' => 'required',
            ],
            [
                'reqId.required' => 'reqId is required',
                'token.required' => 'token is required',
                'currency.required' => 'currency is required',
                'game.required' => 'game is required',
                'round.required' => 'round is required',
                'betAmount.required' => 'betAmount is required',
                'winloseAmount.required' => 'winloseAmount is required',
                'userId.required' => 'userId is required',
                'sessionId.required' => 'sessionId is required',
                'type.required' => 'type is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("取消注单接口数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[],1);
        }

        /*$userinfo = Db::table('user_token as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->where('a.token',$param['token'])
            ->selectRaw('br_a.uid,br_b.puid,IFNULL(br_b.coin,0) AS coin,IFNULL(br_b.bonus,0) AS bonus,br_b.channel,br_b.package_id')
            ->first();*/
//        $uid = $this->slotsCommon->getUserUid($param['token']);
//        $userinfo = $this->slotsCommon->getUserInfo($uid);
        $userinfo = $this->slotsCommon->getUserInfoToken($param['token']);
        //$userinfo = $this->slotsCommon->getUserInfo($userinfo['uid']);
        //dd($userinfo);
        if (empty($userinfo)){
            $this->logger->error("取消注单token无效");
            return $this->response->json(['errorCode'=>3, 'message'=>'Invalid parameter']);
        }

        /*$game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','jl')
            ->where('b.slotsgameid',$param['game'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();*/
        $game_info = Db::connection('readConfig')->table('slots_game')
            ->where('terrace_id',9)
            ->where('slotsgameid',$param['game'])
            ->select('englishname','id as slots_game_id')
            ->first();
        if (empty($game_info)){
            return $this->response->json(['errorCode'=>5, 'message'=>'Game does not exist']);
        }

        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;

        //$table = 'slots_log_' . date('Ymd');
        //$log = Db::table($table)->where('betId',$param['round'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['round']);
        $game_log = [
            'betId' => $param['round'],
            'parentBetId' => $param['sessionId'],
            'uid' => $userinfo['uid'],
            'puid' => $userinfo['puid'],
            'terrace_name' => 'jl',
            'slotsgameid' => $param['game'],
            'game_id' => $game_info['slots_game_id'],
            'englishname' => $game_info['englishname'],
            'package_id' => $userinfo['package_id'],
            'channel' => $userinfo['channel'],
            'betTime' => time(),
            'createtime' => time(),
            'cashBetAmount' => $param['betAmount']*100,
            'cashWinAmount' => $param['winloseAmount']*100,
            'cashTransferAmount' => bcsub((string)$param['winloseAmount'],(string)$param['betAmount'],4) * 100,
            'is_settlement' => 2,
        ];

        try {
            Db::beginTransaction();

            $balance = [];
            if (empty($log)){
//                Db::table($table)->insert($game_log);

                $balance['data'] = $userinfo['coin'] + $userinfo['bonus'];
            }else{
                if ($log['is_settlement'] == 2){
                    return $this->response->json(['errorCode'=>1, 'message'=>'Already canceled']);
                }elseif ($log['is_settlement'] == 1){
                    $balance['data'] = $userinfo['coin'] + $game_log['cashBetAmount'] + ($param['preserve']*100);
                    //Db::table('userinfo')->where('uid',$userinfo['uid'])->update(['coin'=>$balance]);
                    /*$res = $this->slotsCommon->userFundChange($userinfo['uid'], $game_log['cashBetAmount'] + ($param['preserve']*100), 0, $balance['data'], $userinfo['bonus'], $userinfo['channel'], $userinfo['package_id']);
                    if (!$res){
                        Db::rollback();
                        $this->logger->error("取消注单修改用戶數據失败");
                        return $this->response->json(['errorCode'=>5, 'message'=>'Other error']);
                    }*/
                    //Db::table($table)->where('betId',$param['round'])->update(['is_settlement'=>2]);
                }else{
                    $balance['data'] = $userinfo['coin'] + $userinfo['bonus'];
                    //Db::table($table)->where('betId',$param['round'])->update(['is_settlement'=>2]);
                }
            }

            //资金变化
            Db::commit();

            //回复
            $data = [
                'errorCode' => 0,
                'message' => 'Success',
                'username' => (string)$userinfo['uid'],
                'currency' => config('jlslots.currency'),
                'balance' =>  (float)bcdiv((string)$balance['data'], (string)100, 2),
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("注单，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['errorCode'=>5, 'message'=>'Other error']);
        }

    }


    /******************************转账模式*******************************/

    private static $herder = [
        'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    /**
     * 创建玩家
     * @param $uid  玩家uid
     * @return bool|string
     */
    public static function playerCreated($uid){

        $url = config('jlslots.api_url').'/CreateMember';
        $data = [
            'Account' => $uid,
            'AgentId' => config('jlslots.AgentId'),
        ];

        $data['Key'] = self::getKey($data);

        $res = self::senPostCurl($url,$data);

        if(!$res || ($res['ErrorCode'] !== 0 && $res['ErrorCode'] !== 101)){
            return ['code' => 201,'msg' => '' ,'data' =>[]];
        }
        return ['code' => 200,'msg' => $res['Message'] ,'data' =>[]];

    }

    /**
     * 获取游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public function getGameUrl($uid,$gameid){
        $token = Db::table('user_token')->where('uid',$uid)->value('token');
        if (empty($token)){
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }

        $url = config('jlslots.api_url').'/singleWallet/LoginWithoutRedirect';
        $data = [
            'Token' => $token,
            'GameId' => $gameid,
            'Lang' => config('jlslots.language'),
            'AgentId' => config('jlslots.AgentId'),
        ];


        $curl = new Curl();

        $data['Key'] = self::getKey($data);

        //$res = $this->guzzle->post($url, $data, [], 1);
        $res = $curl::get($url, $data, []);
        //$gameUrl =  $curl::getUrl($url,$data,[]);
        //$this->logger->error("jlgame===>url：".$res);
        //$this->logger->error("jlgame===>data：".json_encode($data));

        $res = json_decode($res,true);
        if (!$res || $res['ErrorCode'] != 0){
            return ['code' => 201,'msg' => $res['Message'] ,'data' =>''];
        }

        return ['code' => 200,'msg' => 'success' ,'data' =>$res['Data']];

    }

    /**
     * @return void  将用户踢下线
     *  @param $uid  用户的uid
     */
    public static function KickMember($uid){
        $url = config('jlslots.api_url').'/KickMember';
        $data = [
            'Account' => $uid,
            'AgentId' => config('jlslots.AgentId'),
        ];

        $data['Key'] = self::getKey($data);
        self::senPostCurl($url,$data);//这里不用判断用户是否真被提下线了，直接掉转出接口
    }


    /**
     * Key = {6 个任意字符} + MD5(所有请求参数串 + KeyG) + {6 个任意字符}
     * @param $body 请求体
     * @return string 获取hash
     */
    private static function getKey($body){
        $querystring = self::getQueryString($body);
        $KeyG = self::KeyG();

        return self::reallyKey($querystring,$KeyG);

    }

    /**
     * 生成44位的key
     * @param $querystring querystring 字符串
     * @param $KeyG KeyG字符串
     * @return string
     */
    private static function reallyKey($querystring,$KeyG,$count = 1){
        $key = rand(111111,999999).md5($querystring.$KeyG).rand(111111,999999);
        if(strlen($key) != 44 && $count <= 3){  //请求3次，如果3次都不行的话，就直接返回
            $count = $count + 1;
            self::reallyKey($querystring,$KeyG,$count);
        }
        return $key;
    }

    /**
     * @return void 获取keyg字符串
     *
     */
    private static function KeyG(){
        return md5(self::getDateTimeNow().config('jlslots.AgentId').config('jlslots.AgentKey'));
    }


    /**
     * DateTime.Now = 当下 UTC-4 时间, 格式為 yyMMd
     *  年: 公元年分末两位
     月: 两位数, 1~9 前面須补 0
     日: 一位数或两位数, 1~9 前面请不要补 0
     * 例如：2018/2/7 => 18027（7 号是 7 不是 07）
    2018/2/18 => 180218
     * @return string
     */
    private static function getDateTimeNow() {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $now->modify('-4 hours');

        $year = $now->format('y');
        $month = $now->format('m');
        $day = $now->format('j');
        // 格式化月份和日期
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $day = strval($day);

        if ($day[0] === '0') {
            $day = substr($day, 1);
        }

        return $year . $month . $day;
    }


    /**
     * 依照 API 参数列表，按顺序以 key1=value1&key2=value2 格式串起：所有 API 的请求参数字符串最后都要加上 AgentId=xxx
     * 例如Test1&GameId=101&Lang=zh-CN&AgentId=10081
     * 获取getQueryString 数据
     * @param $body 请求的数据
     * @return string
     */
    private static function getQueryString($body){
        $string = [];
        foreach ($body as $key => $value) {
            if ($key == 'sign') continue;
            $string[] = $key . '=' . $value;
        }
        return implode('&', $string);

    }

    /** 发送Curl
     * @param $url
     * @param $body
     * @return mixed
     */
    private static function senPostCurl($url,$body)
    {
        $guzzle = new Guzzle();
        $dataString = $guzzle->post($url, $body, self::$herder);

        return $dataString;

    }

    /** 发送Curl
     * @param $url
     * @param $body
     * @return mixed
     */
    private static function senGetCurl($url,$body,$urlencodeData = []){
        $dataString =  Curl::get($url,$body,$urlencodeData);
        return json_decode($dataString, true);

    }

    /**格式: YYYY-MM-DDThh:mm:ss, 例如
    2021-01-01T03:00:00。请用 GMT-4 时区的时间。
     * @param $time 时间戳
     * @return false|string
     */
    private static function getUtc4tDate($time){

        return gmdate('Y-m-d\TH:i:s', $time - (4 * 3600)); // 转换为GMT-4时区的时间

    }

    /**
     * 将游戏数据时区转为巴西或者中国时区
     * @param $date
     * @return void
     * @throws \Exception
     */
    public static function getTime($date){
        $datetime = new \DateTime($date, new \DateTimeZone('UTC')); // 创建DateTime对象，并设置时区为UTC

//        // 转换为巴西时间
//        $brazilTimezone = new \DateTimeZone('America/Sao_Paulo'); // 巴西时区
//        $datetime->setTimezone($brazilTimezone); // 设置时区为巴西时区
//        $Time = $datetime->format('y-m-d H:i:s'); // 格式化为yy-mm-dd hh:ii:ss

        // 转换为中国时间
        $chinaTimezone = new \DateTimeZone('Asia/Shanghai'); // 中国时区
        $datetime->setTimezone($chinaTimezone); // 设置时区为中国时区
        $Time = $datetime->format('y-m-d H:i:s'); // 格式化为yy-mm-dd hh:ii:ss

        return strtotime($Time);
    }
}






