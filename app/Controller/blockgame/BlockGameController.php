<?php
/**
 * 游戏
 */
declare(strict_types=1);
/**
 * 游戏
 */

namespace App\Controller\blockgame;



use App\Controller\AbstractController;
use App\Enum\EnumType;
use App\Exception\ErrMsgException;
use App\Service\BlockApi\BlockApiService;
use App\Service\BlockApi\TronNodeService;
use App\Service\BlockGameBetService;
use App\Service\BlockGamePeriodsService;
use App\Service\BlockGameService;
use App\Service\UserService;
use App\Service\WebSocket\SysConfService;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\Controller;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

#[Controller(prefix:"blockgame")]
class BlockGameController extends AbstractController{

    /**
     * 设置游戏
     * @return null
     */
    #[PostMapping(path: 'set')]
    public function setGame()
    {
        // 参数校验
        $params = $this->request->post();
        $validator = $this->validatorFactory->make($params,
            [
                'game_name' => 'required|max:125',
                'icon_img' => 'required|max:255',
                'cover_img' => 'required|max:255',
                'allow_bet_way' => 'required|in:1,2,3',
                'network' => 'required|in:1,2,3',
                'game_type_top' => 'required|in:1,2',
                'game_type_second' => 'required',
                'play_method' => 'required|in:1,2,3',
                'page_bet_rule' => 'required|max:1000',
                'transfer_bet_rule' => 'required|max:1000',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            // 设置游戏
            $res = (new BlockGameService())->setGame($params);
            return $this->ReturnJson->successFul(200, $res);
        } catch (ErrMsgException $e) {
            $this->logger->alert('BlockGameController.setGame.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * @return null
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[GetMapping(path: 'latestBlock')]
    public function getLatestBlock()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        $validator = $this->validatorFactory->make($params,
            [
                'network' => 'required|in:1,2,3',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            // 获取数据
            $res = BlockApiService::getLatestBlock((int)$params['network']);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取区块信息
     * @return null
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[GetMapping(path: 'blockInfo')]
    public function getBlockInfo()
    {

        // 参数校验
        $params = $this->request->getQueryParams();
        $validator = $this->validatorFactory->make($params,
            [
                'network' => 'required|in:1,2,3',
                'block_number' => 'required',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            // 获取数据
            $res = BlockApiService::getBlockInfo((int)$params['block_number'], (int)$params['network']);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取游戏列表
     * @return null
     */
    #[GetMapping(path: 'game/list')]
    public function getGameList()
    {
        // 参数
        $params = $this->request->getQueryParams();

        try {
            // 排序
            $sort = isset($params['sort']) && $params['sort'] == 'desc' ? 'desc' : 'asc';
            // 获取数据
            $res = BlockGameService::getGameList([
                'play_method' => $params['play_method'] ?? 0,
                'page' => $params['page'] ?? 0,
                'page_size' => $params['page_size'] ?? 0,
                // 排序
                'order' => 'sort ' . $sort,
            ], (!empty($params['page']) ? false : true));
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取游戏信息
     * @return null
     */
    #[GetMapping(path: 'game/info')]
    public function getGameInfo()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        $validator = $this->validatorFactory->make($params,
            [
                'game_id' => 'required',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            // 获取数据
            $res = BlockGameService::getGameInfo($params['game_id']);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 游戏下注
     * @return null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[PostMapping(path: 'bet')]
    public function addGameBet()
    {
        // 参数校验
        $params = $this->request->post();
        $params['uid'] = $this->request->getAttribute('uid');
        $validator = $this->validatorFactory->make($params,
            [
                'game_id' => 'required',
                'bet_data' => 'required|array', // 下注数据
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            $params['bet_way'] = EnumType::BET_WAY_BALANCE; // 平台余额下注
            // 下注
            $res = BlockGameBetService::cacheGameBet($params);
            $res['code'] = 200;
            return $this->ReturnJson->successFul(200, $res);
        } catch (ErrMsgException|\RedisException $e) {
            $this->logger->alert('BlockGameController.addGameBet.Exception：' . $e->getMessage());
            return $this->ReturnJson->successFul(200, ['code' => $e->getCode()]);
        }
    }

    /**
     * 获取游戏房间下注统计数据
     * @return null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[GetMapping(path: 'room/betdata')]
    public function getGameRoomBetStatisticsData()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        $validator = $this->validatorFactory->make($params,
            [
                'game_id' => 'required',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }
        $uid = $this->request->getAttribute('uid');

        try {
            $betLevel = $params['bet_level'] ?? EnumType::ROOM_CJ; // 下注房间等级
            // 获取数据
            $res = BlockGameBetService::getGameRoomBetStatisticsData($params['game_id'], (int)$betLevel, $uid);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getGameRoomBetStatisticsData.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 用户下注记录
     * @return null
     */
    #[GetMapping(path: 'user/betlog')]
    public function getUserBetLog()
    {
        // 用户ID
        $uid = $this->request->getAttribute('uid');
        $params = $this->request->getQueryParams();

        try {
            // 排序
            $sort = isset($params['sort']) && $params['sort'] == 'desc' ? 'desc' : 'asc';
            // 获取数据
            $res = BlockGameBetService::getGameBetList([
                'uid' => $uid,
                'game_id' => $params['game_id'] ?? '',
                'bet_way' => $params['bet_way'] ?? 0,
                'is_valid' => EnumType::BET_IS_VALID_YES,
                'status' => EnumType::BET_STATUS_COMPLETE,
                'page' => $params['page'] ?? 0,
                'page_size' => $params['page_size'] ?? 0,
                'date' => $params['date'] ?? '',
                // 查询字段
                'field' => ['bet_id', 'uid', 'game_id', 'game_name', 'open_block', 'open_result', 'open_data', 'block_hash', 'open_data', '"transaction_hash', 'bet_amount_bonus', 'win_lose_amount', 'win_lose_amount_bonus'],
                // 排序
                'order' => 'create_time ' . $sort,
            ], (!empty($params['page']) ? false : true), false);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getUserBetLog.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取用户余额
     * @return null
     */
    #[GetMapping(path: 'user/balance')]
    public function getUserBalance()
    {
        // 用户ID
        $uid = $this->request->getAttribute('uid');

        try {
            // 获取数据
            $res = UserService::getUserBalance($uid);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getUserBalance.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 游戏周期开奖记录
     * @return null
     */
    #[GetMapping(path: 'periods/openlog')]
    public function getGameOpenPeriodsLog()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        $validator = $this->validatorFactory->make($params,
            [
                'game_id' => 'required',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            // 排序
            $sort = isset($params['sort']) && $params['sort'] == 'asc' ? 'asc' : 'desc';
            // 获取数据
            $res = BlockGamePeriodsService::getGamePeriodsList([
                'game_id' => $params['game_id'],
                'page' => $params['page'] ?? 0,
                'page_size' => $params['page_size'] ?? 0,
                'date' => $params['date'] ?? '',
                'play_method' => $params['play_method'] ?? 0,
                // 排序
                'order' => 'curr_periods ' . $sort,
            ], (!empty($params['page']) ? false : true), false);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getGameOpenPeriodsLog.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取游戏期数区块开奖结果
     * @return null
     */
    #[GetMapping(path: 'open/result')]
    public function getGameOpenPeriodsOpenResult()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        $validator = $this->validatorFactory->make($params,
            [
                'game_id' => 'required',
                'block_number' => 'required',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            // 获取游戏信息
            $game = BlockGameService::getGameInfo($params['game_id']);
            // 获取区块信息
            $block = BlockApiService::getBlockInfo((int)$params['block_number'], (int)$game['network']);
            if ($game && $block) {
                // 获取数据
                $res = BlockGamePeriodsService::packageGamePeriodsPushData($block['block_hash'], (string)$block['block_number'], $game);
                $info['game_id'] = $res['game_id'] ?? '';
                $info['open_result'] = (string)$res['announce_area'] ?? '';
                $info['block_number'] = (int)$block['block_number'];
                $info['block_hash'] = $block['block_hash'] ?? '';
                $info['transaction_hash'] = $block['transaction_hash'] ?? '';
            } else {
                $info = [
                    'game_id' => $game['game_id'],
                    'open_result' => '',
                    'block_number' => (int)$params['block_number'],
                    'block_hash' => '',
                    'transaction_hash' => '',
                ];
            }

            return $this->ReturnJson->successFul(200, $info);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getGameOpenPeriodsOpenResult.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取游戏最后区开奖块信息
     * @return null
     */
    #[GetMapping(path: 'last/openblock')]
    public function getLastOpenBlockInfo()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        $validator = $this->validatorFactory->make($params,
            [
                'game_id' => 'required',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            // 获取数据
            $res = BlockGamePeriodsService::lastOpenBlockInfo($params['game_id']);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getLastOpenBlockInfo.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取用户游戏转账下注统计数据
     * @return null
     */
    #[GetMapping(path: 'user/bet/statistics')]
    public function getUserGameBetDataStatistics()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        $validator = $this->validatorFactory->make($params,
            [
                'game_id' => 'required',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }
        $params['uid'] = $this->request->getAttribute('uid');

        try {
            // 获取数据
            $res = BlockGameBetService::userGameBetDataStatistics($params);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getUserGameBetDataStatistics.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取用户钱包地址
     * @return null
     */
    #[GetMapping(path: 'user/address/list')]
    public function getUserAddressList()
    {
        // 参数校验
        $uid = $this->request->getAttribute('uid');

        try {
            // 获取数据
            $res = UserService::userAddressList($uid);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getuserAddressList.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 获取系统配置接口
     * @return null
     */
    #[GetMapping(path: 'sys/conf')]
    public function getSysConf()
    {
        try {
            // 获取数据
            $res = SysConfService::getHashGameConf();
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.getSysConf.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 手动结算
     * @return null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[PostMapping(path: 'bet/settlement')]
    public function betSettlement()
    {
        // 参数校验
        $params = $this->request->post();
        $validator = $this->validatorFactory->make($params,
            [
                'periods_no' => 'required|integer',
                'network' => 'required|integer',
            ]
        );
        if ($validator->fails()) {
            return $this->ReturnJson->failFul(201, null, 1, $validator->errors()->first());
        }

        try {
            // 结算数据
            $res = BlockGamePeriodsService::periodsSettlement3S($params);
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.betSettlement.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * hash游戏中奖排行榜
     * @return null
     */
    #[GetMapping(path: 'ranking/hashwin')]
    public function hashWinRanking()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        try {
            // 获取数据
            $res = BlockGameBetService::winRankingList($params['ranking_type'] ?? 'real');
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.winRanking.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 下注排行榜
     * @return null
     */
    #[GetMapping(path: 'ranking/userbet')]
    public function userBetRanking()
    {
        // 参数校验
        $params = $this->request->getQueryParams();
        try {
            // 获取数据
            $res = UserService::userBetRankingList($params['ranking_type'] ?? 'day');
            return $this->ReturnJson->successFul(200, $res);
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.betRanking.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    /**
     * 更新用户排行榜数据
     * @return null
     */
    #[PostMapping(path: 'update/userbet/ranking')]
    public function updateUserBetRanking()
    {
        // 参数校验
        $params = $this->request->post();
        try {
            UserService::updateUserBetRanking($params);
            return $this->ReturnJson->successFul();
        } catch (\Exception $e) {
            $this->logger->alert('BlockGameController.updateUserBetRanking.Exception：' . $e->getMessage());
            return $this->ReturnJson->failFul($e->getCode());
        }
    }

    #[PostMapping(path: 'transfer')]
    public function tronTransfer()
    {
        // 参数校验
        $params = $this->request->post();
        $res = TronNodeService::sendTransaction($params['address'], (float)$params['amount'], $params['currency']);
        return $this->ReturnJson->successFul(200, $res);
    }
}








