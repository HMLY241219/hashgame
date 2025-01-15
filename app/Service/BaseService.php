<?php

namespace App\Service;

use App\Common\Common;
use App\Enum\EnumType;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

class BaseService
{
    #[Inject]
    protected LoggerInterface $logger;

    // 时间格式
    public static string $dateTimeFormat = 'Y-m-d H:i:s';
    public static string $dateFormat = 'Y-m-d';

    // 分页条数
    protected static int $pageSize = 10;

    // 缓存
    public static int $cacheExpire = 3600 * 24 * 7; // 缓存统一过期时间

    // 金额精度
    protected static int $amountDecimal = 100;

    /**
     * 获取分表
     * @param string $tbName
     * @param string $suffix 分表后缀
     * @param string $connPool
     * @return \Hyperf\Database\Query\Builder
     */
    public static function getPartTb(string $tbName, string $suffix = '', string $connPool = 'default'): \Hyperf\Database\Query\Builder
    {
        $tbName .= empty($suffix) ? '_' . date('Ymd') : '_' . $suffix;
        return Db::connection($connPool)->table($tbName);
    }

    /**
     * 获取分库表
     * @param string $tbName
     * @param string $connPool
     * @return \Hyperf\Database\Query\Builder
     */
    public static function getPoolTb(string $tbName, string $connPool = 'default'): \Hyperf\Database\Query\Builder
    {
        return Db::connection($connPool)->table($tbName);
    }

    /**
     * 获取分表后缀
     * @param array $params
     * @return mixed|string
     */
    public static function getTbSuffix(array $params = []): mixed
    {
        if (!empty($params['date'])) {
            $suffix = $params['date'];
        } else if (!empty($params['time'][0])) {
            $suffix = date('Ymd', strtotime($params['time'][0]));
        } else {
            $suffix = date('Ymd');
        }
        return $suffix;
    }

    /**
     * 执行sql语句
     * @param string $sql
     * @param string $connPool
     * @return array
     */
    public static function doQuery(string $sql, string $connPool = 'default'): array
    {
        return Db::connection($connPool)->select($sql);
    }

    /**
     * 获取批量更新sql
     * @param string $tbName
     * @param array $data
     * @param string $pkField
     * @return string
     */
    public static function getBatchUpdateSql(string $tbName, array $data, string $pkField): string
    {
        if (empty($data)) return '';

        $sql = "UPDATE {$tbName} SET ";
        $setFields = [];
        $fieldArr = array_keys($data[0]);
        unset($fieldArr[array_search($pkField, $fieldArr)]); // 过滤主键
        foreach ($fieldArr as $field) {
            $sqlTmp = "{$field} = CASE {$pkField} ";
            foreach ($data as $item) {
                $sqlTmp .= sprintf(" WHEN %s THEN '%s'", $item[$pkField], $item[$field]);
            }
            $sqlTmp .= " END";
            $setFields[] = $sqlTmp;
        }
        $ids = implode(',', array_column($data, $pkField));
        $sql .= implode(',', $setFields) . " WHERE {$pkField} IN ({$ids})";

        return $sql;
    }

    /**
     * 设置缓存
     * @param string $hTbName hash表名
     * @param array $params 存储数据
     * @param int $expire 过期时间
     * @param string $pool 连接池
     * @param int $dbIndex 数据库索引
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function setCache(string $hTbName, array $params, int $expire = 0, string $pool = 'default', int $dbIndex = 0): void
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        $cache->hMSet($hTbName, $params);
        if ($expire > 0) {
            $cache->expire($hTbName, $expire);
        }
    }

    /**
     * 设置hash字段缓存
     * @param string $hTbName
     * @param string $field
     * @param $value
     * @param int $expire
     * @param string $pool
     * @param int $dbIndex 数据库索引
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function setFieldCache(string $hTbName, string $field, $value, int $expire = 0, string $pool = 'default', int $dbIndex = 0): void
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        $cache->hSet($hTbName, $field, $value);
        if ($expire > 0) {
            $cache->expire($hTbName, $expire);
        }
    }

    /**
     * 获取缓存
     * @param string $hTbName hash表名
     * @param string $pool 连接池
     * @param int $dbIndex 数据库索引
     * @return array|bool|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getCache(string $hTbName, string $pool = 'default', int $dbIndex = 0): array|bool|\Redis
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        return $cache->hGetAll($hTbName);
    }

    /**
     * 获取hash字段缓存
     * @param string $hTbName
     * @param string $field
     * @param string $pool
     * @param int $dbIndex 数据库索引
     * @return array|bool|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getFieldCache(string $hTbName, string $field, string $pool = 'default', int $dbIndex = 0): array|bool|\Redis
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        return $cache->hGet($hTbName, $field);
    }

    /**
     * 设置缓存锁，key不存在的时候，才能set成功，也即保证只有第一个客户端请求才能获得锁，而其他客户端请求只能等其释放锁，才能获取
     * @param string $lockName
     * @param int $expire
     * @param string $pool
     * @param int $dbIndex 数据库索引
     * @return array|bool|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function setCacheLock(string $lockName, int $expire = 5, string $pool = 'default', int $dbIndex = 0): array|bool|\Redis
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        return $cache->set($lockName, 1, ['nx', 'ex' => $expire]);
    }

    /**
     * 遍历缓存键值
     * @param int $cursor
     * @param string $prefix
     * @param string $pool
     * @param int $dbIndex 数据库索引
     * @return array|bool|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function scanCacheKeys(int $cursor = 0, string $prefix = '*', string $pool = 'default', int $dbIndex = 0): array|bool|\Redis
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        return $cache->scan($cursor, $prefix, 20);
    }

    /**
     * 获取匹配的缓存键值
     * @param string $prefix
     * @param string $pool
     * @param int $dbIndex 数据库索引
     * @return array|bool|\Redis
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getCacheKeys(string $prefix = '*', string $pool = 'default', int $dbIndex = 0): array|bool|\Redis
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        return $cache->keys($prefix);
    }

    /**
     * 删除缓存
     * @param string $hTbName
     * @param string $pool
     * @param int $dbIndex 数据库索引
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function delCache(string $hTbName, string $pool = 'default', int $dbIndex = 0): void
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        $cache->del($hTbName);
    }

    /**
     * 删除hash表字段缓存
     * @param string $hTbName
     * @param string $field
     * @param string $pool
     * @param int $dbIndex 数据库索引
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function hDelCache(string $hTbName, string $field, string $pool = 'default', int $dbIndex = 0): void
    {
        $cache = Common::Redis($pool);
        $cache->select($dbIndex);
        $cache->hDel($hTbName, $field);
    }

    /**
     * 根据传入数据创建HashKey
     * @param array $params
     * @return string
     */
    public static function createHashKey(array $params): string
    {
        return md5(serialize($params));
    }

    public static function getNetworkLatestBlockCacheKey(int $network = EnumType::NETWORK_TRX): string
    {
        return match ($network) {
            EnumType::NETWORK_TRX => EnumType::LATEST_BLOCK_CACHE_TRX,
            EnumType::NETWORK_ETH => EnumType::LATEST_BLOCK_CACHE_ETH,
            EnumType::NETWORK_BSC => EnumType::LATEST_BLOCK_CACHE_BSC,
        };
    }

    /**
     * 根据字符获取区块网络
     * @param string $networkChar
     * @return string
     */
    public static function getBlockNetworkByChar(string $networkChar = EnumType::NETWORK_CHAR_TRX): string
    {
        return match ($networkChar) {
            EnumType::NETWORK_CHAR_TRX => EnumType::NETWORK_TRX,
            EnumType::NETWORK_CHAR_ETH => EnumType::NETWORK_ETH,
            EnumType::NETWORK_CHAR_BSC => EnumType::NETWORK_BSC,
        };
    }

    /**
     * 根据编号获取区块网络
     * @param int $number
     * @return string
     */
    public static function getBlockNetworkByNumber(int $number = EnumType::NETWORK_TRX): string
    {
        return match ($number) {
            EnumType::NETWORK_TRX => EnumType::NETWORK_CHAR_TRX,
            EnumType::NETWORK_ETH => EnumType::NETWORK_CHAR_ETH,
            EnumType::NETWORK_BSC => EnumType::NETWORK_CHAR_BSC,
        };
    }

    /**
     * 通过货币number获取货币字符
     * @param int $number
     * @return string
     */
    public static function getBetCurrencyByNumber(int $number = EnumType::BET_CURRENCY_COIN): string
    {
        return match ($number) {
            EnumType::BET_CURRENCY_COIN => EnumType::BET_CURRENCY_CHAR_COIN,
            EnumType::BET_CURRENCY_USDT => EnumType::BET_CURRENCY_CHAR_USDT,
            EnumType::BET_CURRENCY_TRX => EnumType::BET_CURRENCY_CHAR_TRX,
        };
    }

    /**
     * 通过货币字符获取货币number
     * @param string $char
     * @return int
     */
    public static function getBetCurrencyByChar(string $char = EnumType::BET_CURRENCY_CHAR_COIN): int
    {
        return match (strtolower($char)) {
            EnumType::BET_CURRENCY_CHAR_COIN => EnumType::BET_CURRENCY_COIN,
            EnumType::BET_CURRENCY_CHAR_USDT => EnumType::BET_CURRENCY_USDT,
            EnumType::BET_CURRENCY_CHAR_TRX => EnumType::BET_CURRENCY_TRX,
        };
    }

    /**
     * 日志打印
     * @return LoggerInterface
     */
    public static function logger()
    {
        return (new static())->logger;
    }
}