<?php
declare(strict_types=1);
namespace App\Common;


use Hyperf\DbConnection\Db;
use function Hyperf\Config\config;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
class Common
{



    /**
     * @param  $RedisName string redis配置的名字
     * @return \Hyperf\Redis\RedisProxy
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function Redis(string $RedisName = ''){
        $RedisName = $RedisName ?: 'default';
        $container = ApplicationContext::getContainer();
        return $container->get(\Hyperf\Redis\RedisFactory::class)->get($RedisName);
    }

    /**
     * 生成唯一的字符串
     * @param int $num 自定义数字
     * @return string
     */
    public static function doOrderSn(int $num):string{
        return date('YmdHis') . $num . substr(microtime(), 2, 3) . sprintf('%02d', rand(0, 99));
    }

    /**
     * @param $url //判断地址是否为绝对路径，并拼接地址
     * @return mixed|string
     */
    public static function domain_name_path(string $url){

        $preg = "/^http(s)?:\\/\\/.+/";
        if(!preg_match($preg,$url)){
            return config('host.adminDomain').$url;
        }else{
            return $url;
        }
    }


    /**
     * @return void 正则表达式
     * $str 效验的字符串
     * $type 类型 'phone' => 判断是否是电话号码 ， 'image' => 判断是否是图片
     *
     */
    public static function PregMatch(string $str,string $type){
        switch ($type){
            case 'phone':
                $status =  preg_match("/^\d{10}$/", $str);
                break;
            case 'image':
                $status = preg_match('/.*(\.png|\.jpg|\.jpeg|\.gif)$/', $str);
                break;
            case 'idcard':
                $status = preg_match('/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$/i', $str);
                break;
            case 'email':
                $status = preg_match("/([a-z0-9]*[-_.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[.][a-z]{2,3}([.][a-z]{2})?/i", $str);
                break;
            case 'ifsc' :
                $status = preg_match("/^[A-Za-z]{4}0[A-Z0-9a-z]{6}$/", $str);
                break;
            case 'cpf' :
                $status = preg_match("/^\d{11}$/", $str);
                break;
            case 'cnpj' :
                $status = preg_match("/^\d{14}$/", $str);
                break;
            case 'account' :
                $status = preg_match('/^[A-Za-z0-9]{6,25}$/', $str);
                break;
            default:
                $status = false;
        }
        return $status;
    }


    /**
     *用户token生成
     * @param  $uid int 用户UID
     * @return void
     */
    public static function setToken(int $uid)
    {
        return md5($uid.time());
    }


    /**
     * 获取用户Ip地址
     * @param $getServerParams array  这个值为$this->request->getServerParams()
     * @return string
     */
     public static function getIp(array $getServerParams = []):string{
         $request = ApplicationContext::getContainer()->get(RequestInterface::class);
         $headers = $request->getHeaders();
         if(isset($headers['x-forwarded-for'][0]) && !empty($headers['x-forwarded-for'][0])) {
             return $headers['x-forwarded-for'][0];
         } elseif (isset($headers['x-real-ip'][0]) && !empty($headers['x-real-ip'][0])) {
             return $headers['x-real-ip'][0];
         }

         $serverParams = $request->getServerParams();
         return $serverParams['remote_addr'] ?? '';

    }


    /**
     * 获取单个参数配置
     * @param $menu
     * @return
     */
    public static function getConfigValue($menu)
    {
        $system_config = Db::connection('readConfig')->table('system_config')->select('value')->where('menu_name',$menu)->first();
        if(!$system_config)return '';
        return json_decode($system_config['value'], true);
    }

    /**
     * 获得多个参数
     * @param $menus
     * @return array
     */
    public static function getMore($menus)
    {
        $menus = is_array($menus) ? $menus : explode(',',$menus);
        $list = Db::connection('readConfig')->table('system_config')->whereIn('menu_name',$menus)->pluck('value','menu_name');
        foreach ($list as $menu => $value) {
            $list[$menu] = json_decode($value, true);
        }
        return $list;
    }

    /** 第三方储存log
     * @param $ordersn 订单号
     * @param $response  第三方返回参数
     * @param $type  类型:1=充值,2=客户端提现,3=pg,4=后台提现,5=打点,6=短信,7=推广返利提现,8=回调退款失败
     * @return void
     */
    public static function log($ordersn,$response,$type){

        Db::table('log')->insert(['out_trade_no'=> $ordersn,'log' => json_encode($response),'type'=>$type,'createtime' => time()]);
        if($type == 2 || $type == 4 || $type == 8){
            $withdraw_log = Db::table('withdraw_log')->selectRaw('id,uid,money,withdraw_money_other')->where('ordersn',$ordersn)->first();
            Db::table('withdraw_log')->where('ordersn',$ordersn)->update(['status' => 2,'finishtime' => time()]);
            //将三方错误日志，存储到第三张表中,是查询速度快一点
            Db::table('withdraw_logcenter')->where('withdraw_id',$withdraw_log['id'])->update([
                'log_error' => json_encode($response),
            ]);
            //增加用户余额
            User::userEditCoin($withdraw_log['uid'],$withdraw_log['money'],5, "玩家系统处理提现失败" . $withdraw_log['uid'] . "退还提现金额" . bcdiv((string)$withdraw_log['money'],'100',2),3,1,$withdraw_log['withdraw_money_other']);

        }
    }

    /**
     * 按照比例概率发奖，概率需要为100%
     * @param array $probabilityMap 概率数组
     * @param string $probabilityField 概率字段
     * @return string
     */
    public static function proportionalProbabilityAward(array $probabilityMap, string $probabilityField = ''):string{
        // 创建一个累积概率数组
        $cumulativeProbabilities = [];
        $sum = 0;
        if($probabilityField){
             foreach ($probabilityMap as $key => $value) {
                 if($value[$probabilityField] <= 0)continue; //如果概率小于等于0直接跳过
                  $sum += $value[$probabilityField];
                  $cumulativeProbabilities[$key] = $sum;
             }
        }else{
            foreach ($probabilityMap as $key => $value) {
                $sum += $value;
                $cumulativeProbabilities[$key] = $sum;
            }
        }


        // 生成一个介于0和1之间的随机数
        $randomNumber = mt_rand() / mt_getrandmax();

        // 遍历累积概率数组找到第一个大于或等于随机数的键
        foreach ($cumulativeProbabilities as $key => $value) {
            if ($randomNumber <= $value) {
                return (string)$key;
            }
        }

        // 如果未返回任何值，则返回最后一个键（理论上不会执行到此处）
        if(!$probabilityField){
            return (string)array_search(max($probabilityMap), $probabilityMap);
        }
        return (string)(count($probabilityMap) - 1);

    }

    /**
     * 获取UID的最后一位
     * @param $uid  用户id
     * @return void
     */
    public function getUidTail($uid){
        return substr((string)$uid, -1);
    }

    /**
     * 无限代每周数据统计
     * @param $uid  UID
     * @param $money  用户充值或者提现金额
     * @param $fee 手续费
     * @param $type 类型:1=修改充值，2=修改提现
     */
    public static function agentTeamWeeklog($uid,$money,$fee,$type = 1){
        $field = $type == 1 ? 'pay_price' : 'withdraw_price';
        [$start,$end] = self::getWeekStartEnd(time());

        $res = Db::table('agent_team_weeklog')
            ->where([['time' ,'>=', $start],['time','<',$end],['uid','=',$uid]])
            ->update([
                $field => Db::raw($field."+".$money),
                'fee' => Db::raw('fee + '.$fee),
                'updatetime' => time(),
            ]);
        if(!$res){
            Db::table('agent_team_weeklog')
                ->insert([
                    $field => $money,
                    'fee' => $fee,
                    'time' => $start,
                    'uid' => $uid,
                ]);
        }
    }

    /**
     * 返回指定时间戳的本周开始时间和结束时间
     * @param $time  时间戳
     * @return void
     */
    public function getWeekStartEnd($time){
        $time = is_numeric($time) ? $time : strtotime((string)$time);
        // 获取本周的开始时间
        $startOfWeek = strtotime('Monday this week', $time);


        // 获取本周的结束时间
        $endOfWeek = strtotime('Sunday this week', $time) + 86399;

        return [$startOfWeek,$endOfWeek];
    }

    /**
     * 生成唯一ID字符串
     * @param int $suffixLen 随机后缀长度
     * @param string $prefix 前缀
     * @param int $upperOrLower 1大写、2小写
     * @return string
     */
    public static function createIdSn(int $suffixLen = 5, string $prefix = '', int $upperOrLower = 1): string
    {
        $str = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $code = date('ymdHis') . substr(microtime(), 2, 3);
        for ($i = 0; $i <= $suffixLen; $i++) {
            $code .= $str[mt_rand(0, strlen($str)-1)];
        }

        return $upperOrLower === 1 ? strtoupper($prefix . $code) : strtolower($prefix . $code);
    }
}