<?php
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\slots;

use App\Common\Common;
use App\Controller\slots\Common as slotsCommon;
use App\Common\Curl;
use App\Common\Guzzle;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use function Hyperf\Config\config;
use App\Controller\ReturnJsonController;
use function Hyperf\Support\env;

#[Controller(prefix:"zyslots")]
class ZyslotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Curl $curl;

    #[Inject]
    protected slotsCommon $slotsCommon;

    #[Inject]
    protected ReturnJsonController $returnJsonController;



    /**
     * 进入游戏获取余额
     * @return false|string
     */
    #[RequestMapping(path: 'GetBalance')]
    public function GetBalance(){
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
            $this->logger->error("zy取得余额接口数据验证失败===>".$errorMessage);
            return $this->returnJsonController->failFul(219);
        }

        $uid = $param['uid'];
        $userinfo = Db::table('user_token as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->where('a.uid',$uid)
            ->selectRaw('br_a.uid,br_a.token,br_b.channel,br_b.package_id,
            IFNULL(br_b.coin,0) AS coin,IFNULL(br_b.bonus,0) AS bonus,IFNULL(br_b.now_cash_score_water,0) AS now_cash_score_water,
            IFNULL(br_b.now_bonus_score_water,0) AS now_bonus_score_water,IFNULL(br_b.total_pay_score,0) AS total_pay_score,
            IFNULL(br_b.total_give_score,0) AS total_give_score,IFNULL(br_b.total_exchange,0) AS total_exchange,
            IFNULL(br_b.self_cash_water_score,0) AS self_cash_water_score,IFNULL(br_b.self_bonus_water_score,0) AS self_bonus_water_score')
            ->first();
//            ->toRawSql();
        //var_dump($userinfo);
        if (empty($userinfo)){
            $this->logger->error("zy获取余额token UID无效");
            return $this->returnJsonController->failFul(216);
        }
        //bonus不带入
        $userinfo['bonus'] = config('slots.is_zy_carry_bonus') == 1 ? $userinfo['bonus'] : 0;

        //三方游戏流水
        $third_water_score = bcsub(bcadd((string)$userinfo['now_cash_score_water'],(string)$userinfo['now_bonus_score_water']),bcadd((string)$userinfo['self_cash_water_score'],(string)$userinfo['self_bonus_water_score']));
        $third_water_score =  $third_water_score > 0 ? $third_water_score : 0;

        try {
            //修改redis
            /*$Redis = Common::Redis('Redis5501');
            $new_user = $Redis->hGet('php_login_1_1', (string)$userinfo['uid']);
            if (!$new_user){//第一次进入自研
                $Redis->hSet('new_user', (string)$userinfo['uid'], '1');
            }

            $Redis->hSet('php_login_1_1', (string)$userinfo['uid'], (string)$userinfo['token']);

            $Redis->hSet('TEXAS_ACCOUNT_1_1', (string)$userinfo['uid'], (string)$userinfo['uid']);

            $Redis5502 = Common::Redis('Redis5502');
            $data = [
                'coins' => $userinfo['coin'],
                'bonus' => $userinfo['bonus'],
                'exchange' => $userinfo['total_exchange'],
                'give' => $userinfo['total_give_score'],
                'outside_score' => $third_water_score,
                'pay' => $userinfo['total_pay_score'],
                'channel' => $userinfo['channel'],
                'package_id' => $userinfo['package_id'],
            ];

            $Redis5502->hMSet('user_'.$userinfo['uid'], $data);
            $Redis5502->expire('user_'.$userinfo['uid'], 3600*24*5);*/

            //测试中转
            $url = 'http://1.13.81.132:5009/api/slots.Zyslots/getBalance';
            $data = [
                'uid' => $userinfo['uid'],
                'token' => $userinfo['token'],
                'coins' => $userinfo['coin'],
                'bonus' => $userinfo['bonus'],
                'exchange' => $userinfo['total_exchange'],
                'give' => $userinfo['total_give_score'],
                'outside_score' => $third_water_score,
                'pay' => $userinfo['total_pay_score'],
                'channel' => $userinfo['channel'],
                'package_id' => $userinfo['package_id'],
            ];
            //$res = self::senPostCurl($url, $data);
            $res = $this->guzzle->post($url, $data, self::$herder);
            if (!isset($res['code'])){
                $this->logger->error('zy进入游戏失败：'.$res);
                return $this->returnJsonController->failFul(224);
            }

            return $this->returnJsonController->successFul(200,$data,1);
        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("zy，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->returnJsonController->failFul(219);
        }

    }

    /**
     * 退出游戏修改余额
     * @return null
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    #[RequestMapping(path: 'setBalance')]
    public function setBalance(){
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
            $this->logger->error("zy退出设置余额接口数据验证失败===>".$errorMessage);
            return $this->returnJsonController->failFul(219);
        }

        /*$Redis5502 = Common::Redis('Redis5502');
        $userinfo = $Redis5502->hGetAll('user_'.$param['uid']);*/

        //str测试使用
        /*$url = 'http://1.13.81.132:5009/api/slots.Zyslots/setBalance';
        $data = [
            'uid' => $param['uid']
        ];
        $res = $this->guzzle->post($url, $data, self::$herder);
        if (!isset($res['code'])){
            $this->logger->error('zy进入游戏失败：'.$res);
            return $this->returnJsonController->failFul(224);
        }
        $userinfo = $res['data'];

        //end

        Db::table('userinfo')->where('uid',$param['uid'])->update([
            'coin' => $userinfo['coins'] ?: 0,
            'bonus' => $userinfo['bonus'] ?: 0,
            'total_give_score' => $userinfo['give'] ?: 0,
        ]);*/

        $userinfo = [];//临时
        return $this->returnJsonController->successFul(200,$userinfo,1);
    }

    #[RequestMapping(path: 'logSet')]
    public function logSet(){
        /*$redis5502 = Common::Redis('Redis5502');
        $time = $redis5502->get('zy_log_time');
        $time = $time ?: 0;*/

        $game_list = Db::connection('readConfig')->table('slots_game')->where('terrace_id',8)->get()->toArray();
        $game_list_new = [];
        if (!empty($game_list)){
            foreach ($game_list as $gk=>$gv){
                $game_list_new[$gv['slotsgameid']] = $gv;
            }
        }

        //$list = Db::connection('lzmj')->table('game_records_'.date('Ymd'))->where('time_stamp','>',$time)->where('is_android',0)->get()->toArray();
        $list = Db::connection('lzmj')->table('game_records_'.date('Ymd'))->where('is_set',0)->where('is_android',0)->get()->toArray();
        if (!empty($list)){
            $ids = array_column($list,'id');
            Db::connection('lzmj')->table('game_records_'.date('Ymd'))->whereIn('id',$ids)->update(['is_set'=>1]);

            //$url = 'http://1.13.81.132:5009/api/slots.Zyslots/userRedis';
            $game_name = config('zygame.game_en_name');
            foreach ($list as $key=>$value){
                $ordersn = Common::doOrderSn(888);
                //$userinfo = $redis5502->hMGet('user_'.$value['uid'], ['coins','bonus','channel','package_id']);

                //测试用
                /*$data = [
                    'uid' => $value['uid']
                ];
                $res = $this->guzzle->post($url, $data, self::$herder);
                if (!isset($res['code']) || $res['code'] != 200){
                    $this->logger->error('zy历史记录用户信息错误：'.$res.' uid==>'.$value['uid']);
                    continue;
                }
                $userinfo = $res['data'];*/
                $userinfo = Db::table('userinfo')->where('uid',$value['uid'])->select('coin','bonus','uid','package_id','channel')->first();
                //bonus不带入
                $userinfo['bonus'] = config('slots.is_zy_carry_bonus') == 1 ? $userinfo['bonus'] : 0;

                $betId = $value['game_id'].'-'.$value['uid'];
                //$log = Db::table('slots_log_'.date('Ymd'))->where('betId',$betId)->first();
                $log = $this->slotsCommon->SlotsLogView((string)$betId);
                if (!empty($log)){
                    continue;
                }

                if ($userinfo){
                    $cashBetAmount = $value['add_bet_13'];
                    $bonusBetAmount = $value['add_bet_14'];
                    if ($value['add_bet_13'] == 0 && $value['add_bet_14'] == 0){
                        $cashBetAmount = $value['bet_score'];
                        $bonusBetAmount = 0;
                    }

                    $game_log = [
                        'betId' => $value['game_id'].'-'.$value['uid'],
                        'parentBetId' => $value['game_id'],
                        'uid' => $value['uid'],
                        'puid' => $value['bind_uid'],
                        'terrace_name' => 'zy',
                        'slotsgameid' => $value['game_type'],
                        'game_id' => isset($game_list_new[$value['game_type']]) ? $game_list_new[$value['game_type']]['id'] : 0,
                        'englishname' => isset($game_name[$value['game_type']]) ? $game_name[$value['game_type']] : '',
                        'transaction_id' => $ordersn,
                        'package_id' => $userinfo['package_id'],
                        'channel' => $userinfo['channel'],
                        'betTime' => $value['time_stamp'],
                        'createtime' => time(),

                        'cashBetAmount' => $cashBetAmount,
                        'bonusBetAmount' => $bonusBetAmount,
                        'cashTransferAmount' => $value['final_score'],
                        'bonusTransferAmount' => $value['add_bet_15'],
                        'cashWinAmount' => $value['final_score'] + $value['add_bet_13'],
                        'bonusWinAmount' => $value['add_bet_14'] + $value['add_bet_15'],
                    ];
                    //资金变化
                    $coin = $value['total_score'];
                    $bonus = $value['add_bet_12'];
                    $balance = $this->slotsCommon->slotsLogZy($game_log, $coin, $bonus);
                    if ($balance['code'] != 200){
                        $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏zy记录存储失败');
                        return json_encode(['ErrorCode'=>99]);
                    }

                }
            }

            //$ids = array_column($list,'id');
            //Db::connection('lzmj')->table('game_records_'.date('Ymd'))->whereIn('id',$ids)->update(['is_set'=>1]);

            //$redis5502->set('zy_log_time',$value['time_stamp']);
            return json_encode(['code' => 200,'msg'=>'zy记录统计完成','data' => []]);
        }else{
            return json_encode(['code' => 201,'msg'=>'无数据','data' => []]);
        }

    }

    /******************************主动请求模式*******************************/

    private static $herder = [
        'Content-Type' => 'application/x-www-form-urlencoded',
    ];
    private static $herder_json = [
        'Content-Type' => 'application/json',
    ];

    /**
     * 获取游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public static function getGameUrl($uid,$gameid){
        $token = Db::table('user_token')->where('uid',$uid)->value('token');
        if (empty($token)){
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }

        $url = config('egslots.api_url').'/'.config('egslots.Platform').'/login';
        $data = [
            'Token' => $token,
            'Username' => $uid,
            'GameID' => $gameid,
            'AgentName' => config('egslots.Agent'),
            'Lang' => config('egslots.language'),
        ];


        $data['Hash'] = self::getKey($data,'get');
        $res = self::senGetCurl($url,$data);

        if(!$res || isset($res['ErrorCode'])){
            return ['code' => 201,'msg' => $res['ErrorCode'] ,'data' =>[]];
        }

        $gameUrl = $res['URL'];

        return ['code' => 200,'msg' => 'success' ,'data' =>$gameUrl];

    }


    /**
     * body数据
     * @param $body
     * @return string hmac-sha256数据
     */
    private static function getKey($body,$type){
        if ($type == 'get'){
            $body = self::getQueryString($body);
        }else {
            $body = json_encode($body);
        }
        $key = config('egslots.Hash_key');
        $hash = hash_hmac('sha256', $body, $key);
        return $hash;
    }

    private static function getQueryString($body){
        $string = [];
        foreach ($body as $key => $value) {
            if ($key == 'sign') continue;
            $string[] = $key . '=' . $value;
        }
        return implode('&', $string);

    }

    /** 发送Curl
     * @param $url
     * @param $body
     * @return mixed
     */
    private static function senPostCurl($url,$body)
    {
        $guzzle = new Guzzle();
        $dataString = $guzzle->post($url, $body, self::$herder);

        return $dataString;

    }

    /** 发送Curl
     * @param $url
     * @param $body
     * @return mixed
     */
    private static function senGetCurl($url,$body,$urlencodeData = []){
        //$dataString =  Curl::get($url,$body,$urlencodeData);
        $url = Curl::getUrl($url,$body);
        //dd($url);
        $guzzle = new Guzzle();
        $dataString = $guzzle->get($url,[],self::$herder_json);
//        $dataString = Curl::get($url);
        return $dataString;

    }




    /**
     * 得到今日的自研游戏
     * @return void
     */
    #[RequestMapping(path: 'get_today_history')]
    public function getTodayHistory(){
        $param = $this->request->all();
        $field = 'id,issue,begin_time,0 as time,seed,value_1 as result,
        value_2 as result2,value_3 as result3,value_4 as result4,value_5 as result5,value_6 as result6,
        cards_0,cards_1,cards_2,cards_3,cards_4,cards_5';
        $data = Db::connection('lzmj')
            ->table('game_table_'.date('Ymd'))
            ->select(Db::raw($field))
            ->where(['game_type'=> $param['game_type']])
            ->forPage(1,50)
            ->orderBy('id','desc')
            ->get()
            ->toArray();
        foreach ($data as &$vo) {
            $vo['result_cards'] = $this->result_cards($param['game_type'],$vo);
            $vo['seed_hash'] = $this->getHash((string)$param['game_type'],(string)$vo['seed'],(string)$vo['result_cards']);
        }
        return $this->response->json($data);
    }



    /**
     * 获取我的下注
     * @return void
     */
    #[RequestMapping(path: 'get_my_bet')]
    public function getMyBet(){
        $param = $this->request->all();

        $field = 'id,time_stamp,0 as time,bet_score,final_score AS profit';
        $dateArray = \App\Common\DateTime::createDateRange(strtotime('-15 day'),time(),'Ymd');
        $dayDescArray = array_reverse($dateArray);
        $data = $this->SubTableQueryPage($dayDescArray,'game_records_',$field,[['uid','=',$param['uid']],['game_type','=',$param['game_type']]]);
        return $this->response->json($data);
    }



    /**
     * 获取我的下注详情
     * @return void
     */
    #[RequestMapping(path: 'get_bet_details')]
    public function getBetDetails(){

        $param = $this->request->all();
        $time = isset($param['time_stamp']) ? (int)$param['time_stamp'] : time();

        $suffix = date('Ymd', $time);
        unset($param['time_stamp']);

        $field = 'game_id,game_type,time_stamp,0 AS date,uid,bet_score,final_score AS profit,
        add_bet_0,add_bet_1,add_bet_2,add_bet_3,add_bet_4,add_bet_5,add_bet_6,add_bet_7,
        add_bet_8,add_bet_9,add_bet_10,add_bet_11,add_bet_12,add_bet_13,add_bet_14,add_bet_15';

        $data = Db::connection('lzmj')
            ->table('game_records_'.$suffix)
            ->select(Db::raw($field))
            ->where(['id'=> $param['id']])
            ->first();

        if (!$data)return $this->response->json([]);


        $field = 'game_id,seed,issue,value_1 as result,value_2 as result2,
                    value_3 as result3,value_4 as result4,value_5 as result5,value_6 as result6,
                    cards_0,cards_1,cards_2,cards_3,cards_4,cards_5';
        $table = Db::connection('lzmj')
            ->table('game_table_'.$suffix)
            ->select(Db::raw($field))
            ->where(['game_id'=> $data['game_id']])
            ->first();
        if(!$table)return $this->response->json([]);

        $data['result_cards'] = $this->result_cards($data['game_type'],$table);
        $data['seed_hash'] = $this->getHash((string)$data['game_type'],(string)$table['seed'],(string)$data['result_cards']);

        $payout = $this->getPayout($data, $table['result'],$table['result2'],$table['result3'],$table['result4'],$table['result5'],$table['result6']);

        $data['bets'] = $payout;
        for ($i = 0; $i < 16; $i++) {
            unset($data['add_bet_' . $i]);
        }

        $data = array_merge($data, $table);
        return $this->response->json($data);
    }



    /**
     * 获取hash
     * @param $game_type string  游戏类型
     * @param $seed  string
     * @param $result_cards string
     * @return void
     */
    private function getHash(string $game_type,string $seed,string $result_cards = ''):string{
        return in_array($game_type,['1514','1511','1510','1512','1513','1515','1516']) ?  hash('sha256', $seed.$result_cards) :  hash('sha256', $seed);

    }


    private function result_cards($game_type,$table){
        $result_cards = '';
        switch ($game_type) {
            case '1504':
                $result_cards = $table['cards_0'].$table['cards_1'];
                break;
            case '1505' :
                $result_cards = $table['cards_0'].$table['cards_1'].$table['cards_2'].$table['cards_3'].$table['cards_4'].$table['cards_5'];
                break;
            case '1514': //火箭
            case '1511': //飞机
            case '1515': //飞机
            case '1516': //飞机
                $result_cards = $table['cards_0'];
                break;
            case '1510'://地雷
            case '1512':
            case '1513':
                $result_cards = $table['issue'];
                break;
        }
        return $result_cards;
//        return $game_type == '1504' ?
//            $table['cards_0'].$table['cards_1'] :
//            ($game_type == '1505' ? $table['cards_0'].$table['cards_1'].$table['cards_2'].$table['cards_3'].$table['cards_4'].$table['cards_5'] : '');
    }


    private function getPayout($data, $result1,$result2 = 0,$result3 = 0,$result4 = 0,$result5 = 0,$result6 = 0)
    {
        $payouts = $this->get_payout($data['game_type']);

        $results = [];
        switch ($data['game_type']) {
            case '1501':  //新AB
                for ($i = 0; $i < 10; $i++) {
                    if ($data['add_bet_' . $i]) {
                        $amount = $data['add_bet_' . $i];
                        if($i == $result1 || $i == $result2){
                            $payout = $payouts[$i] ?? 0;
                        }else{
                            $payout = 0;
                        }

                        $results[] = ['bet' => $i, 'amount' => $amount, 'payout' => $payout];
                    }
                }
                break;
            case '1502':
            case '1503':
            case '1504':
//                $rotaryConfig = [0, 1, 0, 1, 0, 1, 2, 0, 1, 0, 1, 0, 1, 0, 1, 0, 2, 1, 0, 1];
//                $result = $data['game_type'] == '1502' ? $rotaryConfig[$result] : $result;
                for ($i = 0; $i < 3; $i++) {
                    if ($data['add_bet_' . $i]) {
                        $amount = $data['add_bet_' . $i];
                        $payout = $i == $result1 ? ($payouts[$result1] ?? 0) : 0;

                        $results[] = ['bet' => $i, 'amount' => $amount, 'payout' => $payout];
                    }
                }
                break;
            case '1505': //印度骰子
                for ($i = 0; $i < 6; $i++) {
                    if ($data['add_bet_' . $i]) {
                        $amount = $data['add_bet_' . $i];
                        $name = 'result'.($i + 1);
                        $payout = $$name <= 1 ? 0 : (($payouts[($$name - 1)])?? 0);

                        $results[] = ['bet' => $i, 'amount' => $amount, 'payout' => $payout];
                    }
                }
                break;
            case '1506':
                for ($i = 0; $i < 13; $i++) {
                    if ($data['add_bet_' . $i]) {
                        $amount = $data['add_bet_' . $i];
                        $config_region = [[1,3,7,9,5],[0,5],[2,4,6,8,0]];
                        if($i <= 2 && in_array($result1,$config_region[$i])){ //判断出现的区域
                            $payout = $payouts[$i][$result1] ?? 0;
                        }elseif ($i > 2){
                            $payout = $amount > 0 && $result1 + 3 == $i ? 9 : 0;
                        }else{
                            $payout = 0;
                        }
                        $results[] = ['bet' => $i, 'amount' => $amount, 'payout' => $payout];
                    }
                }
                break;
            case '1507':
                for ($i = 0; $i < 4; $i++) {
                    if ($data['add_bet_' . $i]) {
                        $amount = $data['add_bet_' . $i];
                        $payout = $i == $result1 ? ($payouts[$result1] ?? 0) : 0;

                        $results[] = ['bet' => $i, 'amount' => $amount, 'payout' => $payout];
                    }
                }
                break;
            case '1508': //百人金花
                for ($i = 0; $i < 7; $i++) {
                    if ($data['add_bet_' . $i]) {
                        $amount = $data['add_bet_' . $i];
                        if($i == $result1 || $i == $result5 ||  ($i == 2 && $result5 >2)){
                            $payout = $payouts[$i] ?? 0;
                        }else{
                            $payout = 0;
                        }

                        $results[] = ['bet' => $i, 'amount' => $amount, 'payout' => $payout];
                    }
                }
                break;
            case '1514': //飞机
            case '1511': //飞机
            case '1515': //飞机
            case '1516': //飞机
                for ($i = 2; $i <= 3; $i++) {
                    if ($data['add_bet_' . $i]) {
                        $amount = $data['add_bet_' . $i];
                        $payout = $data['add_bet_' . ($i + 2)];
                        $results[] = ['bet' => ($i - 2), 'amount' => $amount, 'payout' => $payout];
                    }
                }
                break;
            default:
                break;
        }

        return $results;
    }


    private  function get_payout($game_id)
    {
        $config = [
            '1501' => [1.9, 2, 2.5, 3.5, 4.5, 3.5, 14.5, 24.5, 49, 119],//新AB
            '1502' => [2, 2, 9],  //黄，蓝，红                                  //转盘
            '1503' => [2, 2, 9],                                    //龙虎
            '1504' => [2, 2, 5],                                    //7up骰子
            '1505' => [0, 3, 5, 10, 20, 100],                       //印度骰子
            '1506' => [
                ['1' => 2, '3' => 2, '7' => 2, '9' => 2, '5' => 1.5],
                ['0' => 4.5, '5' => 4.5],
                ['2' => 2, '4' => 2, '6' => 2, '8' => 2, '0' => 1.5]],//wingo
            '1507' => [2, 2, 11, 50],                                 //国王和皇后
            '1508' => [2, 2, 3, 8, 9, 100, 120],                      //百人金花
        ];

        return $config[$game_id] ?? '';
    }

    private function SubTableQueryPage($dateList,$table,$field,$where){
        $builder = Db::connection('lzmj')->table($table . $dateList[0])->selectRaw($field)->where($where);
        for ($i = 1; $i < count($dateList); $i++) {
            $tables = 'lzmj_'.$table . $dateList[$i];
            $res = Db::select("SHOW TABLES LIKE '$tables'");
            if (!$res) {
                continue;
            }
            $builder->unionAll(function ($query) use ($dateList, $i, $field, $where, $table) {
                $query->from($table . $dateList[$i])->selectRaw($field)->where($where);
            });
        }

        try {
            return $builder->forPage(1, 50)->orderBy('time_stamp', 'desc')->get()->toArray();
        } catch (\Throwable $e) {
            // 在这里处理异常，例如记录日志或者抛出异常
            $this->logger->error($e->getMessage());
            return [];
        }
    }

}






