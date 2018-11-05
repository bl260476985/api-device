<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use App\Utils\NumTransNameHelper as TransHelper;
use Ramsey\Uuid\Uuid;

class TransDeviceInfo implements ShouldQueue
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
        $device_type_status = TransHelper::DEVICE_TYPE_STATUS;
        $device_type_heart = TransHelper::DEVICE_TYPE_HEART;
        $device_type_status_detail = TransHelper::DEVICE_TYPE_STATUS_DETAIL;
        $device_type_status_recover = TransHelper::DEVICE_TYPE_STATUS_RECOVER;
        $device = isset($newData['device']) ? $newData['device'] : [];
        $reqData = isset($newData['content']) ? $newData['content'] : [];
        $d_status = isset($reqData['info']['status']) ? (int)$reqData['info']['status'] : 0;//上传的状态
        $d_type = isset($reqData['info']['type']) ? (int)$reqData['info']['type'] : 0;//上报的类型
        $d_voltage = isset($reqData['info']['voltage']) ? (double)((int)$reqData['info']['voltage'] / 100) : $device['voltage'];//上传的电压
        $d_temperature = isset($reqData['info']['temperature']) ? $reqData['info']['temperature'] : null;//温度
        $d_humidity = isset($reqData['info']['humidity']) ? $reqData['info']['humidity'] : null;//湿度
        $d_light = isset($reqData['info']['light']) ? (int)$reqData['info']['light'] : null;//光照
        $d_water = isset($reqData['info']['water']) ? (int)$reqData['info']['water'] : 0;//是否浸水 0正常 1浸水
        $d_lock_status = isset($reqData['info']['lock_status']) ? (int)$reqData['info']['lock_status'] : 1;//锁状态 0开启 1关闭
        $d_door_status = isset($reqData['info']['door_status']) ? (int)$reqData['info']['door_status'] : 1;//门状态 0开启 1关闭
        $device_heart_info = json_encode($reqData, JSON_UNESCAPED_UNICODE);
        $device_status = isset($device_type_status[$d_type][$d_status]) ? (int)$device_type_status[$d_type][$d_status] : 1;//未知
        $hearted_at = isset($reqData['uploadDateTime']) ? $reqData['uploadDateTime'] : date('Y-m-d H:i:s');//上传的设备类型
        $warning = 0;//心跳信息中有可能存在多种报警状态每个设备只保存最后的状态
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
                $warning = 13; //门磁打开 咱定为报警
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
        if ($device_status == 2) {
            $warning = isset($device_type_status_recover[$device['warning']]) ? $device_type_status_recover[$device['warning']] : 0;
        }
        if ($device_status == 2 && in_array($device['warning'], [3, 4, 6, 8, 10, 12, 14, 16])) {
            $warning = 0;
        }
        $warning_content = isset($device_type_status_detail[$warning]) ? $device_type_status_detail[$warning] : '未知';
        $update = [
            'voltage' => $d_voltage,
            'warning' => $warning,
            'warning_content' => $warning_content,
            'recent_interval' => 4,//最近一次的心跳间隔
            'device_status' => $device_status,//设备状态默认1未知2正常或门磁关闭3门磁开启或异常或告警4故障中5测试中或操作中6离线
            'device_heart_info' => $device_heart_info,
            'hearted_at' => $hearted_at,
        ];
        $heart_table = isset($device_type_heart[$d_type]) ? $device_type_heart[$d_type] : '';
        //插入心跳记录
        $resmsg = '';
        try {
            DB::transaction(function () use ($update, $device_heart_info, $device, $hearted_at, $heart_table, $d_temperature, $d_humidity, $d_light, $d_water) {
                //更新设备状态
                DB::table('device')->where('id', $device['id'])->update($update);
                $id = $this->getUid(); //生成唯一标识
                $insert = [
                    'id' => $id,
                    'device_id' => $device['id'],
                    'device_number' => $device['device_number'],
                    'voltage' => $update['voltage'],
                    'temperature' => $d_temperature,
                    'dampness' => $d_humidity,
                    'beam' => $d_light,
                    'is_water' => $d_water,
                    'info' => $device_heart_info,
                    'uploaded_at' => $hearted_at,
                ];
                if (!empty($heart_table)) {
                    DB::table($heart_table)->insert($insert);
                }
                //如果上一次状态为报警这一次为正常则产生一次回复记录
                /*if ($device['device_status'] == 3 && $update['device_status'] == 2) {
                    $insert_warn = [
                        'id' => $this->getUid(),//生成唯一标识
                        'device_id' => $device['id'],
                        'device_number' => $device['device_number'],
                        'fault_type' => $update['warning'],
                        'type' => 2,
                        'warning_content' => $update['warning_content'],
                        'detail' => $device_heart_info,
                        'uploaded_at' => $hearted_at,
                    ];
                    DB::table('device_warning')->insert($insert_warn);
                }*/
            });
        } catch (\Exception $e) {
            $resmsg = 'third heart system error';
        };

    }

    /**
     * 根据分配长度获取id
     * @param $len
     * @return string
     */
    protected function getUid($len = 9)
    {
        $data = Uuid::uuid1('af8f9cb');
        $id = $data->getInteger()->getValue();
        $id = substr($id, 0, $len);
        return $id;
    }
}
