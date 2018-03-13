<?php

namespace App\Http\Middleware;

use Closure;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $session = $request->session()->all();
        if(!isset($session['role'])||$session['role']!='admin'){
            $returnData = array(
                'code' => 401,
                'msg' => '用户未登录',
                'data' => (object)array()
            );
            return apiReturn($returnData);
            exit(); 
        }
        return $next($request);
    }
}
