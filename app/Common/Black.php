<?php
declare(strict_types=1);
namespace App\Common;


use Hyperf\DbConnection\Db;
use function Hyperf\Config\config;

/**
 *  黑名单处理
 */
class Black
{

    /**
     * 检查数据是否在黑名单
     * @return bool
     * @param $filed array 数组 代表各个黑名单表 ['deviceid' => '1200000','email'=>'12121313@qq.com']  br_black_email br_black_deviceid 表示去这2张表
     */
    public static function is_block(array $filed):bool{
        $status = false;
        foreach ($filed as $key => $value){
            $res = Db::table('black_'.$key)->where($key,$value)->first();
            if($res){
                $status = true;  //确定是关联用户以后直接封禁
                break;
            }
        }
        return $status; //true = 是 , 0=否
    }

    /**
     * 封禁用户
     * @param $uid int 用户的uid
     * @param $admin_id int 管理员id 0=系统 ,其它的就是管理员id
     * @param $filed array 数组 代表各个黑名单表 ['deviceid' => '1200000','email'=>'12121313@qq.com']  br_black_email br_black_deviceid 表示去这2张表
     * @param $reason string 封禁原因
     * @return void
     */
    public static function prohibitedUsers(int $uid,int $admin_id,array $filed = [],string $reason = ''){
        $black_user = Db::table('black_user')->select('uid')->where('uid',$uid)->first();
        if($black_user){
            return '用户已经封禁';
        }
        $status = true;  //判断用户是否要检查是否封禁状态
        if(count($filed) > 0){  //如果需要检查成功$status变为true，否则false
            $status = false;
            foreach ($filed as $key => $value){
                $res = Db::table('black_'.$key)->where($key,$value)->first();
                if($res){
                    $reason = $value;
                    $status = true;  //确定是关联用户以后直接封禁
                    break;
                }
            }
        }
        if($status){  //封禁玩家
            Db::table('share_strlog')->where('uid',$uid)->update(['status' => 0]); //修改用户状态为封禁
            Db::table('user_token')->where('uid',$uid)->delete();

            //加入封禁名单
            $list = [
                'uid' => $uid,
                'admin_id' => $admin_id,
                'reason' => $reason,
                'createtime' => time(),
            ];
            Db::table('black_user')->insertOrIgnore($list);
            self::blackInfomation($uid); //拉黑用户信息
            self::blockGlUser($uid);  //拉黑用户和关联用户
        }
    }

    /**
     *  解封
     * @param $uid  int 用户的uid
     */
    public static function unblockingUser(int $uid){


        Db::table('share_strlog')->where('uid',$uid)->update(['status' => 1]); //解除用户封禁

        self::blackInfomation($uid,2);//解除拉黑信息
        Db::table('black_user')->where('uid',$uid)->delete();
    }

    /**
     * @return void 拉黑时拉黑用户信息，解封时删除用户拉黑信息
     * @param $uid int 用户的uid
     * @param $type int 类型 1= 拉黑 2=解除拉黑
     *
     */
    public static function blackInfomation(int $uid,int $type = 1){
        $getUidinformation = My::getUidinformation($uid);
        if($getUidinformation['code'] != 200){
            return ;
        }
        if($type == 1){
            foreach ($getUidinformation['data'] as $k => $v){
                Db::table('black_'.$k)->insertOrIgnore([$k => $v]);
            }
        }else{
            foreach ($getUidinformation['data'] as $k => $v){
                Db::table('black_'.$k)->where([$k => $v])->delete();
            }
        }
    }

    /**
     * 封禁关联用户
     * @param $uid 用户uid
     * @return void
     */
    public static function blockGlUser(int $uid){
        $uidArray = My::glUid($uid);
        if($uidArray){
            $userinfoArray = [];
            $phpExecArray = []; //
            $blackUserArray = [];
            foreach ($uidArray as $val){
                $glUid = Db::table('black_user')->where('uid',$val)->value('uid');
                if($glUid){
                    continue;
                }

                $userinfoArray[] = $val;


                //加入封禁名单
                $blackUserArray[] = [
                    'uid' => $val,
                    'admin_id' => 0,
                    'reason' => "关联".$uid."用户封号",
                    'createtime' => time(),
                ];
            }

            if($blackUserArray){
                Db::table('black_user')->insertOrIgnore($blackUserArray);
                Db::table('share_strlog')->whereIn('uid',$userinfoArray)->update(['status' => 0]);
                Db::table('user_token')->whereIn('uid',$userinfoArray)->delete();
            }
        }

    }


    /**
     * 拉黑三方拉黑的用户
     * @return void 黑名单检测
     * @param  $msg string 三方错误信息
     * @param  $field string 匹配的拉黑特殊字符
     * @param  $uid int 用户uid
     */
    public static function blackStatusUser(string $msg,string $field,int $uid){
        if(strpos($msg, $field)){  //说明是三方拉黑用户信息
            self::prohibitedUsers($uid,0,[],'三方支付黑名单玩家');
        }
    }
}