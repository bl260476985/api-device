<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Utils\CommonHelper;

final class AccountHelper
{
    const TYPE_MAP = [
        1 => 'DW_ADMIN_SYSTEMUSER_',
        2 => 'A',
        3 => 'B',
        4 => 'C',
        5 => 'D',
    ];

    /**
     * validate password
     * @param  string $pwd
     * @return boolean
     */
    public static function isPasswordValid($pwd)
    {
        $matches = [];
        if (preg_match('/^[A-Za-z0-9@&#\._]{6,16}$/', $pwd, $matches)) {
            return true;
        }
        return false;
    }

    /**
     * encrypt password
     * @param  string $pwd
     * @param  string $salt
     * @return string
     */
    public static function encryptPassword($pwd, $salt)
    {
        return md5('CODT_FROG_' . $salt . $pwd . '_PWD');
    }

    /**
     * generate image file name
     * @return string
     */
    public static function generateImageFileName()
    {
        $random = microtime(true) . mt_rand(1, 99999);
        return substr(base64_encode(hash_hmac('sha256', $random, Config::get('app.key'))), 0, 16);
    }

    /**
     * generate uuid
     * @return string
     */
    public static function generateUUID()
    {
        $random = microtime(true) . mt_rand(1, 99999);
        return substr(base64_encode(hash_hmac('sha256', $random, Config::get('app.key'))), 0, 32);
    }

    /**
     * generate session id
     * @param
     * @param
     * @return
     */
    public static function generateSESSIONID($userId, $timestamp)
    {
        $random = $userId . $timestamp;
        return substr(base64_encode(hash_hmac('sha256', $random, Config::get('app.key'))), 0, 32);
    }

    /**
     * do login
     * @param int $user
     * @param string $ip
     * @return void
     */
    public static function login($userId, $ip)
    {
        Session::flush();
        Session::regenerate();
        Session::put('DwUserId', $userId);

        logger('logined in.', ['userId' => $userId, 'ip' => $ip]);

        DB::table('log_system_user_login')->insert([
            'user_id' => $userId,
            'ip' => $ip,
        ]);
        return;
    }

    /**
     * do logout
     * @param  array $user
     * @return void
     */
    public static function logout($userId, $ip)
    {
        if (!empty($userId)) {
            logger('logout.', ['userId' => $userId, 'ip' => $ip]);
            Session::flush();
            Session::regenerate();
        }

    }

    /**
     * 平台系统用户 初始化密码
     * @param  array $user
     * @return
     */
    public static function initpwd($pwd = 'CdotDw123456')
    {
        $msg = [
            'seed' => '',
            'pass' => ''
        ];

        $msg['seed'] = Str::random(16);
        $msg['pass'] = AccountHelper::encryptPassword($pwd, $msg['seed']);

        return $msg;
    }

    /**
     * can manage the enterprise
     * @param array $enterpriseMap
     * @param array $opSystemUser
     * @param int $enterpriseId
     * @return boolean
     */
    public static function canManageTheEnterprise($enterpriseMap, $opSystemUser, $enterpriseId)
    {
        if ($opSystemUser['type'] == 0 || self::have($enterpriseMap, $opSystemUser['enterprise_id'], $enterpriseId)) {
            return true;
        }
        return false;
    }

    /**
     * check parent enterprise and child enterprise
     * @param array $enterpriseMap
     * @param int $parentId
     * @param int $childId
     * @return boolean
     */
    public static function have($enterpriseMap, $parentId, $childId)
    {
        if ($parentId == 0) {
            return true;
        }
        if (!isset($enterpriseMap[$parentId]) || !isset($enterpriseMap[$childId])) {
            return false;
        }

        if ($childId == $parentId) {
            return true;
        }
        $id = $childId;
        $deps = 4;
        while ($deps > 0) {
            $id = $enterpriseMap[$id]['parent_id'];
            if (!isset($enterpriseMap[$id])) {
                return false;
            }
            if ($id == $parentId) {
                return true;
            }

            $deps--;
        }
        return false;
    }

    /**
     * generateMicro
     * @param
     * @return
     */
    public static function generateMicro()
    {
        $msectime = 0;
        list($mesc, $sec) = explode(' ', microtime());
        $msectime = sprintf('%03d', floatval($mesc) * 1000);
        return $msectime;
    }

    /**
     * generateStream
     * @param
     * @return
     */
    public static function generateStream($userId)
    {
        $userStreamId = '';
        $tempNumber = 100000000;
        $newNumber = $tempNumber + (int)$userId;
        $realNumber = substr($newNumber, -8);
        $msectime = self::generateMicro();
        $userStreamId = 'F' . date('YmdHis') . $msectime . $realNumber . mt_rand(100000, 999999);
        return $userStreamId;
    }

    /**
     * generateStream
     * @param
     * @return
     */
    public static function generateDeposit($type = 'A')
    {
        $orderNumber = $type . date('YmdHis') . mt_rand(100000, 999999);
        return $orderNumber;
    }

    /** 插入充值订单记录
     * insertDeposit  A 为平台充值
     * @param
     * @return
     */
    public static function insertDeposit($info, $type = 'A')
    {
        $result = [
            'depositId' => 0,
            'orderNumber' => ''
        ];

        $result['orderNumber'] = self::generateDeposit($type);
        $result['depositId'] = DB::table('deposit_order')->insertGetId([
            'order_number' => $result['orderNumber'],
            'system_user_id' => $info['system_user_id'],
            'user_id' => $info['user_id'],
            'payment' => $info['payment'],
            'status' => $info['status'],
            'remarks' => $info['remarks'],
            'deposit_type' => $info['deposit_type'],
        ]);

        return $result;
    }

    /** 插入提现订单记录T为提现R为退款S为分润系统产生
     * insertWithDrawals
     * @param
     * @return
     */
    public static function insertWithDrawals($info, $type = 'T')
    {
        $result = [
            'depositId' => 0,
            'orderNumber' => ''
        ];

        $result['orderNumber'] = self::generateDeposit($type);
        $result['depositId'] = DB::table('withdrawals_order')->insertGetId([
            'order_number' => $result['orderNumber'],
            'user_id' => $info['user_id'],
            'payment' => $info['payment'],
            'status' => $info['status'],
            'type' => $info['type'],
            'deposit_type' => $info['deposit_type'],
            'remarks' => $info['remarks'],
        ]);

        return $result;
    }

    /** 插入流水记录
     * insertStream
     * @param
     * @return
     */
    public static function insertStream($userId, $info)
    {
        $streamId = 0;
        $userStreamId = self::generateStream($userId);

        $insert = [
            'stream_number' => $userStreamId,
            'user_id' => (int)$userId,
            'enterprise_id' => (int)$info['enterprise_id'],
            'order_id' => (int)$info['order_id'],
            'order_number' => $info['order_number'],
            'action_source' => (int)$info['action_source'],
            'action_type' => (int)$info['action_type'],
            'pre_balance' => (int)$info['pre_balance'],
            'pay_balance' => (int)$info['pay_balance'],
            'cur_balance' => (int)$info['cur_balance'],
            'system_user_id' => (int)$info['system_user_id'],
            'remarks' => $info['remarks'],
        ];
        $streamId = DB::table('balance_stream')->insertGetId($insert);

        return $streamId;
    }

    /** 插入提现流水记录
     * insertStream
     * @param
     * @return
     */
    public static function insertWithDrawalsStream($userId, $info)
    {
        $streamId = 0;
        $userStreamId = self::generateStream($userId);

        $insert = [
            'stream_number' => $userStreamId,
            'user_id' => (int)$userId,
            'enterprise_id' => (int)$info['enterprise_id'],
            'order_id' => (int)$info['order_id'],
            'order_number' => $info['order_number'],
            'refund_id' => (int)$info['refund_id'],
            'refund_number' => $info['refund_number'],
            'action_source' => (int)$info['action_source'],
            'action_type' => (int)$info['action_type'],
            'pre_balance' => (int)$info['pre_balance'],
            'pay_balance' => (int)$info['pay_balance'],
            'cur_balance' => (int)$info['cur_balance'],
            'system_user_id' => (int)$info['system_user_id'],
            'remarks' => $info['remarks'],
        ];
        $streamId = DB::table('system_balance_stream')->insertGetId($insert);

        return $streamId;
    }

    /** 返回图片logo
     * insertStream
     * @param
     * @return
     */

    public static function logoUrl($logoId)
    {
        $epr_logo_url = '';
        $picurls = DB::table('station_pic')->select('id', 'url')->where('id', $logoId)->where('is_del', 0)->first();
        if (!empty($picurls)) {
            $epr_logo_url = Config::get('app.logo_url') . $picurls['url'];
        }

        return $epr_logo_url;
    }

    /** 联桩获取当前用户组增值服务数据
     * insertStream
     * @param
     * @return
     */

    public static function getAllModules()
    {
        //系统所有模块
        $modulesAllInfo = [];
        $modulesAll = CommonHelper::getSystemModules()->get();
        $modulesAllInfo = $modulesAll->keyBy('id')->all();

        return $modulesAllInfo;
    }

    /** 联桩获取当前用户组增值服务数据
     * insertStream
     * @param
     * @return
     */

    public static function getCurGroupModules($userGroupId)
    {
        //系统所有模块
        $modulesAllInfo = self::getAllModules();
        //系统用户组拥有权限
        $hsasmodules = [];
        $modules = CommonHelper::getSystemGroupModules($userGroupId)->get();
        $hsasmodules = $modules->map(function ($item) use ($modulesAllInfo) {
            $module = $item;
            $module['name'] = '';
            $module['key'] = '';
            $module['level'] = 0;
            $module['parent_id'] = 0;
            if (array_key_exists($module['module_id'], $modulesAllInfo)) {
                $module['name'] = $modulesAllInfo[$item['module_id']]['name'];
                $module['key'] = $modulesAllInfo[$item['module_id']]['key'];
                $module['level'] = $modulesAllInfo[$item['module_id']]['level'];
                $module['parent_id'] = $modulesAllInfo[$item['module_id']]['parent_id'];
            }
            return $module;
        })->all();
        return $hsasmodules;
    }

    /** 联桩获取新版增值服务数据
     * insertStream
     * @param
     * @return
     */

    public static function getGroupModules($userGroupId)
    {
        $result = [];
        //系统用户组拥有权限
        $hsasmodules = CommonHelper::getSystemGroupModules($userGroupId)->get()->toArray();

        $mapModule = array_column($hsasmodules, 'module_id');

        $modulesAll = CommonHelper::getSystemModules()->get();
        foreach ($modulesAll as $module) {
            $module['has_privilege'] = false;
            if (in_array($module['id'], $mapModule)) {
                $module['has_privilege'] = true;
            }
            $result[] = $module;
        }
        return $result;
    }

    /** 装换对应数字为字符类型
     * insertStream
     * @param
     * @return
     */

    public static function numberString($type)
    {
        $type_str = '';
        switch ($type) {
            case 1:
                $type_str = self::TYPE_MAP[1];
                break;
            case 2:
                $type_str = self::TYPE_MAP[2];
                break;
            case 3:
                $type_str = self::TYPE_MAP[3];
                break;
            default:
                break;
        }

        return $type_str;
    }


    /** 根据类型判断数据缓存是否存在
     * insertStream
     * @param
     * @return
     */

    public static function cacheHas($type, $content)
    {
        $type_str = self::numberString($type);
        $type_str .= $content;
        $info = Cache::store('redis')->get($type_str);
        if (empty($info)) {
            return false;
        }
        return true;
    }

    /** 根据类型进行数据缓存
     * insertStream
     * @param
     * @return
     */

    public static function cachePut($type, $content, $data)
    {
        $type_str = self::numberString($type);
        $type_str .= $content;
        if (!empty($type_str)) {
            Cache::store('redis')->forever($type_str, $data);
        }
        return true;

    }

    /** 根据类型获取数据缓存
     * insertStream
     * @param
     * @return
     */

    public static function cacheGet($type, $content)
    {
        $type_str = self::numberString($type);
        $type_str .= $content;
        $info = Cache::store('redis')->get($type_str);
        return $info;
    }

    /** 根据站点桩号统计下面的不同状态的端口数量
     * insertStream
     * @param
     * @return
     */
    public static function devicePortGet($stationIds = [], $deviceIds = [])
    {
        $device_ports = DB::table('device_port')->select('id', 'status')
            ->when(count($stationIds) > 0, function ($query) use ($stationIds) {
                return $query->whereIn('station_id', $stationIds);
            })->when(count($deviceIds) > 0, function ($query) use ($deviceIds) {
                return $query->whereIn('device_id', $deviceIds);
            })->where('is_del', 0)->get();

        return $ports = $device_ports->groupBy('status')->toArray();
    }

    /** 根据站点获取关联的系统用户id
     * insertStream
     * @param
     * @return
     */
    public static function usersRoleGet($id)
    {
        $stations = '';
        $stations = DB::table('station')->where(function ($query) use ($id) {
            $query->where('recommend_account_id', $id)->orWhere('copemate_account_id', $id)->orWhere('elec_account_id', $id)->orWhere('frog_account_id', $id)->orWhere('other_account_id', $id);
        })->where('is_del', 0);

        return $stations;
    }


    /**
     * 获取推送时间段
     */
    public static function compareHour($hour, $st, $et)
    {
        $st = (int)$st;
        $et = (int)$et;
        $hour = (int)$hour;
        if ($st == $et) {
            return true;
        } else if ($st < $et) {
            if ($hour >= $st && $hour <= $et) {
                return true;
            }
        } else if ($st > $et) {
            $et_tem = $et + 24;
            if (($hour >= $st && $hour <= $et_tem) || ($hour + 24 >= $st && $hour + 24 <= $et_tem)) {
                return true;
            }
        }
        return false;
    }

}