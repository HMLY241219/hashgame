<?php
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\slots;

use App\Common\Guzzle;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use function Hyperf\Config\config;
use function Symfony\Component\Translation\t;

#[Controller(prefix:"cq9slots")]
class Cq9slotsController extends AbstractController {

    #[Inject]
    protected Guzzle $guzzle;

    #[Inject]
    protected Common $common;

    /**
     * 檢查玩家帳號
     * @return void
     */

    public function check(string $account){
        $wtoken = $this->request->getHeaderLine('wtoken');
        $uid = $this->common->setUserMoney($account);
        $data = [
            'data' => false,
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => $this->common->getDate(),
            ],
        ];
        if($wtoken != config('cq9slots.wtoken')){
            $this->logger->error('cq9-check厂商验证失败:-token:'.config('cq9slots.wtoken').'三方提供的-token:'.$wtoken);
            return $this->response->json($data);
        }
        if(!$uid){
            $this->logger->error('cq9-check玩家验证失败三方提供的-UID:'.$account.'在我们平台不存在');
            return $this->response->json($data);
        }
        $data['data'] = true;
        return $this->response->json($data);
    }

    /**
     * 取得玩家錢包餘額
     * @param string $account
     * @return void
     */
    public function balance(string $account){
        $wtoken = $this->request->getHeaderLine('wtoken');

        if($wtoken != config('cq9slots.wtoken')){
            $this->logger->error('cq9-check厂商验证失败:-token:'.config('cq9slots.wtoken').'三方提供的-token:'.$wtoken);
            return $this->response->json($this->errorData(2));
        }

        $res = $this->common->setUserMoney($account);
        if($res['code'] != 200){
            $this->logger->error('EQ9用户获取失败:用户-UID:'.$account.'未找到用户');
            return $this->response->json($this->errorData());
        }
        $data = [
            'data' => [
                'balance' => (float)bcdiv((string)$res['data'],'100',4),
                'currency' => config('cq9slots.currency'),
            ],
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => $this->common->getDate(),
            ],
        ];
        return $this->response->json($data);
    }

    /**
     * 下注(遊戲類型 gametype :老虎機[slot] / 街機[arcade])
     * @return
     */
    #[RequestMapping(path: 'transaction/game/bet')]
    public function bet(){
        $param = $this->request->all();
//        $this->logger->error('cq9bet:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'account' => 'required',
                'gamecode' => 'required',
                'roundid' => 'required',
                'amount' => 'required',
                'mtcode' => 'required',
            ],
            [
                'account.required' => 'account is required',
                'gamecode.required' => 'gamecode is required',
                'roundid.required' => 'roundid is required',
                'amount.required' => 'amount is required',
                'mtcode.required' => 'mtcode is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("CQ-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(2));
        }

        $wtoken = $this->request->getHeaderLine('wtoken');
        if($wtoken != config('cq9slots.wtoken')){
            $this->logger->error('cq9-check厂商验证失败:-token:'.config('cq9slots.wtoken').'三方提供的-token:'.$wtoken);
            return $this->response->json($this->errorData(2));
        }

        $userinfo = $this->common->getUserInfo($param['account']);
        if(!$userinfo){
            $this->logger->error('CQ9玩家-UID:'.$param['account'].'不存在');
            return $this->response->json($this->errorData());
        }


        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',4);

        if($money < $param['amount']){
            $this->logger->error('cq9玩家-UID:'.$param['account'].'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$param['amount']);
            return $this->response->json($this->errorData(3));
        }


        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($param['roundid']);
        if($slots_log){
            $data = [
                'data' => [
                    'balance' => (float)$money,
                    'currency' => config('cq9slots.currency'),
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => $this->common->getDate(),
                ],
            ];
            return $this->response->json($data);
        }




        $slots_game = Db::table('slots_game')->select('id','englishname')->where(['slotsgameid' => $param['gamecode']])->first();

        $slotsData = [
            'betId' => $param['roundid'],
            'parentBetId' => $param['roundid'],
            'uid' => $param['account'],
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['gamecode'],
            'englishname' => $slots_game['englishname'],
            'game_id' => $slots_game['id'],
            'terrace_name' => 'cq9',
            'transaction_id' => $param['mtcode'],
            'betTime' => time(),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
            'is_settlement' => 0,
        ];

        $res = $this->common->slotsLog($slotsData,$userinfo['coin'],$userinfo['bonus'],bcmul((string)$param['amount'],'100',0),'0',2);
        if($res['code'] != 200)return $this->response->json($this->errorData(5));
        $data = [
            'data' => [
                'balance' => (float)bcdiv((string)$res['data'],'100',4),
                'currency' => config('cq9slots.currency'),
            ],
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => $this->common->getDate(),
            ],
        ];
        return $this->response->json($data);
    }



    /**
     * 结算(遊戲類型 gametype :老虎機[slot] / 街機[arcade])
     * @return void
     */
    #[RequestMapping(path: 'transaction/game/endround')]
    public function endround(){
        $param = $this->request->all();
//        $this->logger->error('cq9endround:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'account' => 'required',
                'gamecode' => 'required',
                'roundid' => 'required',
                'data' => 'required',
            ],
            [
                'account.required' => 'account is required',
                'gamecode.required' => 'gamecode is required',
                'roundid.required' => 'roundid is required',
                'data.required' => 'data is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("CQ9-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(2));
        }

        $wtoken = $this->request->getHeaderLine('wtoken');
        if($wtoken != config('cq9slots.wtoken')){
            $this->logger->error('cq9-check厂商验证失败:-token:'.config('cq9slots.wtoken').'三方提供的-token:'.$wtoken);
            return $this->response->json($this->errorData(2));
        }

        $userinfo = $this->common->getUserInfo($param['account']);
        if(!$userinfo){
            $this->logger->error('CQ9数据库userinfo不存在用户-UID:'.$param['account']);
            return $this->response->json($this->errorData());
        }

        $slots_log = $this->common->SlotsLogView($param['roundid']);
        if(!$slots_log || $slots_log['is_settlement']){
            $money = bcdiv(bcadd((string)$userinfo['coin'],(string)$userinfo['bonus'],0),'100',4);
            $data = [
                'data' => [
                    'balance' => (float)$money,
                    'currency' => config('cq9slots.currency'),
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => $this->common->getDate(),
                ],
            ];
            return $this->response->json($data);
        }
        $GameDataArray = $this->getGameData($param['data'],['amount']);
        $res = $this->common->resultDealWith($slots_log,$userinfo,bcmul((string)$GameDataArray['amount'],'100',0),2);
        if($res['code'] !== 200){
            $this->logger->error('cq9事务处理失败-UID:'.$param['userId'].'三方游戏-betId:'.$slots_log['betId']);
            return $this->response->json($this->errorData(5));
        }


        $data = [
            'data' => [
                'balance' => (float)bcdiv((string)$res['data'],'100',4),
                'currency' => config('cq9slots.currency'),
            ],
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => $this->common->getDate(),
            ],
        ];
        return $this->response->json($data);

    }


    /**
     * 下注遊戲類型 gametype : 牌桌[table] / 街機[arcade] / 魚機[fish] / 真人視訊 [live]
     * @return
     */
    #[RequestMapping(path: 'transaction/game/rollout')]
    public function rollout(){
        $param = $this->request->all();
//        $this->logger->error('cq9rollout:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'account' => 'required',
                'gamecode' => 'required',
                'roundid' => 'required',
                'amount' => 'required',
                'mtcode' => 'required',
            ],
            [
                'account.required' => 'account is required',
                'gamecode.required' => 'gamecode is required',
                'roundid.required' => 'roundid is required',
                'amount.required' => 'amount is required',
                'mtcode.required' => 'mtcode is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("CQ-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(2));
        }

        $wtoken = $this->request->getHeaderLine('wtoken');
        if($wtoken != config('cq9slots.wtoken')){
            $this->logger->error('cq9-check厂商验证失败:-token:'.config('cq9slots.wtoken').'三方提供的-token:'.$wtoken);
            return $this->response->json($this->errorData(2));
        }

        $userinfo = $this->common->getUserInfo($param['account']);
        if(!$userinfo){
            $this->logger->error('CQ9玩家-UID:'.$param['account'].'不存在');
            return $this->response->json($this->errorData());
        }


        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',4);

        if($money < $param['amount']){
            $this->logger->error('cq9玩家-UID:'.$param['account'].'余额不足此次转账,玩家余额:'.$money.'下注金额:'.$param['amount']);
            return $this->response->json($this->errorData(3));
        }

        $data = [
            'data' => [
                'balance' => (float)$money,
                'currency' => config('cq9slots.currency'),
            ],
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => $this->common->getDate(),
            ],
        ];

        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($param['roundid']);
        if($slots_log){
            $this->logger->error('PP该笔订单已经通知过-roundid:'.$param['roundid']);
            return $this->response->json($data);
        }

        $data['data']['balance'] = (float)bcsub($money,$param['amount'],4);

        return $this->response->json($data);



    }


    /**
     * 下注遊戲類型 gametype : 牌桌[table] / 街機[arcade] / 魚機[fish] / 真人視訊 [live]
     * @return
     */
    #[RequestMapping(path: 'transaction/game/takeall')]
    public function takeall(){
        $param = $this->request->all();
        $validator = $this->validatorFactory->make(
            $param,
            [
                'account' => 'required',
                'gamecode' => 'required',
                'roundid' => 'required',
                'mtcode' => 'required',
            ],
            [
                'account.required' => 'account is required',
                'gamecode.required' => 'gamecode is required',
                'roundid.required' => 'roundid is required',
                'mtcode.required' => 'mtcode is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("CQ-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(2));
        }

        $wtoken = $this->request->getHeaderLine('wtoken');
        if($wtoken != config('cq9slots.wtoken')){
            $this->logger->error('cq9-check厂商验证失败:-token:'.config('cq9slots.wtoken').'三方提供的-token:'.$wtoken);
            return $this->response->json($this->errorData(2));
        }

        $userinfo = $this->common->getUserInfo($param['account']);
        if(!$userinfo){
            $this->logger->error('CQ9玩家-UID:'.$param['account'].'不存在');
            return $this->response->json($this->errorData());
        }


        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',4);

        if($money < $param['amount']){
            $this->logger->error('cq9玩家-UID:'.$param['account'].'余额不足此次转账,玩家余额:'.$money.'下注金额:'.$param['amount']);
            return $this->response->json($this->errorData(3));
        }

        $data = [
            'data' => [
                'balance' => (float)$money,
                'currency' => config('cq9slots.currency'),
            ],
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => $this->common->getDate(),
            ],
        ];

        return $this->response->json($data);

    }


    /**
     * ※ 街機[arcade] / 魚機[fish] : Rollin amount = Rollout amount - bet + win
    ※ 牌桌[table] / 真人[live] : Rollin amount=Rollout amount+win-rake
     * @return
     */
    #[RequestMapping(path: 'transaction/game/rollin')]
    public function rollin(){
        $param = $this->request->all();
//        $this->logger->error('cq9rollin:'.json_encode($param));
        $validator = $this->validatorFactory->make(
            $param,
            [
                'account' => 'required',
                'gamecode' => 'required',
                'roundid' => 'required',
                'validbet' => 'required',
                'bet' => 'required',
                'win' => 'required',
                'amount' => 'required',
                'mtcode' => 'required',
                'gametype' => 'required',
            ],
            [
                'account.required' => 'account is required',
                'gamecode.required' => 'gamecode is required',
                'roundid.required' => 'roundid is required',
                'validbet.required' => 'validbet is required',
                'bet.required' => 'bet is required',
                'win.required' => 'win is required',
                'amount.required' => 'amount is required',
                'mtcode.required' => 'mtcode is required',
                'gametype.required' => 'gametype is required',
            ]
        );
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->logger->error("CQ9-验证账号接口数据验证失败===>".$errorMessage);
            return $this->response->json($this->errorData(2));
        }

        $wtoken = $this->request->getHeaderLine('wtoken');
        if($wtoken != config('cq9slots.wtoken')){
            $this->logger->error('cq9-check厂商验证失败:-token:'.config('cq9slots.wtoken').'三方提供的-token:'.$wtoken);
            return $this->response->json($this->errorData(2));
        }

        $userinfo = $this->common->getUserInfo($param['account']);
        if(!$userinfo){
            $this->logger->error('cq9-数据库userinfo不存在用户-UID:'.$param['account']);
            return $this->response->json($this->errorData());
        }

        //下注金额
        $bet_amount = in_array($param['gametype'],['fish','arcade','slot']) ? $param['bet'] : $param['validbet'];

        $money = bcdiv((string)($userinfo['coin'] + $userinfo['bonus']),'100',4);
        if($money < $bet_amount){
            $this->logger->error('cq9玩家-UID:'.$param['account'].'余额不足此次下注,玩家余额:'.$money.'下注金额:'.$bet_amount);
            return $this->response->json($this->errorData(3));
        }


        //效验订单是否已经使用过
        $slots_log = $this->common->SlotsLogView($param['roundid']);
        if($slots_log){
            $this->logger->error('cq9该笔订单已经通知过-roundid:'.$param['roundid']);
            $data = [
                'data' => [
                    'balance' => (float)$money,
                    'currency' => config('cq9slots.currency'),
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => $this->common->getDate(),
                ],
            ];
            return $this->response->json($data);
        }




        $slots_game = Db::table('slots_game')->select('id','englishname')->where(['slotsgameid' => $param['gamecode']])->first();


        $slotsData = [
            'betId' => $param['roundid'],
            'parentBetId' => $param['roundid'],
            'uid' => $param['account'],
            'puid' => $userinfo['puid'],
            'slotsgameid' => $param['gamecode'],
            'englishname' => $slots_game['englishname'],
            'game_id' => $slots_game['id'],
            'terrace_name' => 'cq9',
            'transaction_id' => $param['mtcode'],
            'betTime' => time(),
            'channel' => $userinfo['channel'],
            'package_id' => $userinfo['package_id'],
            'createtime' => time(),
        ];
        if($param['gametype'] == 'table'){
            $param['win'] = $param['win'] < 0 ? '0' : bcsub(bcadd($param['win'],$bet_amount,2),$param['rake'],2);
        }
        $res = $this->common->slotsLog($slotsData,$userinfo['coin'],$userinfo['bonus'],bcmul((string)$bet_amount,'100',0),bcmul((string)$param['win'],'100',0));
        if($res['code'] != 200)return $this->response->json($this->errorData(5));
        $data = [
            'data' => [
                'balance' => (float)bcdiv((string)$res['data'],'100',4),
                'currency' => config('cq9slots.currency'),
            ],
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => $this->common->getDate(),
            ],
        ];

        return $this->response->json($data);
    }

    /**
     * 获取游戏启动url
     * @param $uid  用户的uid
     * @param $gameid  游戏的id
     * @return array
     */
    public function getGameUrl($uid,$gameid){
        //单一钱包启动
        $user_token = Db::table('user_token')->where('uid',$uid)->value('token');
        if(!$user_token){
            $this->logger->error('用户获取PP游戏链接时-uid:'.$uid.'获取token失败');
            return ['code' => 201,'msg' => '用户获取PP游戏链接时-uid:'.$uid.'获取token失败' ,'data' =>[] ];
        }
        $url  = config('cq9slots.api_url').'/gameboy/player/sw/gamelink';
        $body = [
            'account' => (string)$uid,
            'gamehall' => 'cq9',
            'gamecode' => $gameid,
            'gameplat' => 'mobile',
            'lang' => config('cq9slots.language'),
        ];
        $data = $this->guzzle->post($url,$body,$this->Herder());
        if(!isset($data['data']['url']) || !$data['data']['url']){
            $this->logger->error('用户获取CQ9游戏链接时-uid:'.$uid.'获取游戏启动链接失败,返回数据：'.json_encode($data));
            return ['code' => 201,'msg' => 'Cq9玩家获取游戏链接' ,'data' =>[] ];
        }
        $url = $data['data']['url'].'&leaveUrl='.config('host.gameurl');
        return ['code' => 200,'msg' => 'success' ,'data' =>$url];

    }



    /**
     *
     * @param $gameData
     * @return void
     */
    private function getGameData(string $gameData,array $filedArray):array{
        $gameData = json_decode($gameData,true);
        $gameData = $gameData[0] ?? [];
        $data = [];
        foreach ($gameData as $k => $v){
            if(in_array($k,$filedArray))$data[$k] = $v;
        }
        return $data;
    }


    /**
     * 请求头
     * @return array
     */
    private function Herder():array{
        return [
            "Content-Type" => "application/x-www-form-urlencoded",
            "Authorization" => config('cq9slots.token'),
        ];
    }

    /**
     * 错误返回
     * @param int $type
     * @return array
     */
    private function errorData(int $type = 1):array{
        if($type == 1){ //查無玩家時回傳該編碼
            $code = '1006';
            $message = 'When checking for no players';
        }elseif ($type == 2){ //當帶入參數錯誤時或未帶入必要參數時 ，回傳該錯誤編碼。
            $code = '1003';
            $message = 'When parameters are entered incorrectly or necessary parameters are not entered';
        }elseif ($type == 3){//餘額不足時回傳該編碼
            $code = "1005";
            $message = "Insufficient Balance";
        }elseif ($type == 4){ //當未查詢到紀錄時回傳該錯誤編碼
            $code = "1014";
            $message = "This error code is returned when no record is found.";
        }else{//伺服器錯誤時回傳該編碼
            $code = "1100";
            $message = "Server error";
        }

        return [
            "data" => null,
            "status" => [
                'code' => $code,
                'message' => $message,
                'datetime' => $this->common->getDate()
            ],
        ];
    }
}







