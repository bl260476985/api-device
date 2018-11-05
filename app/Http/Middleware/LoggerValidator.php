<?php

namespace App\Http\Middleware;

use App\Utils\VarStore;
use App\Utils\AccountHelper;
use Closure;

class LoggerValidator extends Validator
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed  session(['key' => 'value']);
     */
    public function handle($request, Closure $next)
    {
        $currentUser = $request->session()->get('DwUserId');
        VarStore::put('currentUserId', $currentUser);

        info($request->method() . ' ' . $request->path() . ' ' . $request->session()->getId() . ' ' . $request->ip() . ' ' . $currentUser, $request->all());

        return $next($request);
    }
}
