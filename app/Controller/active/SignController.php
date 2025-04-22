<?php

declare(strict_types=1);

namespace App\Controller\active;

use App\Common\Common;
use App\Common\User;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\AutoController;
use function Hyperf\Config\config;

#[AutoController(prefix: "sign")]
class SignController extends AbstractController
{

    /**
     * 签到天列表
     * @param Request $request
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function signList(){
        $param = $this->request->all();
        $total_pay_score = 0;
        $uid = isset($param['uid']) ? $param['uid'] : -1;
        if ($uid != -1 && $uid > 0){
            $userinfo = Db::table('userinfo')->where('uid',$uid)->first();
            $total_pay_score = $userinfo['total_pay_score'];
        }

        //没充值或者没登录 返回空
        if ($total_pay_score <= 0){
            return $this->ReturnJson->successFul(200,['list'=>[],'today'=>0],1);
        }

        $sign = Db::table('sign_config')->where('start_money','<=',$total_pay_score)->where('end_money','>=',$total_pay_score)->first();
        if (empty($sign)){
            $sign = Db::table('sign_config')->orderBy('id','desc')->first();
            //return $this->ReturnJson->failFul(204,[],1);
        }
        //解析签到配置
        $cash_sign_arr = [];
        $bonus_sign_arr = [];
        $cash_sign = explode(',',$sign['cash']);
        foreach ($cash_sign as $ck=>$cv){
            $cvv = explode('-',$cv);
            $is_f = 0;
            if (isset($cvv[1])){
                $is_f = 1;
            }
            $cash_sign_arr[] = [
                'cash'=>$cv,
                'is_f'=>$is_f,
            ];
        }
        $bonus_sign = explode(',',$sign['bonus']);
        foreach ($bonus_sign as $bv){
            $bvv = explode('-',$bv);
            $is_fb = 0;
            if (isset($bvv[1])){
                $is_fb = 1;
            }
            $bonus_sign_arr[] = [
                'bonus'=>$bv,
                'is_f'=>$is_fb,
            ];
        }

        $user_sign = Db::table('sign_in')->where('uid',$uid)->first();
        if ($user_sign){//签到过
            $sign_date = date('Y-m-d',strtotime($user_sign['signTime']));
            //连续7天后，关闭签到功能
            if ($user_sign['num'] >= 7 && date('Y-m-d') > $sign_date){
                return $this->ReturnJson->successFul(200,['list'=>[],'today'=>0],1);
            }

            // 计算天数
            $differDay = $this->diffBetweenTwoDays(date('Y-m-d', time()), substr($user_sign['signTime'], 0, 10));
            // 0今日签到，1昨日已签到
            if ($differDay == 0 || $differDay == 1) {
                // 这里不对数据库的签到天数做处理，可以一直记录连续签到的天数，方便后面需求的拓展
                // 而在逻辑上连续签到7天需重置签到天数，所以对签到天数做7求余（具体天数看实际项目需求更改求余），得出逻辑需求里的连续签到天数
                $num = $user_sign['num'] % 7;// == 0 ? 7 : $user_sign['num'] % 7;
                if ($differDay == 0){
                    $num = $user_sign['num'] % 7 == 0 ? 7 : $user_sign['num'] % 7;
                }
            } else {
                // 断签，重置签到天数，因为是获取签到而不是签到，所以num重置为0
                $num = 0;
                Db::table('sign_in')->where('uid',$uid)->update(['num'=>$num]);
            }

            // 判断今日是否签到
            if ($differDay == 0) {
                // 今天已签到
                $today = 1;
            } else {
                // 今日未签到
                $today = 0;
            }

        }else{//无签到
            // 未签到，插入数据，签到天数为0
            $num = 0;
            $today = 0;
            //Db::connect($this->connection)->name('sign_in')->insert(['uid'=>$param['uid'],'num'=>$num]);
        }

        $data = [];
        for ($i=0; $i<7; $i++){
            $data[$i]['name'] = ($i+1).'Day';
            $data[$i]['cash'] = $cash_sign_arr[$i]['cash'];
            $data[$i]['bonus'] = $bonus_sign_arr[$i]['bonus'];
            $data[$i]['is_sign'] = $num >= $i+1 ? 1 : 0;
            $data[$i]['is_f'] = $cash_sign_arr[$i]['cash'] ? $cash_sign_arr[$i]['is_f'] : $bonus_sign_arr[$i]['is_f'];
        }

        $list = [];
        $list['list'] = $data;
        $list['today'] = $today;
        $list['now_time'] = time();
        $list['re_time'] = strtotime('tomorrow midnight') - time();

        return $this->ReturnJson->successFul(200,$list,1);
    }

    /**
     * @api {post} /minwx/sign/signIn 签到
     * @apiName signIn
     * @apiParam {string} userId 用户id
     * @apiGroup 签到
     */
    public function signIn()
    {
        $userId = $this->request->getAttribute('uid');
        $user_sign = Db::table('sign_in')->where('uid',$userId)->first();
        $userinfo = Db::table('userinfo')->where('uid',$userId)->first();
        if (empty($userinfo)) return $this->ReturnJson->failFul(204,[],1);
        if ($userinfo['total_pay_score'] > 0){
            $total_pay_score = $userinfo['total_pay_score'];
        }else{
            return $this->ReturnJson->failFul(267,[],1);
            $total_pay_score = 0;
        }

        try {
            Db::beginTransaction();

            // 判断用户是否签到过
            if ($user_sign) {
                //连续7天后，关闭签到功能
                if ($user_sign['num'] >= 7){
                    return $this->ReturnJson->failFul(201,[],1);
                }

                // 更新用户数据
                $differDay = $this->diffBetweenTwoDays(date('Y-m-d', time()), substr($user_sign['signTime'], 0, 10));
                if ($differDay == 0){//今日已签到
                    return $this->ReturnJson->failFul(201,[],1);
                }
                if ($differDay == 1) {
                    // 连续签到，更新签到时间，签到天数+1
                    $num = $user_sign['num'] + 1;
                } else {
                    // 没有连续签到，更新签到时间，签到次数初始化为1
                    $num = 1;
                }
                Db::table('sign_in')->where('uid',$userId)->update(['num' => $num, 'signTime' => date('Y-m-d H:i:s', time())]);

            } else {
                $num = 1;
                // 添加用户数据
                $indata = [
                    'uid' => $userId,
                    'num' => $num,
                    'signTime' => date('Y-m-d H:i:s', time())
                ];
                Db::table('sign_in')->insert($indata);
            }

            // 获取签到奖励
            $sign = Db::table('sign_config')->where('start_money','<=',$total_pay_score)->where('end_money','>=',$total_pay_score)->first();
            if (empty($sign)){
                $sign = Db::table('sign_config')->orderBy('id','desc')->first();
                //return $this->ReturnJson->failFul(204,[],1);
            }
            //解析签到配置
            $cash_sign_arr = [];
            $bonus_sign_arr = [];
            $cash_sign = explode(',',$sign['cash']);
            foreach ($cash_sign as $ck=>$cv){
                $cvv = explode('-',$cv);
                $is_f = 0;
                if (isset($cvv[1])){
                    $is_f = 1;
                }
                $cash_sign_arr[] = [
                    'cash'=>$cv,
                    'is_f'=>$is_f,
                    'cash_arr'=>$cvv,
                ];
            }
            $bonus_sign = explode(',',$sign['bonus']);
            foreach ($bonus_sign as $bv){
                $bvv = explode('-',$bv);
                $is_fb = 0;
                if (isset($bvv[1])){
                    $is_fb = 1;
                }
                $bonus_sign_arr[] = [
                    'bonus'=>$bv,
                    'is_f'=>$is_fb,
                    'bonus_arr'=>$bvv,
                ];
            }


            $i = $num % 7 == 0 ? 7 : $num % 7;
            if ($cash_sign_arr[$i-1]['is_f'] == 1){
                $cash = mt_rand((int)$cash_sign_arr[$i-1]['cash_arr'][0], (int)$cash_sign_arr[$i-1]['cash_arr'][1]);
            }else{
                $cash = (int)$cash_sign_arr[$i-1]['cash'];
            }
            if ($bonus_sign_arr[$i-1]['is_f'] == 1){
                $bonus = mt_rand((int)$bonus_sign_arr[$i-1]['bonus_arr'][0], (int)$bonus_sign_arr[$i-1]['bonus_arr'][1]);
            }else{
                $bonus = (int)$bonus_sign_arr[$i-1]['bonus'];
            }


            // 给予用户奖励
            $res = User::userEditCoin($userId, $cash, 19, '玩家'.$userId.'签到获取：'.$cash, 2, 10);
            if (!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(202,[],1);
            }

            $res = User::userEditBonus($userId, $bonus, 19, '玩家'.$userId.'签到获取：'.$bonus, 2, 10);
            if (!$res){
                Db::rollback();
                return $this->ReturnJson->failFul(203,[],1);
            }

            if ($cash > 0) {
                $res = User::editUserTotalGiveScore($userId, $cash);
                if (!$res) {
                    Db::rollback();
                    return $res;
                    return $this->ReturnJson->failFul(204, [], 1);
                }
            }

            Db::commit();

            $data = [];
            $data['cash'] = $cash;
            $data['bonus'] = $bonus;

            return $this->ReturnJson->successFul(200,$data,1);
        }catch (\Throwable $ex){
            Db::rollBack();
            $this->logger->error("错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->ReturnJson->failFul(204,[],1);
        }
    }


    //两个日期之间相差的天数
    public function diffBetweenTwoDays($day1, $day2)
    {
        $second1 = strtotime($day1);
        $second2 = strtotime($day2);

        if ($second1 < $second2) {
            $tmp = $second2;
            $second2 = $second1;
            $second1 = $tmp;
        }
        return ($second1 - $second2) / 86400;
    }



}
