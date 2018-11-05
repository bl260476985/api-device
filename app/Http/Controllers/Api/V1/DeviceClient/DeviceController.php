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

class DeviceController extends BaseApiController
{

    /**
     * login
     * @param  Request $req
     * @return Response
     */
    public function login(Request $req)
    {
        $time = time();
        $data = $this->Dealparam(trim($req->getContent()));
        if (!is_array($data) || count($data) != 6) {
            return response('{fail,' . $time . ',}');
        }
        if (is_array($data) && count($data) == 6) {
            $IMEI = isset($data[0]) ? (string)$data[0] : '';
            $ICCID = isset($data[1]) ? (string)$data[1] : '';
            $device_type = isset($data[2]) ? (string)$data[2] : '';
            $model = isset($data[3]) ? (string)$data[3] : '';
            $PRO_VER = isset($data[4]) ? (string)$data[4] : '';
            $SW_VER = isset($data[5]) ? (string)$data[5] : '';
            if (empty($IMEI)) {
                return response('{fail,' . $time . ',IMEI is empty}');
            }
            //判断设备是否存在
            $device = DB::table('device')->select('id')->where('device_number', $IMEI)->where('is_del', 0)->first();
            if (empty($device)) {
                return response('{fail,' . $time . ',device is empty}');
            }
            $device_open_info = [
                'IMEI' => $IMEI,
                'ICCID' => $ICCID,
                'device_type' => $device_type,
                'model' => $model,
                'PRO_VER' => $PRO_VER,
                'SW_VER' => $SW_VER,
            ];
            $device_open_info = json_encode($device_open_info, JSON_UNESCAPED_UNICODE);
            $update = [
                'device_iccid' => $ICCID,
                'model' => $model,
                'pro_ver' => $PRO_VER,
                'sw_ver' => $SW_VER,
                'device_status' => 2,//设备状态默认1未知2正常或门磁关闭3门磁开启或异常或告警4故障中5测试中或操作中6离线
                'device_open_info' => $device_open_info,
                'opened_at' => date('Y-m-d H:i:s', $time),
            ];
            DB::table('device')->where('id', $device['id'])->update($update);
            return response('{Ok,' . $time . ',}');
        }
    }

    /**
     * 设备状态上报
     * @param Request $req
     */
    public function report(Request $req)
    {
        $device_type_trans = TransHelper::DEVICE_BASE_TYPE;
        $data = $this->Dealparam(trim($req->getContent()));
        if (!is_array($data) || count($data) != 6) {
            return response('{fail,}');
        }

        if (is_array($data) && count($data) == 6) {
            $data_time = isset($data[0]) ? (string)$data[0] : '';
            $IMEI = isset($data[1]) ? (string)$data[1] : '';
            $ICCID = isset($data[2]) ? (string)$data[2] : '';
            $device_type = isset($data[3]) ? $data[3] : '';
            $model = isset($data[4]) ? (string)$data[4] : '';
            $warning = isset($data[5]) ? (string)$data[5] : '';
            if (empty($IMEI)) {
                return response('{fail,IMEI is empty}');
            }
            //判断设备是否存在
            $device = DB::table('device')->select('id', 'device_number', 'station_id', 'address', 'device_type', 'open_push', 'push_st', 'push_et')
                ->where('device_number', $IMEI)
                ->where('is_del', 0)
                ->first();
            if (empty($device)) {
                return response('{fail,device is empty}');
            }
            $detail = [
                'data_time' => $data_time,
                'IMEI' => $IMEI,
                'ICCID' => $ICCID,
                'device_type' => $device_type,
                'model' => $model,
                'warning' => $warning,
            ];
            $device_info = json_encode($detail, JSON_UNESCAPED_UNICODE);
            $trans_warn = 2;//2监测故障告警1低电量告警
            if ($warning == '99') {
                $trans_warn = 1;
            }
            $fault_type_name = $trans_warn == 1 ? '低电量告警' : '监测故障告警';
            $recented_at = date('Y-m-d H:i:s', hexdec((string)($data_time)));//报警时间
            $update = [
                'device_iccid' => $ICCID,
                'model' => $model,
                'warning' => $trans_warn,
                'warning_content' => $fault_type_name,//报警内容说明
                'device_status' => 3,//设备状态默认1未知2正常或门磁关闭3门磁开启或异常或告警4故障中5测试中或操作中6离线
                'device_info' => $device_info,
                'recented_at' => $recented_at,
            ];
            //插入报警记录并更新设备记录
            $resmsg = '';
            $warning_id = parent::getUid();
            try {
                DB::transaction(function () use ($update, $device_info, $device, $recented_at, $trans_warn, $warning_id, $fault_type_name) {
                    DB::table('device')->where('id', $device['id'])->update($update);
                    //插入报警记录
                    $insert = [
                        'id' => $warning_id,
                        'device_id' => $device['id'],
                        'device_number' => $device['device_number'],
                        'fault_type' => $trans_warn,
                        'type' => 1,
                        'warning_content' => $fault_type_name,
                        'detail' => $device_info,
                        'uploaded_at' => $recented_at,
                    ];
                    DB::table('device_warning')->insert($insert);
                });
            } catch (\Exception $e) {
                $resmsg = 'system error';
            };
            $hour = date('H');
            $can_push = AccountHelper::compareHour($hour, $device['push_st'], $device['push_et']);
            if (!$can_push) {
                info('our device report can not push message:', ['warning_id' => $warning_id, 'hour' => $hour, 'push_st' => $device['push_st'], 'push_et' => $device['push_et']]);
            }
            if (!empty($resmsg)) {
                return response('{fail,' . $resmsg . '}');
            } else if ($device['open_push'] == 1 && $can_push) {
                //向相关负责人推送报警消息
                $device_type_name = isset($device_type_trans[$device['device_type']]) ? $device_type_trans[$device['device_type']] : '';
                $push_data = [
                    'warning_id' => $warning_id,
                    'device_id' => $device['id'],
                    'device_number' => $device['device_number'],
                    'station_id' => $device['station_id'],
                    'uploaded_at' => $recented_at,
                    'address' => $device['address'],
                    'content' => $device['device_number'] . $device_type_name . '发生' . $fault_type_name,
                    'detail' => $device_info
                ];
                Event::fire(new WarningPushEvent($push_data));
            }
            return response('{Ok,}');
        }
    }

    /**
     * 心跳及设置
     * @param Request $req
     * @return Response
     */
    public function heartSet(Request $req)
    {
        $device_type_status_recover = TransHelper::DEVICE_TYPE_STATUS_RECOVER;
        $data = $this->Dealparam(trim($req->getContent()));

        if (!is_array($data) || count($data) != 5) {
            return response('{fail,1,}');
        }
        if (is_array($data) && count($data) == 5) {
            $IMEI = isset($data[0]) ? (string)$data[0] : '';
            $device_type = isset($data[2]) ? (string)$data[2] : '';
            $model = isset($data[3]) ? (string)$data[3] : '';
            $cur_interval = isset($data[4]) ? (string)$data[4] : '';
            $d_status = isset($data[5]) ? (string)$data[5] : '';
            if (empty($IMEI)) {
                return response('{fail,0,IMEI is empty}');
            }
            //判断设备是否存在
            $device = DB::table('device')->select('id', 'device_number', 'cur_interval', 'device_status', 'warning')->where('device_number', $IMEI)->where('is_del', 0)->first();
            if (empty($device)) {
                return response('{fail,0,device is empty}');
            }
            $info = [
                'IMEI' => $IMEI,
                'device_type' => $device_type,
                'model' => $model,
                'cur_interval' => $cur_interval,
                'd_status' => $d_status,
            ];
            $device_heart_info = json_encode($info, JSON_UNESCAPED_UNICODE);
            $device_status = 1;
            if ($d_status == 0) {
                //未知
                $device_status = 1;
            } else if ($d_status == 1) {
                //正常
                $device_status = 2;
            } else if ($d_status == 5) {
                //操作中
                $device_status = 5;
            } else if ($d_status == 10) {
                //告警
                $device_status = 3;
            } else if ($d_status == 99) {
                //故障
                $device_status = 4;
            }
            $warning = 0;//详细的报警信息 若之前是正常或恢复 则这次无报警则
            if ($device_status == 2) {
                $warning = isset($device_type_status_recover[$device['warning']]) ? $device_type_status_recover[$device['warning']] : 0;
            }
            if ($device_status = 2 && in_array($device['warning'], [3, 4, 6, 8, 10, 12, 14])) {
                $warning = 0;
            }
            $warning_content = isset($device_type_status_detail[$warning]) ? $device_type_status_detail[$warning] : '未知';
            $time = time();
            $update = [
                'model' => $model,
                'warning' => $warning,
                'warning_content' => $warning_content,
                'recent_interval' => $cur_interval,//最近一次的心跳间隔
                'device_status' => $device_status,//设备状态默认1未知2正常或门磁关闭3门磁开启或异常或告警4故障中5测试中或操作中6离线
                'device_heart_info' => $device_heart_info,
                'hearted_at' => date('Y-m-d H:i:s', $time),
            ];

            //插入心跳记录
            $resmsg = '';
            try {
                DB::transaction(function () use ($update, $device_heart_info, $device, $time) {
                    //更新设备状态
                    DB::table('device')->where('id', $device['id'])->update($update);
                    $id = parent::getUid(); //生成唯一标识
                    $insert = [
                        'id' => $id,
                        'device_id' => $device['id'],
                        'device_number' => $device['device_number'],
                        'info' => $device_heart_info,
                        'uploaded_at' => date('Y-m-d H:i:s', $time),
                    ];
                    DB::table('heart_records_check')->insert($insert);
                    //如果上一次状态为报警这一次为正常则产生一次回复记录
                    /*if ($device['device_status'] == 3 && $update['device_status'] == 2) {
                        $insert_warn = [
                            'id' => parent::getUid(),//生成唯一标识
                            'device_id' => $device['id'],
                            'device_number' => $device['device_number'],
                            'fault_type' => $update['warning'],
                            'type' => 2,
                            'warning_content' => $update['warning_content'],
                            'detail' => $device_heart_info,
                            'uploaded_at' => date('Y-m-d H:i:s', $time),
                        ];
                        DB::table('device_warning')->insert($insert_warn);
                    }*/

                });
            } catch (\Exception $e) {
                $resmsg = 'system error';
            };
            if (!empty($resmsg)) {
                return response('{fail,0,' . $resmsg . '}');
            }
            return response('{Ok,' . $device['cur_interval'] . ',}');
        }
    }

}