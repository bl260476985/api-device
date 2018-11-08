<?php

namespace App\Http\Controllers\Api\V1\DeviceClient;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use App\Http\Controllers\BaseApiController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Tasks\WarningPushEvent;
use Hhxsv5\LaravelS\Swoole\Task\Event;
use App\Utils\NumTransNameHelper as TransHelper;
use App\Utils\AccountHelper;

class DeviceTelecomController extends BaseApiController
{

    /**
     * update 设备基础数据的改变
     * @param  Request $req
     * @return Response
     */
    public function update(Request $req)
    {
        return $this->success();
    }

    /**
     * add 设备基础数据的新增
     * @param  Request $req
     * @return Response
     */
    public function add(Request $req)
    {
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
        if (isset($data['deviceId']) && !empty($data['deviceId']) && $data['deviceId'] != 'xxx') {
            $deviceId = trim($data['deviceId']);
            $device = DB::table('device')->select('id', 'device_number', 'station_id', 'address', 'device_iccid', 'sw_ver', 'device_type', 'recent_interval', 'open_push', 'push_st', 'push_et', 'psk')
                ->where('device_mini_Id', $deviceId)
                ->where('is_del', 0)
                ->first();
            if (!empty($device)) {
                $warning_type = isset($data['service']['data']['warning']) ? $data['service']['data']['warning'] : '';
                $iccid = isset($data['service']['data']['ICCID']) ? $data['service']['data']['ICCID'] : $device['device_iccid'];
                $sw_ver = isset($data['service']['data']['SW_VER']) ? $data['service']['data']['SW_VER'] : $device['sw_ver'];
                $content = isset($data['service']['data']) ? json_encode($data['service']['data'], JSON_UNESCAPED_UNICODE) : '';
                $uploaded_at = isset($data['service']['eventTime']) ? date('Y-m-d H:i:s', strtotime($data['service']['eventTime'])) : date('Y-m-d H:i:s');
                $recent_interval = (isset($data['service']['data']['C_interval']) && !empty($data['service']['data']['C_interval'])) ? (int)$data['service']['data']['C_interval'] : (int)$device['recent_interval'];
                $detail = [
                    'device' => $device,
                    'content' => $content,
                    'device_iccid' => $iccid,
                    'sw_ver' => $sw_ver,
                    'uploaded_at' => $uploaded_at,
                    'recent_interval' => $recent_interval
                ];
                if (in_array($warning_type, ['00', '01', '99'])) {
                    //报警 进行报警处理
                    self::dealWarning($warning_type, $detail);
                } else if (in_array($warning_type, ['98'])) {
                    self::dealInfo($warning_type, $detail);
                }
            }
        }
        return $this->success();
    }

    /**
     * 处理心跳信息
     * @param $detail
     */
    private static function dealInfo($warning_type, $detail)
    {
        //只有心跳
        $device_status = 2;
        $warning = 0;//详细的报警信息 若之前是正常或恢复 则这次无报警则
        $warning_content = '无告警';
        $device = $detail['device'];
        $update = [
            'warning' => $warning,
            'warning_content' => $warning_content,
            'device_iccid' => $detail['device_iccid'],
            'sw_ver' => $detail['sw_ver'],
            'recent_interval' => $detail['recent_interval'],//最近一次的心跳间隔
            'device_status' => $device_status,//设备状态默认1未知2正常或门磁关闭3门磁开启或异常或告警4故障中5测试中或操作中6离线
            'device_heart_info' => $detail['content'],
            'hearted_at' => $detail['uploaded_at'],
        ];

        //插入心跳记录
        try {
            DB::transaction(function () use ($update, $device) {
                //更新设备状态
                DB::table('device')->where('id', $device['id'])->update($update);
                $id = parent::getUid(); //生成唯一标识
                $insert = [
                    'id' => $id,
                    'device_id' => $device['id'],
                    'device_number' => $device['device_number'],
                    'info' => $update['device_heart_info'],
                    'uploaded_at' => $update['hearted_at'],
                ];
                DB::table('heart_records_check')->insert($insert);
            });
        } catch (\Exception $e) {
            logger('DeviceTelecomController Info update device error');
        };
    }

    /**
     * 处理报警信息
     * @param $detail
     */
    private static function dealWarning($warning_type, $detail)
    {
        $device_type_status_detail = TransHelper::DEVICE_TYPE_STATUS_DETAIL;
        $device = $detail['device'];
        //'00', '01', '99' 00故障恢复 01报警 99低电量
        $warning = 2;//2监测故障告警1低电量告警
        $device_status = 3;//告警
        if ($warning_type == '00') {
            $warning = 6;
            $device_status = 2;
        } else if ($warning_type == '01') {
            $warning = 2;
        } else if ($warning_type == '99') {
            $warning = 1;
        }

        $fault_type_name = isset($device_type_status_detail[$warning]) ? $device_type_status_detail[$warning] : '未知';
        $update = [
            'warning' => $warning,
            'warning_content' => $fault_type_name,
            'device_iccid' => $detail['device_iccid'],
            'sw_ver' => $detail['sw_ver'],
            'recent_interval' => $detail['recent_interval'],//最近一次的心跳间隔
            'device_status' => $device_status,//设备状态默认1未知2正常或门磁关闭3门磁开启或异常或告警4故障中5测试中或操作中6离线
            'device_info' => $detail['content'],
            'recented_at' => $detail['uploaded_at'],
        ];
        //插入报警记录并更新设备记录
        $warning_id = parent::getUid();
        try {
            DB::transaction(function () use ($update, $device, $warning, $warning_id) {
                DB::table('device')->where('id', $device['id'])->update($update);
                $type = ($warning == 6) ? 2 : 1;
                //插入报警记录
                $insert = [
                    'id' => $warning_id,
                    'device_id' => $device['id'],
                    'device_number' => $device['device_number'],
                    'fault_type' => $update['warning'],
                    'type' => $type,
                    'warning_content' => $update['warning_content'],
                    'detail' => $update['device_info'],
                    'uploaded_at' => $update['recented_at'],
                ];
                DB::table('device_warning')->insert($insert);
            });
        } catch (\Exception $e) {
            logger('DeviceTelecomController warning update device error');
        };
        $hour = date('H');
        $can_push = AccountHelper::compareHour($hour, $device['push_st'], $device['push_et']);
        if (!$can_push) {
            info('DeviceTelecomController report can not push message:', ['warning_id' => $warning_id, 'hour' => $hour, 'push_st' => $device['push_st'], 'push_et' => $device['push_et']]);
        }
        if ($device['open_push'] == 1 && $can_push && $warning != 6) {
            //向相关负责人推送报警消息
            $device_type_name = isset($device_type_trans[$device['device_type']]) ? $device_type_trans[$device['device_type']] : '';
            $push_data = [
                'warning_id' => $warning_id,
                'device_id' => $device['id'],
                'device_number' => $device['device_number'],
                'station_id' => $device['station_id'],
                'uploaded_at' => $detail['uploaded_at'],
                'address' => $device['address'],
                'content' => $device['device_number'] . $device_type_name . '发生' . $fault_type_name,
                'detail' => $detail['content'],
            ];
            Event::fire(new WarningPushEvent($push_data));
        }
    }

}