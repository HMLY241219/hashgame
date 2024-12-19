<?php

declare(strict_types=1);

namespace App\Controller\share;

use App\Common\Common;
use App\Common\User;
use App\Controller\AbstractController;
use Hyperf\Context\Context;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\AutoController;
use Swoole\Coroutine\Channel;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;
use function Hyperf\Support\today;

#[AutoController(prefix: "top")]
class TopController extends AbstractController
{
    /**
     * 每日排行数据
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function dayTopList(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'type' => 'required|in:0,1',
                'is_last' => 'required|in:0,1',
            ],
            [
                'type.required' => 'type is required',
                'is_last.required' => 'is_last is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("排行榜数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[]);
        }

        $uid = isset($param['uid']) ? $param['uid'] : 0;

        $data = [];
        //$limit = isset($param['page_size']) ? $param['page_size'] : 18;
        if ($param['type'] == 0) {
            $time = strtotime('00:00:00');
            if ($param['is_last']){
                $time = $time - 86400;
            }
            $give_time = $time - 86400;
            $data['end_time'] = strtotime('23:59:59');
        }else{
            $time = strtotime('last monday', strtotime('tomorrow'));
            if ($param['is_last']){
                $time = $time - 604800;
            }
            $give_time = $time - 604800;
            $data['end_time'] = $time + 604800;
        }
        $data['now_time'] = time();
        $list = Db::table('bet_top as a')
            ->leftJoin('share_strlog as b','a.uid','=','b.uid')
            ->select('a.uid','a.bet','b.avatar')
            ->where('a.time',$time)
            ->where('a.type',$param['type'])
            ->orderBy('a.bet','desc')
            ->limit(100)
            ->get()->toArray();

        $top_config = Db::table('top_config')->where('type',$param['type'])->get()->toArray();

        if (!empty($list)) {
            foreach ($list as $k => &$v) {
                $v['rank'] = $k + 1;
                if ($v['avatar']) {
                    $v['avatar'] = Common::domain_name_path((string)$v['avatar']);
                }else{
                    $avatar = config('avatar');
                    $random_key = array_rand($avatar);
                    $v['avatar'] = Common::domain_name_path((string)$avatar[$random_key]);
                }

                $v['give'] = 0;
                foreach ($top_config as $k1 => $v1) {
                    $tmp = explode(',',$v1['rank']);
                    $tmp[1] = isset($tmp[1]) ? $tmp[1] : $tmp[0];
                    if ($k + 1 >= $tmp[0] && $k + 1 <= $tmp[1]) {
                        $v['give'] = $v1['cash'] + $v1['bonus'];
                        break;
                    }
                }
                if ($v['give'] == 0){
                    $v['give'] = $v1['cash'] + $v1['bonus'];
                }
            }
        }
        $data['list'] = $list;

        $give = 0;
        //var_dump($time - 86400);
        if ($uid != 0){
            $user_give = Db::table('bet_top')->where('time',$give_time)->where('uid',$uid)
                ->where('user_type',0)
                ->where('type',$param['type'])
                ->first();
            if (!empty($user_give)) {
                $give = $user_give['get_cash'] + $user_give['get_bonus'];
            }
        }
        $data['give'] = $give;

        if ($data) {
            return $this->ReturnJson->successFul(200, $data, 1);
        }else{
            return $this->ReturnJson->successFul(200, [], 1);
        }
    }

    /**
     * 领取奖励
     * @return null
     */
    public function getDayGive()
    {
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
                'type' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
                'type.required' => 'type is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("领取每日排行奖励数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[]);
        }
        $time = strtotime('00:00:00') - 86400;
        if ($param['type'] == 0) {
            $time = strtotime('00:00:00') - 86400;
        }else{
            $time = strtotime('last monday', strtotime('tomorrow')) - 604800;
        }

        $users = Db::table('bet_top')->where('time',$time)->where('type',$param['type'])->orderBy('bet','desc')->limit(100)->pluck('uid')->toArray();
        if (!in_array($param['uid'],$users)) {
            return $this->ReturnJson->failFul(248,[]);
        }

        $user_give = Db::table('bet_top')
            ->where('time',$time)
            ->where('uid',$param['uid'])
            ->where('type',$param['type'])
            ->where('user_type',0)
            ->first();
        if (empty($user_give) || ($user_give['get_cash'] + $user_give['get_bonus']) <= 0) return $this->ReturnJson->failFul(248,[]);

        try {
            Db::beginTransaction();

            Db::table('top_log')->insert([
                'uid' => $param['uid'],
                'top_id' => $user_give['id'],
                'get_cash' => $user_give['get_cash'],
                'get_bonus' => $user_give['get_bonus'],
                'createtime' => time(),
            ]);

            if ($user_give['get_cash'] > 0) {
                $res = User::userEditCoin($param['uid'], $user_give['get_cash'], 28, '每日下注排行榜奖励', 2, 18);
                if (!$res){
                    Db::rollback();
                    $this->logger->error("领取每日排行奖励userEditCoin失败");
                    return $this->ReturnJson->failFul(201,[],1);
                }
                $res = User::editUserTotalGiveScore($param['uid'], $user_give['get_cash']);
                if (!$res){
                    Db::rollback();
                    $this->logger->error("领取每日排行奖励editUserTotalGiveScore失败");
                    return $this->ReturnJson->failFul(201,[],1);
                }
            }
            if ($user_give['get_bonus'] > 0) {
                $res = User::userEditBonus($param['uid'], $user_give['get_bonus'], 28, '每日下注排行榜奖励', 2, 18);
                if (!$res){
                    Db::rollback();
                    $this->logger->error("领取每日排行奖励userEditBonus失败");
                    return $this->ReturnJson->failFul(201,[],1);
                }
            }

            Db::table('bet_top')->where('id',$user_give['id'])->update(['get_cash'=>0,'get_bonus'=>0]);


            Db::commit();
            return $this->ReturnJson->successFul(200,[],1);

        }catch (\Throwable $e){
            Db::rollback();
            $this->logger->error("错误文件===" . $e->getFile() . '===错误行数===' . $e->getLine() . '===错误信息===' . $e->getMessage());
            return $this->ReturnJson->failFul();
        }

    }

    /**
     * 排行榜规则
     * @return null
     */
    public function topRule(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'type' => 'required|in:0,1',
            ],
            [
                'type.required' => 'type is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("排行榜规则数据验证失败===>".$errorMessage);
            return $this->ReturnJson->failFul(219,[]);
        }

        $data = [];

        $list = Db::table('top_config')
            ->select('rank','cash','bonus')
            ->where('type',$param['type'])
            ->orderBy('id','asc')
            ->get()->toArray();

        $data['list'] = $list;

        if ($data) {
            return $this->ReturnJson->successFul(200, $data, 1);
        }else{
            return $this->ReturnJson->successFul(200, [], 1);
        }
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
