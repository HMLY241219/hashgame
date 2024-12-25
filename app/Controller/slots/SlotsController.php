<?php
/**
 * 游戏
 */

namespace App\Controller\slots;


use App\Common\Guzzle;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use App\Common\DateTime;
use Hyperf\Di\Annotation\Inject;
use App\Common\SqlUnion;
use App\Common\Common;
use function Hyperf\Config\config;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Parallel;
use Hyperf\Coroutine\Exception\ParallelExecutionException;
use App\Controller\slots\Common as slotsCommon;
#[Controller(prefix: 'slots.Slots')]
class SlotsController extends AbstractController
{


//    private array $createGameUser = ['getPgPaerID','getPpPaerID','getTdPaerID']; //正式
    private array $createGameUser = ['getSbsPaerID','getSboPaerID']; //正式
    #[Inject]
    protected SqlUnion $SqlUnion;
    #[Inject]
    protected PpslotsController $ppslots;
    #[Inject]
    protected PgslotsController $pgslots;
    #[Inject]
    protected Cq9slotsController $cq9slots;
    #[Inject]
    protected SbsslotsController $sbsslots;
    #[Inject]
    protected SboslotsController $sboslots;
    #[Inject]
    protected EgslotsController $egslots;
    #[Inject]
    protected WeslotsController $weslots;
    #[Inject]
    protected EzugislotsController $ezugislots;
    #[Inject]
    protected JdbslotsController $jdbslots;
    #[Inject]
    protected EvoController $evoslots;
    #[Inject]
    protected SprslotsController $sprslots;
    #[Inject]
    protected JokerslotsController $jokerslots;
    #[Inject]
    protected BgslotsController $bgslots;
    #[Inject]
    protected TurboslotsController $turboslots;
    #[Inject]
    protected AvislotsController $avislots;
    #[Inject]
    protected JlslotsController $jlslots;
    #[Inject]
    protected Guzzle $guzzle;
    #[Inject]
    protected slotsCommon $slotsCommon;

    /**
     * 用户Cash和Bonus流水记录
     * @return void
     */
    #[RequestMapping(path: 'SlotsLog')]
    public function SlotsLog(){

        $uid = $this->request->post('uid');
        $page = $this->request->post('page') ?? 1; //当前页数
        $date = $this->request->post('date');
        $date = $date ? str_replace('-','',$date) : date('Ymd');
        $type = $this->request->post('type') ?? 1;//1=全部，2=slots,3=体育

        $table = 'slots_log_';
        $field = "slotsgameid,terrace_name,betTime,englishname,(cashBetAmount + bonusBetAmount) as BetAmount,(cashTransferAmount + bonusTransferAmount) as TransferAmount,is_settlement,other";

        if($type == 2){
            $where = [['uid','=',$uid],['is_sports','=',0],['is_settlement','<>',0]];
        }elseif ($type == 3){
            $where = [['uid','=',$uid],['is_sports','=',1]];
        }else{
            $where = [['uid','=',$uid],['is_settlement','<>',0]];
        }

        //查询很多天的
//        $dateArray = DateTime::createDateRange(strtotime('-7 day'),time(),'Ymd');
//        $dayDescArray = array_reverse($dateArray);
//        $list = $this->SqlUnion->SubTableQueryPage($dayDescArray,$table,$field,$where,'betTime',$page);

        $list = Db::connection('readConfig')->table($table.$date)->selectRaw($field)->where($where)->orderBy('betTime','desc')->forPage($page,30)->get()->toArray();

        if($list)foreach ($list as &$v){
            $v['betEndTime'] = $v['betTime'] ? date('d/m/Y H:i',$v['betTime']) : '';
            if (!empty($v['other'])) $v['other'] = json_decode($v['other'],true);
        }
        return $this->ReturnJson->successFul(200,$list);
    }


    /**
     * @param  $type 游戏厂商类型 pp td
     * @param  $game_id 游戏id
     * @return void 获取免费游戏url
     */
    #[RequestMapping(path: 'getFreeGameUrl')]
    private function getFreeGameUrl($type,$game_id,$game_type=1){
        if($type == 'pp'){
            return vsprintf(config('ppslots.fee_entry_address'),[$game_id]);
        }elseif ($type == 'td'){
            return vsprintf(config('tdslots.fee_entry_address'),[$game_id,config('tdslots.language')]);
        }elseif ($type == 'jl'){
            return vsprintf(config('jlslots.fee_entry_address'),[$game_id,config('jlslots.language')]);
        }elseif ($type == 'eg'){
            if ($game_type == 1){
                return vsprintf(config('egslots.fee_entry_address1'),[$game_id,config('egslots.language')]);
            }else{
                return vsprintf(config('egslots.fee_entry_address2'),[$game_id,config('egslots.language')]);
            }
        }elseif ($type == 'jdb'){
            $res = $this->jdbslots->getFreeGameUrl($game_id);
            if($res['code'] != 200){
                $this->logger->error('jdb游戏试玩连接获取失败==>'.json_encode($res));
                return '';
            }
            return $res['data'];
        }elseif ($type == 'spr'){
            $res = $this->sprslots->getFreeGameUrl($game_id);
            if($res['code'] != 200){
                $this->logger->error('spr游戏试玩连接获取失败==>'.json_encode($res));
                return '';
            }
            return $res['data'];
        }elseif ($type == 'bg'){
            /*$res = $this->bgslots->getFreeGameUrl($game_id);
            if($res['code'] != 200){
                $this->logger->error('bg游戏试玩连接获取失败==>'.json_encode($res));
                return '';
            }
            return $res['data'];*/
            return '';
        }elseif ($type == 'turbo'){
            $res = $this->turboslots->getFreeGameUrl($game_id);
            if($res['code'] != 200){
                $this->logger->error('turbo游戏试玩连接获取失败==>'.json_encode($res));
                return '';
            }
            return $res['data'];
        }elseif ($type == 'avi'){
            $res = $this->avislots->getFreeGameUrl($game_id);
            if($res['code'] != 200){
                $this->logger->error('avi游戏试玩连接获取失败==>'.json_encode($res));
                return '';
            }
            return $res['data'];
        }
        return '';
    }

    /**
     * @return void 创建3方游戏玩家
     */
    #[RequestMapping(path: 'createPayer')]
    public function createPayer(){
        $uid = $this->request->post('uid');
        $parallel = new Parallel(10);
        foreach ($this->createGameUser as $fun){
            $parallel->add(function () use($uid,$fun) {
                $this->$fun($uid);  //创建三方游戏玩家
                return Coroutine::id();
            });
        }
        try{
            $parallel->wait();
        } catch(ParallelExecutionException $e){
            $this->logger->error('创建三方玩家获取协程中的返回值:'.json_encode($e->getResults()));
            $this->logger->error('创建三方获取协程中出现的异常:'.json_encode($e->getThrowables()));
        }

        return $this->ReturnJson->successFul();
    }



    /**
     * @return void 获取足球这种厂商的h5链接
     */
    #[RequestMapping(path: 'getTerraceUrl')]
    public function getTerraceUrl(){
        $uid = $this->request->post('uid');
        $terrace_id = $this->request->post('terrace_id');  //厂商ID
        $type = Db::table('slots_terrace')
            ->where(['id' => $terrace_id,'status' => 1])
            ->value('type');
        if($type == 'sbs'){
            $res = $this->sbsslots->getGameUrl($uid);
            if($res['code'] != 200) return $this->ReturnJson->failFul(224);
            $data['type'] = 1;
            $data['GameUrl'] = $res['data'];
        }elseif ($type == 'ezugi'){
            $res = $this->ezugislots->getGameUrl((int)$uid);
            $data['type'] = 1;
            $data['GameUrl'] = $res;
        }elseif ($type == 'evo'){
            $res = $this->evoslots->getGameUrl((int)$uid);
            $data['type'] = 1;
            $data['GameUrl'] = $res;
        }else{
            return $this->ReturnJson->failFul(257);
        }
        $this->setUserRunGameTage($uid,$type);
        return $this->ReturnJson->successFul(200,$data);
    }


    /**
     * @return void 得到游戏的url
     */
    #[RequestMapping(path: 'getGameUrl')]
    public function getGameUrl(){
        $uid = $this->request->post('uid');
        $id = $this->request->post('id');  //游戏id

        $this->slotsCommon->setRedisUser($uid);

        $slots_game = Db::table('slots_game as a')
            ->select("b.type","a.slotsgameid","a.type as game_type","a.table_id")
            ->join('slots_terrace as b','b.id', '=', 'a.terrace_id')
            ->where(['a.status' => '1','a.maintain_status' => 1,'a.id' => $id])
            ->whereIn('b.show_type',[1,3])
            ->first();

        if($slots_game['type'] == 'pg'){
            $this->getPgPaerID($uid);
            //运营商后端的响应需要传入以下标头（headers），以防止响应被存储在缓存中：
            $TraceId = $this->pgslots->getUserTraceId($uid);
            //测试服
            $url = 'https://cg.teenpatticlub.shop/api/Cepgbx/getGameUrl';
            $geturlData = [
                'gameid' => $slots_game['slotsgameid'],
                'TraceId' => $TraceId,
            ];
            $data['GameUrl'] = $this->guzzle->post($url,$geturlData,[],2);
            $data['type'] = 2;
            //正式服
//            $GameUrl = $this->pgslots->getGameUrl($slots_game['slotsgameid'],$TraceId);

            $this->setUserRunGameTage($uid,$slots_game['type']);

            //运营商后端的响应需要传入以下标头（headers），以防止响应被存储在缓存中：
            $data['freeUrl'] = '';
            $PgData = $this->ReturnJson->successFul(200,$data);
            $response = $this->response->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');

            // 返回设置了响应头的响应对象
            return $response->json($PgData);


        }elseif ($slots_game['type'] == 'pp'){
            $res = $this->ppslots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200) $this->ReturnJson->failFul(224);
            $data['type'] = 1;
            $data['GameUrl'] = $res['data'];
        }elseif ($slots_game['type'] == 'td'){
            $this->getTdPaerID($uid);
            $res = TdslotsController::getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200) return $this->ReturnJson->failFul(224);
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'jl'){
            //$this->getTdPaerID($uid);
            $res = $this->jlslots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200) return $this->ReturnJson->failFul(224);
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        } elseif ($slots_game['type'] == 'eg'){
            $res = $this->egslots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200) return $this->ReturnJson->failFul(224);
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'cq9'){
            $res = $this->cq9slots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200) return $this->ReturnJson->failFul(224);
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'sbo'){
            $res = $this->sboslots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200){
                $this->logger->error('sbo游戏获取连接失败==>'.json_encode($res));
                return $this->ReturnJson->failFul(224);
            }
            $data['GameUrl'] = $res['data'];
            $this->logger->error('Sbo无缝链接==>:'.$data['GameUrl']);
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'we'){
            $res = $this->weslots->getGameUrl((int)$uid,$slots_game['slotsgameid']);
            if($res['code'] != 200) return $this->ReturnJson->failFul(224);

            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'zy'){
            $this->setUserRunGameTage($uid,$slots_game['type']);
            return $this->ReturnJson->successFul();
        }elseif ($slots_game['type'] == 'jdb'){
            $res = $this->jdbslots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200){
                $this->logger->error('jdb游戏获取连接失败==>'.json_encode($res));
                return $this->ReturnJson->failFul(224);
            }
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'spr'){
            //$res = $this->jdbslots->getGameUrl($uid,$slots_game['slotsgameid']);
            $res = $this->sprslots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200){
                $this->logger->error('spr游戏获取连接失败==>'.json_encode($res));
                return $this->ReturnJson->failFul(224);
            }
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'evo'){
            $res = $this->evoslots->getGameUrl((int)$uid,$slots_game['slotsgameid']);
            $data['type'] = 1;
            $data['GameUrl'] = $res;
        }elseif ($slots_game['type'] == 'ezugi'){
            $res = $this->ezugislots->getGameUrl((int)$uid,(string)$slots_game['table_id']);
            $data['type'] = 1;
            $data['GameUrl'] = $res;
        }elseif ($slots_game['type'] == 'joker'){
            $res = $this->jokerslots->getGameUrl((int)$uid,(string)$slots_game['slotsgameid']);
            $data['type'] = 1;
            $data['GameUrl'] = $res;
        }elseif ($slots_game['type'] == 'bg'){
            $res = $this->bgslots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200){
                $this->logger->error('bg游戏获取连接失败==>'.json_encode($res));
                return $this->ReturnJson->failFul(224);
            }
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'turbo'){
            $res = $this->turboslots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200){
                $this->logger->error('turbo游戏获取连接失败==>'.json_encode($res));
                return $this->ReturnJson->failFul(224);
            }
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }elseif ($slots_game['type'] == 'avi'){
            $res = $this->avislots->getGameUrl($uid,$slots_game['slotsgameid']);
            if($res['code'] != 200){
                $this->logger->error('avi游戏获取连接失败==>'.json_encode($res));
                return $this->ReturnJson->failFul(224);
            }
            $data['GameUrl'] = $res['data'];
            $data['type'] = 1;
        }else{
            return $this->ReturnJson->failFul(225);
        }
        $this->setUserRunGameTage($uid,$slots_game['type']);
        $data['freeUrl'] = $this->getFreeGameUrl($slots_game['type'],$slots_game['slotsgameid'],$slots_game['game_type']);
//        var_dump($data);
        // return json(['code' => 200 ,'msg' => 'success','data' => $GameUrl]);
        return $this->ReturnJson->successFul(200,$data,1);
    }

    /**
     * 获取游戏试玩链接
     * @return null
     */
    #[RequestMapping(path: 'getFreeUrl')]
    public function getFreeUrl(){
        $id = $this->request->post('id');  //游戏id
        $slots_game = Db::table('slots_game as a')
            ->select("b.type","a.slotsgameid","a.type as game_type")
            ->join('slots_terrace as b','b.id', '=', 'a.terrace_id')
            ->where(['a.status' => '1','a.maintain_status' => 1,'a.id' => $id])
            ->whereIn('b.show_type',[1,3])
            ->first();

        $data = [];
        $data['type'] = 1;
        if (empty($slots_game)){
            $data['freeUrl'] = '';
            return $this->ReturnJson->successFul(200,$data,1);
        }
        $data['freeUrl'] = $this->getFreeGameUrl($slots_game['type'],$slots_game['slotsgameid'],$slots_game['game_type']);
        return $this->ReturnJson->successFul(200,$data,1);
    }


    /**
     * 创建沙巴体育用户
     * @param $uid
     * @return void
     */
    private function getSbsPaerID($uid){
        $sbsslots_player = Db::table('sbsslots_player')->where('uid',$uid)->first();
        if(!$sbsslots_player){
            $res = $this->sbsslots->playerCreated($uid);
            if($res['code'] == 200){
                Db::table('sbsslots_player')->insertOrIgnore([
                    'uid' => $uid,
                    'createtime' => time(),
                ]);
            }
        }
    }

    /**
     * 创建sbo用户
     * @param $uid
     * @return void
     */
    private function getSboPaerID($uid){
        $sboslots_player = Db::table('sboslots_player')->where('uid',$uid)->first();
        if(!$sboslots_player){
            $res = $this->sboslots->playerCreated($uid);
            if($res['code'] == 200){
                Db::table('sboslots_player')->insertOrIgnore([
                    'uid' => $uid,
                    'createtime' => time(),
                ]);
            }
        }
    }


    /**
     * 获取用的PgTraceID 同时创建第三方用户
     * @param $uid
     * @param $nackname
     * @return void
     */
    #[RequestMapping(path: 'getPgPaerID')]
    public function getPgPaerID($uid){
        $pgslots_player = Db::table('pgslots_player')->where('uid',$uid)->first();
        if(!$pgslots_player){
            $trace_id = $this->pgslots->guid();
            //测试服
            $data = [
                'player_name' => (string)$uid,
                'nickname' => (string)$uid,
                'guid' => $trace_id
            ];
            $url = 'https://cg.teenpatticlub.shop/api/Cepgbx/playerCreated';
            $playerCreate = $this->guzzle->post($url,$data);

            //正式服
//            $playerCreate = $this->pgslots->playerCreated($uid,$trace_id);

            $action_result =  $playerCreate['data']['action_result'] ??'';
            $playerCreateerror =  $playerCreate['error']['code'] ?? '';
            if($action_result == 1 || in_array($playerCreateerror,[1305,1315])){
                $pgslots_player_data = [
                    'uid' => $uid,
                    'createtime' => time(),
                ];
                Db::table('pgslots_player')->insertOrIgnore($pgslots_player_data);
                return 1;
            }
            $this->logger->error('PG用户创建失败-uid:'.$uid);
            return 0;
        }
        return 1;

    }

    /**
     * 创建用户的Pp三方用户uid
     * @param $uid
     * @return void
     */
    private function getPpPaerID($uid){
        $ppslots_player = Db::table('ppslots_player')->where('uid',$uid)->first();
        if(!$ppslots_player){
            $res = $this->ppslots->playerCreated($uid);
            if($res['code'] == 200){
                Db::table('ppslots_player')->insertOrIgnore([
                    'uid' => $uid,
                    'createtime' => time(),
                ]);
            }
        }
    }


    /**
     * 创建用户的Td三方用户uid
     * @param $uid
     * @return void
     */
    private function getTdPaerID($uid){
        $ppslots_player = Db::table('tdslots_player')->where('uid',$uid)->first();
        if(!$ppslots_player){
            $res = TdslotsController::playerCreated($uid);
            if($res['code'] == 200){
                Db::table('tdslots_player')->insertOrIgnore([
                    'uid' => $uid,
                    'createtime' => time(),
                ]);
            }
        }
    }

    /**
     * 设置用户启动游戏厂商标记
     * @param $uid
     * @param string $terrace_name
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    private function setUserRunGameTage($uid,string $terrace_name){
        $Redis = Common::Redis('Redis5501');
        $Redis->hSet('gameTage',(string)$uid,$terrace_name);
    }
}




