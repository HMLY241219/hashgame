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

#[Controller(prefix: 'jdbslots')]
class JdbslotsController extends AbstractController {

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
    #[RequestMapping(path: 'commons')]
    public function commons(){
        $params = $this->request->all();
        if (!isset($params['x'])){
            $this->logger->error("jdb入口加密数据验证失败===>".json_encode($params));
            return $this->response->json(['status' => '0001', 'balance' => 0, 'err_text' => 'x is required']);
        }
        $config = config('jdbslots');
        $param = self::decrypt($params['x'], $config['Key'], $config['Iv']);
        $param = json_decode($param, true);

        //$this->logger->error("jdb入口数据===>".json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'action' => 'required',
            ],
            [
                'action.required' => 'action is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("jdb入口数据验证失败===>".$errorMessage);
            return $this->response->json(['status' => '0001', 'balance' => 0, 'err_text' => $errorMessage]);
        }

        $action = 'action'.$param['action'];
        $methodExists = method_exists($this, $action);
        if ($methodExists) {
            $res = self::$action($param);
        }else{
            return $this->response->json(['status' => '0002', 'balance' => 0, 'err_text' => 'Parameter value error']);
        }

        return $res;
    }


    /**
     * 获取余额
     * @param $param
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function action6($param)
    {
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("jdb获取余额数据验证失败===>".$errorMessage);
            return $this->response->json(['status' => '0001', 'balance' => 0, 'err_text' => $errorMessage]);
        }

        $res = $this->slotsCommon->setUserMoney($param['uid']);
        if($res['code'] != 200){
            $this->logger->error('jdb用户获取失败:用户-UID:'.$param['uid'].'未找到用户');
            return $this->response->json(['status' => '0003', 'balance' => 0, 'err_text' => 'Player not found or is logged out']);
        }

        $data = [
            'status' => '0000',
            'balance' => (float)bcdiv((string)$res['data'],'100',2),
            'err_text' => ''
        ];

        return $this->response->json($data);
    }

    /**
     * 下注结算
     * @param $param
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function action8($param)
    {
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
                'bet' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
                'bet.required' => 'bet is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("jdb下注结算数据验证失败===>".$errorMessage);
            return $this->response->json(['status' => '0001', 'balance' => 0, 'err_text' => $errorMessage]);
        }
        //$this->logger->error('jdb下注结算数据:==>'.json_encode($param));

        $userinfo = $this->slotsCommon->getUserInfo($param['uid']);
        if(!$userinfo){
            $this->logger->error('jdb下注结算用户获取失败:用户-UID:'.$param['uid'].'未找到用户');
            return $this->response->json(['status' => '0003', 'balance' => 0, 'err_text' => 'Player not found or is logged out']);
        }

        $bet = abs($param['bet']);
        $old_balance = (float)bcdiv((string)bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2);
        //检查余额是否充足
        if ($old_balance < $bet){
            $data['status'] = '6006';
            $data['balance'] = $old_balance;
            $data['err_text'] = 'Insufficient balance';
            return $this->response->json($data);
        }

//        $log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['transferId'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['transferId']);
        //已存在
        if (!empty($log) && $log['transaction_id'] != ''){
            $data['status'] = '0000';
            $data['balance'] = $old_balance;
            $data['err_text'] = '';
            return $this->response->json($data);
        }

        /*$game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','jdb')
            ->where('b.slotsgameid',$param['gType'].'-'.$param['mType'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();*/
        $game_info = Db::connection('readConfig')->table('slots_game')
            ->where('terrace_id',12)
            ->where('slotsgameid', $param['gType'].'-'.$param['mType'])
            ->select('englishname','id as slots_game_id')
            ->first();
        if (empty($game_info)){
            $this->logger->error('jdb下注游戏不存在ID: '.$param['gType'].'-'.$param['mType']);
            return $this->response->json(['status' => '0004', 'balance' => $old_balance, 'err_text' => 'Game does not exist']);
        }

        $ordersn = Common::doOrderSn(133);
        try {
            Db::beginTransaction();

            //下注记录
            if (!empty($log)) {
                //Db::table('slots_log_' . date('Ymd'))->where('betId',$param['transferId'])->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$param['transferId'], ['transaction_id'=>$ordersn]);
                $game_log = $log;
            } else {
                $game_log = [
                    'betId' => $param['transferId'],
                    'parentBetId' => $param['transferId'],
                    'uid' => $param['uid'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => 'jdb',
                    'slotsgameid' => $param['gType'].'-'.$param['mType'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $ordersn,
                    'package_id' => $userinfo['package_id'],
                    'channel' => $userinfo['channel'],
                    'betTime' => mb_substr((string)$param['ts'],0,-3),
                    'createtime' => time(),
                ];
                //Db::name($table)->insert($game_log);
            }

            //资金变化
            $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $bet*100, $param['win']*100);
            if ($balance['code'] != 200){
                $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏jdb记录存储失败');
                Db::rollback();
                return $this->response->json(['status' => '0005', 'balance' => $old_balance, 'err_text' => 'fail']);
            }
            Db::commit();

            //回复
            $data = [
                'status' => '0000',
                'balance' =>  (float)bcdiv((string)$balance['data'], (string)100, 2),
                'err_text' =>  '',
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("jdb下注结算，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['status' => '0006', 'balance' => $old_balance, 'err_text' => 'fail']);
        }

    }

    /**
     * 取消下注结算
     * @param $param
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function action4($param)
    {
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
                'transferId' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
                'transferId.required' => 'transferId is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("jdb取消下注数据验证失败===>".$errorMessage);
            return $this->response->json(['status' => '0001', 'balance' => 0, 'err_text' => $errorMessage]);
        }

        $userinfo = $this->slotsCommon->getUserInfo($param['uid']);
        if(!$userinfo){
            $this->logger->error('jdb取消下注用户获取失败:用户-UID:'.$param['uid'].'未找到用户');
            return $this->response->json(['status' => '0003', 'balance' => 0, 'err_text' => 'Player not found or is logged out']);
        }

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['transferId'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['transferId']);
        if (empty($log)){
            return $this->response->json(['status' => '9017', 'balance' => 0, 'err_text' => 'Order does not exist']);
        }else{
            if ($log['is_consume'] == 1){
                return $this->response->json(['status' => '6101', 'balance' => 0, 'err_text' => 'Settled']);
            }else{
                //Db::table('slots_log_' . date('Ymd'))->where('betId',$param['transferId'])->update(['is_settlement'=>0]);
                //$this->common->setRefundSlotsLog($log,$rollbackAmount);
                $data = [
                    'status' => '0000',
                    'balance' => (float)bcdiv((string)bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2),
                    'err_text' => ''
                ];
                return $this->response->json($data);
            }
        }

    }

    /**
     * 下注
     * @param $param
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function action9($param)
    {
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
                'amount' => 'required',
                'transferId' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
                'amount.required' => 'amount is required',
                'transferId.required' => 'transferId is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("jdb下注数据验证失败===>".$errorMessage);
            return $this->response->json(['status' => '0001', 'balance' => 0, 'err_text' => $errorMessage]);
        }
        //$this->logger->error('jdb下注数据:==>'.json_encode($param));

        $userinfo = $this->slotsCommon->getUserInfo($param['uid']);
        if(!$userinfo){
            $this->logger->error('jdb下注用户获取失败:用户-UID:'.$param['uid'].'未找到用户');
            return $this->response->json(['status' => '0003', 'balance' => 0, 'err_text' => 'Player not found or is logged out']);
        }

        $old_balance = (float)bcdiv((string)bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2);
        //检查余额是否充足
        if ($old_balance < $param['amount']){
            $this->logger->error('jdb下注余额不足:==>'.$old_balance);
            $data['status'] = '6006';
            $data['balance'] = $old_balance;
            $data['err_text'] = 'Insufficient balance';
            return $this->response->json($data);
        }

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['transferId'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['transferId']);
        //已存在
        if (!empty($log) && $log['transaction_id'] != ''){
            $data['status'] = '0000';
            $data['balance'] = $old_balance;
            $data['err_text'] = '';
            return $this->response->json($data);
        }

        $game_type = 'jdb';
        if ($param['gType'] == 22){
            $game_type = 'spr';
        }
        /*$game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type',$game_type)
            ->where('b.slotsgameid',$param['gType'].'-'.$param['mType'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();*/
        $game_info = Db::connection('readConfig')->table('slots_game')
            ->where('terrace_id',12)
            ->where('slotsgameid', $param['gType'].'-'.$param['mType'])
            ->select('englishname','id as slots_game_id')
            ->first();
        if (empty($game_info)){
            $this->logger->error('jdb下注游戏不存在:==>'.json_encode($game_info));
            return $this->response->json(['status' => '0004', 'balance' => $old_balance, 'err_text' => 'Game does not exist']);
        }

        $ordersn = Common::doOrderSn(133);
        try {
            Db::beginTransaction();

            //下注记录
            if (!empty($log)) {
                //Db::table('slots_log_' . date('Ymd'))->where('betId',$param['transferId'])->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$param['transferId'], ['transaction_id'=>$ordersn]);
                $game_log = $log;
            } else {
                $game_log = [
                    'betId' => $param['transferId'],
                    'parentBetId' => $param['gameRoundSeqNo'],
                    'uid' => $param['uid'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => $game_type,
                    'slotsgameid' => $param['gType'].'-'.$param['mType'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $ordersn,
                    'package_id' => $userinfo['package_id'],
                    'channel' => $userinfo['channel'],
                    'betTime' => mb_substr((string)$param['ts'],0,-3),
                    'createtime' => time(),
                    'is_settlement' => 0,
                ];
                //Db::name($table)->insert($game_log);
            }

            //资金变化
            $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $param['amount']*100, 0, 2);
            if ($balance['code'] != 200){
                $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏jdb记录存储失败');
                Db::rollback();
                return $this->response->json(['status' => '0005', 'balance' => $old_balance, 'err_text' => 'fail']);
            }
            Db::commit();

            //回复
            $data = [
                'status' => '0000',
                'balance' =>  (float)bcdiv((string)$balance['data'], (string)100, 2),
                'err_text' =>  '',
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("jdb下注，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['status' => '0006', 'balance' => $old_balance, 'err_text' => 'fail']);
        }
    }

    /**
     * 结算
     * @param $param
     * @return \Psr\Http\Message\ResponseInterface|void
     */
    public function action10($param)
    {
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
                'amount' => 'required',
                'transferId' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
                'amount.required' => 'amount is required',
                'transferId.required' => 'transferId is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("jdb结算数据验证失败===>".$errorMessage);
            return $this->response->json(['status' => '0001', 'balance' => 0, 'err_text' => $errorMessage]);
        }
        //$this->logger->error('jdb结算数据:==>'.json_encode($param));

        $userinfo = $this->slotsCommon->getUserInfo($param['uid']);
        if(!$userinfo){
            $this->logger->error('jdb结算用户获取失败:用户-UID:'.$param['uid'].'未找到用户');
            return $this->response->json(['status' => '0003', 'balance' => 0, 'err_text' => 'Player not found or is logged out']);
        }

        $old_balance = (float)bcdiv((string)bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2);

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['transferId'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['transferId']);
        if (!empty($log) && $log['transaction_id'] != '' && $log['is_settlement'] == 1){
            $data['status'] = '0000';
            $data['balance'] = $old_balance;
            $data['err_text'] = '';
            return $this->response->json($data);
        }

        $game_type = 'jdb';
        if ($param['gType'] == 22){
            $game_type = 'spr';
        }
        /*$game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type',$game_type)
            ->where('b.slotsgameid',$param['gType'].'-'.$param['mType'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();*/
        $game_info = Db::connection('readConfig')->table('slots_game')
            ->where('terrace_id',12)
            ->where('slotsgameid', $param['gType'].'-'.$param['mType'])
            ->select('englishname','id as slots_game_id')
            ->first();
        if (empty($game_info)){
            $this->logger->error('jdb结算游戏不存在:==>'.json_encode($game_info));
            return $this->response->json(['status' => '0004', 'balance' => $old_balance, 'err_text' => 'Game does not exist']);
        }

        $ordersn = Common::doOrderSn(133);
        try {
            Db::beginTransaction();

            //下注记录
            if (!empty($log)) {
                //Db::table('slots_log_' . date('Ymd'))->where('betId',$param['transferId'])->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$param['transferId'], ['transaction_id'=>$ordersn]);
                $game_log = $log;
            } else {
                $game_log = [
                    'betId' => $param['transferId'],
                    'parentBetId' => $param['gameRoundSeqNo'],
                    'uid' => $param['uid'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => $game_type,
                    'slotsgameid' => $param['gType'].'-'.$param['mType'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $ordersn,
                    'package_id' => $userinfo['package_id'],
                    'channel' => $userinfo['channel'],
                    'betTime' => mb_substr((string)$param['ts'],0,-3),
                    'createtime' => time(),
                ];
                //Db::name($table)->insert($game_log);
            }

            //资金变化
            $balance = $this->slotsCommon->slotsLog2($game_log, $userinfo['coin'], $userinfo['bonus'], $param['validBet']*100, 0, $param['win']*100, 2, 1, 1);
            if ($balance['code'] != 200){
                $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏jdb记录存储失败');
                Db::rollback();
                return $this->response->json(['status' => '0005', 'balance' => $old_balance, 'err_text' => 'fail']);
            }

            /*if ($param['roundClosed']){//本局全部下注已结算
                Db::table('slots_log_' . date('Ymd'))->where('parentBetId',$param['gameRoundSeqNo'])->update(['is_settlement'=>1]);
            }*/

            Db::commit();

            //回复
            $data = [
                'status' => '0000',
                'balance' =>  (float)bcdiv((string)$balance['data'], (string)100, 2),
                'err_text' =>  '',
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("jdb下注，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['status' => '0006', 'balance' => $old_balance, 'err_text' => 'fail']);
        }
    }

    /**
     * 取消下注
     * @param $param
     * @return \Psr\Http\Message\ResponseInterface|void
     */
    public function action11($param)
    {
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
                'amount' => 'required',
                'transferId' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
                'amount.required' => 'amount is required',
                'transferId.required' => 'transferId is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("jdb取消下注数据验证失败===>".$errorMessage);
            return $this->response->json(['status' => '0001', 'balance' => 0, 'err_text' => $errorMessage]);
        }

        //$this->logger->error('jdb取消下注数据:==>'.json_encode($param));

        $userinfo = $this->slotsCommon->getUserInfo($param['uid']);
        if(!$userinfo){
            $this->logger->error('jdb取消下注用户获取失败:用户-UID:'.$param['uid'].'未找到用户');
            return $this->response->json(['status' => '0003', 'balance' => 0, 'err_text' => 'Player not found or is logged out']);
        }
        $old_balance = (float)bcdiv((string)bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',2);

        /*$log = Db::table('slots_log_' . date('Ymd'))
            ->where('parentBetId', $param['gameRoundSeqNo'])
            ->where('is_consume', 0)
            ->selectRaw('IFNULL(SUM(cashBetAmount),0) AS cashBetAmount,IFNULL(SUM(bonusBetAmount),0) AS bonusBetAmount')
            ->first();*/
        $log = $this->slotsCommon->SlotsLogView((string)$param['transferId']);
        if (empty($log)){
            return $this->response->json(['status' => '0003', 'balance' => 0, 'err_text' => 'Player not found or is logged out']);
        }

//        $ordersn = Common::doOrderSn(133);
        try {
            Db::beginTransaction();

            $new_cash = bcadd((string)$userinfo['coin'],(string)$log['cashBetAmount'],0);
            $new_bouns = bcadd((string)$userinfo['bonus'],(string)$log['bonusBetAmount'],0);
            $res = $this->slotsCommon->userFundChange($param['uid'], $log['cashBetAmount'], $log['bonusBetAmount'], $new_cash, $new_bouns, $userinfo['channel'], $userinfo['package_id']);
            if (!$res) {
                $this->logger->error('uid:' . $log['uid'] . 'jdb取消下注余额修改失败-Cash:' . $log['cashBetAmount'] . '-Bonus:' . $log['bonusBetAmount']);
                Db::rollback();
                return $this->response->json(['status' => '0005', 'balance' => $old_balance, 'err_text' => 'fail']);
            }

            /*$re = Db::table('slots_log_' . date('Ymd'))
                ->where('parentBetId', $param['gameRoundSeqNo'])
                ->where('is_consume', 0)
                ->update(['is_settlement'=>2]);*/
            
            Db::commit();

            //回复
            $data = [
                'status' => '0000',
                'balance' =>  bcdiv((string)($new_cash+$new_bouns), '100', 2),
                'err_text' =>  '',
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("jdb取消下注，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['status' => '0006', 'balance' => $old_balance, 'err_text' => 'fail']);
        }

    }

    /******************************转账模式*******************************/

    private static $herder = [
//        'Content-Type' => 'application/x-www-form-urlencoded',
        'Content-Type' => 'application/json',
    ];

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
        $userinfo = Db::table('userinfo')->where('uid',$uid)->select('coin')->first();
        if (empty($userinfo)){
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }

        $config = config('jdbslots');
        $url = $config['api_url'];
        $gameid_arr = explode('-',(string)$gameid);

        $data = [
            'action' => 21,
            'ts' => round(microtime(true) * 1000),
            'parent' => $config['parent'],
            'uid' => $uid,
            'balance' => bcdiv((string)$userinfo['coin'], '100', 2),
            'gType' => $gameid_arr[0],
            'mType' => $gameid_arr[1],
            'windowMode' => '2',
            'isAPP' => true
        ];
        $encryptData = self::encrypt(json_encode($data), $config['Key'], $config['Iv']);
        $url_data = [
            'dc' => $config['Dc'],
            'x' => $encryptData
        ];

        $pra = self::getQueryString($url_data);
        $all_url = trim($url.'?'.$pra);

//        $res =  self::senPostCurl($url,$url_data);
        //$res =  $this->guzzle->get($all_url,[],self::$herder); //正式

        //测试
        $test_data = [
            'url' => $all_url
        ];
//        $test_url = 'http://1.13.81.132:5009/api/slots.Jdbslots/jdbHttp';
        $test_url = 'https://inrtakeoff.3377win.com/api/slots.Jdbslots/jdbHttp';
        $res = $this->guzzle->post($test_url, $test_data, [], 1);

        //$res = json_decode($gameUrl, true);
        if (!$res || $res['status'] != '0000'){
            return ['code' => 201,'msg' => $res['err_text'] ,'data' =>''];
        }

        return ['code' => 200,'msg' => 'success' ,'data' =>$res['path']];

    }

    /**
     * 获取试玩游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public function getFreeGameUrl($gameid){
        $config = config('jdbslots');
        $url = $config['api_url'];
        $gameid_arr = explode('-',(string)$gameid);

        $data = [
            'action' => 47,
            'ts' => round(microtime(true) * 1000),
            'lang' => $config['language'],
            'gType' => $gameid_arr[0],
            'mType' => $gameid_arr[1],
            'windowMode' => '2',
            'isAPP' => true
        ];
        //$this->logger->error('jdb游戏获取json==>'.json_encode($data));
        $encryptData = self::encrypt(json_encode($data), $config['Key'], $config['Iv']);
        $url_data = [
            'dc' => $config['Dc'],
            'x' => $encryptData
        ];

        $pra = self::getQueryString($url_data);
        $all_url = trim($url.'?'.$pra);
        //$this->logger->error('jdb游戏获取url==>'.$all_url);

        $curl = new Curl();

//        $res =  self::senPostCurl($url, $url_data);
        //$res =  $this->guzzle->get($all_url, [], self::$herder); //正式

        //测试
        $test_data = [
            'url' => $all_url
        ];
//        $test_url = 'http://1.13.81.132:5009/api/slots.Jdbslots/jdbHttp';
        $test_url = 'https://inrtakeoff.3377win.com/api/slots.Jdbslots/jdbHttp';
        $res = $this->guzzle->post($test_url, $test_data, [], 1);

        //$this->logger->error('jdb游戏获取res==>'.json_encode($res));

        if (!$res || $res['status'] != '0000'){
            return ['code' => 201,'msg' => $res['err_text'] ,'data' =>''];
        }

        return ['code' => 200,'msg' => 'success' ,'data' =>$res['path']];

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

    public static function encrypt($data, $key, $iv)
    {
        $data = self::padString($data);
        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
        $encrypted = base64_encode($encrypted);
        $encrypted = str_replace(array('+','/','=') , array('-','_','') , $encrypted);
        return $encrypted;
    }

    private static function padString($source)
    {
        $paddingChar = ' ';
        $size = 16;
        $x = strlen($source) % $size;
        $padLength = $size - $x;
        for ($i = 0;$i < $padLength;$i++)
        {
            $source .= $paddingChar;
        }
        return $source;
    }

    public static function decrypt($data, $key, $iv)
    {
        $data = str_replace(array('-','_') , array('+','/') , $data);
        $data = base64_decode($data);
        $decrypted = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
        return mb_convert_encoding(trim($decrypted), 'UTF-8');
    }
}






