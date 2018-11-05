<?php

namespace App\Utils;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use App\Utils\WxpushHelper;

class WarnInfoPush
{
    /**
     * get an instance
     * @return
     */

    public function __construct()
    {

    }

    /**
     * send
     * @param string $type
     * @param string $phone
     * @param string $code
     * @return
     */
    public function pushChange()
    {
        $pushs = DB::table('device_warning_push')->select('id', 'device_id', 'device_number', 'open_id', 'bind_user_id',
            'warning_id', 'content', 'address', 'detail', 'uploaded_at', 'created_at')
            ->where('push_again', 1)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
        foreach ($pushs as $push) {
            //距离上次发送时间不到的暂不发送
            if ((time() - strtotime($push['created_at'])) < 90) {
                continue;
            }
            $openId = $push['open_id'];
            $push_id = $this->getUid();
            try {
                //插入报警信息待发送
                $push_again = [
                    'id' => $push_id,
                    'device_id' => $push['device_id'],
                    'device_number' => $push['device_number'],
                    'open_id' => $push['open_id'],
                    'bind_user_id' => $push['bind_user_id'],
                    'warning_id' => $push['warning_id'],
                    'content' => $push['content'],
                    'address' => $push['address'],
                    'status' => 1,
                    'detail' => $push['detail'],
                    'push_again' => 2,//默认1仍需发送2不需要
                    'push_cnt' => 2,//第二次发送
                    'uploaded_at' => $push['uploaded_at'],//报警时间
                ];
                DB::table('device_warning_push')->insert($push_again);
                //发送
                $wx_push = [
                    'warn_time' => $push['uploaded_at'],
                    'address' => $push['address'],
                    'content' => $push['content'],
                    'warning_id' => $push['warning_id'],
                ];

                $res = WxpushHelper::buildNoticeInfo($openId, $wx_push);
                //更新报警信息为已发送
                if ($res) {
                    DB::table('device_warning_push')->where('id', $push['id'])->update([
                        'push_again' => 2 //不需要重新发送
                    ]);
                    DB::table('device_warning_push')->where('id', $push_id)->update([
                        'status' => 2,
                    ]);
                }
            } catch (\Exception $e) {
                logger('WarnInfoPush handle again exception push id:' . $push_id);
            }
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
