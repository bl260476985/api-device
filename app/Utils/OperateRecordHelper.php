<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class OperateRecordHelper
{
    public static function insertLog($id, $type, $level, $parent_id, $user_id, $object_type, $data = [])
    {
        $insert = [];
        $now = time();
        switch ($object_type) {
            case 'enterprise':
                if ($level == 0) {
                    $existedEnterprise = DB::table('enterprise')->where('id', $id)->first();
                    if (!empty($existedEnterprise)) {
                        $insert['object_id'] = 1;
                        $insert['name'] = $existedEnterprise['name'];
                        $insert['type'] = $type;
                        $insert['level'] = $level;
                        $insert['parent_id'] = $parent_id;
                        $insert['user_id'] = $user_id;
                        $insert['detail'] = json_encode($existedEnterprise, JSON_UNESCAPED_UNICODE);
                        $id = DB::table('log_object_details')->insertGetId($insert);
                    }
                }
                break;
            case 'station':
                $existedStation = DB::table('station')->where('id', $id)->first();
                if (!empty($existedStation)) {
                    $insert['object_id'] = 2;
                    $insert['name'] = $existedStation['name'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedStation, JSON_UNESCAPED_UNICODE);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $enterprise_id = $data['new_enterprise_id'];
                        $existedEnterprise = DB::table('enterprise')->where('id', $enterprise_id)->where('is_del', 0)->first();
                        if (!empty($existedEnterprise)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 1;
                            $insert_sec['name'] = $existedEnterprise['name'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedEnterprise, JSON_UNESCAPED_UNICODE);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                        if ($level == 2) {
                            $old_enterprise_id = $data['old_enterprise_id'];
                            $oldEnterprise = DB::table('enterprise')->where('id', $old_enterprise_id)->where('is_del', 0)->first();
                            if (!empty($oldEnterprise)) {
                                $insert_sec = [];
                                $insert_sec['object_id'] = 1;
                                $insert_sec['name'] = $oldEnterprise['name'];
                                $insert_sec['type'] = 'update';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($oldEnterprise, JSON_UNESCAPED_UNICODE);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        }
                        if ($level == 3) {
                            $pic_ids = $data['pic_ids'];
                            if (count($pic_ids) > 0) {
                                $pics = DB::table('station_pic')->whereIn('id', $pic_ids)->where('is_del', 0)->get();
                                $insert_sec = [];
                                $insert_sec['object_id'] = 0;
                                $insert_sec['name'] = 'pic';
                                $insert_sec['type'] = 'delete';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($pics, JSON_UNESCAPED_UNICODE);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        }
                    }
                }
                break;
            case 'station_group':
                $existedStationGroup = DB::table('station_group')->where('id', $id)->first();
                if (!empty($existedStationGroup)) {
                    $insert['object_id'] = 3;
                    $insert['name'] = $existedStationGroup['name'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedStationGroup, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $stationMapIds = $data['stationMapIds'];
                        $existedStations = DB::table('station')->select('id', 'name', 'group_id', 'updated_at')->whereIn('id', $stationMapIds)->where('is_del', 0)->get();
                        if (!empty($existedStations)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 2;
                            $insert_sec['name'] = $existedStations[0]['name'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedStations, JSON_UNESCAPED_UNICODE);
                            $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                        if ($level == 2) {
                            $stationUserMapIds = $data['stationUserMapIds'];
                            $station_user = DB::table('station_group_user_group')->where('station_group_id', $data['id'])->whereIn('user_group_id', $stationUserMapIds)->where('is_del', 0)->get();
                            if (!empty($station_user)) {
                                $insert_sec = [];
                                $insert_sec['object_id'] = 0;
                                $insert_sec['name'] = $station_user[0]['station_group_id'];
                                $insert_sec['type'] = 'update';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($station_user, JSON_UNESCAPED_UNICODE);
                                $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        }
                    }
                }
                break;
            case 'device':
                $existedDevice = DB::table('device')->where('id', $id)->first();
                if (!empty($existedDevice)) {
                    $insert['object_id'] = 3;
                    $insert['name'] = $existedDevice['device_number'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedDevice, JSON_UNESCAPED_UNICODE);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $ports_id = $data['ports_id'];
                        $existedDevicePort = DB::table('device_port')->whereIn('id', $ports_id)->where('is_del', 0)->get()->toArray();
                        if (count($existedDevicePort) > 0) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 4;
                            $insert_sec['name'] = $existedDevicePort[0]['port_number'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedDevicePort, JSON_UNESCAPED_UNICODE);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                    }
                }
                break;
            case 'device_port':
                $existedPort = DB::table('device_port')->where('id', $id)->first();
                if (!empty($existedDevice)) {
                    $insert['object_id'] = 4;
                    $insert['name'] = $existedDevice['port_number'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedDevice, JSON_UNESCAPED_UNICODE);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $device_id = $existedPort['device_id'];
                        $existedDevice = DB::table('device')->where('id', $device_id)->first();
                        if (!empty($existedDevice)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 3;
                            $insert_sec['name'] = $existedDevice['device_number'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedDevice, JSON_UNESCAPED_UNICODE);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                    }
                }
                break;
            case 'device_maintain':
                $devices = DB::table('device')->select('id', 'device_number')->where('is_del', 0)->get();
                $devicesMap = [];
                foreach ($devices as $device) {
                    $devicesMap[$device['id']] = $device;
                }
                $existedDevice = DB::table('device_maintain')->where('id', $id)->first();
                if (!empty($existedDevice)) {
                    $device_number = '';
                    if (array_key_exists($existedDevice['device_id'], $devicesMap)) {
                        $device_number = $devicesMap[$existedDevice['device_id']]['device_number'];
                    } else {
                        $device_number = $existedDevice['device_id'];
                    }
                    $insert['object_id'] = 5;
                    $insert['name'] = $device_number;
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedDevice, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $device_id = $data['device_id'];
                        $existedEnterprise = DB::table('device')->select('id', 'device_number', 'maintained_at')->where('id', $device_id)->where('is_del', 0)->first();
                        if (!empty($existedEnterprise)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 4;
                            $insert_sec['name'] = $existedEnterprise['device_number'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedEnterprise, JSON_UNESCAPED_UNICODE);
                            $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                    }
                }
                break;
            case 'article':
                $existedDevice = DB::table('coupon')->where('id', $id)->first();
                if (!empty($existedDevice)) {
                    $insert['object_id'] = 6;
                    $insert['name'] = $existedDevice['coupon_name'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedDevice, JSON_UNESCAPED_UNICODE);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $existedDevicePort = DB::table('coupon_station')->where('coupon_id', $existedDevice['id'])->where('is_del', 0)->get()->toArray();
                        if (count($existedDevicePort) > 0) {
                            $stationInfo = DB::table('station')->select('name')->where('id', $existedDevicePort[0]['station_id'])->where('is_del', 0)->first();
                            $insert_sec = [];
                            $insert_sec['object_id'] = 0;
                            $insert_sec['name'] = isset($stationInfo['name']) ? $stationInfo['name'] : '';
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedDevicePort, JSON_UNESCAPED_UNICODE);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                    }
                }
                break;
            case 'user':
                $existedUser = DB::table('user')->where('id', $id)->first();
                if (!empty($existedUser)) {
                    $insert['object_id'] = 7;
                    $insert['name'] = $existedUser['phone'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedUser, JSON_UNESCAPED_UNICODE);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        if ($level == 1) {
                            $user_id = $existedUser['id'];
                            $existedUserGroupUser = DB::table('user_authen')->where('user_id', $user_id)->orderBy('created_at', 'desc')->first();
                            if (!empty($existedUserGroupUser)) {
                                $insert_sec = [];
                                $insert_sec['object_id'] = 0;
                                $insert_sec['name'] = $existedUserGroupUser['phone'];
                                $insert_sec['type'] = 'update';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($existedUserGroupUser, JSON_UNESCAPED_UNICODE);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        } else if ($level == 2) {
                            $deposit_orderId = $data['deposit_orderId'];
                            $existedDeposit = DB::table('deposit_order')->where('id', $deposit_orderId)->where('is_del', 0)->first();
                            if (!empty($existedDeposit)) {
                                $insert_sec = [];
                                $insert_sec['object_id'] = 12;
                                $insert_sec['name'] = $existedDeposit['order_number'];
                                $insert_sec['type'] = 'insert';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($existedDeposit, JSON_UNESCAPED_UNICODE);
                                $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        }
                    }
                }
                break;
            case 'user_group':
                $existedUserGroup = DB::table('user_group')->where('id', $id)->first();
                if (!empty($existedUserGroup)) {
                    $insert['object_id'] = 8;
                    $insert['name'] = $existedUserGroup['name'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedUserGroup, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $usersMapIds = $data['usersMapIds'];
                        $existedStations = DB::table('user_group_user')->where('group_id', $data['id'])->whereIn('user_id', $usersMapIds)->get();
                        if (!empty($existedStations)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 0;
                            $insert_sec['name'] = $existedStations[0]['group_id'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedStations, JSON_UNESCAPED_UNICODE);
                            $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                        if ($level == 2) {
                            $stationGroupMapIds = $data['stationGroupMapIds'];
                            $station_user = DB::table('station_group_user_group')->where('user_group_id', $data['id'])->whereIn('station_group_id', $stationGroupMapIds)->where('is_del', 0)->get();
                            if (!empty($station_user)) {
                                $insert_sec = [];
                                $insert_sec['object_id'] = 0;
                                $insert_sec['name'] = $station_user[0]['user_group_id'];
                                $insert_sec['type'] = 'update';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($station_user, JSON_UNESCAPED_UNICODE);
                                $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        }
                    }
                }
                break;
            case 'feedback':
                $users = DB::table('user')->select('id', 'phone')->where('is_del', 0)->get();
                $userMap = [];
                foreach ($users as $user) {
                    $userMap[$user['id']] = $user;
                }
                $existedFeed = DB::table('feedback')->where('id', $id)->where('is_del', 0)->first();
                if (!empty($existedFeed)) {
                    $contact_phone = '';
                    if (array_key_exists($existedFeed['user_id'], $userMap)) {
                        $contact_phone = $userMap[$existedFeed['user_id']]['phone'];
                    } else {
                        $contact_phone = $existedFeed['phone'];
                    }
                    $insert['object_id'] = 9;
                    $insert['name'] = $contact_phone;
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedFeed, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                }

                break;
            case 'device_apply':
                $users = DB::table('user')->select('id', 'phone')->where('is_del', 0)->get();
                $userMap = [];
                foreach ($users as $user) {
                    $userMap[$user['id']] = $user;
                }
                $existedApplication = DB::table('application')->where('id', $id)->where('is_del', 0)->first();
                if (!empty($existedApplication)) {
                    $contact_phone = '';
                    if (array_key_exists($existedApplication['user_id'], $userMap)) {
                        $contact_phone = $userMap[$existedApplication['user_id']]['phone'];
                    } else {
                        $contact_phone = $existedApplication['phone'];
                    }
                    $insert['object_id'] = 10;
                    $insert['name'] = $contact_phone;
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedApplication, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                }

                break;
            case 'application_card':
                $users = DB::table('user')->select('id', 'phone')->where('is_del', 0)->get();
                $userMap = [];
                foreach ($users as $user) {
                    $userMap[$user['id']] = $user;
                }
                $existedCardApplication = DB::table('application')->where('id', $id)->first();
                if (!empty($existedCardApplication)) {
                    $contact_phone = '';
                    if (array_key_exists($existedCardApplication['user_id'], $userMap)) {
                        $contact_phone = $userMap[$existedCardApplication['user_id']]['phone'];
                    } else {
                        $contact_phone = $existedCardApplication['phone'];
                    }
                    $insert['object_id'] = 11;
                    $insert['name'] = $contact_phone;
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedCardApplication, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $card_sourceId = $data['card_sourceId'];
                        $existedUserSource = DB::table('user_source')->where('id', $card_sourceId)->where('is_del', 0)->first();
                        if (!empty($existedUserSource)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 0;
                            $insert_sec['name'] = $existedUserSource['source_id'];
                            $insert_sec['type'] = 'insert';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedUserSource, JSON_UNESCAPED_UNICODE);
                            $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }

                        $cardNumber = $data['cardNumber'];
                        $existedCard = DB::table('card')->where('card_number', $cardNumber)->where('is_del', 0)->first();
                        if (!empty($existedCard)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 12;
                            $insert_sec['name'] = $existedCard['card_number'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedCard, JSON_UNESCAPED_UNICODE);
                            $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                    }
                }
                break;
            case 'card_manage':

                if ($id > 0) {
                    $existedCard = DB::table('card')->where('id', $id)->where('is_del', 0)->first();

                } else {
                    $cardNumber = $data['cardNumber'];
                    $existedCard = DB::table('card')->where('card_number', $cardNumber)->where('is_del', 0)->first();
                }
                if (!empty($existedCard)) {

                    $insert_sec = [];
                    $insert_sec['object_id'] = 12;
                    $insert_sec['name'] = $existedCard['card_number'];
                    $insert_sec['type'] = $type;
                    $insert_sec['level'] = $level;
                    $insert_sec['parent_id'] = $parent_id;
                    $insert_sec['user_id'] = $user_id;
                    $insert_sec['detail'] = json_encode($existedCard, JSON_UNESCAPED_UNICODE);
                    $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert_sec);
                    if ($level > 0 && $id > 0) {
                        $cardNumber = $data['cardNumber'];
                        $existedUserSource = DB::table('user_source')->where('source_id', $cardNumber)->where('is_del', 0)->first();
                        if (!empty($existedUserSource)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 0;
                            $insert_sec['name'] = $existedUserSource['source_id'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedUserSource, JSON_UNESCAPED_UNICODE);
                            $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                    }
                }
                break;
            case 'approve':
                $users = DB::table('user')->select('id', 'phone')->where('is_del', 0)->get();
                $userMap = [];
                foreach ($users as $user) {
                    $userMap[$user['id']] = $user;
                }
                $approveApplication = DB::table('application')->where('id', $id)->where('is_del', 0)->first();
                if (!empty($approveApplication)) {
                    $contact_phone = '';
                    if (array_key_exists($approveApplication['user_id'], $userMap)) {
                        $contact_phone = $userMap[$approveApplication['user_id']]['phone'];
                    } else {
                        $contact_phone = $approveApplication['phone'];
                    }
                    $insert['object_id'] = 13;
                    $insert['name'] = $contact_phone;
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($approveApplication, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $userApproveId = $data['userApproveId'];
                        $existedUserSource = DB::table('user')->where('id', $userApproveId)->where('is_del', 0)->first();
                        if (!empty($existedUserSource)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 7;
                            $insert_sec['name'] = $existedUserSource['phone'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedUserSource, JSON_UNESCAPED_UNICODE);
                            $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                        if ($level == 2) {
                            $stationUserMapIds = $data['stationUserMapIds'];
                            $station_user = DB::table('user_group_user')->where('user_id', $userApproveId)->whereIn('group_id', $stationUserMapIds)->where('is_del', 0)->get();
                            if (!empty($station_user)) {
                                $insert_sec = [];
                                $insert_sec['object_id'] = 0;
                                $insert_sec['name'] = $station_user[0]['user_id'];
                                $insert_sec['type'] = 'update';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($station_user, JSON_UNESCAPED_UNICODE);
                                $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        }
                    }
                }
                break;
            case 'system_user_group_new':
                $existedSystemGroup = DB::table('system_user_group_new')->where('id', $id)->first();
                if (!empty($existedSystemGroup)) {
                    $insert['object_id'] = 14;
                    $insert['name'] = $existedSystemGroup['name'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedSystemGroup, JSON_UNESCAPED_UNICODE);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $systemuserMapIds = $data['systemuserMapIds'];
                        $existedSystemusers = DB::table('system_user')->select('id', 'name', 'phone', 'group_id', 'updated_at')->whereIn('id', $systemuserMapIds)->where('is_del', 0)->get()->toArray();
                        if (!empty($existedSystemusers)) {
                            $insert_sec = [];
                            $insert_sec['object_id'] = 15;
                            $insert_sec['name'] = $existedSystemusers[0]['name'];
                            $insert_sec['type'] = 'update';
                            $insert_sec['level'] = 0;
                            $insert_sec['parent_id'] = $id;
                            $insert_sec['user_id'] = $user_id;
                            $insert_sec['detail'] = json_encode($existedSystemusers, JSON_UNESCAPED_UNICODE);
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                    }
                }
                break;
            case 'system_user':
                $existedSystemUser = DB::table('system_user')->where('id', $id)->first();
                if (!empty($existedSystemUser)) {
                    $insert = [
                        'object_id' => 15,
                        'name' => $existedSystemUser['name'],
                        'type' => $type,
                        'level' => $level,
                        'parent_id' => $parent_id,
                        'user_id' => $user_id,
                        'detail' => json_encode($existedSystemUser, JSON_UNESCAPED_UNICODE)
                    ];
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        $existed_user_authen = DB::table('system_user_authen')->where('user_id', $id)->orderBy('created_at', 'desc')->first();
                        if (!empty($existed_user_authen)) {
                            $insert_sec = [
                                'object_id' => 0,
                                'name' => $existed_user_authen['name'],
                                'type' => 'update',
                                'level' => 0,
                                'parent_id' => $id,
                                'user_id' => $user_id,
                                'detail' => json_encode($existed_user_authen, JSON_UNESCAPED_UNICODE)
                            ];
                            DB::table('log_object_details')->insertGetId($insert_sec);
                        }
                    }
                }
                break;
            case 'system_user_authen':
                $authen = DB::table('system_user_authen')->where('user_id', $id)->where('is_del', 0)->first();
                if (!empty($authen)) {
                    $insert['object_id'] = 11;
                    $insert['name'] = $authen['name'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($authen, JSON_UNESCAPED_UNICODE);
                    DB::table('log_object_details')->insertGetId($insert);
                }
                break;
            case 'system_conf':
                $existedSystemConf = DB::table('system_conf')->where('type', 0)->where('is_del', 0)->get();
                if (count($existedSystemConf) > 0) {
                    $insert['object_id'] = 16;
                    $insert['name'] = '系统参数';
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedSystemConf, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                }
                break;
            case 'charge_order':
                $orderUpdate = $data['orderUpdate'];
                $existedChargeOrder = DB::table('charge_order')->select('id', 'order_number', 'user_id', 'device_id', 'status', 'updated_at')->whereIn('id', $orderUpdate)->where('is_del', 0)->get();
                if (count($existedChargeOrder) > 0) {
                    $insert['object_id'] = 17;
                    $insert['name'] = $existedChargeOrder[0]['order_number'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedChargeOrder, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                }
                break;
            case 'device_upgrade':
                $device_number = $data['device_number'];
                if ($level == 0) {
                    $insert['object_id'] = 4;
                    $insert['name'] = $device_number;
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = '升级';
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    DB::table('log_object_details')->insertGetId($insert);
                }
                break;
            case 'score_clear':
                if ($level == 0) {
                    $insert['object_id'] = 18;
                    $insert['name'] = '所有用户';
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = '年度积分清零';
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    DB::table('log_object_details')->insertGetId($insert);
                }
                break;
            case 'score':
                $score_orderId = $data['score_orderId'];
                $existedScore = DB::table('score')->where('id', $score_orderId)->first();
                if (!empty($existedScore)) {
                    $insert['object_id'] = 19;
                    $insert['name'] = $existedScore['score_number'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedScore, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        if ($level == 1) {
                            $existedUser = DB::table('user')->where('id', $existedScore['user_id'])->where('is_del', 0)->first();
                            if (!empty($existedUser)) {
                                $insert_sec = [];
                                $insert_sec['object_id'] = 7;
                                $insert_sec['name'] = $existedUser['phone'];
                                $insert_sec['type'] = 'update';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($existedUser, JSON_UNESCAPED_UNICODE);
                                $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        }
                    }
                }
                break;
            case 'system_conf_score':
                $existedSystemConf = DB::table('system_conf')->where('type', 1)->where('is_del', 0)->get();
                if (count($existedSystemConf) > 0) {
                    $insert['object_id'] = 20;
                    $insert['name'] = '积分参数';
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedSystemConf, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                }
                break;
            case 'receipt_add_operation':
                $existedAccount = DB::table('charge_order_account')->where('id', $id)->where('is_del', 0)->first();
                // $orderInfo = DB::table('charge_order')->where('id',$data['order_ids'][0])->where('is_del',0)->first();
                // $name = $orderInfo['order_number'];
                if (!empty($existedAccount)) {
                    $insert['object_id'] = 21;
                    $insert['name'] = $existedAccount['account_name'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedAccount, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level > 0 && $id > 0) {
                        foreach ($data['order_ids'] as $key => $order_id) {
                            $existedOrder = DB::table('charge_order')->where('id', $order_id)->where('is_del', 0)->first();
                            if (!empty($existedOrder)) {
                                $insert_sec = [];
                                $insert_sec['object_id'] = 17;
                                $insert_sec['name'] = $existedOrder['order_number'];
                                $insert_sec['type'] = 'update';
                                $insert_sec['level'] = 0;
                                $insert_sec['parent_id'] = $id;
                                $insert_sec['user_id'] = $user_id;
                                $insert_sec['detail'] = json_encode($existedOrder, JSON_UNESCAPED_UNICODE);
                                $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                                DB::table('log_object_details')->insertGetId($insert_sec);
                            }
                        }

                    }
                }
                break;
            case 'receipt_deal_operation':
                $existedAccount = DB::table('charge_order_account')->where('id', $id)->where('is_del', 0)->first();
                // dd($existedAccount);
                // $orderInfo = DB::table('charge_order')->where('id',$data['order_ids'][0])->where('is_del',0)->first();
                // $name = $orderInfo['order_number'];
                if (!empty($existedAccount)) {
                    $insert['object_id'] = 21;
                    $insert['name'] = $existedAccount['account_name'];
                    $insert['type'] = $type;
                    $insert['level'] = $level;
                    $insert['parent_id'] = $parent_id;
                    $insert['user_id'] = $user_id;
                    $insert['detail'] = json_encode($existedAccount, JSON_UNESCAPED_UNICODE);
                    $insert['created_at'] = date('Y-m-d H:i:s', $now);
                    $id = DB::table('log_object_details')->insertGetId($insert);
                    if ($level === 1) {
                        $existedOrder = DB::table('charge_order_account_order')->where('id', $data['order_id'])->first();
                        // dd($existedOrder);
                        $orderInfo = DB::table('charge_order')->where('id', $existedOrder['order_id'])->where('is_del', 0)->first();
                        // dd($orderInfo);
                        $insert_sec = [];
                        $insert_sec['object_id'] = 17;
                        $insert_sec['name'] = $orderInfo['order_number'];
                        $insert_sec['type'] = 'delete';
                        $insert_sec['level'] = 0;
                        $insert_sec['parent_id'] = $id;
                        $insert_sec['user_id'] = $user_id;
                        $insert_sec['detail'] = json_encode($orderInfo, JSON_UNESCAPED_UNICODE);
                        $insert_sec['created_at'] = date('Y-m-d H:i:s', $now);
                        DB::table('log_object_details')->insertGetId($insert_sec);
                    }

                }
                break;
            default:
                break;
        }
        return true;
    }


}