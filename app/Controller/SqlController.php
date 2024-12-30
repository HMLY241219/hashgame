<?php
declare(strict_types=1);
namespace App\Controller;



use Hyperf\DbConnection\Db;

/**
 * 分日期数据表处理
 * Class PublicController
 * @package app\api\controller
 */
class SqlController
{

    /**
     *m 每日登录数据表
     * @return void
     */
    public static function getLoginTable($date = ''){
        $table = 'br_login_' . ($date ?: date('Ymd'));
        $res = Db::select("SHOW TABLES LIKE '$table'");
        if(!$res){
            $sql = "CREATE TABLE `$table` (
                      `uid` bigint(20) NOT NULL,
                      `channel` int(11) DEFAULT '0',
                      `package_id` int(11) DEFAULT '0' COMMENT '包id',
                      `createtime` int(11) DEFAULT NULL COMMENT '最近登录时间',
                      PRIMARY KEY (`uid`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户每日登录数据表'";
            Db::select($sql);
        }

    }



    /**
     *m 每日登录数据表
     * @return void
     */
    public static function getRegistTable($date = ''){
        $table = 'br_regist_' . ($date ?: date('Ymd'));
        $res = Db::select("SHOW TABLES LIKE '$table'");
        if(!$res){
            $sql = "CREATE TABLE `$table` (
                      `uid` bigint(20) NOT NULL,
                      `channel` int(11) DEFAULT '0',
                      `package_id` int(11) DEFAULT '0' COMMENT '包id',
                      `createtime` int(11) DEFAULT NULL COMMENT '注册时间',
                      PRIMARY KEY (`uid`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户注册表'";
            Db::select($sql);
        }

    }


    /**
     *m 用户余额变化表
     * @return void
     */
    public static function getCoinTable($date = ''){
        $table = 'br_coin_' . ($date ?: date('Ymd'));
        $res = Db::select("SHOW TABLES LIKE '$table'");
        if(!$res){
            $sql = "CREATE TABLE `$table` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `uid` bigint(15) NOT NULL,
                      `num` bigint(15) DEFAULT '0' COMMENT '操作数',
                      `total` bigint(20) DEFAULT '0' COMMENT '操作结果',
                      `reason` int(11) DEFAULT '0' COMMENT '操作原因',
                      `type` int(2) DEFAULT '1' COMMENT '类型:1发放/0回收',
                      `content` varchar(255) DEFAULT NULL COMMENT '备注',
                      `channel` int(11) DEFAULT '0',
                      `package_id` int(11) DEFAULT '0' COMMENT '包id',
                      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
                      PRIMARY KEY (`id`),
                      KEY `uid` (`uid`)
                    ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='用户余额变化表'";
            Db::select($sql);
        }

    }

    /**
     * 用户bonus变化表
     * @return void
     */
    public static function getBonusTable($date = ''){
        $table = 'br_bonus_' . ($date ?: date('Ymd'));
        $res = Db::select("SHOW TABLES LIKE '$table'");
        if(!$res){
            $sql = "CREATE TABLE `$table` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `uid` bigint(15) NOT NULL,
                      `num` bigint(15) DEFAULT '0' COMMENT '操作数',
                      `total` bigint(20) DEFAULT '0' COMMENT '操作结果',
                      `reason` int(11) DEFAULT '0' COMMENT '操作原因',
                      `type` int(2) DEFAULT '1' COMMENT '类型:1发放/0回收',
                      `content` varchar(255) DEFAULT NULL COMMENT '备注',
                      `channel` int(11) DEFAULT '0',
                      `package_id` int(11) DEFAULT '0' COMMENT '包id',
                      `createtime` int(11) DEFAULT NULL COMMENT '创建时间',
                      PRIMARY KEY (`id`),
                      KEY `uid` (`uid`)
                    ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='用户余额变化表'";
            Db::select($sql);
        }

    }


    /**
     *m 用户每日数据表
     * @return void
     */
    public static function getUserDayTable($date = ''){
        $table = 'br_user_day_' . ($date ?: date('Ymd'));
        $res = Db::select("SHOW TABLES LIKE '$table'");
        if(!$res){
            $sql = "CREATE TABLE `$table` (
                      `uid` bigint(20) NOT NULL COMMENT '用户id',
                      `puid` bigint(20) DEFAULT '0' COMMENT '上级用户ID',
                      `vip` int(11) DEFAULT '0' COMMENT 'vip',
                      `channel` int(11) DEFAULT '0' COMMENT '渠道号',
                      `package_id` int(11) DEFAULT '0' COMMENT '包id',
                      `cash_total_score` bigint(20) DEFAULT '0' COMMENT 'Cash总输赢',
                      `bonus_total_score` bigint(20) DEFAULT '0' COMMENT 'Bonus总输赢',
                      `total_cash_water_score` bigint(20) DEFAULT '0' COMMENT 'Cash游戏流水',
                      `total_bonus_water_score` bigint(20) DEFAULT '0' COMMENT 'Bonus游戏流水',      
                      `total_game_num` bigint(20) DEFAULT '0' COMMENT '总游戏次数',                   
                      `total_pay_score` bigint(20) DEFAULT '0' COMMENT '总充值金额',
                      `total_give_score` bigint(20) DEFAULT '0' COMMENT '总赠送',
                      `total_pay_num` bigint(20) DEFAULT '0' COMMENT '总充值次数',
                      `total_exchange` bigint(20) DEFAULT '0' COMMENT '总提现金额',
                      `total_exchange_num` bigint(20) DEFAULT '0' COMMENT '总提现次数',
                      `updatetime` bigint(20) DEFAULT '0' COMMENT '更新时间',
                      PRIMARY KEY (`uid`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户余额变化表'";
            Db::select($sql);
        }

    }


    /**
     * 获取三方游戏记录数据表
     * @return void
     */
    public static function getSlotsLogTable($date = ''){
        $table = 'br_slots_log_' . ($date ?: date('Ymd'));
        $res = Db::select("SHOW TABLES LIKE '$table'");
        if(!$res){
            $sql = "CREATE TABLE $table (
                 betId varchar(64) NOT NULL  COMMENT '第三方唯一标识',
                 parentBetId varchar(64) DEFAULT NULL COMMENT '上级的betId',
                 uid int(15) NOT NULL COMMENT '用户UID',
                 puid int(15) DEFAULT '0' COMMENT '上级用户UID',
                 terrace_name varchar(25) DEFAULT NULL COMMENT '游戏厂商',
                 slotsgameid varchar(64) NOT NULL COMMENT '第三方游戏id',
                 game_id int(15) NOT NULL DEFAULT 0 COMMENT '平台三方游戏id',
                 englishname varchar(255) DEFAULT NULL COMMENT '第三方游戏英文名称',
                 cashBetAmount int(15) NOT NULL DEFAULT 0 COMMENT 'Cash玩家投注额',
                 bonusBetAmount int(15) NOT NULL DEFAULT 0 COMMENT 'Bonus玩家投注额',
                 cashWinAmount int(15) NOT NULL DEFAULT 0 COMMENT 'Cash结算金额',
                 bonusWinAmount int(15) NOT NULL DEFAULT 0 COMMENT 'Bonus结算金额',
                 cashTransferAmount int(15) NOT NULL DEFAULT 0 COMMENT 'Cash输赢金额',
                 bonusTransferAmount int(15) NOT NULL DEFAULT 0 COMMENT 'Bonus输赢金额',
                 cashRefundAmount int(15) NOT NULL DEFAULT 0 COMMENT '退还Cash金额当is_settlement为2时退还',
                 bonusRefundAmount int(15) NOT NULL DEFAULT 0 COMMENT '退还Bonus金额当is_settlement为2时退还',
                 transaction_id varchar(255) NOT NULL COMMENT '三方唯一标识',
                 betTime int(11) DEFAULT NULL COMMENT '投注开始时间',
                 package_id int(15) NOT NULL DEFAULT 0 COMMENT '包名id',
                 channel int(15) NOT NULL DEFAULT 0 COMMENT '渠道',
                 betEndTime int(11) DEFAULT NULL COMMENT '投注结束时间',
                 createtime int(11) DEFAULT NULL COMMENT '创建时间',
                 is_consume int(2) NOT NULL DEFAULT 0 COMMENT '是否消费了:1=是,0=否',
                 is_sports int(2) NOT NULL DEFAULT 0 COMMENT '是否体育订单:1=是,0=否',
                 is_settlement int(2) NOT NULL DEFAULT 1  COMMENT'是否结算:1=已完成,0=未结算,2=已退还,3=赢的钱已结算(PP需要这个字段，下注,结果和结算是2个不同接口),4=以回滚(订单变为进行中)',
                 really_betAmount int(15) NOT NULL DEFAULT 0 COMMENT '(sbs)体育实际下注金额',
                 other varchar(520) DEFAULT NULL COMMENT '其它字段(有些三方需要的额外字段，这里可以使用)',
                 other2 varchar(520) DEFAULT NULL COMMENT '其它字段(有些三方需要的额外字段，这里可以使用)',
                 other3 varchar(520) DEFAULT NULL COMMENT '其它字段(有些三方需要的额外字段，这里可以使用)',
                 id int(11) NOT NULL AUTO_INCREMENT  COMMENT 'ID',
                 PRIMARY KEY (id),
                 CONSTRAINT unique_betId UNIQUE (betId),
                 KEY game_id (game_id),
                 KEY transaction_id (transaction_id),
                 KEY uid (uid)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='三方slots游戏历史记录'";
            Db::select($sql);

        }

    }

    /**
     * 获取区块游戏下注记录数据表
     * @param $date
     * @return void
     */
    public static function getBlockGameBetTable($date = ''){
        $table = 'br_block_game_bet_' . ($date ?: date('Ymd'));
        $res = Db::select("SHOW TABLES LIKE '$table'");
        if(!$res){
            $sql = "CREATE TABLE `$table` (
                      `bet_id` char(21) NOT NULL COMMENT '下注ID',
                      `bet_way` tinyint(1) NOT NULL COMMENT '下注方式：1（平台余额）、2（地址转账）',
                      `bet_level` tinyint(1) NOT NULL COMMENT '下注等级：1（初级场）、2（中级场）、3（高级场）',
                      `uid` int(11) NOT NULL COMMENT '用户ID',
                      `puid` int(11) NOT NULL DEFAULT '0' COMMENT '上级用户ID',
                      `channel` int(11) NOT NULL DEFAULT '0' COMMENT '渠道',
                      `package_id` int(11) NOT NULL DEFAULT '0' COMMENT '包名id',
                      `slots_game_id` int(11) NOT NULL DEFAULT '0' COMMENT 'slots游戏ID',
                      `game_id` char(21) NOT NULL COMMENT '游戏ID',
                      `game_name` varchar(100) NOT NULL COMMENT '游戏名称',
                      `network` tinyint(1) NOT NULL DEFAULT '1' COMMENT '区块链网络：1（波场）、2（以太坊）、3（币安）',
                      `game_type_top` tinyint(1) NOT NULL COMMENT '游戏顶级分类：1（Hash）、2（Up Down）',
                      `game_type_second` enum('1','2','3','4','5','6','7','101','102','103','104','105','301','302','303','304','305') NOT NULL DEFAULT '1' COMMENT '游戏第二分类：1（Hash大小）、2（Hash单双）、3（Hash牛牛）、4（Hash庄闲）、5（Hash幸运）、6（Hash和值大小）、7（Hash和值单双）、101（Hash1分大小）、102（Hash1分单双）、103（Hash1分牛牛）、104（Hash1分庄闲）、105（Hash1分幸运）、301（Hash3分大小）、302（Hash3分单双）、303（Hash3分牛牛）、304（Hash3分庄闲）、305（Hash3分幸运）',
                      `periods_id` char(21) NOT NULL DEFAULT '' COMMENT '游戏期数ID',
                      `curr_periods` int(11) NOT NULL DEFAULT '0' COMMENT '当前期数',
                      `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '当期开始时间',
                      `end_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '当期结束时间',
                      `is_open` tinyint(1) unsigned NOT NULL COMMENT '是否开奖：0（否）、1（是）',
                      `open_data` varchar(100) DEFAULT NULL COMMENT '开奖数据，不同游戏类型数据不一样：{\"data_default\":1}',
                      `open_result` tinyint(1) unsigned zerofill NOT NULL DEFAULT '0' COMMENT '开奖结果（不同游戏类型按顺序对应）：1、2、3...',
                      `open_time` timestamp NULL DEFAULT NULL COMMENT '开奖时间',
                      `open_block` int(11) NOT NULL DEFAULT '0' COMMENT '开奖区块',
                      `block_hash` varchar(100) CHARACTER SET utf32 NOT NULL DEFAULT '' COMMENT '开奖区块hash',
                      `transaction_hash` varchar(100) NOT NULL DEFAULT '' COMMENT '开奖交易hash',
                      `bet_area` tinyint(1) NOT NULL COMMENT '下注区域：1（左）、2（右）、3（中）',
                      `bet_amount` int(15) NOT NULL DEFAULT '0' COMMENT '下注金额-cash',
                      `bet_amount_bonus` int(15) NOT NULL DEFAULT '0' COMMENT '下注金额-bonus',
                      `bet_currency` tinyint(1) NOT NULL DEFAULT '1' COMMENT '下注币种：1（金币）、2（USDT）、3（TRX）',
                      `is_win` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否中奖：0（和）、1（赢）、2（输）',
                      `win_lose_amount` int(15) NOT NULL DEFAULT '0' COMMENT '输赢金额-cash',
                      `win_lose_amount_bonus` int(15) NOT NULL DEFAULT '0' COMMENT '输赢金额-bonus',
                      `refund_amount` int(15) NOT NULL DEFAULT '0' COMMENT '退还金额-cash',
                      `refund_amount_bonus` int(15) NOT NULL DEFAULT '0' COMMENT '退还金额-bonus',
                      `sxfee_amount` int(15) NOT NULL DEFAULT '0' COMMENT '手续费-cash',
                      `sxfee_amount_bonus` int(15) NOT NULL DEFAULT '0' COMMENT '手续费-bonus',
                      `settlement_amount` int(15) NOT NULL DEFAULT '0' COMMENT '结算金额-cash',
                      `settlement_amount_bonus` int(15) NOT NULL COMMENT '结算金额-bonus',
                      `win_lose_ratio` float(3,2) NOT NULL DEFAULT '0.00' COMMENT '输赢赔付率',
                      `sxfee_ratio` float(4,3) NOT NULL DEFAULT '0.000' COMMENT '手续费率',
                      `status` tinyint(1) NOT NULL COMMENT '状态：0（待结算）、1（已完成）、2（已退款）',
                      `is_valid` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否有效：0（否）、1（是）',
                      `date` int(8) NOT NULL COMMENT '日期整型加快查询效率',
                      `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                      `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '更新时间',
                      PRIMARY KEY (`bet_id`) USING BTREE,
                      KEY `index_game_id` (`game_id`) USING BTREE,
                      KEY `index_curr_periods` (`curr_periods`) USING BTREE,
                      KEY `index_open_block` (`open_block`) USING BTREE,
                      KEY `index_channel` (`channel`) USING BTREE,
                      KEY `index_package_id` (`package_id`) USING BTREE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏下注记录表';";

            Db::select($sql);
        }

    }

    /**
     * 获取区块游戏开奖期数数据表
     * @param $date
     * @return void
     */
    public static function getBlockGamePeriodsTable($date = ''){
        $table = 'br_block_game_periods_' . ($date ?: date('Ymd'));
        $res = Db::select("SHOW TABLES LIKE '$table'");
        if(!$res){
            $sql = "CREATE TABLE `$table` (
                      `periods_id` char(21) NOT NULL COMMENT '游戏期数ID',
                      `game_id` char(21) NOT NULL COMMENT '游戏ID',
                      `game_name` varchar(100) NOT NULL COMMENT '游戏名称',
                      `network` tinyint(1) NOT NULL DEFAULT '1' COMMENT '区块链网络：1（波场）、2（以太坊）、3（币安）',
                      `game_type_top` tinyint(1) NOT NULL COMMENT '游戏顶级分类：1（Hash）、2（Up Down）',
                      `game_type_second` enum('1','2','3','4','5','6','7','101','102','103','104','105','301','302','303','304','305') NOT NULL DEFAULT '1' COMMENT '游戏第二分类：1（Hash大小）、2（Hash单双）、3（Hash牛牛）、4（Hash庄闲）、5（Hash幸运）、6（Hash和值大小）、7（Hash和值单双）、101（Hash1分大小）、102（Hash1分单双）、103（Hash1分牛牛）、104（Hash1分庄闲）、105（Hash1分幸运）、301（Hash3分大小）、302（Hash3分单双）、303（Hash3分牛牛）、304（Hash3分庄闲）、305（Hash3分幸运）',
                      `curr_periods` int(11) unsigned NOT NULL DEFAULT '1' COMMENT '当前期数',
                      `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '当期开始时间',
                      `end_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '当期结束时间',
                      `is_open` tinyint(1) unsigned NOT NULL COMMENT '是否开奖：0（否）、1（是）',
                      `open_data` varchar(100) DEFAULT NULL COMMENT '开奖数据，不同游戏类型数据不一样：{\"result\":12,\"data\":13}',
                      `open_result` tinyint(1) unsigned zerofill NOT NULL DEFAULT '0' COMMENT '开奖结果：1、2、3...（按顺序对应）',
                      `open_time` timestamp NULL DEFAULT NULL COMMENT '开奖时间',
                      `open_block` int(11) NOT NULL DEFAULT '0' COMMENT '开奖区块',
                      `block_hash` varchar(100) NOT NULL DEFAULT '' COMMENT '开奖区块hash',
                      `transaction_hash` varchar(100) NOT NULL DEFAULT '' COMMENT '开奖交易hash',
                      `bet_user_num` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '下注人数',
                      `bet_total_amount` int(15) NOT NULL DEFAULT '0' COMMENT '下注总金额',
                      `is_one_min` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是一分开奖期数：0（否）、1（是）',
                      `is_three_min` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是三分开奖期数：0（否）、1（是）',
                      `date` int(8) NOT NULL COMMENT '日期整型加快查询效率',
                      `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                      `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '更新时间',
                      PRIMARY KEY (`periods_id`) USING BTREE,
                      KEY `index_game_id` (`game_id`),
                      KEY `index_open_block` (`open_block`) USING BTREE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏期数记录表'";
            Db::select($sql);

        }

    }
}

