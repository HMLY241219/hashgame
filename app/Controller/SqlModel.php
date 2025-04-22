<?php
namespace App\Controller;

use Hyperf\DbConnection\Db;


/**
 *  处理一些常用的分天数据表插入
 */
class SqlModel  {

    private $suffix; //数据表后缀

    private $insertData = []; //添加的数据

    private $updateData = []; //修改的数据

    private $rule = [ //规则 ：值|规则
        'up' => '直接修改或添加的数据',
        'raw' => '修改时在原基础上+-*/等，用-分隔一下,例如raw-+,表示数据需要原来基础上加'
    ];


    public function __construct(array $data,$suffix = '')
    {
        $this->suffix = $suffix ?: date('Ymd');
        $this->dealWithData($data);
    }


    /**
     * 处理数据
     * @param array $data 修改或添加的数据
     * @return void
     */
    private function dealWithData(array $data){
        foreach ($data as $field => $value){
            [$val,$rule] = explode('|',$value);
            $this->insertData[$field] = $val;
            $this->updateData[$field] = $this->dealWithRule($rule,$field,$val);
        }
    }

    /**
     * 处理修改时的数据
     * @param $rule  string 规则
     * @param $field string 字段
     * @param $val string 值
     * @return mixed|\think\db\Raw
     */
    private function dealWithRule(string $rule,string $field,string $val){
        if($rule == 'up') {
            return $val;
        }elseif (stripos($rule, 'raw') !== false){
            [,$symbol] = explode('-',$rule);
            return Db::raw($field.$symbol.$val);
        }
        return $val;

    }

    /**
     * user_day数据表处理
     * @return void
     */
   public function userDayDealWith(){
       $user_day = Db::table('user_day_'.$this->suffix)->select('uid')->where('uid',$this->updateData['uid'])->first();
       if($user_day){
           $res = Db::table('user_day_'.$this->suffix)->where('uid',$this->updateData['uid'])->update($this->updateData);
       }else{
           $res = Db::table('user_day_'.$this->suffix)->insert($this->insertData);
       }
       return $res;
   }



}




