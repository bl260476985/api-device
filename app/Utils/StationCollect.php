<?php

namespace App\Utils;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;


class StationCollect
{
    /**
     * get an instance
     * @return
     */

    public function __construct()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new SmsSender();
        }
        return $instance;
    }

    /**
     * send
     * @param string $type
     * @param string $phone
     * @param string $code
     * @return
     */
    public function quantityCount($stationId)
    {
        logger('curDate1:' . date('Y-m-d H:i:s'));
        info('curId:' . $stationId);
        logger('curDate2:' . date('Y-m-d H:i:s'));

    }

}
