<?php

declare(strict_types=1);

namespace App\Command;


use App\Enum\EnumType;
use App\Service\BaseService;
use App\Service\BlockApi\BlockApiService;
use App\Service\BlockApi\TronNodeService;
use App\Service\BlockGamePeriodsService;
use App\Service\BlockGameService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Coroutine\Coroutine;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputOption;
use WsProto\Block\MessageId;
use function Hyperf\Support\env;
use Hyperf\Di\Annotation\Inject;
use Hyperf\WebSocketClient\ClientFactory;

/**
 * 推送最近区块
 */
#[Command]
class PushLatestBlock extends HyperfCommand
{
    #[Inject]
    protected LoggerInterface $logger;

    #[Inject]
    protected ClientFactory $clientFactory;
    protected array $blockTrx = [];
    protected array $blockNumberBsc = [];
    protected bool $initPushTime = true;

    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('bg-block:push-latest-block');
    }

    public function configure()
    {
        parent::configure();
        // 描述
        $this->setDescription('Push Latest Block To All Client');
        // 可选项-要执行的方法
        $this->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Do Action', 'default');
    }

    public function handle()
    {
        $action = $this->input->getOption('action');
        if ($action == 'default') {
            $this->pushLatestBlock();
        }
    }

    public function pushLatestBlock()
    {
        $this->writeLog('Push Start');
        // 对端服务的地址，如没有提供 ws:// 或 wss:// 前缀，则默认补充 ws://
        $host = env('URL_WEBSOCKET', 'ws://127.0.0.1:9511/ws');
        // 通过 ClientFactory 创建 Client 对象，创建出来的对象为短生命周期对象
        $client = $this->clientFactory->create($host, false);
        // 缓存所有游戏
        $this->getGameList();
        $sleepTime = 3; // 睡眠时间

        while (true) {
            // 获取最近区块
            $block = $this->getLatestBlock();
            // 纠正区块时间与当前时间戳的差距
            if ($this->initPushTime) {
                $timeTmp = explode(' ', microtime());
                $currTime = bcadd($timeTmp[1], $timeTmp[0], 4);
                // 当前时间与区块生成时间的时间差
                $timeDiff = (float)bcsub($currTime, (string)$block['timestamp'], 4);
                $this->writeLog('$timeDiff：' . $timeDiff);
                if ($timeDiff <= 3){
                    // 推送下一个区块
                    Coroutine::sleep(3.15 - $timeDiff);
                    $block['block_number'] += 1;
                    $block['timestamp'] += 3;
                } else {
                    // 推送下下一个区块
                    Coroutine::sleep(6.15 - $timeDiff);
                    $block['block_number'] += 2;
                    $block['timestamp'] += 6;
                }
                $this->initPushTime = false; // 初始化完成
            }

            if ($block) {
                // 缓存最后开奖区块
                $this->cacheLastOpenBlock($block);
                // 推送区块消息
                \Hyperf\Coroutine\go(function () use ($client, $block) {
                    // 发送TRX区块消息
                    $msgSend = json_encode([
                        'msg_id' => MessageId::MSG_LATEST_BLOCK,
                        'data' => $block
                    ]);
                    $client->push($msgSend);
                    // 发送开奖结果消息
                    $client->push(json_encode([
                        'msg_id' => MessageId::MSG_OPEN_RES,
                        'data' => json_encode($this->getGamesOpenResult($block))
                    ]));
                });
            }

            Coroutine::sleep($sleepTime);
        }
    }

    /**
     * 缓存最后开奖区块
     * @param array $block
     * @param int $network
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function cacheLastOpenBlock(array $block, int $network = 1): void
    {
        // 缓存最后开奖区块
        BaseService::setCache(EnumType::PERIODS_LAST_OPEN_BLOCK_CACHE_3S.$network, $block);
        // 缓存1分最后开奖区块
        if ($block['block_number'] % EnumType::NEXT_BLOCK_INCREMENT_1M == 0) {
            BaseService::setCache(EnumType::PERIODS_LAST_OPEN_BLOCK_CACHE_1M.$network, $block);
        }
        // 缓存3分最后开奖区块
        if ($block['block_number'] % EnumType::NEXT_BLOCK_INCREMENT_3M == 0) {
            BaseService::setCache(EnumType::PERIODS_LAST_OPEN_BLOCK_CACHE_3M.$network, $block);
        }
    }

    /**
     * 获取所有游戏当前区块开奖结果
     * @param array $openBlock
     * @return array
     */
    public function getGamesOpenResult(array $openBlock): array
    {
        // 获取所有游戏
        $gameList = $this->getGameList();
        $pushData = [
            'block_number' => $openBlock['block_number'],
            'block_hash' => $openBlock['block_hash'],
            'transaction_hash' => $openBlock['transaction_hash'] ?? '',
            'timestamp' => $openBlock['timestamp'],
        ];
        foreach ($gameList as $game) {
            $pushData['results'][] = BlockGamePeriodsService::packageGamePeriodsPushData($openBlock['block_hash'], (string)$openBlock['block_number'], $game);
        }
        return $pushData;
    }

    /**
     * 获取游戏列表
     * @return array
     */
    public function getGameList(): array
    {
        return BlockGameService::getGameList([
            'network' => EnumType::NETWORK_TRX,
            'field' => ['game_id', 'game_type_second']
        ], true);
    }

    /**
     * 获取最近区块
     * @return array
     */
    public function getLatestBlock(): array
    {
        // 波场
        $block = TronNodeService::getLatestBlock();
        if (!empty($block)) {
            $block['network'] = EnumType::NETWORK_TRX;
        }
        $this->writeLog('tronBlock：' . $block['block_number'] ?? '');

        // 币安
//        \Hyperf\Coroutine\go(function () {
//            $this->writeLog('getBscBlockStart');
//            $block = BscScanService::getLatestBlock();
//            if (!empty($block)) {
//                $block['network'] = EnumType::NETWORK_BSC;
//                $this->blockNumberBsc = $block;
//            }
//            $this->writeLog('bscBlock：' . $block['block_number'] ?? '');
//            $this->writeLog('getBscBlockEnd');
//        });

        return $block;
    }

    /**
     * 写日志
     * @param string $msg
     * @param string $type
     * @return void
     */
    public function writeLog(string $msg, string $type = 'info'): void
    {
        $this->line('[' . date('Y-m-d H:i:s') . ']' . $msg, $type);
    }
}
