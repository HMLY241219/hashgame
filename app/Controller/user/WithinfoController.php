<?php
namespace App\Controller\user;

use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use App\Common\Common;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
/**
 *  用户提现信息
 */
#[Controller(prefix:'user.Withinfo')]
class WithinfoController extends AbstractController {

    #[RequestMapping(path:'delete')]
    public function delete(){
        $uid = $this->request->post('uid');
        $user_withinfo_id = $this->request->post('user_withinfo_id');
        $res = Db::table('user_withinfo')->where(['uid' => $uid,'id' => $user_withinfo_id])->delete();
        if(!$res){
            return $this->ReturnJson->failFul();
        }
        return $this->ReturnJson->successFul();
    }

    /**
     * @return void 用户提现银行卡UIP列表
     */
    public function index(){
        $type = $this->request->post('type') ?: 1;//类型:1=银行账户,2=upi
        $uid = $this->request->post('uid');
        $user_withinfo = Db::table('user_withinfo')->where([['uid' ,'=', $uid],['type' ,'=',$type]])->first();
//        return json(['code' => 200 ,'msg'=>'','data' =>$user_withinfo ]);
        return $this->ReturnJson->successFul(200, $user_withinfo);
    }


    /**
     * 用户提现银行卡UIP添加与修改
     * @return void
     */
    #[RequestMapping(path:'add')]
    public function add(){
        $type = $this->request->post('type') ?: 1; //类型:1=银行卡,2=UPI,3=钱包,4=数字货币
        $uid = $this->request->post('uid');
        $wallet_address_type = $this->request->post('wallet_address_type') ?? 1;//1=user_wallet_address,2=pay_wallet_address,3=withdraw_wallet_address
        if(in_array($type,[3,4])){
            $wallet_address_id = match ((int)$wallet_address_type){
                1 => $this->addUserWalletAddress($uid,$type),
                2 => $this->addPayAndWtihdrawWalletAddress('pay_wallet_address',$uid), //充值钱包
                3 => $this->addPayAndWtihdrawWalletAddress('withdraw_wallet_address',$uid,2), //退款钱包
            };
            if ($wallet_address_id === false) {
                return $this->ReturnJson->failFul(3018);
            } else {
                return $this->ReturnJson->successFul(200,$wallet_address_id);
            }
        }

        $account = trim(base64_decode($this->request->post('account')));  //账户信息
        $backname = trim(base64_decode($this->request->post('backname')));  //	银行昵称/up昵称
        $id = $this->request->post('id') ?? 0;  //	退款ID
        $ifsccode = '';
        $phone = '';
        $email = '';
        if($type == 1){
            $ifsccode = strtoupper(str_replace(' ', '', trim(base64_decode($this->request->post('ifsccode'))))); //银行代码
            $phone = $this->request->post('phone');  //	手机号
            $email =  trim(base64_decode($this->request->post('email')));  //	邮箱
            if (!Common::PregMatch($phone,'phone'))return $this->ReturnJson->failFul(207);
            if (!Common::PregMatch($email,'email'))return $this->ReturnJson->failFul(232);
            if (!Common::PregMatch($ifsccode,'ifsc'))return $this->ReturnJson->failFul(259);
        }

        if(!$account) return $this->ReturnJson->failFul();

        // 如果字符串全部由数字组成则返回 true，否则返回 false
        $res = ctype_digit($account);
        if(!$res)return $this->ReturnJson->failFul(274);



        // 如果字符串全部由字母组成则返回 true，否则返回 false
        $res = ctype_alpha(str_replace(' ', '', $backname));
        if(!$res)return $this->ReturnJson->failFul(275);

        $user_withinfo = Db::table('user_withinfo')->where([['uid' ,'=', $uid],['type' ,'=',$type]])->orderBy('id','desc')->first();
        if($user_withinfo){  //修改
            $data = [
                'account' => $account,
                'backname' => $backname,
                'ifsccode' => $ifsccode,
                'phone' => $phone,
                'email' => $email,
                'updatetime' => time(),
            ];
            $res = Db::table('user_withinfo')->where('id',$user_withinfo['id'])->update($data);
            if(!$res) return $this->ReturnJson->failFul(237);
            $user_withinfo_id = $user_withinfo['id'];
        }else{  //添加

            $data = [
                'uid' => $uid,
                'account' => $account,
                'backname' => $backname,
                'ifsccode' => $ifsccode,
                'type' => $type,
                'phone' => $phone,
                'email' => $email,
                'status' => 1,
                'createtime' => time(),
            ];

            $user_withinfo_id = Db::table('user_withinfo')->insertGetId($data);
            if(!$user_withinfo_id) return $this->ReturnJson->failFul(238);

        }


        self::editstatus($user_withinfo_id,$uid);
//        return json(['code' => 200 ,'msg'=>'success','data' =>$data ]);
        return $this->ReturnJson->successFul(200,$user_withinfo_id);
    }



    /**
     * 修改用户提现信息默认值
     * @return void
     */
    public static function editstatus($id,$uid){

        //判断前端提交的数据是否正确
        $user_withinfo = Db::table('user_withinfo')->where([['id' ,'=', $id],['uid' ,'=',$uid]])->first();
        if(!$user_withinfo){
            return true;
        }

        Db::beginTransaction();
        $res = Db::table('user_withinfo')->where([['uid' ,'=' , $uid],['id' ,'<>',$id]])->first();
        if($res){
            $res = Db::table('user_withinfo')->where([['uid' ,'=' , $uid],['id' ,'<>',$id]])->update(['status'=> '-1','updatetime'=>time()]);
            if(!$res){
                Db::rollback();
                return false;
            }
        }
        $res = Db::table('user_withinfo')->where('id','=',$id)->update(['status' => 1,'updatetime'=>time()]);
        if(!$res){
            Db::rollback();
            return false;
        }

        Db::commit();
//        return json(['code' => 200 ,'msg'=>'success','data' =>[] ]);
        return true;
    }



    public function addUserWalletAddress($uid,$type){
        $address = trim($this->request->post('address'));
        $protocol_name = $this->request->post('protocol_name') ?? '';

        $wallet_address = Db::table('user_wallet_address')->where(['address' => $address])->first();
        if($wallet_address){
//            Db::table('user_wallet_address')->where(['id' => $wallet_address['id']])->update(['address' => $address,'protocol_name' => $protocol_name]);
//            $wallet_address_id = $wallet_address['id'];
            return false;
        }else{
            $wallet_address_id = Db::table('user_wallet_address')->insertGetId(['uid' => $uid,'type' =>$type,'address' => $address,'protocol_name' => $protocol_name]);

        }
        return $wallet_address_id;
    }


    /**
     * 添加充值与退款的钱包地址
     * @param string $table  钱包表
     * @param string|int $uid  用户ID
     * @param int $type 类型 1：用钱包地址判断是否能继续添加 , 2= 根据用户加钱包地址判断是否添加还是修改
     * @return false|int
     */
    public function addPayAndWtihdrawWalletAddress(string $table,string|int $uid,int $type = 1){
        $address = trim($this->request->post('address'));
        $protocol_name = $this->request->post('protocol_name') ?? '';

        $wallet_address = Db::table($table)->where(['uid' => $uid])->first();

        if($wallet_address){
            if($type == 1){
                $wallet_address = Db::table($table)->where(['address' => $address])->value('uid');
                if($wallet_address)return false;
            }
            Db::table($table)->where(['id' => $wallet_address['id']])->update(['address' => $address,'protocol_name' => $protocol_name]);
            $wallet_address_id = $wallet_address['id'];
        }else{
            $wallet_address_id = Db::table($table)->insertGetId(['uid' => $uid,'address' => $address,'protocol_name' => $protocol_name]);
        }
        return $wallet_address_id;
    }
}

