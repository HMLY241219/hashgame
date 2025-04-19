<?php
declare(strict_types=1);
namespace App\Common;


use Hyperf\DbConnection\Db;
use function Hyperf\Config\config;
//use App\Controller\slots\Common as slotsCommon;

/**
 *  消息
 */
class Message
{
    //#[Inject]
    //protected slotsCommon $slotsCommon;


    /**
     * 用户触发行为发生消息
     * @param int $type 消息类型
     * @param array $data uid s1 s2...
     * @return int
     */
    public static function messageSelect(int $type, array $data){
        if (!isset($data['uid'])) return 1;
        $userinfo = Db::table('userinfo')->where('uid', $data['uid'])->select('package_id')->first();
        if (empty($userinfo)) return 1;
        $package1699 = config('my.package1699');
        if (!$package1699){
            $package1699 = [];
        }
        if (in_array($userinfo['package_id'], $package1699)){
            $skin_type = 2;
        }else{
            $skin_type = 1;
        }

        $message_config = Db::table('message_config')->where(['status'=>1,'delete_time'=>0, 'skin_type'=>$skin_type])->get()->keyBy('type')->toArray();

        switch ($type){
            case 1:
                $text = isset($message_config[1]['content']) ? $message_config[1]['content'] : '';
                break;
            case 2:
                $s1 = isset($data['s1']) ? $data['s1'] : '';
                $text = isset($message_config[2]['content']) ? vsprintf($message_config[2]['content'], [$s1]) : '';
                break;
            case 3:
                $text = isset($message_config[3]['content']) ? $message_config[3]['content'] : '';
                break;
            case 4:
                $text = isset($message_config[4]['content']) ? $message_config[4]['content'] : '';
                break;
            case 6:
                $text = isset($message_config[6]['content']) ? $message_config[6]['content'] : '';
                break;
            case 7:
                $text = isset($message_config[7]['content']) ? $message_config[7]['content'] : '';
                break;
            case 8:
                $text = isset($message_config[8]['content']) ? $message_config[8]['content'] : '';
                break;
            case 9:
                $s1 = isset($data['s1']) ? $data['s1'] : '';
                $text = isset($message_config[9]['content']) ? vsprintf($message_config[9]['content'], [$s1]) : '';
                break;
            case 10:
                $text = isset($message_config[10]['content']) ? $message_config[10]['content'] : '';
                break;
            case 12:
                $s1 = isset($data['s1']) ? $data['s1'] : '';
                $text = isset($message_config[12]['content']) ? vsprintf($message_config[12]['content'], [$s1]) : '';
                break;
            default:
                $text = '';
                break;
        }

        if (!empty($text) && isset($data['uid'])){
            self::sendMessage($data['uid'], $text);
        }

        return 1;
    }

    public static function sendMessage($uid, $text){
        //存储发送内容
        Db::table('user_information')->insert([
            'uid' => $uid,
            'title' => 'System messages',
            'content' => $text,
            'createtime' => time(),
        ]);
    }

}