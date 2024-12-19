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
use Swoole\Coroutine\Channel;
use Swoole\Runtime;

#[Controller(prefix: 'avislots')]
class AvislotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Curl $curl;

    #[Inject]
    protected slotsCommon $slotsCommon;

    #[Inject]
    protected Channel $channel;

    /**
     * 健康检测
     * @param Request $request
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    #[RequestMapping(path: 'health')]
    public function health(){
        $param = $this->request->all();
        //$this->logger->error("avi验证数据验证===>".json_encode($param));

        $data = [
            'code' => 200,
            'message' => 'ok',
            'data' => [
            ]
        ];

        return $this->response->json($data);
    }

    /**
     * 获取用户信息
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'playerInfo')]
    public function playerInfo()
    {
        $param = $this->request->all();
        //$this->logger->error("avi用户信息数据验证===>".json_encode($param));

        $sign = $this->request->getHeaderLine('X-Auth-Signature');
        $config = config('avislots');
        $param_sign = $this->sign(json_encode($param), $config['secret_token']);
        if ($sign !== $param_sign) {
            $this->logger->error("playerInfo签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['message' => "Invalid authentication signature"])->withStatus(400);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'cid' => 'required',
                'sessionToken' => 'required',
                'timestamp' => 'required',
            ],
            [
                'cid.required' => 'cid is required',
                'sessionToken.required' => 'sessionToken is required',
                'timestamp.required' => 'timestamp is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("avi用户信息数据验证失败===>".$errorMessage);
            return $this->response->json(['message' => 'Invalid request'])->withStatus(400);
        }

        $user_id = $this->slotsCommon->getUserUid($param['sessionToken']);
        $userinfo = $this->slotsCommon->getUserInfo($user_id);
        if(!$userinfo){
            $this->logger->error('avi信息用户获取失败:用户-UID:'.$param['sessionToken'].'未找到用户');
            return $this->response->json(['message' => 'Invalid session token'])->withStatus(400);
        }

        $config = config('avislots');
        $data = [
            'playerId' => (string)$user_id,
            'balance' => (int)bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']),
            'currency' => $config['currency']
        ];

        return $this->response->json($data);

    }


    /**
     * 下注
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'bet')]
    public function bet()
    {
        $param = $this->request->all();
        $this->logger->error("avi下注数据验证===>".json_encode($param));

        $sign = $this->request->getHeaderLine('X-Auth-Signature');
        $config = config('avislots');
        $param_sign = $this->sign(json_encode($param), $config['secret_token']);
        if ($sign !== $param_sign) {
            $this->logger->error("bet签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['message' => "Invalid authentication signature"])->withStatus(400);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'betId' => 'required',
                'cid' => 'required',
                'sessionToken' => 'required',
                'playerId' => 'required',
                'productId' => 'required',
                'txId' => 'required',
                'roundId' => 'required',
                'amount' => 'required',
                'currency' => 'required',
                'timestamp' => 'required',
            ],
            [
                'betId.required' => 'betId is required',
                'cid.required' => 'cid is required',
                'sessionToken.required' => 'sessionToken is required',
                'playerId.required' => 'playerId is required',
                'productId.required' => 'productId is required',
                'txId.required' => 'txId is required',
                'roundId.required' => 'roundId is required',
                'amount.required' => 'amount is required',
                'currency.required' => 'currency is required',
                'timestamp.required' => 'timestamp is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("avi用户信息数据验证失败===>".$errorMessage);
            return $this->response->json(['message' => 'Invalid request'])->withStatus(400);
        }

        $waitTime = mt_rand(100, 500);
// 将毫秒转换为微秒（1秒 = 1,000,000 微秒）
        $waitTimeInMicroseconds = $waitTime * 1000;
// 使用 usleep 函数来进行等待
        usleep($waitTimeInMicroseconds);
        /*$userinfo = $this->slotsCommon->getUserInfo($param['playerId']);
        if(!$userinfo){
            $this->logger->error('avi下注用户获取失败:用户-UID:'.$param['playerId'].'未找到用户');
            return $this->response->json(['message' => 'Player not found'])->withStatus(404);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);*/

        $bet = $param['amount'];
        //检查余额是否充足
        /*if ($old_balance < $bet){
            return $this->response->json(['message' => 'Insufficient balance'])->withStatus(403);
        }*/

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['provider_tx_id'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['betId']);
        //已存在
        if (!empty($log) && $log['transaction_id'] != ''){
            return $this->response->json(['message' => 'Invalid request'])->withStatus(400);
        }

        /*$game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','spr')
            ->where('b.slotsgameid',$param['game'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();*/
        $game_info = Db::connection('readConfig')->table('slots_game')
            ->where('terrace_id',18)
            ->where('slotsgameid',$param['productId'])
            ->select('englishname','id as slots_game_id')
            ->first();
        if (empty($game_info)){
            return $this->response->json(['message' => 'Product not found'])->withStatus(404);
        }

        try {
            Db::beginTransaction();

            $userinfo = $this->slotsCommon->getUserInfo($param['playerId']);
            if(!$userinfo){
                $this->logger->error('avi下注用户获取失败:用户-UID:'.$param['playerId'].'未找到用户');
                return $this->response->json(['message' => 'Player not found'])->withStatus(404);
            }
            $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);
            if ($old_balance < $bet){
                return $this->response->json(['message' => 'Insufficient balance'])->withStatus(403);
            }

            //下注记录
            if (!empty($log)) {
                //Db::table('slots_log_' . date('Ymd'))->where('betId',$param['provider_tx_id'])->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$param['betId'], ['transaction_id'=>$param['txId']]);
                $game_log = $log;
            } else {
                $game_log = [
                    'betId' => $param['betId'],
                    'parentBetId' => $param['roundId'],
                    'uid' => $param['playerId'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => 'avi',
                    'slotsgameid' => $param['productId'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $param['txId'],
                    'package_id' => $userinfo['package_id'],
                    'channel' => $userinfo['channel'],
                    'betTime' => time(),
                    'createtime' => time(),
                    'is_settlement' => 0,
                ];
                //Db::name($table)->insert($game_log);
            }

            //资金变化
            $balance = $this->slotsCommon->slotsLog($game_log, $userinfo['coin'], $userinfo['bonus'], $bet, 0, 2);
            if ($balance['code'] != 200){
                $this->logger->error('uid:'.$param['user_id'].'slotsLog三方游戏avi记录存储失败');
                Db::rollback();
                return $this->response->json(['message' => 'Service overloaded'])->withStatus(429);
            }

            Db::commit();

            $currentTime = new \DateTime();
            $iso8601String = $currentTime->format('Y-m-d\TH:i:s.u\Z');

            //回复
            $data = [
                'processedTxId' => $param['txId'],
                'createdAt' => $iso8601String,
                'balance' => (int)$balance['data'],
            ];
            //$this->logger->error('avi下注回复:'.json_encode($data));

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("avi下注，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['message' => 'Service overloaded'])->withStatus(429);
        }

    }


    /**
     * 派奖
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'win')]
    public function win()
    {
        $param = $this->request->all();
        //$this->logger->error("avi派奖数据验证===>".json_encode($param));

        $sign = $this->request->getHeaderLine('X-Auth-Signature');
        $config = config('avislots');
        $param_sign = $this->sign(json_encode($param), $config['secret_token']);
        if ($sign !== $param_sign) {
            $this->logger->error("win签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['message' => "Invalid authentication signature"])->withStatus(400);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'betId' => 'required',
                'cid' => 'required',
                'sessionToken' => 'required',
                'playerId' => 'required',
                'productId' => 'required',
                'txId' => 'required',
                'roundId' => 'required',
                'amount' => 'required',
                'currency' => 'required',
                'timestamp' => 'required',
                'operation' => 'required',
            ],
            [
                'betId.required' => 'betId is required',
                'cid.required' => 'cid is required',
                'sessionToken.required' => 'sessionToken is required',
                'playerId.required' => 'playerId is required',
                'productId.required' => 'productId is required',
                'txId.required' => 'txId is required',
                'roundId.required' => 'roundId is required',
                'amount.required' => 'amount is required',
                'currency.required' => 'currency is required',
                'timestamp.required' => 'timestamp is required',
                'operation.required' => 'operation is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("avi派奖数据验证失败===>".$errorMessage);
            return $this->response->json(['message' => 'Invalid request'])->withStatus(400);
        }

        $userinfo = $this->slotsCommon->getUserInfo($param['playerId']);
        if(!$userinfo){
            $this->logger->error('avi派奖用户获取失败:用户-UID:'.$param['playerId'].'未找到用户');
            return $this->response->json(['message' => 'Player not found'])->withStatus(404);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        $log = $this->slotsCommon->SlotsLogView((string)$param['betId']);
        //不存在
        if (empty($log)){
            $this->logger->error('avi派奖下注不存在:'.json_encode($param));
            return $this->response->json(['message' => 'Bet not found'])->withStatus(404);
        }
        if ($log['is_settlement'] == 1 || $log['is_settlement'] == 2){
            $this->logger->error('avi派奖已派奖:'.json_encode($param));
            return $this->response->json(['message' => 'Bet not found'])->withStatus(404);
        }

        try {
            Db::beginTransaction();

            if ($param['operation'] == 'SettleBet') {
                //资金变化
                $balance = $this->slotsCommon->resultDealWith($log, $userinfo, (string)($param['amount']), 2);
                if ($balance['code'] != 200) {
                    $this->logger->error('uid:' . $param['playerId'] . 'slotsLog三方游戏avi记录存储失败');
                    Db::rollback();
                    return $this->response->json(['message' => 'Service overloaded'])->withStatus(429);
                }

            }else{//取消投资
                $new_cash = bcadd((string)$userinfo['coin'],(string)-$log['cashTransferAmount'],0);
                $new_bouns = bcadd((string)$userinfo['bonus'],(string)-$log['bonusTransferAmount'],0);
                $res = $this->slotsCommon->userFundChange($param['playerId'], -$log['cashTransferAmount'], -$log['bonusTransferAmount'], $new_cash, $new_bouns, $userinfo['channel'], $userinfo['package_id']);
                if (!$res) {
                    $this->logger->error('uid:' . $log['uid'] . 'avi回滚余额修改失败-Cash:' . -$log['cashTransferAmount'] . '-Bonus:' . -$log['bonusTransferAmount']);
                    Db::rollback();
                    return $this->response->json(['message' => 'Service overloaded'])->withStatus(429);
                }

                $updateData = [
                    'is_settlement' => 2,
                ];
                $re = $this->slotsCommon->updateSlotsLog($param['betId'], $updateData, 1);
                if (!$re){
                    $this->logger->error('uid:' . $log['uid'] . 'avi回滚记录修改失败betId:' . $param['betId']);
                    Db::rollback();
                    return $this->response->json(['message' => 'Service overloaded'])->withStatus(429);
                }

                $balance['data'] = bcadd((string)$new_cash, (string)$new_bouns,0);
            }

            Db::commit();

            $currentTime = new \DateTime();
            $iso8601String = $currentTime->format('Y-m-d\TH:i:s.u\Z');
            //回复
            $data = [
                'processedTxId' => $param['txId'],
                'createdAt' => $iso8601String,
                'balance' => (int)$balance['data'],
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("avi派奖，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['message' => 'Service overloaded'])->withStatus(429);
        }

    }


    /**
     * 奖金
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'transactions/promoWin')]
    public function promoWin()
    {
        $param = $this->request->all();
        //$this->logger->error("spr回滚数据验证===>".json_encode($param));

        $sign = $this->request->getHeaderLine('X-Auth-Signature');
        $config = config('avislots');
        $param_sign = $this->sign(json_encode($param), $config['secret_token']);
        if ($sign !== $param_sign) {
            $this->logger->error("promoWin签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['message' => "Invalid authentication signature"])->withStatus(400);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'cid' => 'required',
                'sessionToken' => 'required',
                'playerId' => 'required',
                'productId' => 'required',
                'txId' => 'required',
                'amount' => 'required',
                'currency' => 'required',
            ],
            [
                'cid.required' => 'cid is required',
                'sessionToken.required' => 'sessionToken is required',
                'playerId.required' => 'playerId is required',
                'productId.required' => 'productId is required',
                'txId.required' => 'txId is required',
                'amount.required' => 'amount is required',
                'currency.required' => 'currency is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("avi奖金数据验证失败===>".$errorMessage);
            return $this->response->json(['message' => 'Invalid request'])->withStatus(400);
        }

        $userinfo = $this->slotsCommon->getUserInfo($param['playerId']);
        if(!$userinfo){
            $this->logger->error('avi奖金用户获取失败:用户-UID:'.$param['playerId'].'未找到用户');
            return $this->response->json(['message' => 'Player not found'])->withStatus(404);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        try {
            Db::beginTransaction();

            $new_cash = bcadd((string)$userinfo['coin'],(string)$param['amount'],0);
            $new_bouns = bcadd((string)$userinfo['bonus'],(string)0,0);
            $res = $this->slotsCommon->userFundChange($param['playerId'], $param['amount'], 0, $new_cash, $new_bouns, $userinfo['channel'], $userinfo['package_id']);
            if (!$res) {
                $this->logger->error('uid:' . $param['playerId'] . 'avi奖金余额修改失败-Cash:');
                Db::rollback();
                return $this->response->json(['message' => 'Service overloaded'])->withStatus(429);
            }

            Db::commit();

            $currentTime = new \DateTime();
            $iso8601String = $currentTime->format('Y-m-d\TH:i:s.u\Z');
            //回复
            $data = [
                'processedTxId' => $param['txId'],
                'createdAt' => $iso8601String,
                'balance' => (int)bcadd((string)$new_cash, (string)$new_bouns,0),
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("avi奖金，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['message' => 'Service overloaded'])->withStatus(429);
        }

    }


    public function sign($message, $secret)
    {
        $signature = hash_hmac('md5', $message, $secret, true);
        $encodedSignature = base64_encode($signature);
        return $encodedSignature;
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
        $userinfo = Db::table('userinfo')->where('uid',$uid)->select('coin')->first();
        if (empty($userinfo)){
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }

        $config = config('avislots');
        $url = $config['api_url'];

        $data = [
            'cid' => $config['cid'],
            'productId' => $gameid,
            'sessionToken' => $token,
            'lang' => $config['language'],
            'lobbyUrl' => config('host.gameurl')
        ];

        $pra = self::getQueryString($data);
        $all_url = trim($url.'?'.$pra);
        $this->logger->error('avi游戏获取连接data==>'.json_encode($all_url));
        $this->logger->error('avi游戏获取连接==>'.$all_url);

        return ['code' => 200,'msg' => 'success' ,'data' =>$all_url];
    }

    /**
     * 获取试玩游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public function getFreeGameUrl($gameid){
        $config = config('avislots');
        $url = $config['fee_entry_address'];

        $data = [
            'cid' => $config['cid'],
            'productId' => $gameid,
            'isDemo' => true,
            'lobbyUrl' => config('host.gameurl')
        ];

        $pra = self::getQueryString($data);
        $all_url = trim($url.'?'.$pra);

        return ['code' => 200,'msg' => 'success' ,'data' =>$all_url];
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






