<?php

namespace App\Utils;

use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

class SmsSender
{

    const TYPE_COMMON = 'common';

    public static $TEXTS = [
        'common' => '【电蛙充电】#content#,详情请登录电蛙管理平台查看或拨打电话咨询。',
    ];

    /**
     * [$httpClient description]
     * @var \GuzzleHttp\Client
     */
    private $httpClient;

    /**
     * get an instance
     * @return \App\Utils\SmsSender
     */
    public static function instance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new SmsSender();
        }
        return $instance;
    }

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 10,
            'verify' => false
        ]);
    }

    /**
     * send message
     * @param string $type
     * @param string $phone
     * @param string $code
     * @return boolean
     */
    public function send($type, $phone, $code)
    {
        $text = str_replace('#content#', $code, self::$TEXTS[$type]);
        $params = [
            'apikey' => env('YUNPIAN_API_KEY', '582b3024812078342228f8d09a3fcd61'),
            'mobile' => $phone,
            'text' => $text,
        ];
        info('Try to send msg to yunpian:' . $type . ',' . $phone . ',' . $code);
        $response = $this->httpClient->post(env('YUNPIAN_API_URL', 'http://yunpian.com/v1/sms/send.json'), ['form_params' => $params]);
        $body = json_decode((string)$response->getBody(), true);
        if ($body['code'] !== 0) {
            logger('Send to yunpian failed:' . $body['code'] . ',' . $body['msg']);
            return false;
        }
        return true;
    }

}
