<?php
namespace App\Tasks;

use Hhxsv5\LaravelS\Swoole\Task\Event;
use Hhxsv5\LaravelS\Swoole\Task\Listener;
use Illuminate\Support\Facades\DB;
use App\Utils\WarningPushHelper;
use App\Utils\NumTransNameHelper as TransHelper;

class DeviceAccessListener extends Listener
{
    // 声明没有参数的构造函数
    public function __construct()
    {
    }

    public function handle(Event $event)
    {
        // throw new \Exception('an exception');// handle时抛出的异常上层会忽略，并记录到Swoole日志，需要开发者try/catch捕获处理
//        sleep(2);// 模拟一些慢速的事件处理
        $info = $event->getData();
        $device_base_type_trans = TransHelper::DEVICE_BASE_TYPE_TRANS;
        $device_base_type_remark = TransHelper::DEVICE_BASE_TYPE_REMARK;
        $station = $info['station'];
        $manufacturer_id = (int)$info['manufacturer_id'];
        $Data = $info['data'];
        $new_data = [];
        //遍历data数据检查是否有重复数据
        if (count($Data) > 0) {
            foreach ($Data as $item) {
                if (isset($item['Data']) && count($item['Data']) > 0) {
                    foreach ($item['Data'] as $value) {
                        $mid = isset($value['Mid']) ? trim($value['Mid']) : '';
                        if (!empty($mid) && !array_key_exists($mid, $new_data)) {
                            $new_data[$mid] = $value;
                        } else {
                            info('DeviceAccessListener double device:' . $mid);
                        }
                    }
                }
            }
        }

        //对唯一数据进行入库
        if (count($new_data) > 0) {
            $devices = DB::table('device')->select('device_number')->where('is_del', 0)->get()->pluck('device_number')->unique()->values()->all();
            foreach ($new_data as $k => $value) {
                if (!in_array($k, $devices)) {
                    info('DeviceAccessListener map device:' . $k);
                    $id = WarningPushHelper::getUid();
                    try {
                        //将设备插入数据库
                        $insert = [
                            'id' => $id,
                            'device_name' => isset($value['Name']) ? trim($value['Name']) : '',
                            'device_number' => isset($value['Mid']) ? trim($value['Mid']) : '',
                            'station_id' => $station['id'],
                            'province_id' => $station['province_id'],
                            'city_id' => $station['city_id'],
                            'district_id' => $station['district_id'],
                            'province' => $station['province'],
                            'city' => $station['city'],
                            'district' => $station['district'],
                            'address' => $station['address'],
                            'longitude' => $station['longitude'],
                            'latitude' => $station['latitude'],
                            'manufacturer_id' => $manufacturer_id,
                            'provider' => isset($value['Remarks']) ? $value['Remarks'] : '',//备注
                            'device_type' => isset($device_base_type_trans[$value['TypeName']]) ? (int)$device_base_type_trans[$value['TypeName']] : 0,
                            'device_remarks' => isset($device_base_type_remark[$value['TypeName']]) ? $device_base_type_remark[$value['TypeName']] : '',
                            'recent_interval' => 4,
                            'cur_interval' => 4,
                        ];
                        DB::table('device')->insert($insert);
                    } catch (\Exception $e) {
                        logger('DeviceAccessListener handle occur exception device id:' . $id);
                    }
                }
            }
        }

    }
}