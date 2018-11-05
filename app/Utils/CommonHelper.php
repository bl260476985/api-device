<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class CommonHelper
{

    /**获取模块函数
     * validate password
     * @param  string $pwd
     * @return boolean
     */
    public static function getSystemModules()
    {
        return DB::table('system_modules_new')
            ->select(
                'id',
                'name',
                'key',
                'level',
                'parent_id',
                'purchase_type',
                'module_type')
            ->where('is_del', 0);

    }

    /** 根据参数获取用户组和模块表
     * validate password
     * @param  string $pwd
     * @return string
     */
    public static function getSystemGroupModules($userGroupId)
    {
        $db_str = DB::table('system_group_modules')
            ->select(
                'id',
                'group_id',
                'module_id',
                'purchase_type'
            )->when($userGroupId, function ($query) use ($userGroupId) {
                return $query->where('group_id', $userGroupId);
            })->where('is_del', 0);

        return $db_str;

    }

    /** 根据参数判断对用数据是否存在
     * validate password
     * @param  string $pwd
     * @return string
     */
    public static function isDataExists($table, $key = '', $value = '')
    {
        $db_str = '';
        if (!empty($table) && ((!empty($key) && !empty($value)) || empty($value))) {
            $db_str = DB::table($table)
                ->when($value, function ($query) use ($key, $value) {
                    return $query->where($key, $value);
                });
        }
        return $db_str;

    }

    /** 根据参数判断对用数据是
     * validate password
     * @param  string $pwd
     * @return string
     */
    public static function isDataBelogs($table, array $enterpriseMapIds, $enterId, $key = '', $value = '')
    {
        $db_str = '';
        if (!empty($table) && ((!empty($key) && !empty($value)) || empty($value))) {
            $db_str = DB::table($table)
                ->when($value, function ($query) use ($key, $value) {
                    return $query->where($key, $value);
                })->when(count($enterpriseMapIds) > 0, function ($query) use ($enterpriseMapIds, $enterId) {
                    return $query->whereIn($enterId, $enterpriseMapIds);
                });
        }
        return $db_str;
    }

    /** 根据ui传入的名称获取对应的id集合
     * validate password
     * @param  string $pwd
     * @return array
     */
    public static function idCollectByName($obj_id, $result, $curColumn, $targetname, $column = '', array $enterpriseMapIds = [])
    {
        $obj_id_target = $obj_id;
        if (empty($obj_id_target) && !empty($targetname)) {
            $temp_collect = collect($result);
            $obj_id_target = $temp_collect->when(!empty($column), function ($temp_collect) use ($column, $enterpriseMapIds) {
                return $temp_collect->whereIn($column, $enterpriseMapIds);
            })->reject(function ($value, $key) use ($curColumn, $targetname) {
                return mb_strpos($value[$curColumn], $targetname) === false;
            })->pluck('id')->all();
        }
        if (!is_array($obj_id_target) && !empty($obj_id_target)) {
            $obj_id_target = [$obj_id_target];
        } else if (!is_array($obj_id_target) && empty($obj_id_target)) {
            $obj_id_target = [];
        }

        return $obj_id_target;
    }

    /** 根据ui传入的名称获取对应的id集合
     * validate password
     * @param  string $pwd
     * @return array
     */
    public static function idDeviceByName($obj_id, $table, $curColumn, $targetname, $collect_id, array $stationMapIds = [])
    {
        $obj_id_target = $obj_id;
        if (empty($obj_id_target) && !empty($targetname)) {
            $obj_id_target = DB::table($table)->select('id')
                ->when($targetname, function ($query) use ($curColumn, $targetname) {
                    return $query->where($curColumn, 'like', '%' . $targetname . '%');
                })->when(!empty($collect_id), function ($query) use ($collect_id, $stationMapIds) {
                    return $query->whereIn($collect_id, $stationMapIds);
                })
                ->where('is_del', 0)
                ->take(100)
                ->get()
                ->pluck('id')
                ->all();
        }
        if (!is_array($obj_id_target) && !empty($obj_id_target)) {
            $obj_id_target = [$obj_id_target];
        } else if (!is_array($obj_id_target) && empty($obj_id_target)) {
            $obj_id_target = [];
        }

        return $obj_id_target;
    }

    /** 根据查询得到的站点信息
     * validate password
     * @param  string $pwd
     * @return array
     */
    public static function getStationCommonInfo($data, $stationIdsMap)
    {
        //获取查询到的站点id
        $stationIds = $data->pluck('station_id')->all();
        //获取查询到的站点名称
        $stationInfos = collect($stationIdsMap)->whereIn('id', $stationIds)->keyBy('id')->all();
        return $stationInfos;
    }

    /** 根据查询得到的设备信息
     * validate password
     * @param  string $pwd
     * @return array
     */
    public static function getDeviceCommonInfo($data)
    {
        //获取查询到的设备id
        $deviceIds = $data->pluck('device_id')->all();
        //获取查询到的设备名称
        $deviceInfos = DB::table('device')->select('id', 'device_number')->whereIn('id', $deviceIds)->where('is_del', 0)->get()->keyBy('id')->all();
        return $deviceInfos;
    }

    /** 根据查询得到的设备端口信息
     * validate password
     * @param  string $pwd
     * @return array
     */
    public static function getDevicePortCommonInfo($data)
    {
        $portIds = $data->pluck('port_id')->all();
        //获取查询到的端口名称
        $portInfos = DB::table('device_port')->select('id', 'port_number', 'port_name')->whereIn('id', $portIds)->where('is_del', 0)->get()->keyBy('id')->all();
        return $portInfos;
    }

    /** 根据查询得到的用户信息
     * validate password
     * @param  string $pwd
     * @return array
     */
    public static function getUserCommonInfo($data)
    {
        //获取查询到的用户id
        $userIds = $data->pluck('user_id')->all();
        //获取查询到的用户信息
        $userInfos = DB::table('user')->select('id', 'phone')->whereIn('id', $userIds)->where('is_del', 0)->get()->keyBy('id')->all();

        return $userInfos;
    }


}