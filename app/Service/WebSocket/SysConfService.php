<?php

namespace App\Service\WebSocket;

use App\Enum\EnumType;
use App\Service\BaseService;

/**
 * 系统配置服务
 */
class SysConfService extends BaseService
{
    // 表名
    protected static string $tbName = 'system_config';

    /**
     * 获取配置列表
     * @param array $params
     * @param bool $cached
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getConf(array $params = [], bool $cached = true): array
    {
        // 从缓存获取
        $hTbName = EnumType::SYS_CONF_CACHE_KEY_LIST.self::createHashKey($params);
        $cacheData = self::getCache($hTbName);
        if ($cacheData && $cached) {
            $conf = $cacheData;
        } else {
            // 字段
            $field = empty($params['field']) ? ['*'] : $params['field'];
            // 排序
            $order = empty($params['order']) ? 'sort desc' : $params['order'];

            $list = self::getPoolTb(self::$tbName)
                ->where('status', EnumType::SYS_CONF_STATUS_YES)
                ->when(!empty($params['config_tab_id']), function ($query) use ($params) {
                    $query->where('config_tab_id', $params['config_tab_id']);
                })
                ->when(!empty($params['menu_name']), function ($query) use ($params) {
                    $query->where('menu_name', $params['menu_name']);
                })
                ->orderByRaw($order)
                ->select($field)->get()->toArray();

            $conf = [];
            foreach ($list as $v) {
                $conf[$v['menu_name']] = str_replace('"', '', $v['value']);
            }
            unset($list);

            // 数据缓存
            self::setCache($hTbName, $conf, self::$cacheExpire);
        }

        return $conf;
    }

    /**
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function getHashGameConf()
    {
        return self::getConf([
            'config_tab_id' => EnumType::SYS_CONF_TYPE_HASH_GAME,
        ]);
    }
}