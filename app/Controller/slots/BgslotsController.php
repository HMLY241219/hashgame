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
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Swoole\Coroutine\Http\Client;
use function Hyperf\Config\config;

#[Controller(prefix: 'bgslots')]
class BgslotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Curl $curl;

    #[Inject]
    protected slotsCommon $slotsCommon;

    #[RequestMapping(path: 'play')]
    public function play()
    {
        $param = $this->request->all();
        //$this->logger->error("bg下注数据验证===>".json_encode($param));
        $sign = $this->request->getHeaderLine('X-REQUEST-SIGN');
        //$this->logger->error("bg下注sign===>".$sign);

        $config = config('bgslots');
        $param_sign = hash_hmac('sha256', json_encode($param), $config['AUTH_TOKEN']);
        //$this->logger->error("bg下注数据sign===>".$param_sign);
        if ($sign !== $param_sign) {
            $this->logger->error("bg下注签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['code' => 403, 'message' => "Request sign doesn't match"])->withStatus(403);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'user_id' => 'required',
                'currency' => 'required',
                'game' => 'required',
            ],
            [
                'user_id.required' => 'user_id is required',
                'currency.required' => 'currency is required',
                'game.required' => 'game is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("bg下注数据验证失败===>".$errorMessage);
            return $this->response->json(['code' => 403, 'message' => $errorMessage, 'balance' => 0]);
        }

        $userinfo = $this->slotsCommon->getUserInfo($param['user_id']);
        if(!$userinfo){
            $this->logger->error('bg信息用户获取失败:用户-UID:'.$param['user_id'].'未找到用户');
            return $this->response->json(['code' => 101, 'message' => 'Player is invalid', 'balance' => 0]);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        if (!isset($param['actions']) || empty($param['actions'])) {
            return $this->response->json(['balance' => (int)$old_balance]);
        }

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['game_id'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['game_id']);
        //已存在
        if (!empty($log) && $log['transaction_id'] != '' && $log['is_settlement'] == 1){

            $other = json_decode($log['other'], true);
            foreach ($other as $ok=>$ov){
                $end_action_id = $ov['action_id'];
            }
            if ($end_action_id == $param['actions'][0]['action_id']) {
                if (count($other) >= 2) {
                    foreach ($other as $okey => &$ovalue) {
                        if (isset($other[$okey + 1])) {
                            unset($other[$okey]);
                        }
                    }
                }
                $other = array_values($other);
                $data = [
                    'balance' => (int)$old_balance,
                    'game_id' => $param['game_id'],
                    'transactions' => $other
                ];
                $this->logger->error("bg下注已存在===>" . json_encode($param));
                return $this->response->json($data);
                //return $this->response->json(['code' => 502, 'message' => 'Unknown error in external service.', 'balance' => (int)$old_balance]);
            }
        }

        $game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','bg')
            ->where('b.slotsgameid',$param['game'])
//            ->whereRaw("REPLACE(slotsgameid, ' ', '') = ?", [$param['game']])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();
//            ->toRawSql();
//        $this->logger->error("bg下注游戏sql===>".$game_info);
        if (empty($game_info)){
            $this->logger->error("bg下注游戏不存在===>".json_encode($param));
            return $this->response->json(['code' => 405, 'message' => 'Game is not available to your casino', 'balance' => (int)$old_balance]);
        }

        $actions_data = [];
        $bet = 0;
        $win_amount = 0;
        $bet_action_id = '';
        $win_action_id = '';
        if (isset($param['actions']) && !empty($param['actions'])) {
            $actions = $param['actions'];
            foreach ($actions as $action) {
                $token_uuid = md5(uniqid((string)mt_rand(), true));
                if ($action['action'] == 'bet'){
                    if ($action['amount'] > $old_balance){//余额不足
                        $this->logger->error('bg下注余额不足，data==>'.json_encode($actions));
                        return $this->response->json(['code' => 100, 'message' => 'Player has not enough funds to process an action.', 'balance' => (int)$old_balance])->withStatus(412);
                    }
                    $bet += $action['amount'];
                    $bet_action_id = $action['action_id'];

                }else{
                    $win_amount += $action['amount'];
                    $win_action_id = $action['action_id'];
                }
                $actions_data[] = [
                    'action_id' => $action['action_id'],
                    'tx_id' => $token_uuid,
                    'processed_at' => gmdate('Y-m-d\TH:i:s.u\Z', time()),
                ];

            }
        }

        $ordersn = Common::doOrderSn(151);
        try {
            Db::beginTransaction();

            //下注记录
            if (!empty($log)) {
                //Db::table('slots_log_' . date('Ymd'))->where('betId',$param['game_id'])->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$param['game_id'], ['transaction_id'=>$ordersn]);
                $game_log = $log;
            } else {
                $game_log = [
                    'betId' => $param['game_id'],
                    'parentBetId' => $param['game_id'],
                    'uid' => $param['user_id'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => 'bg',
                    'slotsgameid' => $param['game'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $ordersn,
                    'package_id' => $userinfo['package_id'],
                    'channel' => $userinfo['channel'],
                    'betTime' => time(),
                    'createtime' => time(),
                    'other' => json_encode($actions_data),
                    'other2' => $bet_action_id,
                    'other3' => $win_action_id,
                ];
                //Db::name($table)->insert($game_log);
            }

            //资金变化
            if ($param['finished'] == true && $param['actions'][0]['action'] == 'bet'){
                if (empty($log)) {
                    $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $bet, $win_amount);
                }else{
                    $balance = $this->slotsCommon->resultDealWith($game_log, $userinfo, (string)($win_amount-$param['actions'][0]['amount']),2);
                }
            }elseif ($param['finished'] == false && $param['actions'][0]['action'] == 'bet'){
                $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $bet, $win_amount,2);
            }elseif ($param['finished'] == true && $param['actions'][0]['action'] == 'win'){
                if (!empty($log)){
                    //Db::table('slots_log_' . date('Ymd'))->where('betId',$log['betId'])->update(['is_consume'=>0,'other'=>json_encode($actions_data),'other3'=>$win_action_id]);
                    $this->slotsCommon->updateSlotsLog((string)$log['betId'], ['other'=>json_encode($actions_data),'other3'=>$win_action_id]);
                }
                $balance = $this->slotsCommon->resultDealWith($game_log, $userinfo, (string)$win_amount,2);
            }

            if ($balance['code'] != 200){
                $this->logger->error('uid:'.$param['user_id'].'slotsLog三方游戏bg记录存储失败');
                Db::rollback();
                return $this->response->json(['code' => 500, 'message' => 'Internal error', 'balance' => (int)$old_balance]);
            }
            Db::commit();

            //回复
            $data = [
                'balance' => (int)$balance['data'],
                'game_id' => $param['game_id'],
                'transactions' => $actions_data
            ];

            //$this->logger->error('bg回复，data==>'.json_encode($data));
            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("bg下注，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['code' => 500, 'message' => 'Internal error', 'balance' => (int)$old_balance]);
        }

    }

    /**
     * 回滚
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'rollback')]
    public function rollback()
    {
        $param = $this->request->all();
        //$this->logger->error("bg回滚数据验证===>".json_encode($param));

        $validator = $this->validatorFactory->make(
            $param,
            [
                'user_id' => 'required',
                'currency' => 'required',
                'game' => 'required',
            ],
            [
                'user_id.required' => 'user_id is required',
                'currency.required' => 'currency is required',
                'game.required' => 'game is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("bg回滚数据验证失败===>".$errorMessage);
            return $this->response->json(['code' => 403, 'message' => $errorMessage]);
        }

        $userinfo = $this->slotsCommon->getUserInfo($param['user_id']);
        if(!$userinfo){
            $this->logger->error('bg回滚用户获取失败:用户-UID:'.$param['user_id'].'未找到用户');
            return $this->response->json(['code' => 101, 'message' => 'Player is invalid']);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        if (!isset($param['actions']) || empty($param['actions'])) {
            return $this->response->json(['balance' => $old_balance]);
        }

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['game_id'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['game_id']);
        //已存在
        if (empty($log)){
            $this->logger->error("bg回滚订单不存在===>".json_encode($param));
            return $this->response->json(['code' => 502, 'message' => 'Unknown error in external service.', 'balance' => (int)$old_balance]);
        }

        $actions_data = [];
        $bet = 0;
        $win_amount = 0;
        if (isset($param['actions']) && !empty($param['actions'])) {
            $actions = $param['actions'];
            foreach ($actions as $action) {
                $token_uuid = md5(uniqid((string)mt_rand(), true));
                /*if ($action['action'] == 'bet'){
                    if ($action['amount'] > $old_balance){//余额不足
                        $this->logger->error('bg回滚余额不足，data==>'.json_encode($actions));
                        return $this->response->json(['code' => 100, 'message' => 'Player has not enough funds to process an action.']);
                    }
                    $bet += $action['amount'];

                }else{
                    $win_amount += $action['amount'];
                }*/
                $actions_data[] = [
                    'action_id' => $action['action_id'],
                    'tx_id' => $token_uuid,
                    'processed_at' => gmdate('Y-m-d\TH:i:s.u\Z', time()),
                ];

            }
        }

        try {
            Db::beginTransaction();

            if (count($param['actions']) == 1 && $param['actions'][0]['original_action_id'] == $log['other2']){
                $cash_chenge = $log['cashBetAmount'];
                $bonus_chenge = $log['bonusBetAmount'];
            }elseif (count($param['actions']) == 1 && $param['actions'][0]['original_action_id'] == $log['other3']){
                $cash_chenge = -$log['cashWinAmount'];
                $bonus_chenge = -$log['bonusWinAmount'];
            }else {
                $cash_chenge = $log['cashBetAmount'] - $log['cashWinAmount'];
                $bonus_chenge = $log['bonusBetAmount'] - $log['bonusWinAmount'];
            }
            $new_cash = bcadd((string)$userinfo['coin'],(string)$cash_chenge,0);
            $new_bouns = bcadd((string)$userinfo['bonus'],(string)$bonus_chenge,0);
            $res = $this->slotsCommon->userFundChange($param['user_id'], $cash_chenge, $bonus_chenge, $new_cash, $new_bouns, $userinfo['channel'], $userinfo['package_id']);
            if (!$res) {
                $this->logger->error('uid:' . $log['uid'] . 'bg回滚余额修改失败-Cash:' . $cash_chenge . '-Bonus:' . $bonus_chenge);
                Db::rollback();
                return $this->response->json(['code' => 500, 'message' => 'Internal error', 'balance' => (int)$old_balance]);
            }

            /*$re = Db::table('slots_log_' . date('Ymd'))
                ->where('betId', $param['game_id'])
//                ->where('is_consume', 0)
                ->update(['is_settlement'=>2]);*/

            Db::commit();

            if (config('slots.is_carry_bonus') == 1) {
                $balance = $new_cash + $new_bouns;
            }else{
                $balance = $new_cash;
            }
            //回复
            $data = [
                'balance' => (int)$balance,
                'game_id' => $param['game_id'],
                'transactions' => $actions_data
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("bg回滚，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['code' => 500, 'message' => 'Internal error', 'balance' => (int)$old_balance]);
        }

    }


    /******************************转账模式*******************************/

    private static $herder = [
//        'Content-Type' => 'application/x-www-form-urlencoded',
        'Content-Type' => 'application/json',
    ];

    /**
     * 获取游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public function getGameUrl($uid,$gameid){
        $token = Db::table('user_token')->where('uid',$uid)->value('token');
        if (empty($token)){
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }

        $userinfo = $this->slotsCommon->getUserInfo($uid);
        if(!$userinfo){
            $this->logger->error('bg获取链接用户获取失败:用户-UID:'.$uid.'未找到用户');
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        $config = config('bgslots');
        $url = $config['api_url'].'/sessions';

        $body = [
            'casino_id' => $config['CASINO_ID'],
            'game' => $gameid,
            'currency' => $config['currency'],
            'locale' => $config['language'],
            'ip' => Common::getIp($this->request->getServerParams()),
            'client_type' => 'mobile',
            'balance' => (float)$old_balance,
            'urls' => [
                'return_url' => config('host.gameurl')
            ],
            'user' => [
                'id' => $uid
            ]
        ];

        self::$herder['X-REQUEST-SIGN'] = hash_hmac('sha256', json_encode($body), $config['AUTH_TOKEN']);

        //$res =  self::senPostCurl($url,$body);
        //$res =  $this->guzzle->post($url, $body, self::$herder); //正式

        //测试
        $test_data = [
            'uid' => $uid,
            'gameid' => $gameid,
            'old_balance' => (float)$old_balance,
        ];
//        $test_url = 'http://1.13.81.132:5009/api/slots.Bgslots/getGameUrl';
        $test_url = 'https://inrtakeoff.3377win.com/api/slots.Bgslots/getGameUrl';
        //$res = $this->guzzle->post($test_url, $test_data, [], 1);
        $definitionSource = new DefinitionSource([]);
        $container = new Container($definitionSource);
        $cil = new ClientFactory($container);
        $client = $cil->create();
        $response = $client->request('POST', $test_url, [
            'headers' => self::$herder,
            'json' => $test_data,
        ]);
        // 获取响应的状态码和内容
        $statusCode = $response->getStatusCode();
        $res = $response->getBody()->getContents();


        $this->logger->error('bg游戏获取链接res==>'.$res.'---'.$statusCode);
        $res = json_decode($res, true);


        if (!$res){
            return ['code' => 201,'msg' => '' ,'data' =>''];
        }

        return ['code' => 200,'msg' => 'success' ,'data' =>$res['launch_options']['game_url']];

    }

        /**
     * 获取试玩游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public function getFreeGameUrl($gameid){
        $config = config('bgslots');
        $url = $config['api_url'].'/demo';

        $body = [
            'casino_id' => $config['CASINO_ID'],
            'game' => $gameid,
            'locale' => $config['language'],
            'ip' => Common::getIp($this->request->getServerParams()),
            'client_type' => 'mobile',
            'urls' => [
                'return_url' => config('host.gameurl')
            ],
        ];

        $res =  $this->guzzle->post($url, $body, self::$herder); //正式

        //$this->logger->error('bg游戏获取试玩res==>'.json_encode($res));

        if (!$res){
            return ['code' => 201,'msg' => '' ,'data' =>''];
        }

        return ['code' => 200,'msg' => 'success' ,'data' =>$res['launch_options']['game_url']];

    }


    /**
     * Key = {6 个任意字符} + MD5(所有请求参数串 + KeyG) + {6 个任意字符}
     * @param $body 请求体
     * @return string 获取hash
     */
    private static function getKey($body){
        $querystring = self::getQueryString($body);
        $KeyG = self::KeyG();

        return self::reallyKey($querystring,$KeyG);

    }

    /**
     * 生成44位的key
     * @param $querystring querystring 字符串
     * @param $KeyG KeyG字符串
     * @return string
     */
    private static function reallyKey($querystring,$KeyG,$count = 1){
        $key = rand(111111,999999).md5($querystring.$KeyG).rand(111111,999999);
        if(strlen($key) != 44 && $count <= 3){  //请求3次，如果3次都不行的话，就直接返回
            $count = $count + 1;
            self::reallyKey($querystring,$KeyG,$count);
        }
        return $key;
    }

    /**
     * @return void 获取keyg字符串
     *
     */
    private static function KeyG(){
        return md5(self::getDateTimeNow().config('jlslots.AgentId').config('jlslots.AgentKey'));
    }


    /**
     * DateTime.Now = 当下 UTC-4 时间, 格式為 yyMMd
     *  年: 公元年分末两位
     月: 两位数, 1~9 前面須补 0
     日: 一位数或两位数, 1~9 前面请不要补 0
     * 例如：2018/2/7 => 18027（7 号是 7 不是 07）
    2018/2/18 => 180218
     * @return string
     */
    private static function getDateTimeNow() {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $now->modify('-4 hours');

        $year = $now->format('y');
        $month = $now->format('m');
        $day = $now->format('j');
        // 格式化月份和日期
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $day = strval($day);

        if ($day[0] === '0') {
            $day = substr($day, 1);
        }

        return $year . $month . $day;
    }


    /**
     * 依照 API 参数列表，按顺序以 key1=value1&key2=value2 格式串起：所有 API 的请求参数字符串最后都要加上 AgentId=xxx
     * 例如Test1&GameId=101&Lang=zh-CN&AgentId=10081
     * 获取getQueryString 数据
     * @param $body 请求的数据
     * @return string
     */
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
        $dataString =  Curl::get($url,$body,$urlencodeData);
        return json_decode($dataString, true);

    }

    /**格式: YYYY-MM-DDThh:mm:ss, 例如
    2021-01-01T03:00:00。请用 GMT-4 时区的时间。
     * @param $time 时间戳
     * @return false|string
     */
    private static function getUtc4tDate($time){

        return gmdate('Y-m-d\TH:i:s', $time - (4 * 3600)); // 转换为GMT-4时区的时间

    }

    /**
     * 将游戏数据时区转为巴西或者中国时区
     * @param $date
     * @return void
     * @throws \Exception
     */
    public static function getTime($date){
        $datetime = new \DateTime($date, new \DateTimeZone('UTC')); // 创建DateTime对象，并设置时区为UTC

//        // 转换为巴西时间
//        $brazilTimezone = new \DateTimeZone('America/Sao_Paulo'); // 巴西时区
//        $datetime->setTimezone($brazilTimezone); // 设置时区为巴西时区
//        $Time = $datetime->format('y-m-d H:i:s'); // 格式化为yy-mm-dd hh:ii:ss

        // 转换为中国时间
        $chinaTimezone = new \DateTimeZone('Asia/Shanghai'); // 中国时区
        $datetime->setTimezone($chinaTimezone); // 设置时区为中国时区
        $Time = $datetime->format('y-m-d H:i:s'); // 格式化为yy-mm-dd hh:ii:ss

        return strtotime($Time);
    }

    public static function encrypt($data, $key, $iv)
    {
        $data = self::padString($data);
        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
        $encrypted = base64_encode($encrypted);
        $encrypted = str_replace(array('+','/','=') , array('-','_','') , $encrypted);
        return $encrypted;
    }

    private static function padString($source)
    {
        $paddingChar = ' ';
        $size = 16;
        $x = strlen($source) % $size;
        $padLength = $size - $x;
        for ($i = 0;$i < $padLength;$i++)
        {
            $source .= $paddingChar;
        }
        return $source;
    }

    public static function decrypt($data, $key, $iv)
    {
        $data = str_replace(array('-','_') , array('+','/') , $data);
        $data = base64_decode($data);
        $decrypted = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
        return mb_convert_encoding(trim($decrypted), 'UTF-8');
    }
}






