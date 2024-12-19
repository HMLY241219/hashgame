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

#[Controller(prefix: 'turboslots')]
class TurboslotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Curl $curl;

    #[Inject]
    protected slotsCommon $slotsCommon;

    #[RequestMapping(path: 'profile')]
    public function profile()
    {
        $param = $this->request->all();
        //$this->logger->error("profile数据验证===>".json_encode($param));
        $sign = $this->request->getHeaderLine('X-Signature');

        $config = config('turboslots');
        $path = '/turboslots/profile';
        $param_sign = hash_hmac('sha256', $path.json_encode($param), $config['TOKEN']);
        if ($sign !== $param_sign) {
            $this->logger->error("profile签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['message' => "Request sign doesn't match"])->withStatus(403);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'token' => 'required',
            ],
            [
                'token.required' => 'token is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("profile数据验证失败===>".$errorMessage);
            return $this->response->json(['message' => $errorMessage])->withStatus(403);
        }

//        $uid = $this->slotsCommon->getUserUid($param['token']);
//        $userinfo = $this->slotsCommon->getUserInfo($uid);
        $userinfo = $this->slotsCommon->getUserInfoToken($param['token']);
        $userinfo = $this->slotsCommon->getUserInfo($userinfo['uid']);
        if(!$userinfo){
            $this->logger->error('profile用户获取失败:用户-token:'.$param['token'].'未找到用户');
            return $this->response->json(['message' => 'Player is invalid'])->withStatus(403);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        //回复
        $data = [
            'id' => (string)$userinfo['uid'],
            'currency' => $config['currency'],
            'isTest' => true,
            'balance' => (float)bcdiv($old_balance, '100', 2),

        ];
        //$this->logger->error("profile数据回复==>".json_encode($data));
        return $this->response->json($data);
    }


    /**
     * 下注
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'place')]
    public function place()
    {
        $param = $this->request->all();
        $param2 = $this->request->getBody()->getContents();
        //$this->logger->error("tur下注数据验证===>".json_encode($param));
        $sign = $this->request->getHeaderLine('X-Signature');

        $config = config('turboslots');
        $path = '/turboslots/place';
        $param_sign = hash_hmac('sha256', $path.json_encode(json_decode($param2)), $config['TOKEN']);
        if ($sign !== $param_sign) {
            $this->logger->error("place签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['message' => "Request sign doesn't match"])->withStatus(403);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'transactionId' => 'required',
                'roundId' => 'required',
                'gameId' => 'required',
                'amount' => 'required',
                'currency' => 'required',
                'playerId' => 'required',
                'token' => 'required',
                'roundStarted' => 'required',
            ],
            [
                'transactionId.required' => 'transactionId is required',
                'roundId.required' => 'roundId is required',
                'gameId.required' => 'gameId is required',
                'amount.required' => 'amount is required',
                'currency.required' => 'currency is required',
                'playerId.required' => 'playerId is required',
                'token.required' => 'token is required',
                'roundStarted.required' => 'roundStarted is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("tur下注数据验证失败===>".$errorMessage);
            return $this->response->json(['code' => 1, 'rollback' => false, 'message' => $errorMessage])->withStatus(403);
        }

//        $uid = $this->slotsCommon->getUserUid($param['token']);
//        $userinfo = $this->slotsCommon->getUserInfo($uid);
        $userinfo = $this->slotsCommon->getUserInfoToken($param['token']);
        $userinfo = $this->slotsCommon->getUserInfo($userinfo['uid']);
        if(!$userinfo){
            $this->logger->error('tur下注用户获取失败:用户-UID:'.$param['playerId'].'未找到用户');
            return $this->response->json(['code' => 4, 'rollback' => false, 'message' => 'Unauthorized'])->withStatus(403);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        $bet = $param['amount'] * 100;
        //检查余额是否充足
        if ($old_balance < $bet){
            return $this->response->json(['code' => 3, 'rollback' => false, 'message' => 'Not enough money.'])->withStatus(403);
        }

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['roundId'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['roundId']);
        //已存在
        if (!empty($log) && $log['transaction_id'] != ''){
            return $this->response->json(['code' => 2, 'rollback' => false, 'message' => 'The bet has already been processed.'])->withStatus(403);
        }

        /*$game_info = Db::table('slots_terrace as a')
            ->leftJoin('slots_game as b','a.id','=','b.terrace_id')
            ->where('a.type','turbo')
            ->where('b.slotsgameid',$param['gameId'])
            ->select('a.name','a.type',
                'b.englishname','b.id as slots_game_id')
            ->first();*/
        $game_info = Db::connection('readConfig')->table('slots_game')
            ->where('terrace_id',17)
            ->where('slotsgameid',$param['gameId'])
            ->select('englishname','id as slots_game_id')
            ->first();
        if (empty($game_info)){
            return $this->response->json(['code' => 9, 'rollback' => false, 'message' => 'Game unavailable.'])->withStatus(403);
        }

        $ordersn = Common::doOrderSn(171);
        try {
            Db::beginTransaction();

            //下注记录
            if (!empty($log)) {
                //Db::table('slots_log_' . date('Ymd'))->where('betId',$param['roundId'])->update(['transaction_id'=>$ordersn]);
                $res = $this->slotsCommon->updateSlotsLog((string)$param['roundId'], ['transaction_id'=>$ordersn]);
                $game_log = $log;
            } else {
                $game_log = [
                    'betId' => $param['roundId'],
                    'parentBetId' => $param['transactionId'],
                    'uid' => $userinfo['uid'],
                    'puid' => $userinfo['puid'],
                    'terrace_name' => 'turbo',
                    'slotsgameid' => $param['gameId'],
                    'game_id' => $game_info['slots_game_id'],
                    'englishname' => $game_info['englishname'],
                    'transaction_id' => $ordersn,
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
                $this->logger->error('uid:'.$userinfo['uid'].'slotsLog三方游戏turbo记录存储失败');
                Db::rollback();
                return $this->response->json(['code' => 9, 'rollback' => false, 'message' => 'Game unavailable.'])->withStatus(403);
            }
            Db::commit();

            $time = microtime(true);
            // 将时间戳转换为毫秒级时间戳
            $createdAt = floor($time * 1000);
            //回复
            $data = [
                'transactionId' => $param['transactionId'],
                'balance' => (float)bcdiv($balance['data'], '100', 2),
                'createdAt' => $createdAt,
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("turbo下注，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['code' => 7, 'rollback' => false, 'message' => 'Internal error'])->withStatus(403);
        }

    }

    /**
     * 派奖
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'settle')]
    public function settle()
    {
        $param = $this->request->all();
        //$this->logger->error("turbo派奖数据验证===>".json_encode($param));
        $sign = $this->request->getHeaderLine('X-Signature');

        $config = config('turboslots');
        $path = '/turboslots/settle';
        $param_sign = hash_hmac('sha256', $path.json_encode($param), $config['TOKEN']);
        if ($sign !== $param_sign) {
            $this->logger->error("settle签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['message' => "Request sign doesn't match"])->withStatus(403);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'transactionId' => 'required',
                'roundId' => 'required',
                'gameId' => 'required',
                'amount' => 'required',
                'currency' => 'required',
                'playerId' => 'required',
                'token' => 'required',
                'roundClosed' => 'required',
            ],
            [
                'transactionId.required' => 'transactionId is required',
                'roundId.required' => 'roundId is required',
                'gameId.required' => 'gameId is required',
                'amount.required' => 'amount is required',
                'currency.required' => 'currency is required',
                'playerId.required' => 'playerId is required',
                'token.required' => 'token is required',
                'roundClosed.required' => 'roundClosed is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("tur派奖数据验证失败===>".$errorMessage);
            return $this->response->json(['code' => 1, 'repeat' => false, 'message' => $errorMessage])->withStatus(403);
        }

        /*$uid = $this->slotsCommon->getUserUid($param['token']);
        $userinfo = $this->slotsCommon->getUserInfo($uid);*/
        $userinfo = $this->slotsCommon->getUserInfoToken($param['token']);
        $userinfo = $this->slotsCommon->getUserInfo($userinfo['uid']);
        if(!$userinfo){
            $this->logger->error('tur派奖用户获取失败:用户-UID:'.$userinfo['uid'].'未找到用户');
            return $this->response->json(['code' => 4, 'repeat' => false, 'message' => 'Unauthorized'])->withStatus(403);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['roundId'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['roundId']);
        //不存在
        if (empty($log)){
            $this->logger->error('tur派奖下注不存在:'.json_encode($param));
            return $this->response->json(['code' => 5, 'repeat' => true, 'message' => 'Internal error'])->withStatus(403);
        }
        if ($log['is_settlement'] == 1){
            $this->logger->error('tur派奖已派奖:'.json_encode($param));
            return $this->response->json(['code' => 3, 'repeat' => true, 'message' => 'Internal error'])->withStatus(403);
        }

        try {
            Db::beginTransaction();

            //资金变化
            $balance = $this->slotsCommon->resultDealWith($log, $userinfo, (string)($param['amount'] * 100), 2);
            if ($balance['code'] != 200){
                $this->logger->error('uid:'.$param['playerId'].'slotsLog三方游戏tur记录存储失败');
                Db::rollback();
                return $this->response->json(['code' => 2, 'repeat' => false, 'message' => 'Internal error']);
            }
            Db::commit();

            $time = microtime(true);
            // 将时间戳转换为毫秒级时间戳
            $createdAt = floor($time * 1000);
            //回复
            $data = [
                'transactionId' => $param['transactionId'],
                'balance' => (float)bcdiv((string)$balance['data'], '100', 2),
                'createdAt' => $createdAt,
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("tur派奖，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['code' => 2, 'repeat' => false, 'message' => 'Internal error']);
        }

    }

    /**
     * 取消
     * @return \Psr\Http\Message\ResponseInterface
     */
    #[RequestMapping(path: 'cancel')]
    public function cancel()
    {
        $param = $this->request->all();
        //$this->logger->error("spr回滚数据验证===>".json_encode($param));
        $sign = $this->request->getHeaderLine('X-Signature');

        $config = config('turboslots');
        $path = '/turboslots/cancel';
        $param_sign = hash_hmac('sha256', $path.json_encode($param), $config['TOKEN']);
        if ($sign !== $param_sign) {
            $this->logger->error("cancel签名验证失败===>sign：".$sign.'--par_sign: '.$param_sign);
            return $this->response->json(['message' => "Request sign doesn't match"])->withStatus(403);
        }

        $validator = $this->validatorFactory->make(
            $param,
            [
                'transactionId' => 'required',
                'roundId' => 'required',
                'gameId' => 'required',
                'amount' => 'required',
                'currency' => 'required',
                'playerId' => 'required',
                'token' => 'required',
                'roundClosed' => 'required',
            ],
            [
                'transactionId.required' => 'transactionId is required',
                'roundId.required' => 'roundId is required',
                'gameId.required' => 'gameId is required',
                'amount.required' => 'amount is required',
                'currency.required' => 'currency is required',
                'playerId.required' => 'playerId is required',
                'token.required' => 'token is required',
                'roundClosed.required' => 'roundClosed is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("tur取消数据验证失败===>".$errorMessage);
            return $this->response->json(['code' => 1, 'repeat' => false, 'message' => $errorMessage])->withStatus(403);
        }

        /*$uid = $this->slotsCommon->getUserUid($param['token']);
        $userinfo = $this->slotsCommon->getUserInfo($uid);*/
        $userinfo = $this->slotsCommon->getUserInfoToken($param['token']);
        $userinfo = $this->slotsCommon->getUserInfo($userinfo['uid']);
        if(!$userinfo){
            $this->logger->error('tur取消用户获取失败:用户-UID:'.$userinfo['uid'].'未找到用户');
            return $this->response->json(['code' => 4, 'repeat' => false, 'message' => 'Unauthorized'])->withStatus(403);
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);

        //$log = Db::table('slots_log_' . date('Ymd'))->where('betId',$param['roundId'])->first();
        $log = $this->slotsCommon->SlotsLogView((string)$param['roundId']);
        //不存在
        if (empty($log)){
            $this->logger->error('tur取消下注不存在:'.json_encode($param));
            return $this->response->json(['code' => 5, 'repeat' => true, 'message' => 'Internal error'])->withStatus(403);
        }
        if ($log['is_settlement'] == 2){
            $this->logger->error('tur取消已取消:'.json_encode($param));
            return $this->response->json(['code' => 3, 'repeat' => true, 'message' => 'Internal error'])->withStatus(403);
        }

        try {
            Db::beginTransaction();

            $new_cash = bcadd((string)$userinfo['coin'],(string)$log['cashBetAmount'],0);
            $new_bouns = bcadd((string)$userinfo['bonus'],(string)$log['bonusBetAmount'],0);
            $res = $this->slotsCommon->userFundChange($param['playerId'], $log['cashBetAmount'], $log['bonusBetAmount'], $new_cash, $new_bouns, $userinfo['channel'], $userinfo['package_id']);
            if (!$res) {
                $this->logger->error('uid:' . $log['uid'] . 'tur取消余额修改失败-Cash:' . $log['cashBetAmount'] . '-Bonus:' . $log['bonusBetAmount']);
                Db::rollback();
                return $this->response->json(['code' => 2, 'repeat' => false, 'message' => 'Internal error'])->withStatus(403);
            }

            /*$re = Db::table('slots_log_' . date('Ymd'))
                ->where('betId', $param['roundId'])
                ->where('is_consume', 0)
                ->update(['is_settlement'=>2]);*/

            Db::commit();

            $time = microtime(true);
            // 将时间戳转换为毫秒级时间戳
            $createdAt = floor($time * 1000);
            //回复
            $data = [
                'transactionId' => $param['transactionId'],
                'balance' => (float)bcdiv(bcadd((string)$new_cash, (string)$new_bouns), '100', 2),
                'createdAt' => $createdAt,
            ];

            return $this->response->json($data);

        }catch (\Throwable $ex){
            Db::rollback();
            $this->logger->error("tur取消，错误文件===" . $ex->getFile() . '===错误行数===' . $ex->getLine() . '===错误信息===' . $ex->getMessage());
            return $this->response->json(['code' => 2, 'repeat' => false, 'message' => 'Internal error'])->withStatus(403);
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

        /*$userinfo = $this->slotsCommon->getUserInfo($uid);
        if(!$userinfo){
            $this->logger->error('turbo获取链接用户获取失败:用户-UID:'.$uid.'未找到用户');
            return ['code' => 201,'msg' => 'fel' ,'data' =>''];
        }
        $old_balance = bcadd((string)$userinfo['coin'],(string)$userinfo['bonus']);*/

        $config = config('turboslots');
        $url = $config['api_url'];
        $game_url = vsprintf($url,[$gameid, $config['language'], $config['CID'], $token, $config['CID']]);

        return ['code' => 200,'msg' => 'success' ,'data' =>$game_url];

    }

        /**
     * 获取试玩游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public function getFreeGameUrl($gameid){
        $config = config('turboslots');
        $url = $config['fee_entry_address'];
        $game_url = vsprintf($url,[$gameid, $config['CID'], 'abc']);

        return ['code' => 200,'msg' => 'success' ,'data' =>$game_url];

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

}






