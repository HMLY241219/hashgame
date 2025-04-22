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

#[Controller(prefix:"egslots")]
class EgslotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Curl $curl;

    #[Inject]
    protected slotsCommon $slotsCommon;


    /**
     * 验证玩家身份
     * @return false|string
     */
    #[RequestMapping(path: 'auth')]
    public function auth(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'Hash' => 'required',
                'Token' => 'required',
            ],
            [
                'Hash.required' => 'Hash is required',
                'Token.required' => 'Token is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("eg验证账号接口数据验证失败===>".$errorMessage);
            return json_encode(['ErrorCode'=>7]);
        }

        $body = $param;
        unset($body['Hash']);
        $hash = self::getKey($body,'post');
        //var_dump($hash);
        if ($hash != $param['Hash']){
            $this->logger->error("eg权限Hash验证失败param===>".json_encode($param));
            return json_encode(['ErrorCode'=>3]);
        }

        $userinfo = Db::table('user_token as a')
            ->leftJoin('userinfo as b','a.uid','=','b.uid')
            ->where('a.token',$param['Token'])
            ->selectRaw('br_a.uid,IFNULL(br_b.coin,0) AS coin,IFNULL(br_b.bonus,0) AS bonus,IFNULL(br_b.total_pay_score,0) AS total_pay_score')
            ->first();
//            ->toRawSql();
        //var_dump($userinfo);
        if (empty($userinfo)){
            $this->logger->error("eg验证账号token无效");
            return json_encode(['ErrorCode'=>3]);
        }
        //bonus不带入
        $carry_bonus_config = Common::getMore('recharge_and_get_bonus,bonus_and_get_bonus');
        $userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && $carry_bonus_config['recharge_and_get_bonus'] >= $userinfo['total_pay_score']) ? $userinfo['bonus'] : 0;

        $data = [
            'Username' => (string)$userinfo['uid'],
            'Currency' => config('egslots.currency'),
//            'balance' => ($userinfo['coin'] + $userinfo['bonus'])/100,
            'Balance' =>  (string)bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100, 2),
        ];

        return json_encode($data);
    }


    #[RequestMapping(path: 'balance')]
    public function balance(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'Hash' => 'required',
                'Username' => 'required',
                'Currency' => 'required',
            ],
            [
                'Hash.required' => 'Hash is required',
                'Username.required' => 'Username is required',
                'Currency.required' => 'Currency is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("eg余额接口数据验证失败===>".$errorMessage);
            return json_encode(['ErrorCode'=>7]);
        }

        $body = $param;
        unset($body['Hash']);
        $hash = self::getKey($body,'post');
        if ($hash != $param['Hash']){
            $this->logger->error("eg余额Hash验证失败param===>".json_encode($param));
            return json_encode(['ErrorCode'=>3]);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("eg余额token无效");
            return json_encode(['ErrorCode'=>3]);
        }
        //bonus不带入
        $balance = (string)bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100, 2);

        $data = [
            'Balance' =>  $balance,
        ];

        return json_encode($data);
    }


    /**
     * 下注
     * @return false|string
     */
    #[RequestMapping(path: 'bet')]
    public function bet(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'Hash' => 'required',
                'Timestamp' => 'required',
                'Username' => 'required',
                'Currency' => 'required',
                'GameID' => 'required',
                'MainTxID' => 'required',
                'SubTxID' => 'required',
                'BetType' => 'required',
                'Bet' => 'required',
                'Win' => 'required',
                'TakeWin' => 'required',
                //'Extra' => 'required',
            ],
            [
                'Hash.required' => 'Hash is required',
                'Timestamp.required' => 'Timestamp is required',
                'Username.required' => 'Username is required',
                'Currency.required' => 'Currency is required',
                'GameID.required' => 'GameID is required',
                'MainTxID.required' => 'MainTxID is required',
                'SubTxID.required' => 'SubTxID is required',
                'BetType.required' => 'BetType is required',
                'Bet.required' => 'Bet is required',
                'Win.required' => 'Win is required',
                'TakeWin.required' => 'TakeWin is required',
                //'Extra.required' => 'Extra is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("eg下注接口数据验证失败===>".$errorMessage);
            $this->logger->error("eg下注接口数据验证失败===>".json_encode($param));
            return json_encode(['ErrorCode'=>7]);
        }

        //单点登录
        $res = $this->slotsCommon->getUserRunGameTage($param['Username'],'eg');
        if(!$res)return json_encode(['ErrorCode'=>7]);

        $body = $param;
        unset($body['Hash']);
        $hash = self::getKey($body,'post');
        if ($hash != $param['Hash']){
            $this->logger->error("eg下注Hash验证失败param===>".json_encode($param));
            return json_encode(['ErrorCode'=>3]);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("eg下注用户不存在param===>".json_encode($param));
            return json_encode(['ErrorCode'=>99]);
        }

        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;
        $old_balance = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100,2);

        $betId = $param['MainTxID'].'-'.$param['SubTxID'];
        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$betId)->first();
        $log = $this->slotsCommon->SlotsLogView((string)$betId);
        if (!empty($log) && $log['transaction_id'] != ''){
            $this->logger->error("eg下注已存在param===>".json_encode($param));
            return json_encode(['ErrorCode'=>1]);
        }

        //检查余额是否充足
        if ($old_balance < $param['Bet']){
            $this->logger->error("eg下注余额不足param===>".json_encode($param));
            return json_encode(['ErrorCode'=>2]);
        }

        $game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','eg')
            ->where('b.slotsgameid',$param['GameID'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();
        if (empty($game_info)){
            $this->logger->error("eg下注游戏不存在param===>".json_encode($param));
            return json_encode(['ErrorCode'=>99]);
        }

        $ordersn = Common::doOrderSn(444);
        try {
            Db::beginTransaction();

            //下注记录
            if (!empty($log)) {
                //Db::table('slots_log_' . date('Ymd'))->where('betId',$betId)->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$betId, ['transaction_id'=>$ordersn]);
                $game_log = $log;
            } else {
                $game_log = [
                    'betId' => $param['MainTxID'].'-'.$param['SubTxID'],
                    'parentBetId' => $param['MainTxID'],
                    'uid' => $userinfo['uid'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => 'eg',
                    'slotsgameid' => $param['GameID'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $ordersn,
                    'package_id' => $userinfo['package_id'],
                    'channel' => $userinfo['channel'],
                    'betTime' => $param['Timestamp'],
                    'createtime' => time(),
                ];
            }

            //资金变化
            $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $param['Bet']*100, $param['Win']*100);
            if ($balance['code'] != 200){
                $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏eg记录存储失败');
                Db::rollback();
                return json_encode(['ErrorCode'=>99]);
            }
            Db::commit();

            //回复
            $data = [
                'Balance' =>  (string)bcdiv((string)$balance['data'], (string)100, 2),
            ];

            return json_encode($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("eg下注，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return json_encode(['errorCode'=>5, 'message'=>'Other error']);
        }
    }

    #[RequestMapping(path: 'cancelBet')]
    public function cancelBet(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'Hash' => 'required',
                'Username' => 'required',
                'Currency' => 'required',
                'GameID' => 'required',
                'MainTxID' => 'required',
                'SubTxID' => 'required',
            ],
            [
                'Hash.required' => 'Hash is required',
                'Username.required' => 'Username is required',
                'Currency.required' => 'Currency is required',
                'GameID.required' => 'GameID is required',
                'MainTxID.required' => 'MainTxID is required',
                'SubTxID.required' => 'SubTxID is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("eg取消下注接口数据验证失败===>".$errorMessage);
            return json_encode(['ErrorCode'=>7]);
        }
        $this->logger->error("eg取消下注接口数据===>".json_encode($param));

        $body = $param;
        unset($body['Hash']);
        $hash = self::getKey($body,'post');
        if ($hash != $param['Hash']){
            $this->logger->error("eg取消下注Hash验证失败param===>".json_encode($param));
            return json_encode(['ErrorCode'=>3]);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        //dd($userinfo);
        if (empty($userinfo)){
            $this->logger->error("eg取消注单token无效");
            return $this->response->json(['ErrorCode'=>3]);
        }
        //bonus不带入
        //$userinfo['bonus'] = (config('slots.is_carry_bonus') == 1 && (Common::getConfigValue('recharge_and_get_bonus') <= $userinfo['total_pay_score'])) ? $userinfo['bonus'] : 0;

        $betId = $param['MainTxID'].'-'.$param['SubTxID'];
        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$betId)->first();
        $log = $this->slotsCommon->SlotsLogView((string)$betId);
        if (empty($log)){
            $this->logger->error("eg取消注单 ，注单不存在");
            return $this->response->json(['ErrorCode'=>1]);
        }else{
            $this->logger->error("eg取消注单 ，注单已成立");
            return $this->response->json(['ErrorCode'=>99]);
        }
    }


    /**
     * 玩家登出
     * @return false|string
     */
    #[RequestMapping(path: 'logout')]
    public function logout(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'Hash' => 'required',
                'Username' => 'required',
                'GameID' => 'required',
            ],
            [
                'Hash.required' => 'Hash is required',
                'Username.required' => 'Username is required',
                'GameID.required' => 'GameID is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("eg登出接口数据验证失败===>".$errorMessage);
            return json_encode(['ErrorCode'=>7]);
        }

        $body = $param;
        unset($body['Hash']);
        $hash = self::getKey($body,'post');
        var_dump($hash);
        if ($hash != $param['Hash']){
            $this->logger->error("eg登出Hash验证失败param===>".json_encode($param));
            return json_encode(['ErrorCode'=>3]);
        }

        /*$userinfo = Db::table('userinfo')
            ->where('uid',$param['Username'])
            ->selectRaw('uid,puid,IFNULL(coin,0) AS coin,IFNULL(bonus,0) AS bonus,channel,package_id')
            ->first();*/
        $userinfo = $this->slotsCommon->getUserInfo($param['Username']);
        if (empty($userinfo)){
            $this->logger->error("eg下注用户不存在param===>".json_encode($param));
            return json_encode(['ErrorCode'=>99]);
        }
        //bonus不带入
        $balance = (string)bcdiv((string)($userinfo['coin'] + $userinfo['bonus']), (string)100, 2);

        $data = [
            'Balance' =>  $balance,
        ];

        return json_encode($data);

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
    public function getGameUrl($uid,$gameid){
        /*$res = $this->guzzle->get('https://tx.api.egslot.cc/api/v1/777bet/agents', [], self::$herder_json);
        $this->logger->error("eg游戏进入失败111==>".$res);*/
        if (!$uid){
            return ['code' => 200,'msg' => 'success' ,'data' =>''];
        }

        $token = Db::table('user_token')->where('uid',$uid)->value('token');
        if (empty($token)){
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }

        $url = config('egslots.api_url').'/'.config('egslots.Platform').'/login?';
        $data = [
            'Token' => $token,
            'Username' => $uid,
            'GameID' => $gameid,
            'AgentName' => config('egslots.Agent'),
            'Lang' => config('egslots.language'),
        ];

        $pra = self::getQueryString($data);
        //var_dump($pra);

        $Hash = self::getKey($data,'get');
        $all_url = trim($url.$pra.'&Hash='.$Hash);
        //$this->logger->error("eg游戏链接参数==>".json_encode($data));
        //$this->logger->error("eg游戏链接==>".$all_url);
        //$res = self::senGetCurl($url,$data);
        $res = $this->guzzle->get($all_url, [], self::$herder_json);

        if(!$res || isset($res['ErrorCode'])){
            $this->logger->error("eg游戏进入失败==>".$res);
            return ['code' => 201,'msg' => $res['ErrorCode'] ,'data' =>[]];
        }

        $gameUrl = $res['URL'];

        //$gameUrl = '';
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


}






