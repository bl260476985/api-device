<?php

namespace App\Http\Middleware;

use App\Utils\VarStore;
use Closure;

class LogCheckValidator extends Validator
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
        info($request->method() . ' ' . $request->path() . ' ' . $request->ip() . ' ' . $request->getContent());
        return $next($request);
    }
}
