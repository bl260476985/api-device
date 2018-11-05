<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class WarningPushHelper
{

    /**
     * encrypt password
     * @param  string $pwd
     * @param  string $salt
     * @return string
     */
    public static function push($data)
    {
        //判断是否要对该报警信息发送
        //获取需要推送的openid集合
        $result = self::getOpenIdS($data['station_id']);//获取openid
        $openIds = $result['open_id'];
        $bind_user_id = $result['user_id'];
        if (count($openIds) == 0) {
            logger('WarningPushListener handle openIds empty');
        } else {
            foreach ($openIds as $openId) {
                $push_id = self::getUid();
                try {
                    DB::transaction(function () use ($push_id, $data, $openId, $bind_user_id) {
                        //插入报警信息待发送
                        $push = [
                            'id' => $push_id,
                            'device_id' => $data['device_id'],
                            'device_number' => $data['device_number'],
                            'open_id' => $openId,
                            'bind_user_id' => $bind_user_id,
                            'warning_id' => $data['warning_id'],
                            'content' => $data['content'],
                            'address' => $data['address'],
                            'status' => 1,
                            'detail' => $data['detail'],
                            'push_again' => 1,//默认1仍需发送2不需要
                            'uploaded_at' => $data['uploaded_at'],//报警时间
                        ];
                        DB::table('device_warning_push')->insert($push);
                        //发送
                        $wx_push = [
                            'warn_time' => $data['uploaded_at'],
                            'address' => $data['address'],
                            'content' => $data['content'],
                            'warning_id' => $data['warning_id'],
                        ];

                        $res = WxpushHelper::buildNoticeInfo($openId, $wx_push);
                        //更新报警信息为已发送
                        if ($res) {
                            DB::table('device_warning_push')->where('id', $push_id)->update([
                                'status' => 2
                            ]);
                        }
                    });
                } catch (\Exception $e) {
                    logger('WarningPushListener handle occur exception push id:' . $push_id);
                }
            }
        }
    }

    private static function getOpenIdS($stationId)
    {
        $result = [
            'user_id' => 0,
            'open_id' => []
        ];
        if (!empty($stationId)) {
            /*
            $userIds = DB::table('station_user_bind')->select('user_id')
                ->where('station_id', $stationId)
                ->where('is_del', 0)
                ->get()
                ->pluck('user_id')
                ->unique()
                ->values()
                ->all();
            */
            $userId = DB::table('station')->select('bind_user_id')
                ->where('id', $stationId)
                ->where('is_del', 0)
                ->first();
            if (!empty($userId) && !empty($userId['bind_user_id'])) {
                $openIds = DB::table('wx_user')->select('open_id')
                    ->where('user_id', $userId['bind_user_id'])
                    ->where('type', 1)
                    ->where('is_del', 0)
                    ->get()
                    ->pluck('open_id')
                    ->unique()
                    ->values()
                    ->all();
                $result = [
                    'user_id' => $userId['bind_user_id'],
                    'open_id' => $openIds
                ];
            }
        }
        return $result;
    }

    /**
     * 根据分配长度获取id
     * @param $len
     * @return string
     */
    public static function getUid($len = 9)
    {
        $data = Uuid::uuid1('bf8f9cb');
        $id = $data->getInteger()->getValue();
        $id = substr($id, 0, $len);
        return $id;
    }


}