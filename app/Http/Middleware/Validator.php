<?php

namespace App\Http\Middleware;

abstract class Validator
{

    protected $responseCode = 1;

    /**
     * @param string $msg
     * @return \Illuminate\Http\JsonResponse
     * 响应错误信息
     */
    public function fail($msg = '')
    {
        return response()->json(['code' => $this->responseCode, 'msg' => $msg]);
    }
}
