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


class DeviceThirdController extends BaseApiController
{

    /**
     * report 上报参数
     * @param  Request $req
     * @return Response
     */
    public function report(Request $req)
    {
        $data = json_decode(trim($req->getContent()), true);
        if (!isset($data['machineNumber']) || empty($data['machineNumber'])) {
            return $this->fail('缺少设备串号参数');
        }
        if (!isset($data['suggest']) || empty($data['suggest'])) {
            return $this->fail('设备串号参数不合法');
        }
        if (!isset($data['uploadDateTime']) || empty($data['uploadDateTime'])) {
            return $this->fail('上报时间参数不合法');
        }
        if (!isset($data['info']) || empty($data['info'])) {
            return $this->fail('上报内容参数不合法');
        }

        $suggest = $data['suggest'];
        $device = DB::table('device')->select('id', 'recent_interval', 'voltage', 'warning', 'device_number',
            'device_status', 'open_push', 'device_type', 'station_id', 'address', 'push_st', 'push_et')
            ->where('device_number', $data['machineNumber'])
            ->where('is_del', 0)
            ->first();
        if (empty($device)) {
            return $this->fail('此设备不存在');
        }
        $detail = [
            'warning_id' => parent::getUid(),
            'device' => $device,
            'content' => $data
        ];
        if ($suggest == 'info') {
            //若合法则更新设备并插入心跳信息
            TransDeviceInfo::dispatch($detail);
        } else if ($suggest == 'warning') {
            //若合法则更新设备信息并插入报警记录
            TransDeviceWarn::dispatch($detail);
        }
        return $this->success();
    }

}