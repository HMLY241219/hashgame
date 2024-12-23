<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\EnumType;
use App\Service\BaseService;
use Carbon\Carbon;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;
use function Hyperf\Support\env;

/**
 * 排行榜数据统计(哈希游戏)
 */
#[Command]
class RankingStatistics extends HyperfCommand
{
    protected string $dateFormat = 'Ymd';

    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('bg-block:ranking-statistics');
    }

    public function configure()
    {
        parent::configure();
        // 描述
        $this->setDescription('Block Game Ranking Statistics');
        // 可选项-要执行的方法
        $this->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Do Action', 'default');
    }

    public function handle()
    {
        $action = $this->input->getOption('action');
        if ($action == 'default') {
            // 期数结算
            try {
                $this->rankingData();
            } catch (\Exception $e) {
                $this->writeLog('Statistics RankingData Exception：' . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * 排行榜数据
     * @return void
     */
    protected function rankingData(): void
    {
        $currTime = Carbon::now();
        $currDate = (int)$currTime->format($this->dateFormat);
        $field = ['bet_id', 'bet_way', 'bet_level', 'uid', 'game_id', 'game_name', 'network', 'open_block',
            'block_hash', 'transaction_hash', 'bet_amount', 'bet_currency', 'is_win', 'win_lose_amount', 'settlement_amount',
            'date', 'create_time', 'update_time'];
        $limitNum = 20; // 每张表查询数据条数
        // 获取所有数据表
        $tables = Db::select("SHOW TABLES");
        $tablesList = array_column($tables, 'Tables_in_' . env('DB_DATABASE'));

        // 周数据
        $this->writeLog('Week Statistics Start');
        $weekDataList = $weekDataTmpList1 = $weekDataTmpList2 = [];
        // 计算本周开始日期和结束日期
        $currWeekDateStart = (int)$currTime->startOfWeek()->format($this->dateFormat);
        for ($cd = $currWeekDateStart; $cd < $currDate; $cd++) {
            // 检测表是否存在
            if (!in_array('br_block_game_bet_'.$cd, $tablesList)) continue;
            // 当日中奖
            $data = BaseService::getPartTb('block_game_bet', (string)$cd)
                ->where('is_win', EnumType::BET_IS_WIN_YES)
                ->select($field)
                ->orderBy('win_lose_amount', 'desc')
                ->limit($limitNum)
                ->get()->toArray();
            if ($data) {
                $weekDataTmpList1 = array_merge($weekDataTmpList1, $data);
            }
            $weekDataTmpList2[$cd] = $data;

        }
        // 排序
        array_multisort(array_column($weekDataTmpList1, 'win_lose_amount'),SORT_DESC, SORT_NUMERIC, $weekDataTmpList1);
        foreach ($weekDataTmpList1 as $k => $v) {
            if ($k+1 > $limitNum) break;
            $v['ranking_no'] = $k + 1;
            $v['ranking_type'] = EnumType::RANKING_TYPE_WIN_WEEK;
            $weekDataList[$k] = $v;
        }
        unset($weekDataTmpList1);
        $this->writeLog('Week Statistics End');

        // 月数据
        $this->writeLog('Month Statistics Start');
        $monthDataList = $monthDataTmpList1 = [];
        // 计算本月开始日期和结束日期
        $currMonthDateStart = (int)$currTime->startOfMonth()->format($this->dateFormat);
        for ($cd = $currMonthDateStart; $cd < $currDate; $cd++) {
            // 检测表是否存在
            if (!in_array('br_block_game_bet_'.$cd, $tablesList)) continue;
            if (isset($weekDataTmpList2[$cd])) {
                $monthDataTmpList1 = array_merge($monthDataTmpList1, $weekDataTmpList2[$cd]);
                continue;
            }

            // 当日中奖
            $data = BaseService::getPartTb('block_game_bet', (string)$cd)
                ->where('is_win', EnumType::BET_IS_WIN_YES)
                ->select($field)
                ->orderBy('win_lose_amount', 'desc')
                ->limit($limitNum)
                ->get()->toArray();
            if ($data) {
                $monthDataTmpList1 = array_merge($monthDataTmpList1, $data);
            }
        }
        // 排序
        array_multisort(array_column($monthDataTmpList1, 'win_lose_amount'),SORT_DESC, SORT_NUMERIC, $monthDataTmpList1);
        foreach ($monthDataTmpList1 as $k => $v) {
            if ($k+1 > $limitNum) break;
            $v['ranking_no'] = $k + 1;
            $v['ranking_type'] = EnumType::RANKING_TYPE_WIN_MONTH;
            $monthDataList[$k] = $v;
        }
        $this->writeLog('Month Statistics End');

        // 插入数据
        $this->addRankingData(array_merge($monthDataList, $weekDataList));
    }

    /**
     * 添加排行数据
     * @param array $data
     * @return void
     */
    public function addRankingData(array $data): void
    {
        // 截断表
        Db::select("TRUNCATE TABLE br_block_game_bet_ranking");
        // 插入数据
        BaseService::getPoolTb('block_game_bet_ranking')->insert($data);
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
