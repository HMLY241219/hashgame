<?php
declare(strict_types=1);
namespace App\Controller\user;

use App\Common\Guzzle;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use App\Common\SqlUnion;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Common\User;
use function Hyperf\Config\config;

/**
 *  用户信息
 */
#[Controller(prefix:'user.User')]
class UserController extends AbstractController {



    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected SqlUnion $SqlUnion;

    /**
     * 上传图片
     * @return null
     */
    #[RequestMapping(path:'uploadImage')]
    public function uploadImage(){
        $file = $this->request->file('file');
        if(!$file)return $this->ReturnJson->failFul();
        $url = config('host.adminDomain').'/api/user.User/uploadImage';
        $res = $this->guzzle->post($url,['file' => $file]);
        if($res['code'] != 200)return $this->ReturnJson->failFul();
        return $this->ReturnJson->successFul(200,['pathUrl' => $res['data'],'url' => config('host.adminDomain').$res['data']]);
    }

    /**
     * 修改用户头像
     * @return null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    #[RequestMapping(path:'editUserAvatar')]
    public function editUserAvatar(){
        $uid = $this->request->post('uid');
        $avatar = $this->request->post('avatar_key'); //图片索引
        Db::table('share_strlog')->where('uid',$uid)->update(['avatar' => config('avatar')[$avatar + 1] ?? '']);
        return $this->ReturnJson->successFul();
    }


    /**
     * 用户Cash和Bonus流水记录
     * @return void
     */
    #[RequestMapping(path:'UserWaterLog')]
    public function UserWaterLog(){
        $uid = $this->request->post('uid');
        $type = $this->request->post('type') ?? 1;//类型1=Cash,2=Bonus
        $page = $this->request->post('page') ?? 1; //当前页数
        $date = $this->request->post('date') ?? date('Ymd');
        if(!$date)$date =  date('Ymd');
        $date = str_replace('-', '', (string)$date);
        $table = $type == 1 ? 'coin_' : 'bonus_';
        $field = "FROM_UNIXTIME(createtime,'%d/%m/%Y %H:%i') as createtime,num,reason";
        $where = [['uid','=',$uid],['reason','<>',3]];
        if($type == 1)$where[] = ['type','=',1];

        $list = Db::connection('readConfig')->table($table.$date)->selectRaw($field)->where($where)->orderBy('createtime','desc')->forPage((int)$page,20)->get()->toArray();
//        $dateArray = \App\Common\DateTime::createDateRange(strtotime('-30 day'),time(),'Ymd');
//        $dayDescArray = array_reverse($dateArray);
//        $list = $this->SqlUnion->subTableQueryPage($dayDescArray,$table,$field,$where,'createtime',(int)$page);

        return $this->ReturnJson->successFul(200,$list);
    }

    /**
     * 获取tawk.to聊天hash值
     * @return void
     */
    #[RequestMapping(path:'tawkHash')]
    public function tawkHash(){
        $uid = $this->request->post('uid');
        $jiaemail = Db::table('share_strlog')->where('uid',$uid)->value('jiaemail');
        return $this->ReturnJson->successFul(200, hash_hmac('sha256',$jiaemail,'89553b729f0570a1c75248f8b02f8986cd20f5a9'));
    }

    /**
     * 手机号检测
     * @return void
     */
    #[RequestMapping(path:'checkPhone')]
    public function checkPhone(){
        $phone = $this->request->post('phone');
        $packname = $this->request->getAttribute('packname');
        $package_id = Db::table('apppackage')->where('appname',$packname)->value('id');
        if(!$package_id)return $this->ReturnJson->failFul(206);
        $share_strlog = Db::table('share_strlog')->select('uid')->where([['phone','=',$phone],['package_id','=',$package_id]])->first();
        if(!$share_strlog)return $this->ReturnJson->failFul();
        return $this->ReturnJson->successFul();
    }

    /**
     * 用户转换bonus领取
     * @return null
     */
    #[RequestMapping(path:'getConvertBonus')]
    public function getConvertBonus(){
        $uid = $this->request->post('uid');
        $sy_cash = Db::table('convert_bonus')->where('uid',$uid)->value('sy_cash');
        if(!$sy_cash || $sy_cash <= 0) return $this->ReturnJson->failFul(258);
        Db::beginTransaction();
        $res = User::userEditCoin($uid,$sy_cash,10,'用户-UID:'.$uid.'Bonus转换为Cash增加Cash'.bcdiv((string)$sy_cash,'100',2));
        if(!$res){
            Db::rollback();
            $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash增加Cash'.bcdiv((string)$sy_cash,'100',2).'失败');
            return $this->ReturnJson->failFul(249);
        }
        $res = Db::table('convert_bonus')->where('uid',$uid)->update(['sy_cash' => 0]);
        if(!$res){
            Db::rollback();
            $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash-convert_bonus数据表sy_cash修改失败:'.bcdiv((string)$sy_cash,'100',2));
            return $this->ReturnJson->failFul(249);
        }
        Db::commit();
        return $this->ReturnJson->successFul();
    }


    /**
     * bonus转换(打流水需求)
     * @return null
     */
    #[RequestMapping(path:'setConvertBonus')]
    public function setConvertBonus(){
        $uid = $this->request->post('uid');

        $userinfo = Db::table('userinfo')->select('bonus','now_bonus_score_water','need_bonus_score_water')->where('uid',$uid)->first();

        if($userinfo && $userinfo['bonus'] > 0 && $userinfo['need_bonus_score_water'] > 0 &&$userinfo['now_bonus_score_water'] >= $userinfo['need_bonus_score_water']){
            Db::beginTransaction();
            $res = User::userEditBonus($uid,bcsub('0',(string)$userinfo['bonus'],0),10,'用户-UID:'.$uid.'Bonus转换为Cash扣除Bonus'.bcdiv((string)$userinfo['bonus'],'100',2),3);
            if(!$res){
                $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash扣除Bonus'.bcdiv((string)$userinfo['bonus'],'100',2).'失败');
                Db::rollback();
                return $this->ReturnJson->successFul();
            }

            $res = User::userEditCoin($uid,(string)$userinfo['bonus'],10,'用户-UID:'.$uid.'Bonus转换为Cash增加Cash'.bcdiv((string)$userinfo['bonus'],'100',2));
            if(!$res){
                $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash增加Cash'.bcdiv((string)$userinfo['bonus'],'100',2).'失败');
                Db::rollback();
                return $this->ReturnJson->successFul();
            }

            //统计
            $res = Db::table('user_other')->where('uid',$uid)->update(['zh_cash' => Db::raw('zh_cash + '.$userinfo['bonus'])]);
            if(!$res){
                $odata = [
                    'uid' => $uid,
                    'zh_cash' => $userinfo['bonus'],
                ];
                $res = Db::table('user_other')->insert($odata);
                if(!$res){
                    $this->logger->error('用户-UID:'.$uid.'Bonus转换为Cash统计Cash'.bcdiv((string)$userinfo['bonus'],'100',2).'失败');
                    Db::rollback();
                    return $this->ReturnJson->successFul();
                }
            }

            Db::commit();

        }
        return $this->ReturnJson->successFul();
    }

    /**
     * @return void 获取用户类型
     * @param $uid 用户uid
     */
    public static function getUserType($uid){
        $share_strlog = Db::table('share_strlog')->selectRaw('puid,channel,af_status,package_id')->where('uid',$uid)->first();
        if(!$share_strlog)  return 2;
        if($share_strlog['af_status'] == 1){  //广告量
            return 1;
        }elseif (in_array($share_strlog['package_id'],[2])){ //分享量
            return 3;
        }else{ //自然量
            return 2;
        }

    }

    /**
     * 判断用户是否首次提现并返回金额
     * @param $uid
     * @return mixed
     */
    public static function getUserWithStatus($uid){
        $total_exchange = Db::table('userinfo')->where('uid',$uid)->value('total_exchange');
        return $total_exchange ?: 0;
    }

}

