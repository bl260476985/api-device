<?php
namespace App\Tasks;

use Hhxsv5\LaravelS\Swoole\Task\Event;
use Hhxsv5\LaravelS\Swoole\Task\Listener;
use App\Utils\WarningPushHelper;


class WarningPushListener extends Listener
{
    // 声明没有参数的构造函数
    public function __construct()
    {
    }

    public function handle(Event $event)
    {
        // throw new \Exception('an exception');// handle时抛出的异常上层会忽略，并记录到Swoole日志，需要开发者try/catch捕获处理
        info('WarningPushListener handle start', $event->getData());
//        sleep(2);// 模拟一些慢速的事件处理
        $data = $event->getData();
        WarningPushHelper::push($data);

    }
}