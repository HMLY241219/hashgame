<?php

declare(strict_types=1);

namespace App\Controller\share;

use App\Common\User;
use App\Controller\AbstractController;
use Hyperf\Context\Context;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\AutoController;
use Swoole\Coroutine\Channel;
use function Hyperf\Coroutine\co;
use function Hyperf\Support\today;

#[AutoController(prefix: "share.share")]
class ShareController extends AbstractController
{
    /**
     * 分享页面控制台数据
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function painel(){
        $param = $this->request->all();
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
            $this->logger->error("分享页面数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[]);
        }

        $day_time = strtotime('today');

        $channel = new Channel();
        $data = co(function () use ($param, $day_time, $channel) {
            try {
                //今日数据
                $day_user = Db::table('teamlevel')
                    ->where('puid', $param['uid'])
                    ->where('level', '<>', 0)
                    ->where('createtime', '>', $day_time)
                    ->pluck('uid')->toArray();
                //            ->toRawSql();

                $all_user = Db::table('teamlevel')
                    ->where('puid', $param['uid'])
                    ->where('level', '<>', 0)
                    ->pluck('uid')->toArray();

                $cz_num = Db::table('user_day_' . date('Ymd'))
                    ->whereIn('uid', $all_user)
                    ->where('total_pay_score', '>', 0)
                    ->count();

                $bet = Db::table('user_day_' . date('Ymd'))
                    ->whereIn('uid', $all_user)
                    ->where(function ($que){
                        $que->where('total_cash_water_score', '>', 0)
                        ->orWhere('total_bonus_water_score', '>', 0);
                    })
//                    ->where('total_cash_water_score', '>', 0)
//                    ->orWhere('total_bonus_water_score', '>', 0)
                    ->select(DB::raw('IFNULL(SUM(total_cash_water_score),0) as total_cash_water_score'),
                        DB::raw('IFNULL(SUM(total_bonus_water_score),0) as total_bonus_water_score'),
                        DB::raw('COUNT(uid) AS bet_num'))
                    ->first();
//                    ->toRawSql();
                //var_dump($bet);

                //$commission_day = Db::table('commission_day')->where('uid', $param['uid'])->whereDate('date', today())->first();
                $time = strtotime(date('Y-m-d'));
                $commission_day = Db::table('commission_log')
                    ->where('puid', $param['uid'])
                    ->where('createtime', '>=', $time)
                    ->selectRaw('IFNULL(SUM(commission),0) as commission')
                    ->first();

                //总数据
                $order = Db::table('order')->whereIn('uid', $all_user)->where('pay_status', 1)->groupBy('uid')->pluck('uid');
                $userinfo = Db::table('userinfo')
                    ->whereIn('uid', $all_user)
                    ->select(DB::raw('IFNULL(SUM(total_cash_water_score),0) as total_cash_water_score'),
                        DB::raw('IFNULL(SUM(total_bonus_water_score),0) as total_bonus_water_score'))
                    ->first();
                //$commission = Db::table('commission_day')->where('uid', $param['uid'])->select(DB::raw('IFNULL(SUM(commission),0) as commission'))->first();
                $commission = Db::table('commission_log')
                    ->where('puid', $param['uid'])
                    ->selectRaw('IFNULL(SUM(commission),0) as commission')
                    ->first();

                //领取的佣金
                $get_commission = Db::table('userinfo as a')
                    ->leftJoin('user_water as b','a.uid','=','b.uid')
                    ->where('a.uid', $param['uid'])->select('a.commission_total', 'a.commission','b.total_cash_water_score','b.total_bonus_water_score')->first();
                $bill_list = Db::table('commission_bill')->get()->toArray();
                $team_water = $get_commission['total_cash_water_score']>=0 && $get_commission['total_bonus_water_score']>=0 ? bcadd((string)$get_commission['total_cash_water_score'], (string)$get_commission['total_bonus_water_score']) : 0;
                //返利比例
                //$bill = 0;
                $bill_level = 1;
                foreach ($bill_list as $kk=>$item) {
                    if ($item['total_amount'] > $team_water) {
                        //$bill = bcdiv($bill_list[$kk-1]['bili'],10000,4);
                        $bill_level = $bill_list[$kk-1]['id'];
                        break;
                    }
                }

                $data = [];
                $data['Hoje']['register'] = count($day_user);
                $data['Hoje']['topup'] = $cz_num;
                $data['Hoje']['bet_money'] = !empty($bet) ? (int)bcadd((string)$bet['total_cash_water_score'], (string)$bet['total_bonus_water_score']) : 0;
                $data['Hoje']['bet_num'] = !empty($bet) ? $bet['bet_num'] : 0;
                $data['Hoje']['commission'] = !empty($commission_day) ? (int)$commission_day['commission'] : 0;

                $data['Total']['register'] = count($all_user);
                $data['Total']['topup'] = count($order);
                $data['Total']['bet_money'] = !empty($userinfo) ? (int)bcadd((string)$userinfo['total_cash_water_score'], (string)$userinfo['total_bonus_water_score']) : 0;
                $data['Total']['commission'] = !empty($commission) ? (int)$commission['commission'] : 0;

                $data['Comissao']['commission_total'] = !empty($get_commission) ? $get_commission['commission_total'] : 0;
                $data['Comissao']['commission'] = !empty($get_commission) ? $get_commission['commission'] : 0;
                $data['Comissao']['bill_level'] = $bill_level;

                $channel->push(json_encode($data));
            } catch (\Throwable $e) {
                // 捕获异常并处理
                $this->logger->error('分享页面数据获取错误:' . $e->getMessage());
                $channel->push('');
            }
        });

        $data = json_decode($channel->pop(),true);
        if ($data) {
            return $this->ReturnJson->successFul(200, $data, 1);
        }else{
            return $this->ReturnJson->successFul(200, [], 1);
        }
    }


    /**
     * 领取佣金
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCommission(){
        $param = $this->request->all();
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
            $this->logger->error("领取佣金数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[]);
        }

        $userinfo = Db::table('userinfo')->where('uid',$param['uid'])->select('commission_total','commission')->first();
        if (empty($userinfo) || $userinfo['commission'] <= 0) return $this->ReturnJson->failFul(226);

        try {
            Db::beginTransaction();

            Db::table('commission_get')->insert([
                'uid' => $param['uid'],
                'commission' => $userinfo['commission'],
                'createtime' => time(),
            ]);

            Db::table('userinfo')->where('uid',$param['uid'])->update([
                'commission_total' => Db::raw('commission_total + '.$userinfo['commission']),
                'commission' => 0
            ]);
            User::userEditCoin($param['uid'], $userinfo['commission'], 9, '领取下级返佣', 4, 9);
            User::editUserTotalGiveScore($param['uid'], $userinfo['commission']);
            Db::commit();
            return $this->ReturnJson->successFul(200,[],1);

        }catch (\Throwable $e){
            Db::rollback();
            $this->logger->error("错误文件===" . $e->getFile() . '===错误行数===' . $e->getLine() . '===错误信息===' . $e->getMessage());
            return $this->ReturnJson->failFul();
        }
    }

    /**
     * 佣金领取记录
     * @param Request $request
     * @return mixed
     */
    public function commissionGetList(){
        $param = $this->request->all();
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
            $this->logger->error("佣金记录数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[]);
        }

        $limit = isset($param['page_size']) ? $param['page_size'] : 30;

        $list = Db::table('commission_get')
            ->where('uid',$param['uid'])
            ->orderBy('createtime','desc')
            ->paginate((int)$limit);
        $data = [];
        $data['list'] = $list->items();
        $data['totalNum'] = $list->total();
        return $this->ReturnJson->successFul(200,$data,1);
    }

    /**
     * 下级用户列表
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function junior(){
        $param = $this->request->all();
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
            $this->logger->error("分享下级数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[]);
        }

        $limit = isset($param['page_size']) ? $param['page_size'] : 30;

        $where = [];
        $zwhere = [];
        if (isset($param['date']) && !empty($param['date'])){
            $time = strtotime($param['date']);
            $end_time = $time + 86400;
            $where[] = ['c.createtime','<',$end_time];
            $where[] = ['c.createtime','>=',$time];
            $zwhere[] = ['createtime','<',$end_time];
            $zwhere[] = ['createtime','>=',$time];
        }

        /*$teamlevel = Db::table('teamlevel as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->leftJoin('commissionlog as c','a.uid','=','c.uid')
            ->where(['a.puid' => $param['uid'], 'a.level' => 1])
            ->where($where)
            ->selectRaw('br_a.uid,IFNULL(br_b.total_cash_water_score,0) as total_cash_water_score,IFNULL(br_b.total_bonus_water_score,0) as total_bonus_water_score,
            IFNULL(SUM(br_c.commission_money),0) AS team_bet,COUNT(DISTINCT br_c.char_uid) as num,IFNULL(GROUP_CONCAT(DISTINCT br_c.char_uid),"") as xj_users')
            ->groupBy('a.uid')
            ->orderBy('a.createtime','desc')
            ->paginate((int)$limit);*/
        $teamlevel = Db::table('teamlevel as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->leftJoin('commission_log as c','a.puid','=','c.puid')
            ->where(['a.puid' => $param['uid'], 'a.level' => 1])
            ->where($where)
            ->selectRaw('br_a.uid,IFNULL(br_b.total_cash_water_score,0) as total_cash_water_score,IFNULL(br_b.total_bonus_water_score,0) as total_bonus_water_score')
            //IFNULL(SUM(br_c.BetAmount),0) AS team_bet,COUNT(DISTINCT br_c.uid) as num,IFNULL(SUM(br_c.commission),0) AS commission,
            //            IFNULL(GROUP_CONCAT(DISTINCT br_c.uid),"") as xj_users
            ->groupBy('a.uid')
            ->orderBy('a.createtime','desc')
            ->paginate((int)$limit);
//            ->toRawSql();
//        var_dump($teamlevel);
        $teamlevels = $teamlevel->items();
        //var_dump($teamlevels);

        $all_bet = 0;
        if (!empty($teamlevels)){
            foreach ($teamlevels as $key=>&$value){
                //直属返佣
                $commission_zs = Db::table('commission_log')->where('uid',$value['uid'])->where('puid',$param['uid'])
                    ->where($zwhere)
                    ->selectRaw('IFNULL(SUM(commission),0) AS commission,IFNULL(SUM(BetAmount),0) AS BetAmount,COUNT(DISTINCT uid) as num')->first();
                //var_dump($commission_zs);

                    //返佣
                //$users_arr = explode(',',$value['xj_users']);
                //$users_arr[] = (string)$value['uid'];
                //$teamleve_xj_user = Db::table('teamlevel')->whereIn('puid',$users_arr)->where('level','>',0)->get('uid')->toArray();
                $teamleve_xj_user = Db::table('teamlevel')->where('puid',$value['uid'])->where('level','>',0)->get('uid')->toArray();
                $teamleve_xj_arr = array_column($teamleve_xj_user,'uid');
                //var_dump($teamleve_xj_arr);

                $commission = Db::table('commission_log')->whereIn('uid',$teamleve_xj_arr)
                    ->where($zwhere)
                    ->selectRaw('IFNULL(SUM(commission),0) AS commission,IFNULL(SUM(BetAmount),0) AS team_bet,
                    COUNT(DISTINCT uid) as num')->first();
                //$all_bet = $all_bet + $commission_zs['BetAmount'];// + $value['team_bet']

                $value['commission'] = $commission['commission'] + $commission_zs['commission']; //$value['commission'] +
                $value['team_bet'] = $commission['team_bet']; //$value['team_bet'] +
//                $value['bet'] = bcadd((string)$value['total_cash_water_score'], (string)$value['total_bonus_water_score']);
                $value['bet'] = $commission_zs['BetAmount'];
                $value['num'] = $commission['num']; //+ $commission_zs['num']; $value['num'] +


                //$value['uid'] = $this->hideMiddle($value['uid']);

                unset($value['total_cash_water_score'],$value['total_bonus_water_score'],$value['xj_users']);
            }
        }

        $commission_all = Db::table('commission_log')->where('puid',$param['uid'])
            ->where($zwhere)
            ->selectRaw('IFNULL(SUM(BetAmount),0) AS team_bet')->first();
        $all_bet = $commission_all['team_bet'];

        $all_teamlevel = Db::table('teamlevel as a')
            ->leftJoin('commission_log as c','a.puid','=','c.puid')
            ->where(['a.puid' => $param['uid']])
            ->where('a.level','>',0)
            ->where($where)
            ->select('a.uid','a.level')
            ->groupBy('a.uid')
            ->get()->toArray();
        $level1 = [];
        $level2 = [];
        if (!empty($all_teamlevel)){
            foreach ($all_teamlevel as $ak=>$av){
                if ($av['level'] == 1){
                    $level1[] = $av['uid'];
                }else{
                    $level2[] = $av['uid'];
                }
            }
        }

        $level1_commission = Db::table('commission_log')->where('puid',$param['uid'])->whereIn('uid',$level1)
            ->where($zwhere)
            ->selectRaw('IFNULL(SUM(commission),0) AS really_money')->first();
        $level2_commission = Db::table('commission_log')->where('puid',$param['uid'])->whereIn('uid',$level2)
            ->where($zwhere)
            ->selectRaw('IFNULL(SUM(commission),0) AS really_money')->first();

        $data = [];
        $data['list'] = $teamlevels;
        $data['totalNum'] = $teamlevel->total();
        $data['total_direct_commission'] = bcadd((string)$level1_commission['really_money'],(string)$level2_commission['really_money']);
        $data['total_indirect_commission'] = $level2_commission['really_money'];
        //$data['all_bet'] = bcadd((string)$level1_commission['commission_money'],(string)$level2_commission['commission_money']);
        $data['all_bet'] = $all_bet;
        return $this->ReturnJson->successFul(200, $data, 1);
    }

    /**
     * 每日数据
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function dayData(){
        $param = $this->request->all();
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
            $this->logger->error("分享每日数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[]);
        }

        $limit = isset($param['page_size']) ? $param['page_size'] : 30;

        /*$list = Db::table('commission_day')
            ->where('uid',$param['uid'])
            ->orderBy('date','desc')
            ->paginate((int)$limit);*/
        $list = Db::table('commission_log')->where('puid',$param['uid'])
            ->selectRaw('DATE(FROM_UNIXTIME(createtime)) AS date,IFNULL(SUM(commission),0) AS commission,IFNULL(SUM(BetAmount),0) AS bet,COUNT(DISTINCT uid) as bet_num,
            MAX(bill_level) AS bill_level')
            ->groupBy('date')
            ->orderBy('date','desc')
            ->paginate((int)$limit);

        $list_data = $list->items();
        if (!empty($list_data)){
            foreach ($list_data as $key=>&$value){
                $level_time = strtotime($value['date']);
                $end_time = $level_time + 86400;
                $teamlevel = Db::table('teamlevel')
                    ->where('level','>',0)
                    ->where('puid',$param['uid'])
                    ->where('createtime','>=',$level_time)
                    ->where('createtime','<',$end_time)
                    ->count();
                $value['num'] = $teamlevel;
            }
        }

        $data = [];
        $data['list'] = $list_data;
        $data['totalNum'] = $list->total();
        return $this->ReturnJson->successFul(200,$data,1);

    }

    /**
     * 返佣比例
     * @return null
     */
    public function commissionBill()
    {
        $data = Db::table('commission_bill')->select('id as level','total_amount','bili')->get()->toArray();
        foreach ($data as &$itam){
            $itam['total_amount'] = $itam['total_amount']/1000000;
        }
        return $this->ReturnJson->successFul(200,$data,1);
    }


    /**
     * 隐藏字符中间几位
     * @param $str
     * @return array|string|string[]
     */
    function hideMiddle($str) {
        $hidden = substr_replace((string)$str,'****',2,3);

        return $hidden;
    }

}
