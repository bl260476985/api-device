<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Utils\VarStore;
use App\Utils\AccountHelper;
use App\Utils\CommonHelper;
use App\Utils\OperateRecordHelper;
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


    protected function getBaseInfo()
    {
        $this->enterpriseMapIds = [];
        $currentUserID = !empty(VarStore::get('currentUserId')) ? (int)VarStore::get('currentUserId') : 0;
        $this->curAdminUser = AccountHelper::cacheGet(1, $currentUserID);//获取系统用户基本信息
        $current_enterpriseId = !empty($this->curAdminUser) ? $this->curAdminUser['enterprise_id'] : 0;
        $result = self::searchAllEnterprise();
        $this->enterpriseMap = $result['enterpriseMap'];
        if ($current_enterpriseId > 0) {
            $this->enterpriseMapIds = self::searchMapEnterprise($result['enterpriseMap'], $this->curAdminUser);
        } else {
            $this->enterpriseMapIds = $result['enterpriseMapId'];
        }
        return;

    }

    /**
     * build the JSON 查找下级所属公司
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return
     */

    public static function getSpecialStations($userID)
    {
//        ini_set('memory_limit', '512M');
        $stationIDs = [];
        $result = [
            'frog_account' => [],
            'recommend_account' => [],
            'copemate_account' => [],
            'elec_account' => [],
            'other_account' => [],
        ];
        $roles = self::getRoles([$userID], $result);
        if (isset($roles['frog_account']) && !empty($roles['frog_account'])) {
            $stationIDs = self::getStationIds($stationIDs, 'frog_account_id', (int)$roles['frog_account']['user_id']);
        }
        if (isset($roles['recommend_account']) && !empty($roles['recommend_account'])) {
            $stationIDs = self::getStationIds($stationIDs, 'recommend_account_id', (int)$roles['recommend_account']['user_id']);
        }
        if (isset($roles['copemate_account']) && !empty($roles['copemate_account'])) {
            $stationIDs = self::getStationIds($stationIDs, 'copemate_account_id', (int)$roles['copemate_account']['user_id']);
        }
        if (isset($roles['elec_account']) && !empty($roles['elec_account'])) {
            $stationIDs = self::getStationIds($stationIDs, 'elec_account_id', (int)$roles['elec_account']['user_id']);
        }
        if (isset($roles['other_account']) && !empty($roles['other_account'])) {
            $stationIDs = self::getStationIds($stationIDs, 'other_account_id', (int)$roles['other_account']['user_id']);
        }

        return $stationIDs;
    }

    protected static function getStationIds($result, $column, $value = 0)
    {
//        ini_set('memory_limit', '512M');
        if (!empty($column)) {
            $stationIds = DB::table('station')->select('id')->where($column, $value)->where('is_del', 0)->get()->pluck('id')->all();
            $result = collect($result)->merge($stationIds)->all();
        }

        return $result;
    }


    /**
     * build the JSON 查找下级所属公司
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return
     */

    protected static function searchAllEnterprise()
    {
        $result = [
            'enterpriseMapId' => [],
            'enterpriseMap' => [],
        ];
        $enterprises = DB::table('enterprise')->where('is_del', 0)->get();
        if (!$enterprises->isEmpty()) {
            $result['enterpriseMap'] = $enterprises->keyBy('id')->all();//获取以公司id为键的value数组
            $result['enterpriseMapId'] = $enterprises->pluck('id')->all();//获取公司id数组
        }

        return $result;
    }

    /**
     * build the JSON 查找下级所属公司
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return
     */

    protected static function searchMapEnterprise($enterpriseMap, $curAdminUser)
    {
        $enterMap = [];
        if (count($enterpriseMap) > 0) {
            $enterMap = collect($enterpriseMap)->filter(function ($item, $key) use ($enterpriseMap, $curAdminUser) {
                return AccountHelper::canManageTheEnterprise($enterpriseMap, $curAdminUser, $item['id']);
            })->pluck('id')->all();

        }
        return $enterMap;
    }

    /**
     * 查找所属公司下对应站点
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return
     */

    protected static function searchMapStations(array $enterpriseMapIds, $condition, array $enter_obj_id = [])
    {
        $stationIdsMap = [];
        $stations = CommonHelper::isDataBelogs('station', $enterpriseMapIds, 'enterprise_id')->select('id', 'name')
            ->when(count($enter_obj_id) > 0, function ($query) use ($enter_obj_id) {
                return $query->whereIn('enterprise_id', $enter_obj_id);
            })->when((isset($condition['status']) && !empty($condition['status'])), function ($query) use ($condition) {
                return $query->where('status', $condition['status']);
            })->when((isset($condition['provider']) && !empty($condition['provider'])), function ($query) use ($condition) {
                return $query->where('provider', $condition['provider']);
            })->where('is_del', 0)->get();
        $stationIdsMap = $stations->keyBy('id')->all();
        return $stationIdsMap;
    }

    /**
     * 查找所属公司下对应系统用户组
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return
     */

    protected static function searchMapSystemGroups(array $enterpriseMapIds, array $enter_obj_id = [])
    {
        /* 其中直接读取数据库
        $group_obj_id = CommonHelper::isDataBelogs('system_user_group_new', $enterpriseMapIds, 'enterprise_id')->select('id')
        ->where([
            ['name', 'like', '%' . $group_obj_name . '%'],
            ['is_del', '=', 0],
        ])
        ->get()
        ->pluck('id')
        ->all();
        */
        $groupIdsMap = [];
        $groups = CommonHelper::isDataBelogs('system_user_group_new', $enterpriseMapIds, 'enterprise_id')->select('id', 'name')->when(count($enter_obj_id) > 0, function ($query) use ($enter_obj_id) {
            return $query->whereIn('enterprise_id', $enter_obj_id);
        })->where('is_del', 0)->get();
        $groupIdsMap = $groups->keyBy('id')->all();
        return $groupIdsMap;
    }


    /**
     * build the JSON response
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return \Illuminate\Http\Response
     */
    protected function buildJSONResponse($code = 0, $msg = '', $data = [])
    {
        return response()->json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    /**
     * success
     * @param  integer $code
     * @param  string $msg
     * @param  array $data
     * @return \Illuminate\Http\Response
     */
    protected function success($data = [])
    {
        if (empty($data)) {
            $data = (object)$data;
        }
        return $this->buildJSONResponse(0, '', $data);
    }

    /**
     * fail
     * @param  string $msg
     * @param  array $data
     * @return \Illuminate\Http\Response
     */
    protected function fail($msg, $data = [])
    {
        return $this->buildJSONResponse(1, $msg, $data);
    }

    /**
     * redirect
     * @param  string $url
     * @return \Illuminate\Http\Response
     */
    protected function redirect($url)
    {
        return $this->buildJSONResponse(2, '', ['url' => $url]);
    }
}
