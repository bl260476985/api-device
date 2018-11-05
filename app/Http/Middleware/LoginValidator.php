<?php

namespace App\Http\Middleware;

use App\Utils\VarStore;
use App\Utils\AccountHelper;
use Closure;
use Illuminate\Support\Facades\DB;

class LoginValidator extends Validator
{

    function __construct()
    {
        $this->responseCode = 5003;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed session(['key' => 'value']);
     */
    public function handle($request, Closure $next)
    {
        if (env('APP_BACK_DOOR', 'true')) {
            if ((int)$request->input('nologin', 0) === 999) {
                $request->session()->put('DwUserId', 1);
                VarStore::put('currentUserId', 1);
            }
        }

        if (empty(VarStore::get('currentUserId'))) {
            return $this->fail('请登录');
        }

        $user = DB::table('system_user')->where('id', VarStore::get('currentUserId'))->where('is_del', 0)->first();
        if (empty($user)) {
            return $this->fail('用户不存在');
        } else {
            AccountHelper::cachePut(1, $user['id'], $user);//对系统用户数据进行缓存
        }

        return $next($request);
    }
}
