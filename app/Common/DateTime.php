<?php
declare(strict_types=1);
namespace App\Common;

/**
 *  时间
 */
class DateTime
{

    /**
     * 返回指定天数的开始日期
     * @param $days int 天数
     * @param $type int 类型:1=时间戳 2=日期
     * @param $start int 开始的天数  0从今天开始 1表示从昨天开始，2表示从前天开始
     * @return array
     */
    public static function getStartTimes(int $days,int $type = 1,int $start = 0){
        $startTimes = array();
        $now = time();
        if($type == 1){
            for ($i = $start; $i < $days; $i++) {
                $startTimes[] = strtotime(date('Y-m-d 00:00:00', $now - 86400 * $i));
            }
        }else{
            for ($i = $start; $i < $days; $i++) {
                $startTimes[] = date('Y-m-d 00:00:00', $now - 86400 * $i);
            }
        }

        return $startTimes;
    }


    /**
     * 返回开始时间和结束时间的所有天数
     * Returns every date between two dates as an array
     * @param string $startDate the start of the date range  2022-12-12
     * @param string $endDate the end of the date range   2023-02-16
     * @param string $format DateTime format, default is Y-m-d
     * @return array returns every date between $startDate and $endDate, formatted as "Y-m-d"
     */
    public static function createDateRange(mixed $startDate,mixed $endDate, string $format = "Y-m-d"):array
    {

        $startDate = is_numeric($startDate) ? date('Y-m-d',$startDate) : $startDate;
        $endDate = is_numeric($endDate) ? date('Y-m-d',$endDate) : $endDate;
        $begin = new \DateTime($startDate);
        $end = new \DateTime(date('Y-m-d',strtotime($endDate .' +1 day')));

        $interval = new \DateInterval('P1D'); // 1 Day

        $dateRange = new \DatePeriod($begin, $interval, $end);

        $range = [];

        foreach ($dateRange as $date) {
            $range[] = $date->format($format);
        }

        return $range;
    }


    /**
     * 获取指定时间戳的当周的开始时间和结束时间
     * @param $time
     * @return array
     */
    public static function startEndWeekTime($time):array{
        $sdefaultDate = date("Y-m-d", $time);
        //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
        $w = date('w', strtotime($sdefaultDate));
        //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
        $week_start = strtotime("$sdefaultDate -" . ($w ? $w - 1 : 6) . ' days');
        //本周结束日期
        $week_end = strtotime(date('Y-m-d',$week_start)." +6 days") + 86399;

        return [$week_start,$week_end];
    }
}

