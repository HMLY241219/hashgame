<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\EnumType;
use App\Service\BaseService;
use App\Service\BlockApi\TronNodeService;
use App\Service\BlockGamePeriodsService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Coroutine\Coroutine;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;
use function Hyperf\Support\env;

/**
 * 开奖期数结算
 */
#[Command]
class PeriodsSettlement extends HyperfCommand
{
    protected bool $initPushTime = true;

    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('bg-block:periods-settlement');
    }

    public function configure()
    {
        parent::configure();
        // 描述
        $this->setDescription('Block Game Periods Settlement');
        // 可选项-要执行的方法
        $this->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Do Action', 'default');
    }

    public function handle()
    {
        $action = $this->input->getOption('action');
        if ($action == 'default') {
            // 期数结算
            $this->periodsSettlement();
        }
    }

    protected function periodsSettlement()
    {
        $this->writeLog('Periods Settlement Start');
        $sleepTime = 3; // 睡眠时间

        while (true) {
            // 获取最近区块
            $block = $this->getLatestBlock();
            if ($this->initPushTime) {
                $timeTmp = explode(' ', microtime());
                $currTime = bcadd($timeTmp[1], $timeTmp[0], 4);
                // 当前时间与区块生成时间的时间差
                $timeDiff = (float)bcsub($currTime, (string)$block['timestamp'], 4);
                if ($timeDiff <= 3){
                    // 结算下一个区块
                    Coroutine::sleep(3.3 - $timeDiff);
                    $block['block_number'] += 1;
                    $block['timestamp'] += 3;
                } else {
                    // 结算下下一个区块
                    Coroutine::sleep(6.3 - $timeDiff);
                    $block['block_number'] += 2;
                    $block['timestamp'] += 6;
                }
                $this->initPushTime = false; // 初始化完成
            }

            if ($block) {
                // 缓存最后结算区块号
                \Hyperf\Coroutine\go(function () use ($block) {
                    $blockNumber = BlockGamePeriodsService::periodsSettlement($block['block_number'], EnumType::NETWORK_TRX);
                    $this->writeLog('Periods Settlement BlockNumber：' . $blockNumber);
                });

                // 缓存没有结算到丢失的区块
                $this->cacheMissBlock($block);
            }

            Coroutine::sleep($sleepTime);
        }
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
     * 缓存丢失的区块
     * @param array $currBlock
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function cacheMissBlock(array $currBlock): void
    {
        try {
            // 获取最后结算区块
            $lastBlockCacheKey = EnumType::PERIODS_LAST_SETTLEMENT_BLOCK_CACHE . EnumType::NETWORK_TRX;
            $lastBlock = BaseService::getCache($lastBlockCacheKey);
            if ($lastBlock) {
                // 检测当前结算区块号和最后结算区块号之间差距
                $diffNum = $currBlock['block_number'] - $lastBlock['block_number'];
                if ($diffNum > 1) {
                    // 获取未结算到的区块
                    $cacheKey = EnumType::PERIODS_MISS_BLOCK_CACHE . EnumType::NETWORK_TRX;
                    for ($i = 1; $i < $diffNum; $i++) {
                        $field = (string)($lastBlock['block_number'] + $i);
                        // 缓存未计算到的区块号
                        BaseService::setFieldCache($cacheKey, $field, 1);
                    }
                }
            }

            // 缓存最后结算区块
            BaseService::setCache($lastBlockCacheKey, $currBlock);

        } catch (\Throwable $exception) {
            $this->writeLog('cacheMissBlock.Exception：' . $exception->getMessage());
        }

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
