<?php

namespace App\Http\Controllers\Api\V1\DeviceClient;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use App\Http\Controllers\BaseApiController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Jobs\TransDeviceWarn;
use App\Jobs\TransDeviceInfo;


class DeviceTelecomController extends BaseApiController
{

    /**
     * device 设备基础数据的改变
     * @param  Request $req
     * @return Response
     */
    public function device(Request $req)
    {
        $data = json_decode(trim($req->getContent()), true);
        if (empty($data)) {
            $data = [];
        }
        info('telecom devic info:', $data);
        return $this->success();
    }

    /**
     * status 设备状态数据的改变
     * @param  Request $req
     * @return Response
     */
    public function status(Request $req)
    {
        $data = json_decode(trim($req->getContent()), true);
        if (empty($data)) {
            $data = [];
        }
        info('telecom devic status:', $data);
        return $this->success();
    }

}