<?php

namespace App\Http\Controllers\Api\V1\DeviceClient;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use App\Http\Controllers\BaseApiController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;
use App\Tasks\DeviceAccessEvent;
use Hhxsv5\LaravelS\Swoole\Task\Event;


class DeviceAccessController extends BaseApiController
{

    /**
     * report 上报参数
     * @param  Request $req
     * @return Response
     */
    public function token(Request $req)
    {
        $data = json_decode(trim($req->getContent()), true);
        if (!isset($data['userName']) || empty($data['userName'])) {
            return $this->fail('缺少用户名参数');
        }
        if (!isset($data['pwd']) || empty($data['pwd'])) {
            return $this->fail('缺少用户名密码');
        }
        $userName = $data['userName'];
        $pwd = $data['pwd'];
        $url = 'http://api.nbiotemtc.com:10221/user/api/User/UserLogin';
        $client = new Client(['timeout' => 10]);
        $res = $client->request('POST', $url, [
            'timeout' => 10,
            'connect_timeout' => 2,
            'json' => ['userName' => $userName, 'pwd' => $pwd]
        ]);
        if ((int)$res->getStatusCode() !== 200) {
            return $this->fail('request access token error');
        }
        $body = json_decode(trim((string)$res->getBody()), true);
        if (isset($body['Message']) && $body['Message'] == 'success') {
            Cache::store('redis')->put('NBIOTEMTC_ACCESS_TOKEN', $body['Data'], 95);
        }
        info('NBIOTEMTC_ACCESS_TOKEN access token:' . $body['Data']);
        return $this->success();
    }

    /**
     * search 获取设备列表
     * @param  Request $req
     * @return Response
     */
    public function search(Request $req)
    {
        $data = json_decode(trim($req->getContent()), true);
        if (!isset($data['station_id']) || empty($data['station_id'])) {
            return $this->fail('缺少设备组参数');
        }
        if (!isset($data['manufacturer_id']) || empty($data['manufacturer_id'])) {
            return $this->fail('缺少设备制造商参数');
        }
        $station_id = (int)$data['station_id'];
        $manufacturer_id = (int)$data['manufacturer_id'];
        $station = DB::table('station')->select('id', 'name', 'country_id', 'province_id', 'city_id', 'district_id', 'province', 'city', 'district', 'address', 'longitude', 'latitude')->where('id', $station_id)->where('is_del', 0)->first();
        if (empty($station)) {
            return $this->fail('设备组不存在');
        }

        $token = Cache::store('redis')->get('NBIOTEMTC_ACCESS_TOKEN');
        if (empty($token)) {
            $token = Cache::store('redis')->get('NBIOTEMTC_ACCESS_TOKEN');
            if (empty($token)) {
                return $this->fail('token已失效，请重新获取token');
            }
        }
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
        if (isset($body['Message']) && $body['Message'] == 'ok') {
            if (isset($body['Data']) && !empty($body['Data'])) {
                $info = [
                    'station' => $station,
                    'manufacturer_id' => $manufacturer_id,
                    'data' => $body['Data'],
                ];
                Event::fire(new DeviceAccessEvent($info));
            }
        } else {
            info('NBIOTEMTC_ACCESS_DEVICE ERROR:', $body);
            $msg = isset($body['Message']) ? $body['Message'] : '';
            return $this->fail($msg);
        }
        return $this->success('设备正在入库，请稍后查询');
    }

}