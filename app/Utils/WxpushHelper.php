<?php
namespace App\Utils;

use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;


class WxpushHelper
{

    /**
     * build pay info
     * @param  array $params
     * @return string
     */
    public static function buildNoticeInfo($openid, $data)
    {
        $warn_time = $data['warn_time'];
        $address = $data['address'];
        $content = $data['content'];
        $warning_id = $data['warning_id'];//报警id
        $token = self::actionGetToken();
        //设置url
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $token;
        $params = [
            'touser' => $openid,//用户openid
            'template_id' => Config::get('wx_template_id', 'XvFkO6504VXCOETsrs3R19gDLAm1L0txtTx7lBVyLc4'),//模板ID
            'url' => '',//模板跳转链接
            'miniprogram' => [
                'appid' => 'wx7b8e09f78e0272e7',//小程序appid
                'pagepath' => 'pages/order-detail/order-detail?warning_id=' . $warning_id,//小程序具体页面
            ],//跳小程序所需数据
            'data' => [
                'first' => [
                    'value' => '设备报警',
                    'color' => '#173177',
                ],//标题
                'keyword1' => [
                    'value' => $warn_time,
                    'color' => '#173177',
                ],//报警时间
                'keyword2' => [
                    'value' => $address,
                    'color' => '#173177',
                ],//报警位置
                'keyword3' => [
                    'value' => $content,
                    'color' => '#173177',
                ],//报警内容
                'remark' => [
                    'value' => '点击查看详情',
                    'color' => '#173177',
                ],//备注
            ],
        ];
        return self::actionCurlRequest($url, $params);
    }

    /**
     * verify notify id
     * @param  string $notifyId
     * @return
     */
    private static function actionGetToken()
    {
        $info = Cache::store('redis')->get('FROG_WEIXIN_ACCESS_TOKEN');
        if ($info === null) {
            logger('can not find FROG_WEIXIN_ACCESS_TOKEN');
            $info = Cache::store('redis')->get('FROG_WEIXIN_ACCESS_TOKEN');
            logger('find FROG_WEIXIN_ACCESS_TOKEN again');
        }
        //重新请求生成token
        if (empty($info)) {
            self::reqToken();
            $info = Cache::store('redis')->get('FROG_WEIXIN_ACCESS_TOKEN');
        }
        return $token = isset($info['token']) ? $info['token'] : '';
    }

    /**
     * 请求token
     */
    private static function reqToken()
    {
        $url = env('WX_PUBLICE_URL', 'http://xiao.nbiotsg.com') . '/refreshaccesstoken';
        $client = new Client(['timeout' => 10]);
        $res = $client->request('GET', $url, [
            'timeout' => 10,
            'connect_timeout' => 2,
        ]);
        if ((int)$res->getStatusCode() !== 200) {
            logger('request push refreshaccesstoken error');
        }
        return true;
    }

    /**
     * verify notify id
     * @param  string $notifyId
     * @return
     */
    private static function actionCurlRequest($url, $data)
    {
        $client = new Client(['timeout' => 10]);
        $res = $client->request('POST', $url, [
            'timeout' => 10,
            'connect_timeout' => 2,
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        if ((int)$res->getStatusCode() !== 200) {
            logger('request push notice error');
            return false;
        }
        $body = json_decode(trim((string)$res->getBody()), true);
        // print_r($body);
        if (isset($body['errcode']) && $body['errcode'] !== 0) {
            logger('request push notice body error:' . $body['errcode'] . ',' . $body['errmsg']);
            return false;
        }

        return true;
    }

}