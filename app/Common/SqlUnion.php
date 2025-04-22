<?php

namespace App\Common;


use Hyperf\DbConnection\Db;
use function Hyperf\Config\config;
use Hyperf\Context\ApplicationContext;
use Psr\Log\LoggerInterface;
use Hyperf\Di\Annotation\Inject;

/**
 *  联合查询
 */
class SqlUnion
{


    private string $prefix = 'br_';

    #[Inject]
    protected LoggerInterface $logger;
    /**
     * 分表查询直接返回所有数据
     * @param $dateList array [20231127,20231126] 数据表示查询这2张表
     * @param $table string 数据表，要加前缀,结尾加_  'game_'
     * @param $field string 字段
     * @param $where array  数组
     * @param $orderField string 排序的字段
     * @param $page int 当前页数
     * @param $limit int 每一页的数量
     * @param $sort string 排巽方式
     * @return array
     */
    public function subTableQueryPage(array $dateList, string $table, string $field, array $where = [], string $orderField = '', int $page = 1, int $limit = 20, string $sort = 'desc'): array
    {
        $builder = Db::table($table . $dateList[0])->selectRaw($field)->where($where);
        for ($i = 1; $i < count($dateList); $i++) {
            $tables = $this->prefix.$table . $dateList[$i];
            $res = Db::select("SHOW TABLES LIKE '$tables'");
            if (!$res) {
                continue;
            }
            $builder->unionAll(function ($query) use ($dateList, $i, $field, $where, $table) {
                $query->from($table . $dateList[$i])->selectRaw($field)->where($where);
            });
        }

        try {
            return $builder->forPage($page, $limit)->orderBy($orderField, $sort)->get()->toArray();
        } catch (\Throwable $e) {
            // 在这里处理异常，例如记录日志或者抛出异常
            $this->logger->error($e->getMessage());
            return [];
        }
    }



    /**
     * 分表查询直接返回所有数据
     *
     * @return void
     * @param $dateList array  [20231127,20231126] 数据表示查询这2张表
     * @param $table string 数据表，要加前缀,结尾加_  'game_'
     * @param $filed string  查询的字段
     * @param $where array  查询条件
     * @param $orderField string 排序的字段
     * @param $sort string 排序方式
     */
    public function SubTableQueryList(array $dateList,string $table,string $field,array $where = [],string $orderField = '',string $sort = 'desc'):array{

        $builder = Db::table($table . $dateList[0])->selectRaw($field)->where($where);
        for ($i = 1; $i < count($dateList); $i++) {
            $tables = $this->prefix.$table . $dateList[$i];
            $res = Db::select("SHOW TABLES LIKE '$tables'");
            if (!$res) {
                continue;
            }
            $builder->unionAll(function ($query) use ($dateList, $i, $field, $where, $table) {
                $query->from($table . $dateList[$i])->selectRaw($field)->where($where);
            });
        }

        try {
            if($orderField){
                return $builder->orderBy($orderField,$sort)->get()->toArray();
            }else{
                return $builder->get()->toArray();
            }
        } catch (\Throwable $e) {
            // 在这里处理异常，例如记录日志或者抛出异常
            $this->logger->error($e->getMessage());
            return [];
        }
    }

}
