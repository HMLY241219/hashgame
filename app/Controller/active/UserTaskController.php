<?php

declare(strict_types=1);

namespace App\Controller\active;

use App\Common\Common;
use App\Common\User;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use ReflectionClass;
use function Hyperf\Config\config;

#[Controller(prefix:'task')]
class UserTaskController extends AbstractController
{

    #[RequestMapping(path:'index')]
    public function index(){
        $uid = $this->request->post('uid');

        //获取需要的日期
        [$dayOfWeek,$today,$week_start] = $this->getTodayWeek();

        $task = Db::table('task')->selectRaw('id,title,introduction,zs_cash,zs_bonus,zs_integral,num')->where('task_time',0)->orWhere('task_time',$dayOfWeek)->get()->toArray();
        if(!$task)return $this->ReturnJson->successFul();

        //连接5501Redis
        $Redis = Common::Redis('Redis5501');
        // 生成 Redis Key
        $redisKey = "tasks_".$uid;


        $getTaskLogIdArray = $this->getTaskLog($uid,$week_start,$dayOfWeek);

        $list = [];
        foreach ($task as $key => $taskValue){
            $list[$key][] = $taskValue;
            // 读取现有的任务进度
            $list[$key]['progress_num'] = 0;
            $list[$key]['status'] = 0;
            $existingData = $Redis->hGet($redisKey, $taskValue['id']);
            if($existingData){
                $taskData = json_decode($existingData, true);
                if(($taskValue['task_time'] == 0 && $taskData['updatetime'] == $week_start) || ($taskValue['task_time'] != 0  && $taskData['updatetime'] == $today)){
                    $list[$key]['progress_num'] = $taskData['progress_num'];
                    if($taskData['progress_num'] >= $taskValue['num'] && !in_array($taskValue['id'],$getTaskLogIdArray)){
                        $list[$key]['status'] = 2;
                    }elseif ($taskData['progress_num'] >= $taskValue['num']){
                        $list[$key]['status'] = 1;
                    }
                }
            }
        }


        $task_integral = $this->getTaskIntegral($uid,$week_start);
        $data = [
            'task_list' => $list,
            'task_integral' => $task_integral
        ];
        return $this->ReturnJson->successFul(200,$data);
    }

    /**
     * 获取用户参与的任务记录
     * @param $uid
     * @param $week_start
     * @param $dayOfWeek 获取今天是一周中的第几天（0-6，其中周日为 0）
     * @return array
     */
    private function getTaskLog($uid,$week_start,$dayOfWeek):array{
        $task_log = Db::table('task_log')->selectRaw('task_id,task_time')->where([['uid','=',$uid],['date','>=',$week_start]])->orderBy('id','desc')->get()->toArray();
        $getTaskLogIdArray = [];
        if($task_log){
            foreach ($task_log as $task_logValue){
                if($task_logValue['task_time'] == 0 || ($task_logValue['task_time'] == $dayOfWeek)){
                    $getTaskLogIdArray[] = $task_logValue['task_id'];
                }
            }

        }
        return $getTaskLogIdArray;
    }

    /**
     * 获取积分数据
     * @param $uid  用户UID
     * @param $week_start 本周的开始时间
     * @return array
     */
    private function getTaskIntegral($uid,$week_start):array{
        $task_integral = Db::table('task_integral')->selectRaw('id,integral,zs_cash,zs_bonus,0 as status')->orderBy('weight','desc')->get()->toArray();
        //用户当前积分
        $user_integral = Db::table('user_integral')->where(['date' => $week_start,'uid' => $uid])->value('integral');
        $user_integral = $user_integral ?: 0;
        //查询用户当日的领取记录
        $task_integral_array = Db::table('task_integral_log')->where(['date' => $week_start,'uid' => $uid])->pluck('task_integral_id');
        $task_integral_array = $task_integral_array ?: [];

        foreach ($task_integral as &$value){
            if($user_integral > $value['integral'] && !in_array($value['id'],$task_integral_array)){
                $value['status'] = 1;
            }elseif ($user_integral > $value['integral']){
                $value['status'] = 2;
            }
        }
        return  $task_integral;
    }

    /**
     * 获取当天的开始时间.当天是周几，本周开始时间
     * @return void
     */
    private function getTodayWeek(){
        //获取当天的日期
        $currentDayOfWeek = date('w'); // 获取今天是一周中的第几天（0-6，其中周日为 0）
        $dayOfWeek = $currentDayOfWeek == 0 ? 7 : $currentDayOfWeek; // 将周日转换为 7
        $today = date('Ymd');
        [$week_start_time,] = \App\Common\DateTime::startEndWeekTime(time());

        $week_start = date('Ymd',$week_start_time);
        return [$dayOfWeek,$today,$week_start];
    }

    /**
     * 领取积分或者任务奖励
     * @return void
     */
    #[RequestMapping(path:'receiveTask')]
    public function receiveReward(){
        $uid = $this->request->post('uid'); //玩家ID
        $id = $this->request->post('id'); //任务ID或者积分ID
        $type = $this->request->post('type') ?? 1; //1=领取任务,2=兑换积分
        $res = $type == 1 ? $this->receiveTask($uid,$id) : $this->receiveIntegral($uid,$id);
        return $res['code'] == 200 ? $this->ReturnJson->successFul() : $this->ReturnJson->failFul($res['code']);
    }

    /**
     * 领取任务奖励
     * @param $uid
     * @param $id
     * @return array
     */
    private function receiveTask($uid,$id){
        $task = Db::table('task')->selectRaw('id,zs_cash,zs_bonus,zs_integral,num,task_time')->where('id',$id)->first();
        if(!$task)return ['code' => 248];
        //获取当天的开始时间.当天是周几，本周开始时间
        [$dayOfWeek,$today,$week_start] = $this->getTodayWeek();
        //连接5501Redis
        $Redis = Common::Redis('Redis5501');
        // 生成 Redis Key
        $redisKey = "tasks_".$uid;
        $existingData = $Redis->hGet($redisKey,$redisKey);
        if(!$existingData)return ['code' => 248];
        $taskData = json_decode($existingData, true);
        if($task == 0){
            //判断本周是否可以领取
            if($taskData['updatetime'] != $week_start || $taskData['progress_num'] < $task['num'])return ['code' => 248];
            $task_log = Db::table('task_log')->where(['uid' => $uid,'task_id' => $id,'date' => $week_start])->value('id');
        }else{
            if($taskData['updatetime'] != $today || $taskData['progress_num'] < $task['num'])return ['code' => 248];
            $task_log = Db::table('task_log')->where(['uid' => $uid,'task_id' => $id,'date' => $today])->value('id');
        }
        if($task_log)return ['code' => 251];

        Db::beginTransaction();

        //存储日志
        $res = Db::table('task_log')->insert([
            'uid' => $uid,
            'task_id' => $id,
            'task_time' => $task['task_time'],
            'zs_cash' => $task['zs_cash'],
            'zs_bonus' => $task['zs_bonus'],
            'zs_integral' => $task['zs_integral'],
            'createtime' => time(),
            'date' => $task['task_time'] == 0 ? $week_start : $today
        ]);
        if(!$res){
            Db::rollback();
            return ['code' => 249];
        }
        //开始发奖
        if($task['zs_cash'] > 0){
            $res = User::userEditCoin($uid,$task['zs_cash'],29,'用户:'.$uid.'任务奖励:'.bcdiv((string)$task['zs_cash'],'100',2),2,19);
            if(!$res){
                Db::rollback();
                return ['code' => 249];
            }
        }

        if($task['zs_bonus'] > 0){
            $res = User::userEditBonus($uid,$task['zs_bonus'],29,'用户:'.$uid.'充值活动'.bcdiv((string)$task['zs_bonus'],'100',2),2,19);
            if(!$res){
                Db::rollback();
                return ['code' => 249];
            }
        }

        if($task['zs_integral'] > 0){
            $user_integral_id = Db::table('user_integral')->where([['date','=',$week_start],['uid','=',$uid]])->value('id');
            if(!$user_integral_id){
                $res = Db::table('user_integral')->insert([
                    'uid' => $uid,
                    'date' => $week_start,
                    'integral' => $task['zs_integral'],
                ]);
            }else{
                $res = Db::table('user_integral')->where('id',$user_integral_id)->update([
                    'integral' => Db::raw('integral + '.$task['zs_integral'])
                ]);
            }
            if(!$res){
                Db::rollback();
                return ['code' => 249];
            }
        }
        Db::commit();
        return ['code' => 200];
    }



    /**
     * 兑换积分
     * @param $uid
     * @param $id
     * @return array
     */
    private function receiveIntegral($uid,$id){
        $task_integral = Db::table('task_integral')->selectRaw('id,integral,zs_cash,zs_bonus')->where('id',$id)->first();
        if(!$task_integral)return ['code' => 248];
        //获取当天的开始时间.当天是周几，本周开始时间
        [$dayOfWeek,$today,$week_start] = $this->getTodayWeek();
        $user_integral = Db::table('user_integral')->where(['date' => $week_start,'uid' => $uid])->value('integral');
        if(!$user_integral)return ['code' => 248];


        if($user_integral < $task_integral['integral'])return ['code' => 248];

        //查询用户是否领取了积分
        $task_integral_id = Db::table('task_integral_log')->where(['date' => $week_start,'uid' => $uid,'task_integral_id' => $id])->value('id');
        if($task_integral_id)return ['code' => 251];
        Db::beginTransaction();
        //存储记录
        $res = Db::table('task_integral_log')->insert([
            'uid' => $uid,
            'task_integral_id' => $id,
            'zs_cash' => $id,
            'zs_bonus' => $id,
            'createtime' => time(),
            'date' => $week_start,
        ]);
        if(!$res){
            Db::rollback();
            return ['code' => 249];
        }

        //开始发奖
        if($task_integral['zs_cash'] > 0){
            $res = User::userEditCoin($uid,$task_integral['zs_cash'],30,'用户:'.$uid.'积分兑换:'.bcdiv((string)$task_integral['zs_cash'],'100',2),2,19);
            if(!$res){
                Db::rollback();
                return ['code' => 249];
            }
        }

        if($task_integral['zs_bonus'] > 0){
            $res = User::userEditBonus($uid,$task_integral['zs_bonus'],30,'用户:'.$uid.'积分兑换'.bcdiv((string)$task_integral['zs_bonus'],'100',2),2,19);
            if(!$res){
                Db::rollback();
                return ['code' => 249];
            }
        }

        Db::commit();
        return ['code' => 200];
    }
}

