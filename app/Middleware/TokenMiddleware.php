<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\Di\Annotation\Inject;
use App\Controller\ReturnJsonController;
use Hyperf\DbConnection\Db;

class TokenMiddleware implements MiddlewareInterface
{

    private array $except = ['/test/index','/home/userinfo','/active.active/turntable','/active.active/getCash','/Withdrawlog/add',
        '/Auth/editPassword','/slots.Slots/getGameUrl','/sign/signIn','/blockgame/bet','/blockgame/user/bet/statistics','/blockgame/user/address/list',
        '/blockgame/user/betlog', '/blockgame/room/betdata', '/blockgame/user/balance']; // 检查是否是需要验证Token的控制器和方法 //'Withinfo/add'
    private string $notControllers = ''; //不需要效验的控制器
    private array $trueControllers = ['Tdslots','pgslots','ppslots','Tasks','test','cq9slots','sbsslots','sboslots','Jlslots','weslots','ezugislots','jdbslots','evolutionslots','spribeslots','jokerslots','bgslots','turboslots','avislots', 'webhook']; //直接通过的控制器，啥都不需要管
    private array $trueFunctions = ['payNotify', 'pushLatestBlock']; //直接通过相似的方法比如支付回调

    private array $trueExcept = ['/tests/index','/api/auth','/api/bet','/api/cancelBet','/api/sessionBet','/api/cancelSessionBet','/egslots/auth','/egslots/balance','/egslots/bet','/egslots/cancelBet','/sboslots/GetBalance','/sboslots/Deduct',
        '/sboslots/Settle','/sboslots/Rollback','/sboslots/Cancel','/sboslots/Bonus','/sboslots/GetBetStatus','/active.active/setTurntableCount','/zyslots/logSet','/Withdrawlog/htWithdrawApi','/Order/htOrderApi',
        '/zyslots/get_today_history','/zyslots/get_my_bet','/zyslots/get_bet_details','/Sms/getcode','/Common/setWebFbpFbc']; //直接通过的方法，不需要packname


    #[Inject]
    protected ReturnJsonController $ReturnJsonController;


    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        //维护的时候开启这段代码
//        return $this->ReturnJsonController->failFul(276,[],2);

        // 获取当前请求的路由信息/test/index 格式
        $pathinfo = $request->getUri()->getPath();
        $module = explode('/',$pathinfo)[1] ?? '';
        $function = explode('/',$pathinfo)[2] ?? '';

        //直接通过的路由或者控制器
        if(in_array($module,$this->trueControllers) || in_array($pathinfo,$this->trueExcept))return $handler->handle($request);

        //直接通过相似的方法比如支付回调
        if($function && $this->trueFunctions){
            foreach ($this->trueFunctions as $v){
                if(str_contains($function, $v))return $handler->handle($request);
            }
        }

        //获取默认语言
        $lang = $request->getHeaderLine('lang') ?? 'en';
        $request = $request->withAttribute('lang', $lang);

        //获取包名
        $packname = $request->getHeaderLine('packname');

        if(!$packname){
            return $this->ReturnJsonController->failFul(402,[],2);
        }

        $request = $request->withAttribute('packname', base64_decode($packname));
        //str_contains(a,b):bool  判断B字符串是否在A字符串中
        if (!in_array($pathinfo, $this->except) && !str_contains($this->notControllers, $module)) {
            // 不需要验证Token的控制器和方法，直接通过
            return $handler->handle($request);
        }


        //获取请求数据
        $formData = $request->getParsedBody();
        $smstype = $formData['smstype'] ?? 0; // 有验证码的话就不需要验证用户UID与token

        if($smstype == 2){
            $request = $request->withAttribute('uid', 0);
            // 继续执行下一个中间件或控制器方法
            return $handler->handle($request);
        }


        // 验证Token
        $token = $request->getHeaderLine('user-token');

        if (!$token) {
            // Token不存在，返回错误响应
            return $this->ReturnJsonController->failFul(404,[],2);
        }




        // 解析Token，获取用户ID
        $tokenArray = $this->parseToken($token);
        if($tokenArray['code'] != 200){
            return $this->ReturnJsonController->failFul(401,[],2);
        }

        $request = $request->withAttribute('uid', $tokenArray['data']);
        // 继续执行下一个中间件或控制器方法
        return $handler->handle($request);
    }

    /**
     * 通过token获取jwt的uid
     * @param $token token
     * @param $loginType 登录来源
     * @return array
     */
    private function parseToken($token)
    {

        $uid = Db::table('user_token')->where('token',$token)->value('uid');

        if(!$uid){
            return ['code' => 201,'msg' => '无效的令牌!','data' => ''];
        }
        return ['code' => 200,'msg' => '成功','data' => $uid];
    }
}