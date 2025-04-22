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

#[Controller(prefix:"sboslots")]
class SboslotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Curl $curl;

    #[Inject]
    protected slotsCommon $slotsCommon;


    /**
     * 取得用户的余额
     * @return false|string
     */
    #[RequestMapping(path: 'GetBalance')]
    public function GetBalance(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'CompanyKey' => 'required',
                'Username' => 'required',
                'ProductType' => 'required',
                'GameType' => 'required',
            ],
            [
                'CompanyKey.required' => 'CompanyKey is required',
                'Username.required' => 'Username is required',
                'ProductType.required' => 'ProductType is required',
                'GameType.required' => 'GameType is required',
            ]
        );

        //$this->logger->error("sb获取余额数据验证===>".json_encode($param));
        $data = [
            'AccountName' => '',
            'Balance' => 0.00,
            'ErrorCode' => 0,
            'ErrorMessage' => '',
        ];
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sb取得余额接口数据验证失败===>".$errorMessage);
            $data['ErrorCode'] = 3;
            $data['ErrorMessage'] = 'Username empty';
            return $this->response->json($data);
        }

        if (config('sboslots.CompanyKey') != $param['CompanyKey']){
            $data['ErrorCode'] = 4;
            $data['ErrorMessage'] = 'CompanyKey Error';
            return $this->response->json($data);
        }

        $uid = $param['Username'];
        /*$userinfo = Db::table('userinfo')
            ->where(DB::raw('BINARY `uid`'), '=', $uid)
            ->selectRaw('uid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus')
            ->first();*/
//            ->toRawSql();
        $userinfo = $this->slotsCommon->getUserInfo($uid);
        //var_dump($userinfo);
        if (empty($userinfo)){
            $this->logger->error("sb获取余额token UID无效");
            $data['ErrorCode'] = 1;
            $data['ErrorMessage'] = 'Member not exist';
            return $this->response->json($data);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;

        $data = [
            'AccountName' => (string)$userinfo['uid'],
            'Balance' => (float)bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100, 2),
            'ErrorCode' => 0,
            'ErrorMessage' => 'No Error',
        ];

        return $this->response->json($data);
    }

    /**
     * 扣除下注
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'Deduct')]
    public function Deduct(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'Amount' => 'required',
                'TransferCode' => 'required',
                'TransactionId' => 'required',
                'BetTime' => 'required',
                //'GamePeriodId' => 'required',
                //'OrderDetail' => 'required',
                'Username' => 'required',
                'ProductType' => 'required',
                'GameType' => 'required',
            ],
            [
                'Amount.required' => 'Amount is required',
                'TransferCode.required' => 'TransferCode is required',
                'TransactionId.required' => 'TransactionId is required',
                'BetTime.required' => 'BetTime is required',
                //'GamePeriodId.required' => 'GamePeriodId is required',
                //'OrderDetail.required' => 'OrderDetail is required',
                'CompanyKey.required' => 'CompanyKey is required',
                'Username.required' => 'Username is required',
                'ProductType.required' => 'ProductType is required',
                'GameType.required' => 'GameType is required',
            ]
        );

        $this->logger->error("sbo下注数据验证===>".json_encode($param));
        $data = [
            'AccountName' => '',
            'Balance' => 0.00,
            'ErrorCode' => 0,
            'ErrorMessage' => '',
            'BetAmount' => 0.00,
        ];
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sb扣除押注接口数据验证失败===>".$errorMessage);
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
        $data['AccountName'] = $param['Username'];
        $data['BetAmount'] = $param['Amount'];

        //单点登录
        $res = $this->slotsCommon->getUserRunGameTage($param['Username'],'sbo');
        if(!$res){
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }

        if (config('sboslots.CompanyKey') != $param['CompanyKey']){
            $data['ErrorCode'] = 4;
            $data['ErrorMessage'] = 'CompanyKey Error';
            return $this->response->json($data);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("sbo下注用户不存在param===>".json_encode($param));
            $data['ErrorCode'] = 1;
            $data['ErrorMessage'] = 'Member not exist';
            return $this->response->json($data);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        //$old_balance = bcsub(bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2),(string)$param['Amount'],2);
        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);
        //检查余额是否充足
        if ($old_balance < $param['Amount']){
            $this->logger->error("sbo下注余额不足param===>".json_encode($param));
            $data['Balance'] = $old_balance;
            $data['ErrorCode'] = 5;
            $data['ErrorMessage'] = 'Not enough balance';
            return $this->response->json($data);
        }

        //游戏
        $game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','sbo')
            ->where('b.slotsgameid',$param['ProductType'].$param['GameType'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();
        if (empty($game_info)){
            $this->logger->error("sbo下注游戏不存在param===>".json_encode($param));
            $data['Balance'] = $old_balance;
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);

            //测试使用
            /*$game_info['slots_game_id'] = 666;
            $game_info['englishname'] = '666';*/
        }

        $ProductType = $param['ProductType'];
        $ordersn = Common::doOrderSn(777);

        try {
            Db::beginTransaction();
            switch ($ProductType) {
                case 1:
                case 3:
                case 5:
                case 7:
                    //$log = Db::table('slots_log_' . date('Ymd'))->where('betId', $param['TransferCode'].'_'.$param['TransactionId'])->first();
                    $log = $this->slotsCommon->SlotsLogView($param['TransferCode'].'_'.$param['TransactionId']);
                    if (empty($log)) {
                        $game_log = [
                            'betId' => $param['TransferCode'].'_'.$param['TransactionId'],
                            'parentBetId' => $param['TransferCode'],
                            'uid' => $userinfo['uid'],
                            'puid' => $userinfo['puid'],
                            'terrace_name' => 'sbo',
                            'slotsgameid' => $param['ProductType'].$param['GameType'],
                            'game_id' => $game_info['slots_game_id'],
                            'englishname' => $game_info['englishname'],
                            'transaction_id' => $ordersn,
                            'package_id' => $userinfo['package_id'],
                            'channel' => $userinfo['channel'],
                            'betTime' => strtotime($param['BetTime']),
                            'createtime' => time(),
                            'is_settlement' => 0, //0-运行中未结算 1-已结算 2-已取消失效 3-已回滚 4-已归还
                        ];
                        //资金变化
                        $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $param['Amount']*100, 0, 2);
                        if ($balance['code'] != 200){
                            $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏sbo记录存储失败');
                            Db::rollback();
                            $data['Balance'] = (float)bcdiv((string)$balance['data'], (string)100, 2);
                            $data['ErrorCode'] = 7;
                            $data['ErrorMessage'] = 'Internal Error';
                            return $this->response->json($data);
                        }

                    }else{//加注
                        $data['Balance'] = $old_balance;
                        if ($ProductType == 1 || $ProductType == 5){//体育
                            $data['ErrorCode'] = 5003;
                            $data['ErrorMessage'] = 'Bet With Same RefNo Exists';
                            return $this->response->json($data);
                        }

                        $game_log = $log;
                        $Amount = $param['Amount']*100;
                        $game_log['other'] = bcsub((string)$Amount, (string)bcadd((string)$log['cashBetAmount'],(string)$log['bonusBetAmount']));

                        switch ($log['is_settlement']){
                            case 1:
                                Db::rollback();
                                $this->logger->error("sbo二次下注已结算param===>".json_encode($param));
                                if ($ProductType == 3){
                                    $data['ErrorCode'] = 5003;
                                    $data['ErrorMessage'] = 'Bet With Same RefNo Exists';
                                }else {
                                    $data['ErrorCode'] = 2001;
                                    $data['ErrorMessage'] = 'Bet Already Settled';
                                }
                                return $this->response->json($data);
                                break;
                            case 2:
                                Db::rollback();
                                $this->logger->error("sbo二次下注已取消无效param===>".json_encode($param));
                                if ($ProductType == 3){
                                    $data['ErrorCode'] = 5003;
                                    $data['ErrorMessage'] = 'Bet With Same RefNo Exists';
                                }else {
                                    $data['ErrorCode'] = 2002;
                                    $data['ErrorMessage'] = 'Bet Already Canceled';
                                }
                                return $this->response->json($data);
                                break;
                            case 3:
                                Db::rollback();
                                $this->logger->error("sbo二次下注已回滚param===>".json_encode($param));
                                $data['ErrorCode'] = 2003;
                                $data['ErrorMessage'] = 'Bet Already Rollback';
                                return $this->response->json($data);
                                break;
                            case 4:
                                Db::rollback();
                                $this->logger->error("sbo二次下注已归还param===>".json_encode($param));
                                $data['ErrorCode'] = 5008;
                                $data['ErrorMessage'] = 'Bet Already Returned Stake';
                                return $this->response->json($data);
                                break;
                            default:
                                break;
                        }

                        //判断加注金额
                        if ($param['Amount'] * 100 <= bcadd((string)$log['cashBetAmount'], (string)$log['bonusBetAmount'])) {
                            Db::rollback();
                            $this->logger->error("sbo二次下注金额不足param===>" . json_encode($param));
                            $data['ErrorCode'] = 7;
                            $data['ErrorMessage'] = 'Internal Error';
                            return $this->response->json($data);
                        }

                        //资金变化
                        $balance = $this->slotsCommon->slotsLog2($game_log, $userinfo['coin'], $userinfo['bonus'], $Amount, $game_log['other'], 0, 1, 1, 1);
                        if ($balance['code'] != 200){
                            $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏sbo记录存储失败');
                            Db::rollback();
                            $data['ErrorCode'] = 7;
                            $data['ErrorMessage'] = 'Internal Error';
                            return $this->response->json($data);
                        }
                    }

                    break;
                case 9:
                    //$log = Db::table('slots_log_' . date('Ymd'))->where('betId', $param['TransferCode'].'_'.$param['TransactionId'])->first();
                    $log = $this->slotsCommon->SlotsLogView($param['TransferCode'].'_'.$param['TransactionId']);
                    if (empty($log)){
                        $game_log = [
                            'betId' => $param['TransferCode'].'_'.$param['TransactionId'],
                            'parentBetId' => $param['TransferCode'],
                            'uid' => $userinfo['uid'],
                            'puid' => $userinfo['puid'],
                            'terrace_name' => 'sbo',
                            'slotsgameid' => $param['ProductType'].$param['GameType'],
                            'game_id' => $game_info['slots_game_id'],
                            'englishname' => $game_info['englishname'],
                            'transaction_id' => $ordersn,
                            'package_id' => $userinfo['package_id'],
                            'channel' => $userinfo['channel'],
                            'betTime' => strtotime($param['BetTime']),
                            'createtime' => time(),
                            'is_settlement' => 0, //0-运行中未结算 1-已结算 2-已取消失效 3-已回滚 4-已归还
                        ];
                        //资金变化
                        $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $param['Amount']*100, 0, 2);
                        if ($balance['code'] != 200){
                            $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏sbo记录存储失败');
                            Db::rollback();
                            $data['Balance'] = (float)bcdiv((string)$balance['data'], (string)100, 2);
                            $data['ErrorCode'] = 7;
                            $data['ErrorMessage'] = 'Internal Error';
                            return $this->response->json($data);
                        }
                    }else{//已存在
//                        if ($ProductType == 9){
//                            $loger = Db::table('slots_log_' . date('Ymd'))->where('betId', $param['TransferCode'])->where('parentBetId',$param['TransactionId'])->first();
//                            if (!empty($loger)){
                                $this->logger->error("sbo无缝二次下注已存在param===>".json_encode($param));
                                $data['ErrorCode'] = 5003;
                                $data['ErrorMessage'] = 'Bet With Same RefNo Exists';
                                return $this->response->json($data);
//                            }
//                        }
                    }

                    break;
                default:
                    $balance['data'] = 0;
                    break;
            }

            Db::commit();

            $data['Balance'] = (float)bcdiv((string)$balance['data'], (string)100, 2);
            $data['ErrorCode'] = 0;
            $data['ErrorMessage'] = 'No Error';
            return $this->response->json($data);
        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("sbo下注，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }

    }

    /**
     * 结算
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'Settle')]
    public function Settle(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'TransferCode' => 'required',
                'WinLoss' => 'required',
                'ResultType' => 'required',
                'ResultTime' => 'required',
                'CommissionStake' => 'required',
                //'GameResult' => 'required',
                'CompanyKey' => 'required',
                'Username' => 'required',
                'ProductType' => 'required',
                'GameType' => 'required',
                'IsCashOut' => 'required',
            ],
            [
                'TransferCode.required' => 'TransferCode is required',
                'WinLoss.required' => 'WinLoss is required',
                'ResultType.required' => 'ResultType is required',
                'ResultTime.required' => 'ResultTime is required',
                'CommissionStake.required' => 'CommissionStake is required',
                //'GameResult.required' => 'GameResult is required',
                'CompanyKey.required' => 'CompanyKey is required',
                'Username.required' => 'Username is required',
                'ProductType.required' => 'ProductType is required',
                'GameType.required' => 'GameType is required',
                'IsCashOut.required' => 'IsCashOut is required',
            ]
        );

        //$this->logger->error("sbo结算接口数据验证===>".json_encode($param));
        $data = [
            'AccountName' => '',
            'Balance' => 0.00,
            'ErrorCode' => 0,
            'ErrorMessage' => '',
        ];
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbo结算接口数据验证失败===>".$errorMessage);
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
        $data['AccountName'] = $param['Username'];

        if (config('sboslots.CompanyKey') != $param['CompanyKey']){
            $data['ErrorCode'] = 4;
            $data['ErrorMessage'] = 'CompanyKey Error';
            return $this->response->json($data);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("sbo结算用户不存在param===>".json_encode($param));
            $data['ErrorCode'] = 1;
            $data['ErrorMessage'] = 'Member not exist';
            return $this->response->json($data);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);
        $data['Balance'] = $old_balance;

        if ($param['ProductType'] != 9) {
            $log = Db::table('slots_log_' . date('Ymd'))->where('parentBetId', $param['TransferCode'])->first();
            if (empty($log)) {
                $this->logger->error("sbo结算订单不存在param===>" . json_encode($param));
                $data['ErrorCode'] = 6;
                $data['ErrorMessage'] = 'Bet not exists';
                return $this->response->json($data);
            }
            switch ($log['is_settlement']) {
                case 1:
                    Db::rollback();
                    $this->logger->error("sbo结算已结算param===>" . json_encode($param));
                    $data['ErrorCode'] = 2001;
                    $data['ErrorMessage'] = 'Bet Already Settled';
                    return $this->response->json($data);
                    break;
                case 2:
                    Db::rollback();
                    $this->logger->error("sbo结算已取消无效param===>" . json_encode($param));
                    $data['ErrorCode'] = 2002;
                    $data['ErrorMessage'] = 'Bet Already Canceled';
                    return $this->response->json($data);
                    break;
                case 3:
                    Db::rollback();
                    $this->logger->error("sbo结算已回滚param===>" . json_encode($param));
                    $data['ErrorCode'] = 2003;
                    $data['ErrorMessage'] = 'Bet Already Rollback';
                    return $this->response->json($data);
                    break;
                case 4:
                    Db::rollback();
                    $this->logger->error("sbo结算已归还param===>" . json_encode($param));
                    $data['ErrorCode'] = 5008;
                    $data['ErrorMessage'] = 'Bet Already Returned Stake';
                    return $this->response->json($data);
                    break;
                default:
                    break;
            }

            try {
                Db::beginTransaction();

                $log['is_settlement'] = 1;
                $log['betEndTime'] = strtotime($param['ResultTime']);
                //资金变化
//            $balance = $this->slotsCommon->slotsLog2($log, $userinfo['coin'], $userinfo['bonus'], bcadd((string)$log['cashBetAmount'],(string)$log['bonusBetAmount']), 0, $param['WinLoss']*100, 2);
                $balance = $this->slotsCommon->resultDealWith((array)$log, (array)$userinfo, (string)($param['WinLoss'] * 100), 2);
                if ($balance['code'] != 200) {
                    $this->logger->error('uid:' . $userinfo['uid'] . 'slotsLog三方游戏sbo记录结算失败');
                    Db::rollback();
                    $data['ErrorCode'] = 7;
                    $data['ErrorMessage'] = 'Internal Error';
                    return $this->response->json($data);
                }


                Db::commit();

                $data['Balance'] = (float)bcdiv((string)$balance['data'], (string)100, 2);
                $data['ErrorCode'] = 0;
                $data['ErrorMessage'] = 'No Error';
                return $this->response->json($data);

            } catch (\Throwable $ex) {
                Db::rollback();
                $this->logger->error("sbo结算，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
                $data['ErrorCode'] = 7;
                $data['ErrorMessage'] = 'Internal Error';
                return $this->response->json($data);
            }

        }else{//ProductType=9 无缝游戏
            $log = Db::table('slots_log_' . date('Ymd'))->where('parentBetId', $param['TransferCode'])->get()->toArray();
            if (empty($log)) {
                $this->logger->error("sbo结算订单不存在param===>" . json_encode($param));
                $data['ErrorCode'] = 6;
                $data['ErrorMessage'] = 'Bet not exists';
                return $this->response->json($data);
            }
            $is_have = false;
            foreach ($log as $key=>$value){
                if ($value['is_settlement'] == 0){
                    $is_have = true;
                }
            }

            if (!$is_have) {
                switch ($value['is_settlement']) {
                    case 1:
                        Db::rollback();
                        $this->logger->error("sbo结算已结算param===>" . json_encode($param));
                        $data['ErrorCode'] = 2001;
                        $data['ErrorMessage'] = 'Bet Already Settled';
//                        return $this->response->json($data);
                        break;
                    case 2:
                        Db::rollback();
                        $this->logger->error("sbo结算已取消无效param===>" . json_encode($param));
                        $data['ErrorCode'] = 2002;
                        $data['ErrorMessage'] = 'Bet Already Canceled';
//                        return $this->response->json($data);
                        break;
                    case 3:
                        Db::rollback();
                        $this->logger->error("sbo结算已回滚param===>" . json_encode($param));
                        $data['ErrorCode'] = 2003;
                        $data['ErrorMessage'] = 'Bet Already Rollback';
//                        return $this->response->json($data);
                        break;
                    case 4:
                        Db::rollback();
                        $this->logger->error("sbo结算已归还param===>" . json_encode($param));
                        $data['ErrorCode'] = 5008;
                        $data['ErrorMessage'] = 'Bet Already Returned Stake';
//                        return $this->response->json($data);
                        break;
                    default:
                        break;
                }
            }

            try {
                Db::beginTransaction();

                if (!$is_have){//无未结算的
                    return $this->response->json($data);
                }

                $balance = 0;
                foreach ($log as $key2=>$value2){
                    $value2['is_settlement'] = 1;
                    $value2['betEndTime'] = strtotime($param['ResultTime']);
                    //资金变化
                    $WinLoss = '0';
                    if ($key2 == 0) {
                        $WinLoss = (string)($param['WinLoss'] * 100);
                    }
                    $balance = $this->slotsCommon->resultDealWith((array)$value2, (array)$userinfo, $WinLoss, 2);
                    if ($balance['code'] != 200) {
                        $this->logger->error('uid:' . $userinfo['uid'] . 'slotsLog三方游戏sbo记录结算失败');
                        Db::rollback();
                        $data['ErrorCode'] = 7;
                        $data['ErrorMessage'] = 'Internal Error';
                        return $this->response->json($data);
                    }
                    $userinfo['coin'] += $WinLoss;
                }


                Db::commit();

                $data['Balance'] = (float)bcdiv((string)$balance['data'], (string)100, 2);
                $data['ErrorCode'] = 0;
                $data['ErrorMessage'] = 'No Error';
                return $this->response->json($data);

            } catch (\Throwable $ex) {
                Db::rollback();
                $this->logger->error("sbo结算，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
                $data['ErrorCode'] = 7;
                $data['ErrorMessage'] = 'Internal Error';
                return $this->response->json($data);
            }
        }


        return $this->response->json($data);
    }

    /**
     * 回滚
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'Rollback')]
    public function Rollback(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'CompanyKey' => 'required',
                'Username' => 'required',
                'TransferCode' => 'required',
                'ProductType' => 'required',
                'GameType' => 'required',
            ],
            [
                'CompanyKey.required' => 'CompanyKey is required',
                'Username.required' => 'Username is required',
                'TransferCode.required' => 'TransferCode is required',
                'ProductType.required' => 'ProductType is required',
                'GameType.required' => 'GameType is required',
            ]
        );

        //$this->logger->error("sbo回滚接口数据验证===>".json_encode($param));
        $data = [
            'AccountName' => '',
            'Balance' => 0.00,
            'ErrorCode' => 0,
            'ErrorMessage' => '',
        ];
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbo回滚接口数据验证失败===>".$errorMessage);
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
        $data['AccountName'] = $param['Username'];

        if (config('sboslots.CompanyKey') != $param['CompanyKey']){
            $data['ErrorCode'] = 4;
            $data['ErrorMessage'] = 'CompanyKey Error';
            return $this->response->json($data);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("sbo回滚用户不存在param===>".json_encode($param));
            $data['ErrorCode'] = 1;
            $data['ErrorMessage'] = 'Member not exist';
            return $this->response->json($data);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);
        $data['Balance'] = $old_balance;

        $log = Db::table('slots_log_' . date('Ymd'))->where('parentBetId', $param['TransferCode'])->first();
        if (empty($log)){
            $this->logger->error("sb回滚订单不存在param===>".json_encode($param));
            $data['ErrorCode'] = 6;
            $data['ErrorMessage'] = 'Bet not exists';
            return $this->response->json($data);
        }
        switch ($log['is_settlement']){
            case 0:
                $this->logger->error("sbo回滚未结算param===>".json_encode($param));
                $data['ErrorCode'] = 2003;
                $data['ErrorMessage'] = 'Bet Already Rollback';
                return $this->response->json($data);
                break;
            /*case 2:
                $this->logger->error("sbo回滚已取消无效param===>".json_encode($param));
                $data['ErrorCode'] = 2002;
                $data['ErrorMessage'] = 'Bet Already Canceled';
                return $this->response->json($data);
                break;*/
            case 3:
                $this->logger->error("sbo已回滚param===>".json_encode($param));
                $data['ErrorCode'] = 2003;
                $data['ErrorMessage'] = 'Bet Already Rollback';
                return $this->response->json($data);
                break;
            case 4:
                $this->logger->error("sbo回滚已归还param===>".json_encode($param));
                $data['ErrorCode'] = 5008;
                $data['ErrorMessage'] = 'Bet Already Returned Stake';
                return $this->response->json($data);
                break;
            default:
                break;
        }


        try {
            Db::beginTransaction();

            if ($param['ProductType'] != 9) {

                if ($log['is_settlement'] == 1) {
                    //下注时已扣押注，结算时派奖 即为 变化
                    $cash_transferAmount = $log['cashWinAmount'];
                    $bouns_transferAmount = $log['bonusWinAmount'];
                } else {
                    $cash_transferAmount = $log['cashBetAmount'];
                    $bouns_transferAmount = $log['bonusBetAmount'];
                }

                $log['cashWinAmount'] = 0;
                $log['bonusWinAmount'] = 0;
                $log['cashTransferAmount'] = 0;
                $log['bonusTransferAmount'] = 0;
                $log['is_settlement'] = 0;
                //修改log
                $this->slotsCommon->updateSlotsLog($log['betId'], $log);

                //修改流水和余额
                if (($cash_transferAmount + $bouns_transferAmount) > 0) {
                    $new_cash = bcsub((string)$userinfo['coin'], (string)$cash_transferAmount);
                    $new_bouns = bcsub((string)$userinfo['bonus'], (string)$bouns_transferAmount);
                    $old_balance = bcadd((string)bcdiv((string)$new_cash, '100', 2), (string)bcdiv((string)$new_bouns, '100', 2), 2);
                    $res = $this->slotsCommon->userFundChange($log['uid'], -$cash_transferAmount, -$bouns_transferAmount, $new_cash, $new_bouns, $log['channel'], $log['package_id']);
                    if (!$res) {
                        $this->logger->error('uid:' . $log['uid'] . '三方游戏huigun余额修改失败-Cash输赢:' . $log['cashTransferAmount'] . '-Bonus输赢:' . $log['bonusTransferAmount']);
                        Db::rollback();
                        $data['ErrorCode'] = 7;
                        $data['ErrorMessage'] = 'Internal Error';
                        return $this->response->json($data);
                    }
                }

            }else{//ProductType=9 无缝游戏
                $all_log = Db::table('slots_log_' . date('Ymd'))->where('parentBetId', $param['TransferCode'])->get()->toArray();
                foreach ($all_log as $key=>$value){
                    if ($value['is_settlement'] == 1) {
                        //下注时已扣押注，结算时派奖 即为 变化
                        $cash_transferAmount = $value['cashWinAmount'];
                        $bouns_transferAmount = $value['bonusWinAmount'];
                    } else {
                        $cash_transferAmount = $value['cashBetAmount'];
                        $bouns_transferAmount = $value['bonusBetAmount'];
                    }
                    $value['cashWinAmount'] = 0;
                    $value['bonusWinAmount'] = 0;
                    $value['cashTransferAmount'] = 0;
                    $value['bonusTransferAmount'] = 0;
                    $value['is_settlement'] = 0;
                    //修改log
                    $this->slotsCommon->updateSlotsLog($value['betId'], $value);

                    //修改流水和余额
                    if (($cash_transferAmount + $bouns_transferAmount) > 0) {
                        $new_cash = bcsub((string)$userinfo['coin'], (string)$cash_transferAmount);
                        $new_bouns = bcsub((string)$userinfo['bonus'], (string)$bouns_transferAmount);
                        $old_balance = bcadd((string)bcdiv((string)$new_cash, '100', 2), (string)bcdiv((string)$new_bouns, '100', 2), 2);
                        $res = $this->slotsCommon->userFundChange($value['uid'], -$cash_transferAmount, -$bouns_transferAmount, $new_cash, $new_bouns, $value['channel'], $value['package_id']);
                        if (!$res) {
                            $this->logger->error('uid:' . $value['uid'] . '三方游戏huigun余额修改失败-Cash输赢:' . $value['cashTransferAmount'] . '-Bonus输赢:' . $value['bonusTransferAmount']);
                            Db::rollback();
                            $data['ErrorCode'] = 7;
                            $data['ErrorMessage'] = 'Internal Error';
                            return $this->response->json($data);
                        }
                    }
                    $userinfo['coin'] -= $cash_transferAmount;
                    $userinfo['bonus'] -= $bouns_transferAmount;
                }
            }

            Db::commit();

            $data['Balance'] = (float)$old_balance;
            $data['ErrorCode'] = 0;
            $data['ErrorMessage'] = 'No Error';
            return $this->response->json($data);
        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("sbo回滚，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
    }

    /**
     * 取消投注
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'Cancel')]
    public function Cancel(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'CompanyKey' => 'required',
                'Username' => 'required',
                'TransferCode' => 'required',
                'TransactionId' => 'required',
                'ProductType' => 'required',
                'GameType' => 'required',
                'IsCancelAll' => 'required',
            ],
            [
                'CompanyKey.required' => 'CompanyKey is required',
                'Username.required' => 'Username is required',
                'TransferCode.required' => 'TransferCode is required',
                'TransactionId.required' => 'TransferCode is required',
                'ProductType.required' => 'ProductType is required',
                'GameType.required' => 'GameType is required',
                'IsCancelAll.required' => 'IsCancelAll is required',
            ]
        );

        //$this->logger->error("sbo取消接口数据验证===>".json_encode($param));
        $data = [
            'AccountName' => '',
            'Balance' => 0.00,
            'ErrorCode' => 0,
            'ErrorMessage' => '',
        ];
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbo取消接口数据验证失败===>".$errorMessage);
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
        $data['AccountName'] = $param['Username'];

        if (config('sboslots.CompanyKey') != $param['CompanyKey']){
            $data['ErrorCode'] = 4;
            $data['ErrorMessage'] = 'CompanyKey Error';
            return $this->response->json($data);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("sbo取消用户不存在param===>".json_encode($param));
            $data['ErrorCode'] = 1;
            $data['ErrorMessage'] = 'Member not exist';
            return $this->response->json($data);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);
        $data['Balance'] = $old_balance;

        $log = Db::table('slots_log_' . date('Ymd'))->where('betId', $param['TransferCode'].'_'.$param['TransactionId'])->first();
        if (empty($log)){
            $this->logger->error("sbo取消订单不存在param===>".json_encode($param));
            $data['ErrorCode'] = 6;
            $data['ErrorMessage'] = 'Bet not exists';
            return $this->response->json($data);
        }
        switch ($log['is_settlement']){
            case 2:
                $this->logger->error("sbo取消已取消无效param===>".json_encode($param));
                $data['ErrorCode'] = 2002;
                $data['ErrorMessage'] = 'Bet Already Canceled';
                return $this->response->json($data);
                break;
            case 3:
                $this->logger->error("sbo取消已回滚param===>".json_encode($param));
                $data['ErrorCode'] = 2003;
                $data['ErrorMessage'] = 'Bet Already Rollback';
                return $this->response->json($data);
                break;
            case 4:
                $this->logger->error("sbo取消回滚已归还param===>".json_encode($param));
                $data['ErrorCode'] = 5008;
                $data['ErrorMessage'] = 'Bet Already Returned Stake';
                return $this->response->json($data);
                break;
            default:
                break;
        }

        try {
            Db::beginTransaction();

            if ($param['ProductType'] != 9) {
                if ($log['is_settlement'] == 0) {
                    if ($log['other'] != '') {//加注取消
                        $cash_transferAmount = $log['other'];
                        $bouns_transferAmount = 0;

                        $log['cashBetAmount'] = $log['cashBetAmount'] - $log['other'];
                        $log['other'] = '';

                    } else {
                        $cash_transferAmount = $log['cashBetAmount'];
                        $bouns_transferAmount = $log['bonusBetAmount'];
                    }
                } else {
                    $cash_transferAmount = -$log['cashTransferAmount'];
                    $bouns_transferAmount = -$log['bonusTransferAmount'];
                }
                $log['is_settlement'] = 2;
                //修改log
                $this->slotsCommon->updateSlotsLog($log['betId'], $log);

                //修改流水和余额
                if (bcadd((string)$cash_transferAmount, (string)$bouns_transferAmount) != 0) {
                    $new_cash = bcadd((string)$userinfo['coin'], (string)$cash_transferAmount);
                    $new_bouns = bcadd((string)$userinfo['bonus'], (string)$bouns_transferAmount);
                    $old_balance = bcadd((string)bcdiv((string)$new_cash, '100', 2), (string)bcdiv((string)$new_bouns, '100', 2), 2);
                    $res = $this->slotsCommon->userFundChange($log['uid'], $cash_transferAmount, $bouns_transferAmount, $new_cash, $new_bouns, $log['channel'], $log['package_id']);
                    if (!$res) {
                        $this->logger->error('uid:' . $log['uid'] . '三方游戏huigun余额修改失败-Cash输赢:' . $log['cashTransferAmount'] . '-Bonus输赢:' . $log['bonusTransferAmount']);
                        Db::rollback();
                        $data['ErrorCode'] = 7;
                        $data['ErrorMessage'] = 'Internal Error';
                        return $this->response->json($data);
                    }
                }

            }else{//ProductType=9 无缝游戏
                if ($log['is_settlement'] == 0){
                    $cash_transferAmount = $log['cashBetAmount'];
                    $bouns_transferAmount = $log['bonusBetAmount'];
                    $log['is_settlement'] = 2;
                    //修改log
                    $this->slotsCommon->updateSlotsLog($log['betId'], $log);
                    //修改流水和余额
                    if (bcadd((string)$cash_transferAmount, (string)$bouns_transferAmount) != 0) {
                        $new_cash = bcadd((string)$userinfo['coin'], (string)$cash_transferAmount);
                        $new_bouns = bcadd((string)$userinfo['bonus'], (string)$bouns_transferAmount);
                        $old_balance = bcadd((string)bcdiv((string)$new_cash, '100', 2), (string)bcdiv((string)$new_bouns, '100', 2), 2);
                        $res = $this->slotsCommon->userFundChange($log['uid'], $cash_transferAmount, $bouns_transferAmount, $new_cash, $new_bouns, $log['channel'], $log['package_id']);
                        if (!$res) {
                            $this->logger->error('uid:' . $log['uid'] . '三方游戏huigun余额修改失败-Cash输赢:' . $log['cashTransferAmount'] . '-Bonus输赢:' . $log['bonusTransferAmount']);
                            Db::rollback();
                            $data['ErrorCode'] = 7;
                            $data['ErrorMessage'] = 'Internal Error';
                            return $this->response->json($data);
                        }
                    }

                }else{//已结算
                    $all_log = Db::table('slots_log_' . date('Ymd'))->where('parentBetId', $param['TransferCode'])->get()->toArray();
                    foreach ($all_log as $key=>$value){
                        if ($value['is_settlement'] == 1){
                            $cash_transferAmount = -$value['cashTransferAmount'];
                            $bouns_transferAmount = -$value['bonusTransferAmount'];
                        }else{
                            $cash_transferAmount = $value['cashBetAmount'];
                            $bouns_transferAmount = $value['bonusBetAmount'];
                        }

                        $value['is_settlement'] = 2;
                        //修改log
                        $this->slotsCommon->updateSlotsLog($value['betId'], $value);
                        //修改流水和余额
                        if (bcadd((string)$cash_transferAmount, (string)$bouns_transferAmount) != 0) {
                            $new_cash = bcadd((string)$userinfo['coin'], (string)$cash_transferAmount);
                            $new_bouns = bcadd((string)$userinfo['bonus'], (string)$bouns_transferAmount);
                            $old_balance = bcadd((string)bcdiv((string)$new_cash, '100', 2), (string)bcdiv((string)$new_bouns, '100', 2), 2);
                            $res = $this->slotsCommon->userFundChange($value['uid'], $cash_transferAmount, $bouns_transferAmount, $new_cash, $new_bouns, $value['channel'], $value['package_id']);
                            if (!$res) {
                                $this->logger->error('uid:' . $value['uid'] . '三方游戏huigun余额修改失败-Cash输赢:' . $value['cashTransferAmount'] . '-Bonus输赢:' . $value['bonusTransferAmount']);
                                Db::rollback();
                                $data['ErrorCode'] = 7;
                                $data['ErrorMessage'] = 'Internal Error';
                                return $this->response->json($data);
                            }
                        }
                        $userinfo['coin'] += $cash_transferAmount;
                        $userinfo['bonus'] += $bouns_transferAmount;
                    }

                }
            }


            Db::commit();

            $data['Balance'] = (float)$old_balance;
            $data['ErrorCode'] = 0;
            $data['ErrorMessage'] = 'No Error';
            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("sbo取消，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
    }

    /**
     * 红利
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'Bonus')]
    public function Bonus(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'CompanyKey' => 'required',
                'Username' => 'required',
                'Amount' => 'required',
                'BonusTime' => 'required',
                'ProductType' => 'required',
                'GameType' => 'required',
                'TransferCode' => 'required',
                'TransactionId' => 'required',
            ],
            [
                'CompanyKey.required' => 'CompanyKey is required',
                'Username.required' => 'Username is required',
                'Amount.required' => 'Amount is required',
                'BonusTime.required' => 'BonusTime is required',
                'ProductType.required' => 'ProductType is required',
                'GameType.required' => 'GameType is required',
                'TransferCode.required' => 'TransferCode is required',
                'TransactionId.required' => 'TransferCode is required',
            ]
        );

        //$this->logger->error("sbo红利接口数据验证===>".json_encode($param));
        $data = [
            'AccountName' => '',
            'Balance' => 0.00,
            'ErrorCode' => 0,
            'ErrorMessage' => '',
        ];
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbo红利接口数据验证失败===>".$errorMessage);
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
        $data['AccountName'] = $param['Username'];

        if (config('sboslots.CompanyKey') != $param['CompanyKey']){
            $data['ErrorCode'] = 4;
            $data['ErrorMessage'] = 'CompanyKey Error';
            return $this->response->json($data);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("sbo红利用户不存在param===>".json_encode($param));
            $data['ErrorCode'] = 1;
            $data['ErrorMessage'] = 'Member not exist';
            return $this->response->json($data);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);
        $data['Balance'] = $old_balance;

        /*$log = Db::table('slots_log_' . date('Ymd'))->where('betId', $param['TransferCode'])->first();
        if (empty($log)){
            $this->logger->error("sbo红利订单不存在param===>".json_encode($param));
            $data['ErrorCode'] = 6;
            $data['ErrorMessage'] = 'Bet not exists';
            return $this->response->json($data);
        }*/
        $bonus_log = Db::table('slots_bonus')->where('roundId',$param['TransferCode'].'_'.$param['TransactionId'])->first();
        if (!empty($bonus_log)){
            $this->logger->error("sbo红利订单已存在param===>".json_encode($param));
            $data['ErrorCode'] = 5003;
            $data['ErrorMessage'] = 'Bet With Same RefNo Exists';
            return $this->response->json($data);
        }

        try {
            Db::beginTransaction();

            $cash_transferAmount = $param['Amount']*100;

            $slots_bonus_data = [
                'uid' => $userinfo['uid'],
                'terrace_name' => 'sbo',
                'roundId' => $param['TransferCode'].'_'.$param['TransactionId'],
                'transactionId' => $param['TransferCode'],
                'amount' => $cash_transferAmount,
                'type' => 1,
                'bonustime' => strtotime($param['BonusTime']),
            ];
            $res = $this->slotsCommon->slotsBonus($slots_bonus_data,(int)$cash_transferAmount);
            if(!$res){
                $this->logger->error("sbo红利订单生成失败param===>".json_encode($param));
                $data['ErrorCode'] = 7;
                $data['ErrorMessage'] = 'Internal Error';
                return $this->response->json($data);
            }

            Db::commit();

            $data['Balance'] = (float)bcadd((string)$old_balance,(string)$param['Amount'],2);
            $data['ErrorCode'] = 0;
            $data['ErrorMessage'] = 'No Error';
            return $this->response->json($data);
        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("sbo取消，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
    }

    /**
     * 归还注额
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'ReturnStake')]
    public function ReturnStake(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'CompanyKey' => 'required',
                'Username' => 'required',
                'CurrentStake' => 'required',
                'ReturnStakeTime' => 'required',
                'ProductType' => 'required',
                'GameType' => 'required',
                'TransferCode' => 'required',
                'TransactionId' => 'required',
            ],
            [
                'CompanyKey.required' => 'CompanyKey is required',
                'Username.required' => 'Username is required',
                'CurrentStake.required' => 'CurrentStake is required',
                'ReturnStakeTime.required' => 'ReturnStakeTime is required',
                'ProductType.required' => 'ProductType is required',
                'GameType.required' => 'GameType is required',
                'TransferCode.required' => 'TransferCode is required',
                'TransactionId.required' => 'TransferCode is required',
            ]
        );

        //$this->logger->error("sbo红利接口数据验证===>".json_encode($param));
        $data = [
            'AccountName' => '',
            'Balance' => 0.00,
            'ErrorCode' => 0,
            'ErrorMessage' => '',
        ];
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbo归还接口数据验证失败===>".$errorMessage);
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
        $data['AccountName'] = $param['Username'];

        if (config('sboslots.CompanyKey') != $param['CompanyKey']){
            $data['ErrorCode'] = 4;
            $data['ErrorMessage'] = 'CompanyKey Error';
            return $this->response->json($data);
        }

        $log = Db::table('slots_log_' . date('Ymd'))->where('betId', $param['TransferCode'].'_'.$param['TransactionId'])->first();
        if (empty($log)){
            $this->logger->error("sbo归还订单不存在param===>".json_encode($param));
            $data['ErrorCode'] = 6;
            $data['ErrorMessage'] = 'Bet not exists';
            return $this->response->json($data);
        }
        if ($log['is_settlement'] == 2){
            $this->logger->error("sbo归还已取消param===>".json_encode($param));
            $data['ErrorCode'] = 2002;
            $data['ErrorMessage'] = 'Bet Already Canceled';
            return $this->response->json($data);
        }
        if ($log['other2'] == 1){
            $this->logger->error("sbo已归还param===>".json_encode($param));
            $data['ErrorCode'] = 5008;
            $data['ErrorMessage'] = 'Bet Already Returned Stake';
            return $this->response->json($data);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("sbo归还用户不存在param===>".json_encode($param));
            $data['ErrorCode'] = 1;
            $data['ErrorMessage'] = 'Member not exist';
            return $this->response->json($data);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);
        $data['Balance'] = $old_balance;

        try {
            Db::beginTransaction();

            $chanBetAmount = bcsub((string)$log['cashBetAmount'], (string)($param['CurrentStake']*100));

            //修改log
            $up_data = [
                'cashBetAmount' => $param['CurrentStake']*100,
                'other2' => 1,
            ];
            $this->slotsCommon->updateSlotsLog($log['betId'], $up_data);

            if ($log['is_settlement'] == 0 || $log['is_settlement'] == 1){//已结算的 归还部分
                //修改流水和余额
                if ($chanBetAmount != 0) {
                    $new_cash = bcadd((string)$userinfo['coin'], (string)$chanBetAmount);
                    $new_bouns = $userinfo['bonus'];
                    $old_balance = bcadd((string)bcdiv((string)$new_cash,'100',2), (string)bcdiv((string)$new_bouns, '100', 2),2);
                    $res = $this->slotsCommon->userFundChange($log['uid'], $chanBetAmount, 0, $new_cash, $new_bouns, $log['channel'], $log['package_id']);
                    if (!$res) {
                        $this->logger->error('uid:' . $log['uid'] . 'sbo归还余额修改失败-Cash输赢:' . $log['cashTransferAmount'] . '-Bonus输赢:' . $log['bonusTransferAmount']);
                        Db::rollback();
                        $data['ErrorCode'] = 7;
                        $data['ErrorMessage'] = 'Internal Error';
                        return $this->response->json($data);
                    }
                }
            }


            Db::commit();

            $data['Balance'] = (float)$old_balance;
            $data['ErrorCode'] = 0;
            $data['ErrorMessage'] = 'No Error';

            return $this->response->json($data);
        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("sbo归还，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }

    }

    /**
     * 取得投注状态
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'GetBetStatus')]
    public function GetBetStatus(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'CompanyKey' => 'required',
                'Username' => 'required',
                'ProductType' => 'required',
                'GameType' => 'required',
                'TransferCode' => 'required',
                'TransactionId' => 'required',
            ],
            [
                'CompanyKey.required' => 'CompanyKey is required',
                'Username.required' => 'Username is required',
                'ProductType.required' => 'ProductType is required',
                'GameType.required' => 'GameType is required',
                'TransferCode.required' => 'TransferCode is required',
                'TransactionId.required' => 'TransferCode is required',
            ]
        );

        //$this->logger->error("sbo状态接口数据验证===>".json_encode($param));
        $data = [
            'TransferCode' => '',
            'TransactionId' => '',
            'Status' => '',
            'WinLoss' => 0.00,
            'Stake' => 0.00,
            'ErrorCode' => 0,
            'ErrorMessage' => '',
        ];
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("sbo状态接口数据验证失败===>".$errorMessage);
            $data['ErrorCode'] = 7;
            $data['ErrorMessage'] = 'Internal Error';
            return $this->response->json($data);
        }
        $data['TransferCode'] = $param['TransferCode'];
        $data['TransactionId'] = $param['TransactionId'];

        if (config('sboslots.CompanyKey') != $param['CompanyKey']){
            $data['ErrorCode'] = 4;
            $data['ErrorMessage'] = 'CompanyKey Error';
            return $this->response->json($data);
        }

        $log = Db::table('slots_log_' . date('Ymd'))->where('betId', $param['TransferCode'].'_'.$param['TransactionId'])->first();
        if (empty($log)){
            $this->logger->error("sbo状态订单不存在param===>".json_encode($param));
            $data['ErrorCode'] = 6;
            $data['ErrorMessage'] = 'Bet not exists';
            return $this->response->json($data);
        }

        $status = '';
        if ($log['is_settlement'] == 0){
            $status = 'running';
        }elseif ($log['is_settlement'] == 1){
            $status = 'settled';
        }elseif ($log['is_settlement'] == 2){
            $status = 'void';
        }

        $data['Status'] = $status;
        $data['WinLoss'] = (float)bcdiv((string)bcadd((string)$log['cashWinAmount'],(string)$log['bonusWinAmount']), '100',2);
        $data['Stake'] = (float)bcdiv((string)bcadd((string)$log['cashBetAmount'],(string)$log['bonusBetAmount']), '100', 2);
        $data['ErrorCode'] = 0;
        $data['ErrorMessage'] = 'No Error';

        return $this->response->json($data);
    }


    /**
     * 创建代理
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    #[RequestMapping(path: 'setAgent')]
    public function setAgent(){
        $config = config('sboslots');

        $url = $config['api_url'].'/web-root/restricted/agent/register-agent.aspx';
        $body = [
            'CompanyKey' => $config['CompanyKey'],
            'ServerId' => $config['ServerId'],
            'Username' => $config['Agent'],
            'Password' => $config['Password'],
            'Currency' => $config['Currency'],
            'min' => 50,
            'max' => 1000,
            'MaxPerMatch' => 1000000,
            'CasinoTableLimit' => 4,
        ];
        $res = $this->guzzle->post($url, $body, self::$herder_json);
        if(!$res || $res['error']['id'] != 0){
            return ['code' => 201,'msg' => $res['error']['msg'] ,'data' =>[]];
        }
        $this->logger->error('创建代理===>'.json_encode($res));

        return ['code' => 200,'msg' => 'success' ,'data' =>$body];
    }


    /**
     * 创建玩家
     * @param $player_name  玩家uid
     * @return bool|string
     */
    //#[RequestMapping(path: 'playerCreated')]
    public function playerCreated($uid){
        $config = config('sboslots');
        $url  = $config['api_url'].'/web-root/restricted/player/register-player.aspx';
        $body = [
            'CompanyKey' => $config['CompanyKey'],
            'ServerId' => $config['ServerId'],
            'Username' => $uid,
            'Agent' => $config['Agent'],
        ];

        $data = $this->guzzle->post($url,$body, self::$herder_json);
        if(!isset($data['error']) || $data['error']['id'] != 0){
            $this->logger->error('Sbo用户创建失败-uid:'.$uid.'三方返回:'.json_encode($data));
            return ['code' => 201,'msg' => $data['error']['msg'] ?? '' ,'data' =>'' ];
        }
        //$this->logger->error('Sbo用户创建-uid:'.$uid.'三方返回:'.json_encode($data));

        return ['code' => 200,'msg' => $data['error']['msg'] ,'data' =>[]];

    }

    /******************************主动请求模式*******************************/

    private static $herder = [
        'Content-Type' => 'application/x-www-form-urlencoded',
    ];
    private static $herder_json = [
        'Content-Type' => 'application/json',
    ];

    /**
     * 获取游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public static function getGameUrl($uid,$gameid){
        $token = Db::table('user_token')->where('uid',$uid)->value('token');
        if (empty($token)){
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }

        $config = config('sboslots');
        $url = $config['api_url'].'/web-root/restricted/player/login.aspx';
        $type = substr((string)$gameid, 0, 1);
        $Portfolio = '';
        switch ($type){
            case 7:
                $Portfolio = 'Casino';
                break;
            case 3:
                $Portfolio = 'Games';
                break;
            case 5:
                $Portfolio = 'VirtualSports';
                break;
            case 9:
                $Portfolio = 'SeamlessGame';
                break;
            default:
                break;
        }
        $data = [
            'CompanyKey' => $config['CompanyKey'],
            'ServerId' => $config['ServerId'],
            'Username' => $uid,
            'Portfolio' => $Portfolio,
        ];


        $res = self::senPostCurl($url,$data);

        if(!$res || $res['error']['id'] != 0){
            return ['code' => 201,'msg' => $res['error']['msg'] ,'data' =>[]];
        }

        $gameid = substr((string)$gameid, 1);
        $gameUrl = '';
        switch ($type){
            case 7:
                $gameUrl = 'https:'.$res['url'].'&locale='.$config['language'].'&device=m&productId='.$gameid;
                break;
            case 3:
                $gameUrl = 'https:'.$res['url'].'&gameId='.$gameid;
                break;
            case 5:
                $gameUrl = 'https:'.$res['url'].'&lang='.$config['language'];
                break;
            case 9:
                $gameUrl = 'https:'.$res['url'].'&gpid='.$gameid.'&gameid=0&lang='.$config['language'].'&device=m';
                break;
            default:
                break;
        }

        return ['code' => 200,'msg' => 'success' ,'data' =>$gameUrl];

    }


    /**
     * body数据
     * @param $body
     * @return string hmac-sha256数据
     */
    private static function getKey($body,$type){
        if ($type == 'get'){
            $body = self::getQueryString($body);
        }else {
            $body = json_encode($body);
        }
        $key = config('egslots.Hash_key');
        $hash = hash_hmac('sha256', $body, $key);
        return $hash;
    }

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
        $dataString = $guzzle->post($url, $body, self::$herder_json);

        return $dataString;

    }

    /** 发送Curl
     * @param $url
     * @param $body
     * @return mixed
     */
    private static function senGetCurl($url,$body,$urlencodeData = []){
        //$dataString =  Curl::get($url,$body,$urlencodeData);
        $url = Curl::getUrl($url,$body);
        //dd($url);
        $guzzle = new Guzzle();
        $dataString = $guzzle->get($url,[],self::$herder_json);
//        $dataString = Curl::get($url);
        return $dataString;

    }


}






