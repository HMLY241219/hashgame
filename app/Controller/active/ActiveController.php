<?php

declare(strict_types=1);

namespace App\Controller\active;

use App\Common\Common;
use App\Common\User;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Contract\RequestInterface;
use function Hyperf\Config\config;

#[AutoController(prefix: "active.active")]
class ActiveController extends AbstractController
{

    /**
     * 活动列表
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function activeList(RequestInterface $request){
        $packname = $request->getAttribute('packname');
        //判断走新包配置还是老包
        $special_package_ids = Common::getConfigValue('special_package_ids');
        $special_package_Array = $special_package_ids ? explode(',',$special_package_ids) : [];

        $package_id = Db::connection('readConfig')->table('apppackage')->where('appname',$packname)->value('id');
        if(in_array($package_id,$special_package_Array)){
            $is_new_package_where = [['terminal_type','=',2]];
        }else{
            $is_new_package_where = [['terminal_type','=',1]];
        }

        $list = Db::connection('readConfig')->table('active')
            ->where(['status'=>1])
            ->where($is_new_package_where)
            ->select('englishname','banner','type','url','name')
            ->orderBy('weight','desc')
            ->get()->toArray();
        if (!empty($list)){
            foreach ($list as $key=>$value){
                $list[$key]['banner'] = Common::domain_name_path((string)$value['banner']);

                //签到判断
                if ($value['name'] == '7天签到'){
                    $is_open = common::getConfigValue('is_open_sign');
                    $user_token = $request->getHeaderLine('user-token');
                    if (empty($user_token) || !$is_open){
                        unset($list[$key]);
                    }else{
                        $userinfo = Db::table('user_token as a')
                            ->join('userinfo as b','a.uid','=','b.uid')
                            ->join('share_strlog as c','a.uid','=','c.uid')
                            ->where('a.token',$user_token)
                            ->selectRaw('br_a.uid,br_b.total_pay_score,br_c.last_pay_time')
                            ->first();
                        if ($userinfo['last_pay_time'] > 0){ //连续5天不充值
                            $last_time = (time() - $userinfo['last_pay_time'])/86400;
                        }else{
                            $last_time = 6;
                        }

                        if (empty($userinfo) || $userinfo['total_pay_score'] == 0 || $last_time > 5){//无充值
                            unset($list[$key]);
                        }else{//签满
                            $user_sign = Db::table('sign_in')->where('uid',$userinfo['uid'])->first();
                            if ($user_sign){
                                $sign_date = date('Y-m-d',strtotime($user_sign['signTime']));
                                //连续7天后，关闭签到功能
                                if ($user_sign['num'] >= 7 && date('Y-m-d') > $sign_date){
                                    unset($list[$key]);
                                }
                            }
                        }
                    }
                }

            }
        }
        return $this->ReturnJson->successFul(200,$list);
    }


    /**
     * 活动列表
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function activeList2(RequestInterface $request){
        $packname = $request->getAttribute('packname');
        //判断走新包配置还是老包
        $special_package_ids = Common::getConfigValue('special_package_ids');
        $special_package_Array = $special_package_ids ? explode(',',$special_package_ids) : [];

        $package_id = Db::connection('readConfig')->table('apppackage')->where('appname',$packname)->value('id');
        if(in_array($package_id,$special_package_Array)){
            $is_new_package_where = [['terminal_type','=',2]];
        }else{
            $is_new_package_where = [['terminal_type','=',1]];
        }

        $listd = Db::connection('readConfig')->table('active_class')
            ->select('id','name','image','not_image')
            ->where(['status'=>1])
            ->orderBy('weight','desc')
            ->get()->toArray();
        if (!empty($listd)) {
            foreach ($listd as $ck=>&$cv){
                $cv['image'] = Common::domain_name_path((string)$cv['image']);

                $list = Db::connection('readConfig')->table('active')
                    ->where(['status' => 1])
                    ->where($is_new_package_where)
                    ->where('skin_type',2)
                    ->where('class_id',$cv['id'])
                    ->select('englishname', 'banner', 'type', 'url', 'name')
                    ->orderBy('weight', 'desc')
                    ->get()->toArray();
                if (!empty($list)) {
                    foreach ($list as $key => $value) {
                        $list[$key]['banner'] = Common::domain_name_path((string)$value['banner']);

                        //签到判断
                        if ($value['name'] == '7天签到') {
                            $is_open = common::getConfigValue('is_open_sign');
                            $user_token = $request->getHeaderLine('user-token');
                            if (empty($user_token) || !$is_open) {
                                unset($list[$key]);
                            } else {
                                $userinfo = Db::table('user_token as a')
                                    ->join('userinfo as b', 'a.uid', '=', 'b.uid')
                                    ->join('share_strlog as c', 'a.uid', '=', 'c.uid')
                                    ->where('a.token', $user_token)
                                    ->selectRaw('br_a.uid,br_b.total_pay_score,br_c.last_pay_time')
                                    ->first();
                                if ($userinfo['last_pay_time'] > 0) { //连续5天不充值
                                    $last_time = (time() - $userinfo['last_pay_time']) / 86400;
                                } else {
                                    $last_time = 6;
                                }

                                if (empty($userinfo) || $userinfo['total_pay_score'] == 0 || $last_time > 5) {//无充值
                                    unset($list[$key]);
                                } else {//签满
                                    $user_sign = Db::table('sign_in')->where('uid', $userinfo['uid'])->first();
                                    if ($user_sign) {
                                        $sign_date = date('Y-m-d', strtotime($user_sign['signTime']));
                                        //连续7天后，关闭签到功能
                                        if ($user_sign['num'] >= 7 && date('Y-m-d') > $sign_date) {
                                            unset($list[$key]);
                                        }
                                    }
                                }
                            }
                        }

                    }
                }

                $listd[$ck]['active_list'] = $list;
            }
        }
        return $this->ReturnJson->successFul(200,$listd);
    }


    /**
     * 转盘活动页面数据
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function turntableActive(){
        $param = $this->request->all();
        $uid = isset($param['uid']) && !empty($param['uid']) ? $param['uid'] : -1;
        $num = isset($param['num']) && !empty($param['num']) ? $param['num'] : '';
        $turntable_config = config('turntable');

        $is_get = false;
        $info = Db::table('turntable')->where(['endstatus'=>1,'uid'=>$uid])->orWhere(function ($que) use ($num){
                $que->Where(['endstatus'=>1,'num'=>$num]);
            })
            ->orderBy('id','desc')
            ->first();
//        ->toRawSql();
//        var_dump($info);
        if (!empty($info)){
            if ($info['uid'] == 0 && $uid != 0){
                Db::table('turntable')->where('id',$info['id'])->update(['uid'=>$uid]);
                Db::table('turntable_log')->where('turntable_id',$info['id'])->update(['uid'=>$uid]);
            }

            /*if ($info['money'] < $turntable_config['money'] && (time()-$info['createtime']) > 86400*3){//超过3天
                Db::table('turntable')->where('id',$info['id'])->update(['endstatus'=>3]);
                $info['remaining_time'] = 0;
                $info['turntable_id'] = 0;
            }else{*/
                $is_get = true;
                $info['remaining_time'] = 86400*180 - (time() - $info['createtime']);
                $info['turntable_id'] = $info['id'];
            //}
        }else{
            $info['money'] = 0;
            $info['z_count'] = 1;
            $info['remaining_time'] = 0;
            $info['turntable_id'] = 0;
            $info['createtime'] = 0;
        }

        $data = [];
        $data['uid'] = $uid;
        $data['is_get'] = $is_get;
        $data['need_money'] = $turntable_config['money'];
        $data['zs_money'] = $info['money'];
        $data['z_count'] = $info['z_count'];
        $data['remaining_time'] = $info['remaining_time'];
        $data['turntable_id'] = $info['turntable_id'];
        $data['end_time'] = $info['createtime'] + 86400*180;
        $data['start_time'] = $info['createtime'];

        //奖品列表
        $options = Db::table('turntable_reward')->select('id as options_id','name','logo','type')
            ->get()->toArray();
        if (!empty($options)){
            foreach ($options as $key=>$value){
                $options[$key]['logo'] = Common::domain_name_path((string)$value['logo']);
            }
        }
        $data['options'] = $options;

        //获奖名单
        $get_list = [];
        for ($i=0; $i<30; $i++){
            $k = mt_rand(2358, 9988);
            $e = mt_rand(101, 998);
            //$get_list[] = $k . '******' . $e . '   Acabou de saca   +100 R$';
            $get_list[$i]['uid'] = $k . '******' . $e;
            $get_list[$i]['text'] = 'Acabou de saca';
            $get_list[$i]['money'] = $turntable_config['money'];
            $get_list[$i]['currency'] = '₹';
        }
        $data['get_list'] = $get_list;

        //邀请记录
        $user_team = [];
        if ($info['turntable_id'] > 0) {
            $user_team = Db::table('user_team')->where('turntable_id',$info['turntable_id'])
                ->select('nickname','money')
                ->selectRaw("'Acabou de ajuda-lo a ganhar' as text")
                ->get()->toArray();
        }
        $data['minha'] = $user_team;

        return $this->ReturnJson->successFul(200,$data,1);
    }

    /**
     * 未登录时转盘
     * @return null
     */
    public function notTurntable(){
        $turntable_config = config('turntable');

        try {
            Db::beginTransaction();

            $num = substr(uniqid(), -6);

            $in_data = [];
            $give = $in_data['money'] = mt_rand($turntable_config['one_start_money'] * 100, $turntable_config['one_end_money'] * 100) / 100;
            $in_data['createtime'] = time();
            $in_data['num'] = $num;
            $in_data['z_count'] = 1;
            $turntable_id = Db::table('turntable')->insertGetId($in_data);
            $turntable_reward_id = 2;
            $type = 1;

            //添加转盘记录
            $log_data = [];
            $log_data['turntable_id'] = $turntable_id;
            $log_data['turntable_reward_id'] = $turntable_reward_id;
            $log_data['reward'] = $give;
            $log_data['type'] = $type;
            $log_data['create_time'] = time();
//            parallel([
//                function () use ($log_data) {
            Db::table('turntable_log')->insert($log_data);


            $data = [];
            $data['options_id'] = $turntable_reward_id;
            $data['give'] = $give;
            $data['num'] = $num;

            Db::commit();
            return $this->ReturnJson->successFul(200, $data, 1);

        }catch (\Throwable $ex) {
            Db::rollBack();
            $this->logger->error("错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->ReturnJson->failFul();
        }
    }

    /**
     * 转动转盘
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function turntable(){
        $uid = $this->request->getAttribute('uid');
        $info = Db::table('turntable')->where(['endstatus'=>1,'uid'=>$uid])->orderBy('id','desc')->first();
        $turntable_config = config('turntable');

        try {
            Db::beginTransaction();
            if (empty($info)) {
                $in_data = [];
                $in_data['uid'] = $uid;
                $give = $in_data['money'] = mt_rand($turntable_config['one_start_money'] * 100, $turntable_config['one_end_money'] * 100) / 100;
                $in_data['createtime'] = time();
                $in_data['z_count'] = 1;
                $turntable_id = Db::table('turntable')->insertGetId($in_data);
            } else {
                $give = 0;
                if ($info['z_count'] < 1) {
                    return $this->ReturnJson->failFul();
                }
                $turntable_id = $info['id'];
            }

            $winnerList = Db::table('turntable_reward')->get()->toArray();
            //概率计算
            $ranges = [];
            $start = 0;
            foreach ($winnerList as $item) {
                $end = $start + $item['probability'];
                $ranges[] = ['start' => $start, 'end' => $end, 'index' => $item['id'], 'reward' => $item['reward'], 'reward_max' => $item['reward_max'], 'type' => $item['type']];
                $start = $end;
            }
            // 生成随机数
            $random = mt_rand(0, 100);
            $turntable_reward_id = 0;
            $reward = 0;
            $reward_max = 0;
            $type = 0;
            // 判断随机数落在哪个范围内
            for ($i = 0; $i < count($ranges); $i++) {
                if ($random >= $ranges[$i]['start'] && $random < $ranges[$i]['end']) {
                    $turntable_reward_id = $ranges[$i]['index'];
                    $reward = $ranges[$i]['reward'];
                    $reward_max = $ranges[$i]['reward_max'];
                    $type = $ranges[$i]['type'];
                }
            }

            //给与奖励
            if (!empty($info)) {
                //if ($info['money'] < bcsub((string)$turntable_config['money'],'0.7',2)) { //到达一定值后 转不到
                    if ($type == 1) {
                        $give = mt_rand((int)($reward * 100), (int)($reward_max * 100)) / 100;
                        if (($info['money'] + $give) > $turntable_config['money']) {
                            Db::table('turntable')->where('id', $info['id'])->update(['money'=>$turntable_config['money']]);
                            $give = bcsub((string)$turntable_config['money'], $info['money'], 2);
                        }else {
                            Db::table('turntable')->where('id', $info['id'])->increment('money', $give);
                        }
                    } elseif ($type == 2) {
                        $give = $reward;
                        $res = User::userEditCoin($uid, $reward * 100, 11, '玩家' . $uid . '转盘中奖：' . $reward, 2);
                        if (!$res) {
                            Db::rollback();
                            return $this->ReturnJson->failFul();
                        }
                        $res = User::editUserTotalGiveScore($uid, $reward * 100);
                        if (!$res) {
                            Db::rollback();
                            return $this->ReturnJson->failFul();
                        }
                    } elseif ($type == 3) {
                        $give = $reward;
                        $res = User::userEditBonus($uid, $reward * 100, 11, '玩家' . $uid . '转盘中奖：' . $reward, 2);
                        if (!$res) {
                            Db::rollback();
                            return $this->ReturnJson->failFul();
                        }
                    }
                /*}else{
                    //控制到达99.7
                    $turntable_reward_id = 7;
                    $give = 0;
                    $type = 1;
                }*/
                $res = Db::table('turntable')->where('id', $info['id'])->decrement('z_count');
                if (!$res) {
                    Db::rollback();
                    return $this->ReturnJson->failFul();
                }

            }else{
                //第一次转动
                $turntable_reward_id = 2;
            }

            //添加转盘记录
            $log_data = [];
            $log_data['uid'] = $uid;
            $log_data['turntable_id'] = $turntable_id;
            $log_data['turntable_reward_id'] = $turntable_reward_id;
            $log_data['reward'] = $give;
            $log_data['type'] = $type;
            $log_data['create_time'] = time();
//            parallel([
//                function () use ($log_data) {
            Db::table('turntable_log')->insert($log_data);
//                }
//            ]);


            $data = [];
            $data['options_id'] = $turntable_reward_id;
            $data['give'] = $give;

            Db::commit();
            return $this->ReturnJson->successFul(200, $data);
        } catch (\Throwable $ex) {
            Db::rollBack();
            $this->logger->error("错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->ReturnJson->failFul();
        }
    }


    /**
     * 领取奖励
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCash(){
        $uid = $this->request->getAttribute('uid');
        $turntable_config = config('turntable');
        $money = $turntable_config['money'] * 100;
        $info = Db::table('turntable')->where(['endstatus'=>1,'uid'=>$uid])->orderBy('id','desc')->first();
        if (!empty($info) && $info['endstatus'] == 1 && $info['money'] >= $turntable_config['money']){
            try {
                Db::beginTransaction();

                $res = User::userEditCoin($uid, $money, 11, '玩家'.$uid.'转盘获取：'.$turntable_config['money'], 4, 6);
//                $res = User::userEditBonus($uid, $money, 11, '玩家'.$uid.'转盘获取：'.$turntable_config['money'], 2, 6);
                if (!$res){
                    Db::rollback();
                    return $this->ReturnJson->failFul(201,[],1);
                }

                /*$res = User::editUserTotalGiveScore($uid, $money);
                if (!$res){
                    Db::rollback();
                    return $this->ReturnJson->failFul(202,[],1);
                }*/

                $res = Db::table('turntable')->where('id',$info['id'])->update([
                    'endstatus' => 2
                ]);
                if (!$res){
                    Db::rollback();
                    return $this->ReturnJson->failFul(203,[],1);
                }

                Db::commit();
                return $this->ReturnJson->successFul(200,[],1);

            }catch (\Throwable $ex){
                Db::rollback();
                $this->logger->error("错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
                return $this->ReturnJson->failFul(204,[],1);
            }

        }else{
            return $this->ReturnJson->failFul(205,[],1);
        }
    }


    /**
     * 每日给与转盘次数
     * @return mixed
     * @throws \think\db\exception\DbException
     */
    public function setTurntableCount(){
        $threeDaysAgo = strtotime('-5 days'); // 获取三天前的时间戳

        $result = Db::table('turntable')
            ->where('createtime', '>=', $threeDaysAgo)
            ->where('endstatus', 1)
            ->update(['z_count' => 1]);

        if ($result){
            return $this->ReturnJson->successFul(200, [], 2);
        }else{
            return $this->ReturnJson->failFul(201, [], 2);
        }
    }
}
