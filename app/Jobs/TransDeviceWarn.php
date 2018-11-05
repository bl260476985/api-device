<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use App\Tasks\WarningPushEvent;
use Hhxsv5\LaravelS\Swoole\Task\Event;
use App\Utils\NumTransNameHelper as TransHelper;
use Ramsey\Uuid\Uuid;
use App\Utils\WarningPushHelper;
use App\Utils\AccountHelper;

class TransDeviceWarn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $newData = $this->data;
        $device_type_trans = TransHelper::DEVICE_BASE_TYPE;
        $device_type_status = TransHelper::DEVICE_TYPE_STATUS;
        $device_type_status_detail = TransHelper::DEVICE_TYPE_STATUS_DETAIL;
        $device = isset($newData['device']) ? $newData['device'] : [];
        $reqData = isset($newData['content']) ? $newData['content'] : [];
        $d_status = isset($reqData['info']['status']) ? (int)$reqData['info']['status'] : 0;//上传的状态
        $d_type = isset($reqData['info']['type']) ? (int)$reqData['info']['type'] : 0;//上报的类型 0 烟感 2 井盖 4 门磁
        $d_voltage = isset($reqData['info']['voltage']) ? (double)((int)$reqData['info']['voltage'] / 100) : $device['voltage'];//上传的电压
        $d_water = isset($reqData['info']['water']) ? (int)$reqData['info']['water'] : 0;//是否浸水 0正常 1浸水
        $d_lock_status = isset($reqData['info']['lock_status']) ? (int)$reqData['info']['lock_status'] : 1;//锁状态 0开启 1关闭
        $d_door_status = isset($reqData['info']['door_status']) ? (int)$reqData['info']['door_status'] : 1;//门状态 0开启 1关闭
        $device_heart_info = json_encode($reqData, JSON_UNESCAPED_UNICODE);
        $device_status = isset($device_type_status[$d_type][$d_status]) ? (int)$device_type_status[$d_type][$d_status] : 1;//未知
        $hearted_at = isset($reqData['uploadDateTime']) ? $reqData['uploadDateTime'] : date('Y-m-d H:i:s');//上传的设备类型
        $warning = 0;//详细的报警信息 若之前是正常或恢复 则这次无报警则
        if ($d_type == 0) {
            if ($device_status == 3) {
                $warning = 11;
            }
        } else if ($d_type == 2) {
            if ($d_water == 1) {
                //发生浸水井盖
                $warning = 7;
                $device_status = 3;
            } else if ($device_status == 3) {
                $warning = 9; //井盖异常开启
            }
        } else if ($d_type == 4) {
            if ($device_status == 3) {
                $warning = 13; //门磁打开
            }
        } else if ($d_type == 9) {
            //门锁
            if ($d_water == 1) {
                //门锁发生浸水
                $warning = 15;
                $device_status = 3;
            } else if ($d_lock_status == 1 && $d_door_status == 0) {
                //门开了锁未开
                $warning = 13;
                $device_status = 3;
            }
        }
        $warning_content = isset($device_type_status_detail[$warning]) ? $device_type_status_detail[$warning] : '未知';
        $update = [
            'voltage' => $d_voltage,
            'warning' => $warning,
            'warning_content' => $warning_content,//报警内容说明
            'device_status' => $device_status,//设备状态默认1未知2正常或门磁关闭3门磁开启或异常或告警4故障中5测试中或操作中6离线
            'device_info' => $device_heart_info,
            'recented_at' => $hearted_at,
        ];
        //插入报警记录并更新设备记录
        $resmsg = '';
        $warning_id = $this->getUid();
        try {
            DB::transaction(function () use ($update, $device_heart_info, $device, $hearted_at, $warning, $warning_id, $warning_content) {
                DB::table('device')->where('id', $device['id'])->update($update);
                //插入报警记录
                $insert = [
                    'id' => $warning_id,
                    'device_id' => $device['id'],
                    'device_number' => $device['device_number'],
                    'fault_type' => $warning,
                    'type' => 1,
                    'warning_content' => $warning_content,
                    'detail' => $device_heart_info,
                    'uploaded_at' => $hearted_at,
                ];
                DB::table('device_warning')->insert($insert);
            });
        } catch (\Exception $e) {
            $resmsg = 'third system error';
        };
        $hour = date('H');
        $can_push = AccountHelper::compareHour($hour, $device['push_st'], $device['push_et']);
        if (!$can_push) {
            info('TransDeviceWarn can not push message:', ['warning_id' => $warning_id, 'hour' => $hour, 'push_st' => $device['push_st'], 'push_et' => $device['push_et']]);
        }
        if (empty($resmsg) && $device['open_push'] == 1 && $can_push) {
            //没有错误产生向相关负责人推送报警消息
            $device_type_name = isset($device_type_trans[$device['device_type']]) ? $device_type_trans[$device['device_type']] : '';
            $push_data = [
                'warning_id' => $warning_id,
                'device_id' => $device['id'],
                'device_number' => $device['device_number'],
                'station_id' => $device['station_id'],
                'uploaded_at' => $hearted_at,
                'address' => $device['address'],
                'content' => $device['device_number'] . $device_type_name . '发生' . $warning_content,
                'detail' => $device_heart_info
            ];
            WarningPushHelper::push($push_data);
        }
    }

    /**
     * 根据分配长度获取id
     * @param $len
     * @return string
     */
    protected function getUid($len = 9)
    {
        $data = Uuid::uuid1('bf8f9cb');
        $id = $data->getInteger()->getValue();
        $id = substr($id, 0, $len);
        return $id;
    }
}
