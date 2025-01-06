<?php

namespace App\Enum;

class EnumType
{
    public const WS_CLIENT_FD_CACHE_KEY = 'WS_CLIENT_FD'; // websocket客户端链接ID缓存KEY
    public const WS_CLIENT_PUSH_BLOCK = 'WS_PUSH_BLOCK'; // websocket客户端链接推送区块
    // 缓存锁
    public const LOCK_LAST_OPEN_BLOCK = 'LOCK_LAST_OPEN_BLOCK'; // 锁定最后开奖区块
    public const LOCK_BET_BY_TRANS_HASH = 'LOCK_BET_BY_TRANS_HASH'; // 锁定转账下注交易hash
    // 区块网络
    public const NETWORK_TRX = 1; // 波场
    public const NETWORK_ETH = 2; // 以太坊
    public const NETWORK_BSC = 3; // 币安
    public const NETWORK_CHAR_TRX = 'TRX'; // 波场
    public const NETWORK_CHAR_ETH = 'ETH'; // 以太坊
    public const NETWORK_CHAR_BSC = 'BSC'; // 币安

    // 代币符号
    public const TOKEN_SYMBOL_USDT = 'USDT'; // USDT代币符号
    public const TOKEN_SYMBOL_TRX = 'TRX'; // TRX代币符号

    // 最近区块缓存KEY
    public const LATEST_BLOCK_CACHE_TRX = 'LATEST_BLOCK_TRX'; // 最近区块缓存KEY-波场
    public const LATEST_BLOCK_CACHE_ETH = 'LATEST_BLOCK_ETH'; // 最近区块缓存KEY-以太坊
    public const LATEST_BLOCK_CACHE_BSC = 'LATEST_BLOCK_BSC'; // 最近区块缓存KEY-币安

    // 交易hash
    public const TRANSACTION_HASH_INFO_PREFIX = 'TRANSACTION_HASH_'; // 交易hash详情前缀

    /*区块游戏*/
    // 房间等级
    public const ROOM_CJ = 1; // 初级场
    public const ROOM_ZJ = 2; // 中级场
    public const ROOM_GJ = 3; // 高级场
    public const ROOM_KEY_CJ = 'room_cj'; // 初级场
    public const ROOM_KEY_ZJ = 'room_zj'; // 中级场
    public const ROOM_KEY_GJ = 'room_gj'; // 高级场
    // 玩法
    public const PLAY_METHOD_HASH_BALANCE = 1; // 余额hash
    public const PLAY_METHOD_HASH_1M = 2; // 1分hash
    public const PLAY_METHOD_HASH_3M = 3; // 3分hash
    // 下一开奖区块增加区块数量
    public const NEXT_BLOCK_INCREMENT_3S= 1; // 余额hash增量
    public const NEXT_BLOCK_INCREMENT_1M= 20; // 1分hash增量
    public const NEXT_BLOCK_INCREMENT_3M= 60; // 3分hash增量
    // 游戏类型
    public const GAME_TYPE_HASH_DX = 1; // hash大小
    public const GAME_TYPE_HASH_DS = 2; // hash单双
    public const GAME_TYPE_HASH_NN = 3; // hash牛牛
    public const GAME_TYPE_HASH_ZX = 4; // hash幸运庄闲
    public const GAME_TYPE_HASH_XY = 5; // hash幸运
    public const GAME_TYPE_HASH_HZ_DX = 6; // hash和值大小
    public const GAME_TYPE_HASH_HZ_DS = 7; // hash和值单双
    public const GAME_TYPE_HASH_DX_1M = 101; // hash1分大小
    public const GAME_TYPE_HASH_DS_1M = 102; // hash1分单双
    public const GAME_TYPE_HASH_NN_1M = 103; // hash1分牛牛
    public const GAME_TYPE_HASH_ZX_1M = 104; // hash1分庄闲
    public const GAME_TYPE_HASH_XY_1M = 105; // hash1分幸运
    public const GAME_TYPE_HASH_DX_3M = 301; // hash3分大小
    public const GAME_TYPE_HASH_DS_3M = 302; // hash3分单双
    public const GAME_TYPE_HASH_NN_3M = 303; // hash3分牛牛
    public const GAME_TYPE_HASH_ZX_3M = 304; // hash3分庄闲
    public const GAME_TYPE_HASH_XY_3M = 305; // hash3分幸运

    /*游戏下注*/
    public const BET_DATA_PREFIX = 'BET_DATA_'; // 下注数据缓存前缀
    public const BET_ADDRESS_BIND_GAME_PREFIX = 'BET_ADDRESS_GAME_INFO_'; // 下注地址绑定游戏缓存前缀
    public const BET_BY_TRANS_HASH_PREFIX = 'BET_BY_TRANS_HASH_'; // 通过转账交易hash下注缓存前缀
    // 下注方式
    public const BET_WAY_BALANCE = 1; // 平台余额
    public const BET_WAY_TRANSFER = 2; // 地址转账
    // 下注币种
    public const BET_CURRENCY_COIN = 1; // 金币
    public const BET_CURRENCY_USDT = 2; // USDT
    public const BET_CURRENCY_TRX = 3; // TRX
    public const BET_CURRENCY_CHAR_COIN = 'coin'; // 金币
    public const BET_CURRENCY_CHAR_USDT = 'usdt'; // USDT
    public const BET_CURRENCY_CHAR_TRX = 'trx'; // TRX
    // 代币合约地址
    public const CURRENCY_CONTRACT_TRON_USDT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'; // 波场USDT
    // 下注区域
    public const BET_AREA_LEFT = 1; // 左
    public const BET_AREA_RIGHT = 2; // 右
    public const BET_AREA_CENTER = 3; // 中
    // 下注状态
    public const BET_STATUS_WAITE = 0; // 待结算
    public const BET_STATUS_COMPLETE = 1; // 已完成
    public const BET_STATUS_REFUND = 2; // 已退款
    // 开奖状态
    public const BET_IS_OPEN_YES = 1; // 已开奖
    public const BET_IS_OPEN_NO = 0; // 未开奖
    // 开奖状态
    public const BET_IS_WIN_YES = 1; // 赢
    public const BET_IS_WIN_NO = 2; // 输
    public const BET_IS_WIN_EQUAL = 0; // 和
    // 是否有效
    public const BET_IS_VALID_YES = 1; // 有效
    public const BET_IS_VALID_NO = 0; // 无效
    // 下注用户信息缓存key
    public const BET_USER_INFO_PREFIX = 'BET_USER_INFO_';

    /*开奖期数结算*/
    public const PERIODS_LIST_PREFIX = 'PERIODS_LIST_'; // 开奖期数信息
    public const PERIODS_INFO_PREFIX = 'PERIODS_INFO_'; // 开奖期数信息
    // 最后开奖区块缓存
    public const PERIODS_LAST_OPEN_BLOCK_CACHE_3S = 'PERIODS_LAST_OPEN_BLOCK_3S_'; // 3秒
    public const PERIODS_LAST_OPEN_BLOCK_CACHE_1M = 'PERIODS_LAST_OPEN_BLOCK_1M_'; // 1分
    public const PERIODS_LAST_OPEN_BLOCK_CACHE_3M = 'PERIODS_LAST_OPEN_BLOCK_3M_'; // 3分
    // 最后结算区块缓存
    public const PERIODS_LAST_SETTLEMENT_BLOCK_CACHE = 'PERIODS_LAST_SETTLEMENT_BLOCK_';
    // 丢失的结算区块缓存
    public const PERIODS_MISS_BLOCK_CACHE = 'PERIODS_MISS_BLOCK_';

    /*系统配置*/
    public const SYS_CONF_CACHE_KEY_LIST = 'SYS_CONF_LIST_'; // 系统配置列表缓存key
    public const SYS_CONF_STATUS_YES = 1; // 系统配置状态-隐藏
    public const SYS_CONF_STATUS_NO = 0; // 系统配置状态-不隐藏
    public const SYS_CONF_TYPE_HASH_GAME = 33; // 系统配置类型-hash游戏

    /*排行榜*/
    public const RANKING_TYPE_WIN_WEEK = 1; // 周中奖类型
    public const RANKING_TYPE_WIN_MONTH = 2; // 月中奖类型

    /*队列*/
    public const QUEUE_ACTION_BLOCK_SETTLEMENT = 1; // 区块结算
}
