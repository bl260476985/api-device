<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\WxpushHelper;
use App\Tasks\WarningPushEvent;
use Hhxsv5\LaravelS\Swoole\Task\Event;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class DebugController extends BaseApiController
{
    public function test(Request $req)
    {
//        $data = [
//            'warn_time' => date('Y-m-d H:i:s'),
//            'address' => '上海市浦东新区',
//            'content' => 'ASE000111发生设备报警',
//            'warning_id' => 167241815
//        ];
//
//        $openid = 'oqga2wqF-c3GAtUdg9pW-rsx5P3E';
//        $res = WxpushHelper::buildNoticeInfo($openid, $data);
//        var_dump($res);

//        $success = Event::fire(new WarningPushEvent('event data'));
//        var_dump(date('Y-m-d H:i:s'));
//        logger(date('Y-m-d H:i:s'));
//        DB::table('station_user_bind')->insert([
//            'id' => parent::getUid(),
//            'station_id' => 287039777,
//            'user_id' => 1000000000,
//        ]);


//        $url = env('WX_PUBLICE_URL', 'http://xiao.nbiotsg.com') . '/refreshaccesstoken';
//        $client = new Client(['timeout' => 10]);
//        $res = $client->request('GET', $url, [
//            'timeout' => 10,
//            'connect_timeout' => 2,
//        ]);
//        if ((int)$res->getStatusCode() !== 200) {
//            logger('request push refreshaccesstoken error');
////        }
//        $IMEI = 'ASE00044666';
//        //判断设备是否存在
//        $device = DB::table('device')->select('id', 'device_number', 'station_id', 'address', 'device_type')->where('device_number', $IMEI)->where('is_del', 0)->first();
//        if (empty($device)) {
//            return response('{fail,device is empty}');
//        }
//        var_dump($device);
        $token = '5bd01fa6799d9b503810097f';
        $url = 'http://api.nbiotemtc.com:10221/machine/api/Machine/GetUserOwnerMachine?session=' . $token;
        $client = new Client(['timeout' => 10]);
        $res = $client->request('GET', $url, [
            'timeout' => 10,
            'connect_timeout' => 2,
        ]);
        if ((int)$res->getStatusCode() !== 200) {
            return $this->fail('get data info error');
        }
        $body = json_decode(trim((string)$res->getBody()), true);
        $new_data = [];
        if (isset($body['Message']) && $body['Message'] == 'ok') {
            if (isset($body['Data']) && !empty($body['Data'])) {
                $Data = $body['Data'];
                if (count($Data) > 0) {
                    foreach ($Data as $item) {
                        if (isset($item['Data']) && count($item['Data']) > 0) {
                            foreach ($item['Data'] as $value) {
                                if (isset($value['Mid']) && !empty($value['Mid']) && !array_key_exists($value['Mid'], $new_data) && !empty($value['LocationPoint'])) {
                                    $new_data[$value['Mid']] = $value;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this->success($new_data);
    }

    public function test2(Request $req)
    {
//        $data = [
//            'warn_time' => date('Y-m-d H:i:s'),
//            'address' => '测试地址',
//            'content' => '测试推送设备报警',
//        ];
//
//        $openid = 'oqga2wpgrXpqD-si8G9heAgI9lvE';
//        $res = WxpushHelper::buildNoticeInfo($openid, $data);
//        dump($res);

//        $success = Event::fire(new WarningPushEvent('event data'));
//        var_dump(date('Y-m-d H:i:s'));
//        logger(date('Y-m-d H:i:s'));
//        DB::table('station_user_bind')->insert([
//            'id' => parent::getUid(),
//            'station_id' => 287039777,
//            'user_id' => 1000000000,
//        ]);
        var_dump('ok2');
    }

}
