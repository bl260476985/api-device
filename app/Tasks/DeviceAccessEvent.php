<?php
namespace App\Tasks;

use Hhxsv5\LaravelS\Swoole\Task\Event;

class DeviceAccessEvent extends Event
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}