<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\AutoController;
use App\Common\Common;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use App\Controller\slots\DealWithController;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\co;

#[AutoController]
class HomeController
{
    #[Inject]
    protected ReturnJsonController $returnJsonController;

    #[Inject]
    protected ValidatorFactoryInterface $validatorFactory;

    #[Inject]
    protected LoggerInterface $logger;

    #[Inject]
    protected DealWithController $DealWithController;
    /**
     * 用户信息
     * @param RequestInterface $request
     * @return null
     */
    public function userinfo(RequestInterface $request){
        $uid = $request->getAttribute('uid');

        //统一处理三方流水输赢情况
        $this->DealWithController->setUserData($uid);

        $userinfo = Db::table('userinfo')
            ->leftJoin('convert_bonus', 'userinfo.uid', '=', 'convert_bonus.uid')
            ->leftJoin('share_strlog', 'share_strlog.uid', '=', 'userinfo.uid')
            ->where('userinfo.uid',$uid)
            ->select('userinfo.coin','userinfo.bonus','convert_bonus.sy_cash','userinfo.channel','userinfo.package_id','userinfo.login_time',
            'userinfo.now_bonus_score_water','userinfo.need_bonus_score_water','share_strlog.status','userinfo.total_pay_score','userinfo.total_exchange')
            ->first();
        $userinfo['sy_cash'] = $userinfo['sy_cash'] ?: 0;
        if($userinfo && $userinfo['login_time'] < strtotime('00:00:00')){
            $chanelid = $userinfo['channel'];$package_id = $userinfo['package_id'];
            try{
                Db::table('userinfo')->where('uid',$uid)->update(['login_time' => time()]);
                Db::table('share_strlog')->where('uid',$uid)->update(['last_login_time' => $userinfo['login_time']]);
                Db::table('login_'.date('Ymd'))->updateOrInsert(
                    ['uid' => $uid],
                    ['channel' => $chanelid, 'package_id' => $package_id, 'createtime' => time()]
                );
            }catch (\Throwable $e){
                $this->logger->error('设置用户每日登录系统发送错误:'.$e->getMessage());
            }

        }

        return $this->returnJsonController->successFul(200,$userinfo);
    }

    /**
     * 首页banner
     * @return null
     */
    public function banner(RequestInterface $request){
        $packname = $request->getAttribute('packname');
        //判断走新包配置还是老包
        $special_package_ids = Common::getConfigValue('special_package_ids');
        $special_package_Array = $special_package_ids ? explode(',',$special_package_ids) : [];

        $package_id = Db::connection('readConfig')->table('apppackage')->where('appname',$packname)->value('id');
        if(in_array($package_id,$special_package_Array)){
            $is_new_package_where = [['end_type','=',2]];
        }else{
            $is_new_package_where = [['end_type','=',1]];
        }

        $list = Db::connection('readConfig')->table('banner')
            ->where('type',1)
            ->where('status',1)
            ->where('skin_type',$request->post('skin_type') ?? 1)
            ->where($is_new_package_where)
            ->select('title','image','url_type','url')
            ->orderBy('weight','desc')
            ->get()->toArray();

        if (!empty($list)){
            foreach ($list as $key=>$value) {
                $list[$key]['image'] = Common::domain_name_path((string)$value['image']);

                //签到判断
                if ($value['title'] == '7天签到'){
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
        return $this->returnJsonController->successFul(200,$list,1);
    }

    /**
     * 跑马灯
     * @param RequestInterface $request
     * @return null
     */
    public function notice(RequestInterface $request){
        $param = $request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'type' => 'required|in:1,2', //1移动端  2-pc端
            ],
            [
                'type.required' => 'type is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("公告接口数据验证失败===>".$errorMessage);
            return $this->returnJsonController->failFul(219,[]);
        }

        $list = Db::table('notice')->where(['status'=>1,'type'=>$param['type'],'delete_time'=>0])->select('title','content')->get()->toArray();
        $data = [];
        $data['notice'] = $list;
        /*$data['marquee'] = [
            '1*****09 अभी-अभी कैश निकाला गया 10000.0 INR',
            '5*****07 अभी-अभी कैश निकाला गया 8000.0 INR',
            '6*****88 अभी-अभी कैश निकाला गया 9000.0 INR',
            '3*****56 अभी-अभी कैश निकाला गया 10000.0 INR',
            '9*****33 अभी-अभी कैश निकाला गया 80000.0 INR',
        ];*/

        $r_money = [8000,9000,10000,20000,80000];
        //获奖名单
        $get_list = [];
        for ($i=0; $i<30; $i++){
            $k = mt_rand(2358, 9988);
            $e = mt_rand(101, 998);
            $get_list[] = $k . '******' . $e . ' अभी-अभी कैश निकाला गया ' . $r_money[array_rand($r_money)] . ' INR';
            /*$get_list[$i]['uid'] = $k . '******' . $e;
            $get_list[$i]['text'] = 'Acabou de saca';
            $get_list[$i]['money'] = $turntable_config['money'];
            $get_list[$i]['currency'] = '₹';*/
        }
        $data['marquee'] = $get_list;


        /*if ($param['type'] == 2){
            $data['marquee'] = [
                '1*****09 acabou de retirar 10000.0 BRL'
            ];
        }*/
        return $this->returnJsonController->successFul(200,$data);
    }

    public function winWith()
    {
        $data = [];
        $todayStartTimestamp = time() - 3600;//strtotime('today midnight'); // 今天开始的时间戳
        $todayEndTimestamp = time();//strtotime('tomorrow midnight') - 1; // 今天结束的时间戳

        $withdraw_log = Db::connection('readConfig')->table('withdraw_log as a')
            ->leftJoin('share_strlog as b','a.uid','=','b.uid')
            ->selectRaw('br_a.money,br_b.avatar')
            ->where('a.status',1)->where('a.money','>=',100000)
            ->whereBetween('finishtime', [$todayStartTimestamp, $todayEndTimestamp])//->whereDate('finishtime','=',date('Y-m-d'))
            ->get()->toArray();
//            ->toRawSql();
        if (!empty($withdraw_log)){
            foreach ($withdraw_log as $value){
                $avatar = !empty($value['avatar']) ? $value['avatar'] : config('avatar')[1];
                $data[] = [
                    'avatar' => Common::domain_name_path($avatar),
                    'type' => 1,
                    'text' => 'Withdraw cash',
                    'money' => $value['money'],
                ];
            }
        }

        //赢
        /*$game_win = Db::table('slots_log_'.date('Ymd').' as a')
            ->leftJoin('share_strlog as b','a.uid','=','b.uid')
            ->whereRaw('cashTransferAmount + bonusTransferAmount >= ?', [100000])
            ->whereBetween('a.createtime', [$todayStartTimestamp, $todayEndTimestamp])
            ->selectRaw('br_a.englishname,br_a.cashTransferAmount,br_a.bonusTransferAmount,br_b.avatar')
            ->get()->toArray();
        if (!empty($game_win)){
            foreach ($game_win as $gv){
                $avatar = !empty($gv['avatar']) ? $gv['avatar'] : config('avatar')[1];
                $data[] = [
                    'avatar' => Common::domain_name_path($avatar),
                    'type' => 2,
                    'text' => $gv['englishname'],
                    'money' => $gv['cashTransferAmount'] + $gv['bonusTransferAmount'],
                ];
            }
        }*/

        //假
        $count = 20;
        $data_count = count($data);
        $totalCount = $count - $data_count;
        $type1Count = round($totalCount * 0.25); // 计算 type=1 元素的数量
        $type2Count = $totalCount - $type1Count; // 计算 type=2 元素的数量

        //退款数量
        $type1Count1 = round($type1Count * 0.5);
        $type1Count2 = round($type1Count * 0.3);
        $type1Count3 = round($type1Count * 0.15);
        //$type1Count4 = ($type1Count - $type1Count1 - $type1Count2 - $type1Count3) ?? 0;

        //win数量
        $type2Count1 = round($type2Count * 0.5);
        $type2Count2 = round($type2Count * 0.3);
        $type2Count3 = round($type2Count * 0.15);
        //$type2Count4 = ($type2Count - $type2Count1 - $type2Count2 - $type2Count3) ?? 0;

        if ($data_count < $count){
            //$r_money = [10,500];
            $r_game = ['TeenPatti','Wheel Of Fortune','Dranon Tiger','Lucky Dice','Jhandi Munda','Lucky Ball','3 Patti Bet','Andar Bahar','Mine','Mines','Mines2','Blastx',
                'Aviator','Aviator','Aviator','Aviator','Aviator','Aviator','Aviator','Aviator','Aviator','Aviator','Aviator',
                'Dranon Tiger','Dranon Tiger','Dranon Tiger','Dranon Tiger','Dranon Tiger','Dranon Tiger','Dranon Tiger','Dranon Tiger'];

            for ($i=0; $i < $totalCount; $i++){
                //$money = mt_rand($r_money[0], $r_money[1]) * 10000;

                if ($i < $type1Count){ //退款
                    if ($i < $type1Count1){
                        $money = mt_rand(1000, 2000);
                    }elseif ($i < ($type1Count1 + $type1Count2)){
                        $money = mt_rand(2000, 5000);
                    }elseif ($i < ($type1Count1 + $type1Count2 + $type1Count3)){
                        $money = mt_rand(5000, 10000);
                    }else{
                        $money = mt_rand(10000, 20000);
                    }

                    $data[] = [
                        'avatar' => Common::domain_name_path($this->getAvatar()),
                        'type' => 1,
                        'text' => 'Withdraw cash',
                        'money' => bcdiv((string)$money,'10') * 1000,
                    ];

                }else{//win
                    if ($i < ($type2Count1 + $type1Count)){
                        $money = mt_rand(2000, 3000);
                    }elseif ($i < ($type2Count1 + $type2Count2 + $type1Count)){
                        $money = mt_rand(3000, 5000);
                    }elseif ($i < ($type2Count1 + $type2Count2 + $type2Count3 + $type1Count)){
                        $money = mt_rand(5000, 10000);
                    }else{
                        $money = mt_rand(10000, 20000);
                    }

                    $data[] = [
                        'avatar' => Common::domain_name_path($this->getAvatar()),
                        'type' => 2,
                        'text' => $r_game[array_rand($r_game)],
                        'money' => bcdiv((string)$money,'10') * 1000,
                    ];
                }
            }
        }
        shuffle($data);

        return $this->returnJsonController->successFul(200,$data, 1);
    }

    private function getAvatar(){
        $key = rand(1,16);
        return config('avatar')[$key] ?? config('avatar')[1];
    }

    /**
     * 首页游戏列表
     * @param RequestInterface $request
     * @return null
     */
    public function gameList(RequestInterface $request){
        $param = $request->all();
        /*$validator = $this->validatorFactory->make(
            $param,
            [
                'type' => 'required|in:1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18', //1-Quente  2-PG SOFT 3-Originais 4-TADA 5-Pragmatic Play 6-Recomendados 7-Live Casino 8-Todos Os(slots) 9-spin 17-收藏列表 18-全部
            ],
            [
                'type.required' => 'type is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("游戏列表数据验证失败===>".$errorMessage);
            return $this->returnJsonController->failFul(219,[]);
        }*/

        $uid = isset($param['uid']) && !empty($param['uid']) ? $param['uid'] : 0;
        $limit = isset($param['page_size']) ? $param['page_size'] : 18;

        $type = isset($param['type']) ? $param['type'] : 0;
        $where = [];
        $where2 = [];
        $where['status'] = 1;
        $orderBy = 'weight';
        switch ($type){
            case 1://热门
                $where['hot'] = 1;
                break;
            case 2://pg
                $where['terrace_id'] = 1;
                $where2 = [['slotsgameid','<>','111111']];
                break;
            case 3://
                $where['type'] = 4;
                break;
            case 4:
                $where['terrace_id'] = 3;
                break;
            case 5:
                $where['terrace_id'] = 2;
                break;
            case 6://推荐
                $where['recommend'] = 1;
                break;
            case 7://真人
                $where['type'] = 2;
                break;
            case 8:
                $where['type'] = 1;
                break;
            case 9:
                $where['type'] = 3;
                break;
            case 10:
                $where['type'] = 5;
                break;
            case 11:
                $where['type'] = 6;
                break;
            case 12:
                $where['type'] = 7;
                $where2 = [['slotsgameid','<>','222222']];
                break;
            case 13:
                $where['type'] = 8;
                break;
            case 14:
                $where['type'] = 9;
                break;
            case 15://自研
                $where['terrace_id'] = 8;
                break;
            case 16:
                $where['terrace_id'] = 4;
                break;
            case 17:
                $where['b.uid'] = $uid;
                $orderBy = 'b.water';
                break;
            case 18:
                break;
            case 20:
                $where['type'] = 20;
                break;
            default:
                break;
        }

        //厂商分类使用
        if (isset($param['terrace_id']) && $param['terrace_id'] > 0){
            $where = [];
            $where['status'] = 1;
            $where['terrace_id'] = $param['terrace_id'];
            if ($where['terrace_id'] == 9){
                $where2 = [['slotsgameid','<>','999999']];
            }elseif ($where['terrace_id'] == 7){
                $where2 = [['slotsgameid','<>','222222']];
            }elseif ($where['terrace_id'] == 1){
                $where2 = [['slotsgameid','<>','111111']];
            }elseif ($where['terrace_id'] == 12){
                $where2 = [['slotsgameid','<>','12121212']];
            }elseif ($where['terrace_id'] == 14){
                $where2 = [['slotsgameid','<>','14141414']];
            }elseif ($where['terrace_id'] == 17){
                $where2 = [['slotsgameid','<>','17171717']];
            }
        }

        $list = Db::connection('readConfig')->table('slots_game as a')
            /*->leftJoin('game_collect as b', function ($join) use ($uid) {
                $join->on('a.id', '=', 'b.game_id')
                    ->where('b.uid', '=', $uid);
            })*/
            ->where($where)
            ->where($where2)
            //->where('b.uid',$uid)
            ->select("englishname","slotsgameid","maintain_status","image","a.id as game_id","free","terrace_id","min_money","a.type","a.jump_type","a.url",
                "a.orientation") //IFNULL(CONCAT('https://', image),'') as
//            ->orderBy('weight','desc')
            ->orderBy($orderBy,'desc')
            ->orderBy('a.id','desc')
            ->paginate((int)$limit);
//            ->toRawSql();
//        var_dump($list);
        $tmp_list = $list->items();
        if (!empty($tmp_list)){
            foreach ($tmp_list as &$value){
                $value['is_self'] = false;
                $value['image'] = Common::domain_name_path((string)$value['image']);
                if ($value['terrace_id'] == 8){
                    $value['is_self'] = true;
                }
                /*if (!empty($value['is_collect'])){
                    $value['is_collect'] = true;
                }else{*/
                    $value['is_collect'] = false;
                //}
            }
        }

        $data = [];
        $data['games'] = $tmp_list;
        $data['totalNum'] = $list->total();

        return $this->returnJsonController->successFul(200,$data, 1);

    }

    /**
     * 首页游戏模版
     * @param RequestInterface $request
     * @return null
     */
    public function homeGame(RequestInterface $request){
        $param = $request->all();
        $uid = isset($param['uid']) && !empty($param['uid']) ? $param['uid'] : 0;

        $home_list = Db::connection('readConfig')->table('home_game')
            ->select('name','tag','icon','type','terrace_id')
            ->where('status',1)
            ->where('skin_type',1)
            ->orderBy('weight','desc')
            ->orderBy('id','desc')
            ->get()->toArray();
        if (!empty($home_list)){
            foreach ($home_list as &$value){
                $value['icon'] = Common::domain_name_path((string)$value['icon']);
                [$where, $where2] = $this->getHomeWhere($value);
                $list = Db::table('slots_game as a')
                    /*->leftJoin('game_collect as b', function ($join) use ($uid) {
                        $join->on('a.id', '=', 'b.game_id')
                            ->where('b.uid', '=', $uid);
                    })*/
                    ->where($where)
                    ->where($where2)
                    ->select("englishname","slotsgameid","maintain_status","image","a.id as game_id","free","terrace_id","min_money","a.type","a.jump_type","a.url",
                        "a.orientation")//"b.id as is_collect")
                    ->orderBy('weight','desc')
                    ->orderBy('a.id','desc')
                    //->paginate(18);
                    ->get()->toArray();

                $tmp_list = $list;//->items();
                if (!empty($tmp_list)){
                    foreach ($tmp_list as &$t_value){
                        $t_value['is_self'] = false;
                        $t_value['image'] = Common::domain_name_path((string)$t_value['image']);
                        if ($t_value['terrace_id'] == 8){
                            $t_value['is_self'] = true;
                        }
                        /*if (!empty($t_value['is_collect'])){
                            $t_value['is_collect'] = true;
                        }else{*/
                            $t_value['is_collect'] = false;
//                        }
                    }
                }

                $value['game_list'] = $tmp_list;
                $value['game_count'] = count($tmp_list);
            }
        }

        return $this->returnJsonController->successFul(200, $home_list, 1);
    }



    /**
     * 新皮首页游戏模版
     * @return null
     */
    public function newHomeGame(){

        $home_list = Db::connection('readConfig')->table('home_game')
            ->select('name','tag','icon','type','click_icon','skin_type','id')
            ->where('status',1)
            ->where('skin_type',2)
            ->orderBy('weight','desc')
            ->orderBy('id','desc')
            ->get()->toArray();
        if (!empty($home_list)){
            foreach ($home_list as &$value){
                $value['icon'] = Common::domain_name_path((string)$value['icon']);
                $value['click_icon'] = Common::domain_name_path((string)$value['click_icon']);
                $value['more_status'] = 1; //是否有更多
                if($value['type'] == 1){
                    $list = Db::connection('readConfig')
                        ->table('slots_game')
                        ->select("englishname","slotsgameid","maintain_status","image2 as image","id as game_id","free","terrace_id","min_money","type","jump_type","url", "orientation")
                        ->where('hot',1)
                        ->where('status',1)
                        ->orderBy('weight','desc')
                        ->orderBy('id','desc')
                        ->get()
                        ->toArray();
                }else{
                    $list = Db::connection('readConfig')
                        ->table('slots_terrace_type')
                        ->selectRaw("terrace_id,image,icon,name,jump_type")
                        ->where(['status' => 1])
                        ->where(['home_game_id' => $value['id']])
                        ->orderBy('weight','desc')
                        ->orderBy('id','desc')
                        ->get()
                        ->toArray();
                }

                if ($list){
                    $count = 0;
                    foreach ($list as &$t_value){
                        $t_value['is_self'] = false;
                        $t_value['image'] = Common::domain_name_path((string)$t_value['image']);
                        if($value['type'] != 1)$t_value['icon'] = Common::domain_name_path((string)$t_value['icon']);
                        if ($t_value['terrace_id'] == 8){
                            $t_value['is_self'] = true;
                        }
                        $t_value['is_collect'] = false;
                        if($t_value['jump_type'] == 0)$count ++ ;
                    }
                    $value['more_status'] = $count > 0 ? 1 : 0;
                }

                $value['game_list'] = $list;
                $value['game_count'] = count($list);
            }
        }
//        $this->logger->error('$home_list'.json_encode($home_list));
        return $this->returnJsonController->successFul(200, $home_list, 1);
    }


    /**
     * 首页游戏列表
     * @param RequestInterface $request
     * @return null
     */
    public function newGameList(RequestInterface $request){
        $is_hot = $request->post('is_hot') ?? 0;
        $terrace_id = $request->post('terrace_id') ?? 0;
        $home_game_type = $request->post('home_game_type') ?? 0;
        $game_name = $request->post('game_name') ?? '';
        $page = $request->post('page') ?? 1;


        $where = [['status','=',1]];
        switch ($home_game_type){
            case 7://真人
                $where[] = ['type' ,'=',2];
                break;
            case 12://捕鱼
                $where[] = ['type' ,'=',7];
                break;
            case 3://区块链
                $where[] = ['type' ,'=',4];
                break;
            case 13://体育
                $where[] = ['type' ,'=',8];
                break;
            case 8://Slots
                $where[] = ['type' ,'=',1];
                break;
            default:
                break;
        }
        if($is_hot == 1)$where[] = ['hot' ,'=',1];
        if($terrace_id)$where[] = ['terrace_id' ,'=',$terrace_id];
        if($game_name)$where[] = ['englishname' ,'like','%'.$game_name.'%'];
        $listArray = Db::connection('readConfig')
            ->table('slots_game')
            ->select("englishname","slotsgameid","maintain_status","image2 as image","id as game_id","free","terrace_id","min_money","type","jump_type","url",
                "orientation")
            ->where($where)
            ->whereNotIn('jump_type',[1,2]) //jump_type : 跳转类型 0-游戏 1-分类 2-厂商 3-链接,4=启动三方游戏大厅
            ->orderBy('weight','desc')
            ->paginate(30, ['*'], 'page', (int)$page);

        $list = $listArray->items();
        if ($list) foreach ($list as &$value){
            $value['is_self'] = false;
            $value['image'] = Common::domain_name_path((string)$value['image']);
            if ($value['terrace_id'] == 8){
                $value['is_self'] = true;
            }
            $value['is_collect'] = false;
        }


        $data = [];
        $data['games'] = $list;
        $data['totalNum'] = $listArray->total();
        return $this->returnJsonController->successFul(200,$data);

    }

    /**
     * 获取首页游戏模版游戏查询条件
     * @param $data
     * @return array|array[]
     */
    public function getHomeWhere($data = [])
    {
        if (empty($data)) return [[],[]];

        $where = [];
        $where2 = [];
        $where['status'] = 1;
        switch ($data['type']){
            case 1://热门
                $where['hot'] = 1;
                break;
            case 6://推荐
                $where['recommend'] = 1;
                break;
            case 7://真人
                $where['type'] = 2;
                break;
            case 12://捕鱼
                $where['type'] = 7;
                $where2 = [['slotsgameid','<>','222222']];
                break;
            case 3://区块链
                $where['type'] = 4;
                break;
            default:
                break;
        }

        //厂商分类使用
        if (isset($data['terrace_id']) && $data['terrace_id'] > 0){
            $where = [];
            $where['status'] = 1;
            $where['terrace_id'] = $data['terrace_id'];
            if ($where['terrace_id'] == 9){
                $where2 = [['slotsgameid','<>','999999']];
            }elseif ($where['terrace_id'] == 7){
                $where2 = [['slotsgameid','<>','222222']];
            }elseif ($where['terrace_id'] == 1){
                $where2 = [['slotsgameid','<>','111111']];
            }elseif ($where['terrace_id'] == 12){
                $where2 = [['slotsgameid','<>','12121212']];
            }elseif ($where['terrace_id'] == 14){
                $where2 = [['slotsgameid','<>','14141414']];
            }elseif ($where['terrace_id'] == 17){
                $where2 = [['slotsgameid','<>','17171717']];
            }
        }

        return [$where, $where2];
    }

    /**
     * 关键词搜索游戏
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function look(RequestInterface $request){
        $param = $request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'keyword' => 'required',
            ],
            [
                'keyword.required' => 'keyword is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("游戏检索数据验证失败===>".$errorMessage);
            return $this->returnJsonController->failFul(219);
        }

        $keyword = $param['keyword'];
        $uid = isset($param['uid']) && !empty($param['uid']) ? $param['uid'] : 0;
        $limit = isset($param['page_size']) ? $param['page_size'] : 18;

        $list = Db::connection('readConfig')->table('slots_game as a')
            ->leftJoin('game_collect as b', function ($join) use ($uid) {
                $join->on('a.id', '=', 'b.game_id')
                    ->where('b.uid', '=', $uid);
            })
            ->where('englishname','like',"%$keyword%")
            ->where('status',1)
            ->select("englishname","slotsgameid","maintain_status","image","a.id as game_id","free","terrace_id","min_money","a.jump_type","a.url","a.type",
                "a.orientation",
                "b.id as is_collect")
            ->orderBy('weight','desc')
            ->orderBy('a.id','desc')
            ->paginate((int)$limit);

        $tmp_list = $list->items();

        if (!empty($tmp_list)){
            foreach ($tmp_list as $key=>&$value){
                $value['is_self'] = false;
                $value['image'] = Common::domain_name_path((string)$value['image']);
                if ($value['terrace_id'] == 8){
                    $value['is_self'] = true;
                }
                if (!empty($value['is_collect'])){
                    $value['is_collect'] = true;
                }else{
                    $value['is_collect'] = false;
                }
            }
        }

        $data = [];
        $data['games'] = $tmp_list;
        $data['totalNum'] = $list->total();

        return $this->returnJsonController->successFul(200,$data,1);
    }


    /**
     * 游戏厂商
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function terrace(){
        $list = Db::connection('readConfig')->table('slots_terrace')
            ->where('status',1)
            ->whereIn('show_type',[1,3])
            ->select('id as terrace_id','name','image','type','images')
            ->orderBy('weight','desc')
            ->orderBy('id','desc')
            ->get()->toArray();
        if (!empty($list)){
            foreach ($list as $key=>$value){
                $list[$key]['image'] = Common::domain_name_path((string)$value['image']);
                $list[$key]['images'] = Common::domain_name_path((string)$value['images']);
            }
        }

        return $this->returnJsonController->successFul(200,$list);
    }

    /**
     * 社区联系账号
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function contact(){
        $list = Db::table('contact')
            ->where('status',1)
            ->select('name','icon','contact','url')
            ->orderBy('sort','desc')
            ->orderBy('id','desc')
            ->get()->toArray();
        if (!empty($list)){
            foreach ($list as $key=>$value){
                $list[$key]['icon'] = Common::domain_name_path((string)$value['icon']);
            }
        }

        return $this->returnJsonController->successFul(200,$list);
    }

    /**
     * 消息列表
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function message(RequestInterface $request){
        $param = $request->all();
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
            $this->logger->error("消息列表数据验证失败===>".$errorMessage);
            return $this->returnJsonController->failFul(219);
        }

        $uid = $param['uid'];
        $limit = isset($param['page_size']) ? $param['page_size'] : 18;
        $user_information = Db::connection('readConfig')->table('user_information')->where(['uid' => $param['uid'],'is_red' => 0])->value('id');
        $user_sysmation = Db::connection('readConfig')->table('user_information as a')
            ->leftJoin('user_sysmation as b', function ($join) use ($uid) {
                $join->on('a.id', '=', 'b.information_id')
                    ->where('b.uid', '=', $uid); // 额外条件
            })
            ->where(['a.uid' => 0,'a.type'=>1])
            ->get();
        $is_sys = 1;
        if (!empty($user_sysmation)){
            foreach ($user_sysmation as $key=>$value){
                if (empty($value['id'])) $is_sys = 0;
            }
        }
        $data['is_red'] = 1;
        if($user_information || $is_sys == 0)$data['is_red'] = 0;
        $list = Db::connection('readConfig')->table('user_information as a')
            ->leftJoin('user_sysmation as b', function ($join) use ($uid) {
                $join->on('a.id', '=', 'b.information_id')
                    ->where('b.uid', '=', $uid); // 额外条件
            })
            ->selectRaw("br_a.id,br_a.title,br_a.content,FROM_UNIXTIME(br_a.createtime,'%Y-%m-%d %H:%i:%s') as createtime,br_a.is_red,br_a.type,
            br_b.id as sys_id")
            ->where('a.uid',$param['uid'])
            ->orWhere('a.type',1)
            ->orderBy('a.createtime','desc')
            ->paginate((int)$limit);
        $data_list = $list->items();
        if (!empty($data_list)){
            foreach ($data_list as &$v){
                if ($v['type'] == 1 && !empty($v['sys_id'])){
                    $v['is_red'] = 1;
                }elseif ($v['type'] == 1 && empty($v['sys_id'])){
                    $v['is_red'] = 0;
                }
            }
        }
        $data['data'] = $data_list;
        return $this->returnJsonController->successFul(200,$data,1);
    }


    /**
     * 修改消息已读状态
     * @param Request $request
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function messageeditstatus(RequestInterface $request){
        $param = $request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
                'id' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
                'id.required' => 'id is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("消息列表数据验证失败===>".$errorMessage);
            return $this->returnJsonController->failFul(219);
        }

        $information = Db::table('user_information')->where(['id' => $param['id']])->first();
        if ($information['type'] == 1){
            $sysmation = Db::table('user_sysmation')->where(['uid'=>$param['uid'],'information_id' => $param['id']])->first();
            if (empty($sysmation)){
                Db::table('user_sysmation')->insert(['uid' => $param['uid'], 'information_id' => $param['id'], 'createtime' => time()]);
            }
        }else {
            Db::table('user_information')->where(['uid' => $param['uid'], 'id' => $param['id']])->update(['is_red' => 1]);
        }

        return $this->returnJsonController->successFul();
    }



    /**
     * 游戏收藏
     * @param RequestInterface $request
     * @return null
     */
    public function collect(RequestInterface $request){
        $param = $request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'uid' => 'required',
                'game_id' => 'required',
            ],
            [
                'uid.required' => 'uid is required',
                'game_id.required' => 'game_id is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("收藏数据验证失败===>".$errorMessage);
            return $this->returnJsonController->failFul(219);
        }

        $collect = Db::table('game_collect')->where('uid',$param['uid'])->where('game_id',$param['game_id'])->first();

        try {
            $data = [];
            $data['uid'] = $param['uid'];
            $data['game_id'] = $param['game_id'];
            $data['create_time'] = time();
            if (empty($collect)) {
                Db::table('game_collect')->insert($data);
            }else{
                Db::table('game_collect')->where('uid',$param['uid'])->where('game_id',$param['game_id'])->delete();
            }

            return $this->returnJsonController->successFul(200,$data,1);
        }catch (\Throwable $ex) {
            $this->logger->error("错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->returnJsonController->failFul();
        }

    }

}
