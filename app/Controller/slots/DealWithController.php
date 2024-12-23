<?php

declare(strict_types=1);

namespace App\Controller\slots;

use Hyperf\Context\Context;
use App\Common\User;
use App\Controller\SqlModel;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use App\Controller\AdjustController;
use App\Common\Common as AppCommon;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;

/**
 *  队列事务处理
 */
class DealWithController
{

    #[Inject]
    protected LoggerInterface $logger;

    #[Inject]
    protected AdjustController $adjust;

    private array $zyGameType = [1502,1503,1504,1506,1508,1509,1514,1511,1510,1512,1513,1515,1516]; //这里自研游戏不单独计算的game_type


    private array $needAdjustGameNumPackageId = ['com.win3377.mtg'];//需要打游戏次数的包
    private array $needAdjustGameNum= [5,15,50,100]; //需要打点的游戏次数
    private array $newNeedAdjustGameNumPackageId = ['com.win3377.gb'];
    private array $newNeedAdjustGameNum = [15,30];
    /**
     * 三方游戏事务处理
     * @param array $data
     * @return int
     */
    public function SlotsLogDealWith(array $data = []):int
    {
        if(!$data)return 1;
        $data = is_array($data) ? $data : json_decode($data,true);
        $slots_log = $data;

        try {

            $res = $this->installSlotsLog($slots_log);
            if(!$res){
                $this->logger->error('UID:'.$slots_log['uid'].'三方游戏-betId:'.$slots_log['betId'].'-slots存储失败表数据处理失败');
                return 1;
            }
            $this->deleteSlotsLog((string)$slots_log['betId']);

            //将数据统一存入到Redis，用户出来以后在统计总输赢,流水等
            $this->setUserWaterTransferAmount($slots_log);

            //处理任务
//        $this->taskDraWith($slots_log);


            return 1;


        }catch (\Exception $e){
            Db::rollback();
            $this->logger->error('三方游戏记录问题:'.$e->getMessage());
            return 1;
        }

    }

    /**
     * 处理用户数据
     * @return int|void
     */
    public function setUserData($uid){
        if(!$uid)return 1;
        $Redis = appCommon::Redis('RedisMy6379_1');
        $slots_log = $Redis->hGetAll('game_info_'.$uid);
        if(!$slots_log)return 1;
        //直接删除，免得后期在调用
        $Redis->del('game_info_'.$uid);

        $userinfo = Db::table('userinfo as a')
            ->join('share_strlog as b','a.uid','=','b.uid')
            ->selectRaw('br_a.vip,(br_a.total_cash_water_score + br_a.total_bonus_water_score) as total_water_score,br_a.need_bonus_score_water,
            br_a.now_bonus_score_water,br_a.need_cash_score_water,br_a.now_cash_score_water,br_a.withdraw_money,br_a.coin,br_a.withdraw_money_other,br_a.total_pay_score')
            ->where('a.uid',$slots_log['uid'])
            ->first();
        Db::beginTransaction();
        //处理VIP升级
        $vip = $userinfo['vip'];
        if(bcadd((string)$slots_log['cashBetAmount'],(string)$slots_log['bonusBetAmount'],0) > 0) $vip = self::vipLevelUpgrade($slots_log['uid'],$userinfo['vip'],$userinfo['total_water_score'],bcadd((string)$slots_log['cashBetAmount'],(string)$slots_log['bonusBetAmount'],0),$userinfo['total_pay_score']);
        //user_day表处理
        $user_day = new SqlModel(self::getSlotsUserDay($slots_log,$vip));
        $res = $user_day->userDayDealWith();
        if(!$res){
            $this->setRedisGameInfo($Redis,$slots_log);
            Db::rollback();
            $this->logger->error('UID:'.$slots_log['uid'].'-统一处理三方游戏-user_day表数据处理失败');
            return 0;
        }
        //修改用户流水
        $res = $this->editUserWater($slots_log,$vip,$userinfo);
        if(!$res){
            $this->setRedisGameInfo($Redis,$slots_log);
            Db::rollback();
            $this->logger->error('UID:'.$slots_log['uid'].'-统一处理三方游戏-用户总流水处理失败');
            return 0;
        }
        //增加上级总流水
        if($slots_log['cashBetAmount'] > 0){
            $res = $this->setTopLevelWater($slots_log);
            if (!$res){
                $this->setRedisGameInfo($Redis,$slots_log);
                Db::rollback();
                $this->logger->error('UID:'.$slots_log['uid'].'-统一处理三方游戏-上级团队总流水添加失败');
                return 0;
            }

        }

        //处理存钱罐
//        $this->UserPiggyBank($slots_log);

        Db::commit();
        return 1;
    }


    /**
     * 计算本次Vip的等级
     * @param $uid
     * @param $oldVip
     * @param $total_water_score
     * @param $now_water_score
     * @param $total_pay_score
     * @return int
     */
    private static function vipLevelUpgrade($uid,$oldVip,$total_water_score,$now_water_score,$total_pay_score){
        $oldVip = $oldVip ?: 0;
        $vipArray = Db::connection('readConfig')
            ->table('vip')
            ->select('sj_amount','vip','sj_bonus')
            ->where([
                ['need_water','<=',bcadd((string)$total_water_score,(string)$now_water_score,0)],
                ['need_pay_price','<=',$total_pay_score],
            ])
            ->orderBy('vip','desc')
            ->first();
        if($vipArray && $oldVip < $vipArray['vip']){
            $vip_log = Db::table('vip_log')->select('id')->where(['uid' => $uid,'vip' => $vipArray['vip'],'type' => 1])->first();
            if(!$vip_log && ($vipArray['sj_amount'] > 0 || $vipArray['sj_bonus'] > 0)){
                $vip_log_array = [
                    'uid' => $uid,
                    'vip' => $vipArray['vip'],
                    'amount' => $vipArray['sj_amount'],
                    'bonus' => $vipArray['sj_bonus'],
                    'createtime' => time(),
                ];
                Db::table('vip_log')->insert($vip_log_array);
            }
            $oldVip = $vipArray['vip'];
        }
        return $oldVip;
    }

    /**
     * 处理Slots数据时,返回user_day表数据
     * @param array $slots_log
     * @param  $vip vip等级
     * @return string[]
     */
    private static function getSlotsUserDay(array $slots_log,$vip){
        return [
            'uid' => $slots_log['uid'].'|up',
            'puid' => $slots_log['puid'].'|up',
            'vip' => $vip.'|up',
            'channel' => $slots_log['channel'].'|up',
            'package_id' => $slots_log['package_id'].'|up',
            'cash_total_score' => $slots_log['cashTransferAmount'].'|raw-+',
            'bonus_total_score' => $slots_log['bonusTransferAmount'].'|raw-+',
            'total_cash_water_score' => $slots_log['cashBetAmount'].'|raw-+',
            'total_bonus_water_score' => $slots_log['bonusBetAmount'].'|raw-+',
            'total_game_num' => $slots_log['total_game_num'].'|raw-+',
        ];
    }


    /**
     * 处理用户流水，输赢，游戏次数等
     * @param $slots_log  三方游戏记录
     * @param $vip vip等级
     * @return
     */
    private function editUserWater($slots_log,$vip,$userinfo){
        $withdraw_money = $this->getWithdrawMoney($userinfo,(string)$slots_log['cashTransferAmount']);
        $need_cash_score_water = $slots_log['need_cash_score_water'];
        $need_bonus_score_water = $slots_log['need_bonus_score_water'];
        $data = [
            'vip' => $vip,
            'now_cash_score_water' => $userinfo['need_cash_score_water'] > $need_cash_score_water + $userinfo['now_cash_score_water']  ? Db::raw('now_cash_score_water + '.$need_cash_score_water) : $userinfo['need_cash_score_water'],
            'now_bonus_score_water' => $userinfo['need_bonus_score_water'] > $need_bonus_score_water + $userinfo['now_bonus_score_water'] ? Db::raw('now_bonus_score_water + '.$need_bonus_score_water) : $userinfo['need_bonus_score_water'],
            'total_cash_water_score' => Db::raw('total_cash_water_score + '.$need_cash_score_water),
            'total_bonus_water_score' => Db::raw('total_bonus_water_score + '.$need_bonus_score_water),
            'total_game_num' => Db::raw('total_game_num + '.$slots_log['total_game_num']),
            'cash_total_score' => Db::raw('cash_total_score + '.$slots_log['cashTransferAmount']),
            'bonus_total_score' => Db::raw('bonus_total_score + '.$slots_log['bonusTransferAmount']),
            'withdraw_money' => $withdraw_money,
        ];
        if($userinfo['withdraw_money_other'] > $userinfo['coin']) $data['withdraw_money_other'] = $userinfo['coin'];
        return Db::table('userinfo')->where('uid',$slots_log['uid'])->update($data);
    }

    /**
     * 获取本次修改的提现额度
     * @param array $userinfo 用户数据
     * @param string $cashTransferAmount 本次输赢的金额
     * @return string
     */
    private function getWithdrawMoney(array $userinfo,string $cashTransferAmount):string{
        $withdraw_money = (string)$userinfo['withdraw_money'];
        if($cashTransferAmount > 0)$withdraw_money = bcadd($withdraw_money,$cashTransferAmount,0);
        if($userinfo['coin'] < $withdraw_money)  return (string)$userinfo['coin'];
        return $withdraw_money;
    }


    /**
     * 增加上级的团队总流水
     * @param $slots_log
     * @return int|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function setTopLevelWater($slots_log){
        $teamlevel = Db::table('teamlevel')->where('uid', $slots_log['uid'])->where('level','>',0)->where('level','<',5)->get()->toArray();
        $res = 1;
        if (!empty($teamlevel)){
            $commissionlog_data = []; //返利记录
            $uidArray = []; //后期需要处理的每个返利用户加多少钱
            $bill_list = Db::table('commission_bill')->get()->toArray();
            $yf_bili = 0; //以分的比例

            try {
                foreach ($teamlevel as $tv) {
                    if ($tv['puid'] > 0) {
                        $user_water = Db::table('user_water')->where('uid', $tv['puid'])->first();
                        if ($user_water) {
                            $team_water = $user_water['total_cash_water_score'] + $slots_log['cashBetAmount'];// + $user_water['total_bonus_water_score'] + $slots_log['bonusBetAmount'];
                            $res = Db::table('user_water')->where('uid', $tv['puid'])->update([
                                'total_cash_water_score' => Db::raw('total_cash_water_score + ' . $slots_log['cashBetAmount']),
                                'total_bonus_water_score' => Db::raw('total_bonus_water_score + ' . $slots_log['bonusBetAmount']),
                            ]);
                            //continue;
                        } else {
                            $team_water = $slots_log['cashBetAmount'];// + $slots_log['bonusBetAmount'];
                            $res = Db::table('user_water')->insert([
                                'uid' => $tv['puid'],
                                'total_cash_water_score' => $slots_log['cashBetAmount'],
                                'total_bonus_water_score' => $slots_log['bonusBetAmount'],
                            ]);
                        }

                        //上级返佣
                        $bill = 0;
                        $bill_level = 1;
                        foreach ($bill_list as $kk => $item) {
                            if ($item['total_amount'] > $team_water) {
                                $bill = bcdiv((string)$bill_list[$kk - 1]['bili'], '10000', 4);
                                $bill_level = $bill_list[$kk - 1]['id'];
                                break;
                            }
                        }
                        if ($bill <= 0) continue;
                        $user_bili = bcsub((string)$bill, (string)$yf_bili, 4);
                        if ($user_bili <= 0) continue;

                        //实际返利金额
                        $commission_money = $slots_log['cashBetAmount'];// + $slots_log['bonusBetAmount'];
                        $really_money = bcmul((string)$user_bili, (string)$commission_money, 0);
                        $commissionlog_data[] = [
                            'uid' => $slots_log['uid'],
                            'puid' => $tv['puid'],
                            'level' => $tv['level'],
                            'bill_level' => $bill_level,
                            'bill' => bcmul((string)$user_bili, '10000'),
                            'BetAmount' => $commission_money,
                            'commission' => $really_money,
                            'createtime' => time(),
                        ];

                        $uidArray[$tv['puid']] = isset($uidArray[$tv['puid']]) ? bcadd((string)$uidArray[$tv['puid']], (string)$really_money, 0) : $really_money;
                        //处理已分的比例
                        $yf_bili = bcadd((string)$yf_bili, (string)$user_bili, 4);

                    }
                }
                //$this->logger->error("ffffffffffffff==>".json_encode($uidArray));
                //返佣记录
                if ($commissionlog_data) Db::table('commission_log')->insert($commissionlog_data);
                //上级数据修改
                if ($uidArray) foreach ($uidArray as $uid => $amount) {
                    Db::table('userinfo')->where('uid', $uid)->update([
                        'commission' => Db::raw('commission + ' . $amount)
                    ]);
                }
            }catch (\Throwable $ex){
                $this->logger->error("修改上级流水错误===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
                return 0;
            }
        }

        return $res;
    }



    /**
     * Bonus转换为Cash(退款额度需求)
     * @param int $uid  用户UID
     * @param string $syBonus  用户剩余的Bonus
     * @param string $cashTransferAmount  用户输的金额
     * @param string $terrace_name  厂商名称
     * @return int
     */
//    private function convertBonusToCash(int $uid,string $syBonus,string $cashTransferAmount,string $terrace_name):int{
//        $convert_bonus_bili = Db::table('slots_terrace')->where('type',$terrace_name)->value('convert_bonus_bili');
//        if($convert_bonus_bili <= 0)return 1;
//        $bonus = bcmul(bcsub('0',$cashTransferAmount,0),(string)$convert_bonus_bili,0); //理想转换的bonus;
//        $reallyBonus =  bcsub($syBonus,$bonus,0) < '0' ? $syBonus : $bonus; //实际转换的bonus
//        if($reallyBonus <= 0)return 1;
//        $res = User::userEditBonus($uid,bcsub('0',$reallyBonus,0),10,'用户-UID:'.$uid.'Bonus转换为Cash扣除Bonus'.bcdiv($reallyBonus,'100',2));
//        if(!$res){
//            $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash扣除Bonus'.bcdiv($reallyBonus,'100',2).'失败');
//            return 0;
//        }
//
//
//        $convert_bonus = Db::table('convert_bonus')->select('uid')->where('uid',$uid)->first();
//        if(!$convert_bonus){
//            $res = Db::table('convert_bonus')->insert([
//                'uid' => $uid,
//                'sy_cash' => $reallyBonus,
//                'zh_cash' => $reallyBonus,
//            ]);
//        }else{
//            $res = Db::table('convert_bonus')->where('uid',$uid)->update([
//                'sy_cash' => Db::raw('sy_cash + '.$reallyBonus),
//                'zh_cash' => Db::raw('zh_cash + '.$reallyBonus),
//            ]);
//        }
//        if(!$res){
//            $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash存入convert_bonus数据表失败-'.bcdiv($reallyBonus,'100',2).'失败');
//            return 0;
//        }
//
//        return 1;
//    }



    /**
     * Cash转换为Bonus(打流水需求)
     * @param $uid  用户UID
     * @param $bonus  用户bonus
     * @return void
     */
    private function convertBonusToCash($uid,$bonus){
        $res = User::userEditBonus($uid,bcsub('0',(string)$bonus,0),10,'用户-UID:'.$uid.'Bonus转换为Cash扣除Bonus'.bcdiv((string)$bonus,'100',2),3);
        if(!$res){
            $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash扣除Bonus'.bcdiv((string)$bonus,'100',2).'失败');
            return 0;
        }
        if($bonus > 0){
            $res = User::userEditCoin($uid,$bonus,10,'用户-UID:'.$uid.'Bonus转换为Cash增加Cash'.bcdiv((string)$bonus,'100',2));
            if(!$res){
                $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash增加Cash'.bcdiv((string)$bonus,'100',2).'失败');
                return 0;
            }
        }

        return 1;
    }

    /**
     * 玩过收藏
     * @param $slots_log
     * @return bool|int
     */
    private function setUserGame($slots_log)
    {
        $water = $slots_log['cashBetAmount'] + $slots_log['bonusBetAmount'];
        $collect = Db::table('game_collect')->where('uid', $slots_log['uid'])->where('game_id', $slots_log['game_id'])->first();
        if (empty($collect)){
            $res = Db::table('game_collect')->insert(['uid' => $slots_log['uid'], 'game_id' => $slots_log['game_id'], 'create_time'=>time(), 'water'=>$water]);
        }else{
            $data = [
                'water' => Db::raw('water + '.$water),
            ];
            $res = Db::table('game_collect')->where('id',$collect['id'])->update($data);
        }
        return $res;
    }


    /**
     * 处理存钱罐数据
     * @param $slots_log
     * @return void
     */
    private function UserPiggyBank($slots_log){
        if($slots_log['terrace_name'] == 'zy'){ //自研游戏对下的取绝对值
            co(function ()use ($slots_log){
                $cashTransferAmount = abs($slots_log['cashTransferAmount']);
                if($cashTransferAmount <= 0)return 1;
                $piggy_bank_bili = AppCommon::getConfigValue('piggy_bank_bili');
                if($piggy_bank_bili <= 0)return 1;
                $piggy_bank_amount = bcmul((string)$cashTransferAmount,(string)$piggy_bank_bili,0);
                if($piggy_bank_amount <= 0)return 1;
                $piggy_bank = Db::table('piggy_bank')->where(['uid' => $slots_log['uid']])->value('uid');
                if(!$piggy_bank){
                    Db::table('piggy_bank')->insert([
                        'uid' => $slots_log['uid'],
                        'piggy_bank_money' => $piggy_bank_amount,
                    ]);
                }else{
                    Db::table('piggy_bank')->where(['uid' => $slots_log['uid']])->update([
                        'piggy_bank_money' => Db::raw('piggy_bank_money + '.$piggy_bank_amount),
                    ]);
                }
            });
        }
        return 1;

    }

    /**
     * 任务处理
     * @return void
     */
    private function taskDraWith($slots_log){
        if($slots_log['terrace_name'] == 'zy'){ //自研游戏对下的取绝对值
            co(function () use($slots_log){
                //获取当天的日期
                $currentDayOfWeek = date('w'); // 获取今天是一周中的第几天（0-6，其中周日为 0）
                $dayOfWeek = $currentDayOfWeek == 0 ? 7 : $currentDayOfWeek; // 将周日转换为 7

                $task = Db::table('task')->selectRaw('id,task_time,type')->where('game_code',0)->orWhere('game_code',$slots_log['game_id'])->get()->toArray();
                if(!$task)return 1;
                //连接5501Redis
                $Redis = AppCommon::Redis('Redis5501');
                // 生成 Redis Key
                $redisKey = "tasks_".$slots_log['uid'];

                $today = date('Ymd');
                [$week_start_time,] = \App\Common\DateTime::startEndWeekTime(time());
                $week_start = date('Ymd',$week_start_time);

                foreach ($task as $taskValue){
                    //如果不是每周任务获取不是当日的任务,直接排除
                    if($taskValue['task_time'] != 0 && $taskValue['task_time'] != $dayOfWeek)continue;

                    // 读取现有的任务进度
                    $existingData = $Redis->hGet($redisKey, $taskValue['id']);

                    //任务类型:1=游戏局数,2=游戏赢得局数,3=1游戏赢金
                    if($taskValue['type'] == 1){
                        $progress = 1;
                    }elseif ($taskValue['type'] == 2){
                        if($slots_log['cashTransferAmount'] <= 0)continue;
                        $progress = 1;
                    }else{
                        if($slots_log['cashTransferAmount'] <= 0)continue;
                        $progress = $slots_log['cashTransferAmount'];
                    }

                    if ($existingData) {
                        $taskData = json_decode($existingData, true);

                        // 更新任务进度
                        $taskData['progress_num'] += $progress;
                        $taskData['updatetime'] = $taskValue['task_time'] == 0 ? $week_start : $today;

                        $Redis->hSet($redisKey, $taskValue['id'], json_encode($taskData));
                    } else {
                        // 新建任务进度
                        $newTaskData = [
                            'progress_num' => $progress,
                            'updatetime' => $taskValue['task_time'] == 0 ? $week_start : $today
                        ];

                        $Redis->hSet($redisKey, $taskValue['id'], json_encode($newTaskData));
                    }
                }
            });


        }
    }

    private function installSlotsLog($slotsData){
//        $getUidTail = AppCommon::getUidTail($slotsData['uid']);
//        $this->logger->error('UID:'.$slotsData['uid'].'-getUidTail:'.$getUidTail);
//        $this->logger->error('uid:'.$slotsData['uid'].'-betId:'.$slotsData['betId']);
        return Db::table('slots_log_'. date('Ymd'))->insert([
            'betId' => $slotsData['betId'],
            'parentBetId' => $slotsData['parentBetId'],
            'uid' => $slotsData['uid'],
            'puid' => $slotsData['puid'],
            'terrace_name' => $slotsData['terrace_name'],
            'slotsgameid' => $slotsData['slotsgameid'],
            'game_id' => $slotsData['game_id'],
            'englishname' => $slotsData['englishname'] ?? '',
            'cashBetAmount' => $slotsData['cashBetAmount'],
            'bonusBetAmount' => $slotsData['bonusBetAmount'],
            'cashWinAmount' => $slotsData['cashWinAmount'],
            'bonusWinAmount' => $slotsData['bonusWinAmount'],
            'cashTransferAmount' => $slotsData['cashTransferAmount'],
            'bonusTransferAmount' => $slotsData['bonusTransferAmount'],
            'cashRefundAmount' => $slotsData['cashRefundAmount'] ?? 0,
            'bonusRefundAmount' => $slotsData['bonusRefundAmount'] ?? 0,
            'transaction_id' => $slotsData['transaction_id'] ?? '',
            'betTime' => $slotsData['betTime'] ?? time(),
            'package_id' => $slotsData['package_id'],
            'channel' => $slotsData['channel'],
            'betEndTime' => time(),
            'createtime' => $slotsData['createtime'] ?? time(),
            'is_consume' => $slotsData['is_consume'] ?? 1,
            'is_sports' => $slotsData['is_sports'] ?? 0,
            'is_settlement' => $slotsData['is_settlement'] ?? 1,
            'really_betAmount' => $slotsData['really_betAmount'] ?? 0,
            'other' => $slotsData['other'] ?? '',
            'other2' => $slotsData['other2'] ?? '',
            'other3' => $slotsData['other3'] ?? '',
        ]);
    }


    /**
     * 修改SlotsLog 数据表
     * @param string $betId 下注ID
     * @param int $is_slots_time  1=直接在最近2天中执行,2=需要先在slots_time中找到哪一天，在执行
     * @return int
     */
    public function deleteSlotsLog(string $betId):int{

        $redis = appCommon::Redis('RedisMy6379');
        $redis->del($betId);
        return 1;
    }


    /**
     * 将数据统一存入到Redis，用户出来以后在统计总输赢,流水等
     * @param array $slots_log
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function setUserWaterTransferAmount(array $slots_log){
        $need_cash_score_water = $slots_log['cashBetAmount'];
        $need_bonus_score_water = $slots_log['bonusBetAmount'];
        if($slots_log['terrace_name'] == 'spr'|| $slots_log['terrace_name'] == 'turbo'|| ($slots_log['terrace_name'] == 'zy' && in_array($slots_log['slotsgameid'],$this->zyGameType))){ //自研游戏对下的取绝对值
            $cashTransferAmount = abs((int)$slots_log['cashTransferAmount']);
            $bonusTransferAmount = abs((int)$slots_log['bonusTransferAmount']);
            $need_cash_score_water = min($cashTransferAmount,$need_cash_score_water); //算需求流水和总流水
            $need_bonus_score_water = min($bonusTransferAmount,$need_bonus_score_water); //算需求流水和总流水
        }
        $Redis = appCommon::Redis('RedisMy6379_1');
        $data = [
            'uid' => $slots_log['uid'],
            'puid' => $slots_log['puid'],
            'channel' => $slots_log['channel'],
            'package_id' => $slots_log['package_id'],
        ];
        $this->logger->error("setRedisGameInfo：66");
        $this->setRedisGameInfo($Redis,$data);
        $this->logger->error("setRedisGameInfo：88");
        $hashKeyArray = ['cashBetAmount','bonusBetAmount','cashTransferAmount','bonusTransferAmount','need_cash_score_water','need_bonus_score_water'];
        foreach ($hashKeyArray as $hashKey){
            $value = in_array($hashKey, ['need_cash_score_water', 'need_bonus_score_water'])
                ? (int)$$hashKey
                : (int)$slots_log[$hashKey];
            $this->logger->error("setRedisGameInfo：$value" . $value);
            $this->logger->error("setRedisGameInfo：$hashKey" . $hashKey);
            try {
                $bb = $Redis->hIncrBy('game_info_' . $slots_log['uid'], $hashKey, $value);
                $this->logger->error("setRedisGameInfo：22" . $bb);
            } catch (\Exception $e) {
                $this->logger->error("Failed to hIncrBy for {$hashKey}: " . $e->getMessage());
            }

        }
        //游戏次数+1
        $Redis->hIncrBy('game_info_' . $slots_log['uid'], 'total_game_num', 1);
        $Redis->expire('game_info_' . $slots_log['uid'], 1296000);
        $this->logger->error("setRedisGameInfo：99");
        $tmp = $Redis->hGetAll('game_info_' . $slots_log['uid']);
        $this->logger->error("setRedisGameInfo：game_info_" . var_export($tmp, true));
    }

    /**
     * 存储后在统计总输赢,流水等
     * @param $Redis
     * @param $slots_log
     * @return void
     */
    private function setRedisGameInfo($Redis,$slots_log){
        $aa = $Redis->hMSet('game_info_'.$slots_log['uid'],$slots_log);
        $this->logger->error("setRedisGameInfo：00" . $aa);
    }
}


