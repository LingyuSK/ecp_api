<?php

namespace App\Modules\Admin\Repository;

use App\Common\Contracts\Repository;
use App\Common\Models\{
    SupplierAccess,
    Supplier,
    AccessTpl,
    SupplierAudit,
    Purchaser,
    Message,
    MessageReceiver,
    User,
    UserSupplier,
    SupplierExtend,
    AccessSetting
};
use App\Modules\Admin\Repository\{
    SupplierBaseRepo,
    PurchaserRepo,
    SupplierGroupRepo
};
use Illuminate\Support\Facades\{
    Auth,
    DB,
    Lang
};
use Illuminate\Http\Request;

class SupplierAccessRepo extends Repository {

    protected $model;

    public function __construct() {
        $this->model = new SupplierAccess();
        parent::__construct($this->model);
    }

    protected function getOrder(&$query, Request $request) {
        /**
         * 排序
         */
        $sort = !empty($request->sort) && in_array(strtolower(trim($request->sort)), $this->sorts) ? trim($request->sort) : 's.created_at';
        $order = !empty($request->order) && in_array(strtolower(trim($request->order)), ['desc', 'asc']) ? trim($request->order) : 'DESC';
        $query->orderBy($sort, $order);
        if ($sort !== 's.created_at') {
            $query->orderBy('s.created_at', 'DESC');
        }
    }

    /**
     * 获取合同列表
     * @param Request $request
     * @param string $filed
     * @return array
     */
    public function getList(Request $request) {
        $supplier = (new Supplier)->getTable();
        $supplierAccess = (new SupplierAccess)->getTable();
        $purchaserId = $this->getPPurchaserId();
        $user = (new User)->getTable();
        $query = $this->model
                ->selectRaw('u.realname,u.phone,u.email,s.status,s.supplier_no,'
                        . 's.registered_at,s.id,s.name,sa.status,sa.access_at,s.social_credit_code')
                ->from($supplierAccess . ' AS sa')
                ->join($supplier . ' AS s', function ($join) {
                    $join->on('s.id', '=', 'sa.supplier_id');
                })
                ->leftJoin($user . ' as u', function($join) {
                    $join->on('u.user_id', 'sa.created_by')
                    ->where('u.deleted_flag', 'N');
                })
                ->where('sa.purchaser_id', $purchaserId);
        $this->getWhere($query, $request);
        $clone = $query->clone();
        $total = $clone->count();
        $this->getPage($query, $request);
        $this->getOrder($query, $request);
        $object = $query->get();
        if (empty($object)) {
            return ['data' => [], 'total' => $total];
        }
        $data = $object->toArray();
        (new SupplierGroupRepo())->setGroups($data);
        $supplierRepo = new SupplierBaseRepo();
        foreach ($data as &$item) {
            $item['status_name'] = $supplierRepo->getStatusText($item['status']);
            $item['phone_email'] = !empty($item['phone']) && !empty($item['email']) ? $item['email'] . '/' . $item['phone'] :
                    (!empty($item['phone']) ? $item['phone'] : $item['email']);
            unset($item['phone'], $item['email']);
        }
        (new UserRepo)->setUsers($data, 'created_by', 'created_name');
        (new UserRepo)->setUsers($data, 'updated_by', 'updated_name');
        (new SupplierAuditRepo)->setAudits($data, 'id');
        $list = [];
        $list['total'] = $total;
        $list['data'] = $data;
        return $list;
    }

    /**
     * 获取合同列表
     * @param Array $ids
     * @param string $enable
     * @return array
     */
    public function deleteData(Request $request) {
        $purchaserId = $this->getPPurchaserId();
        $supplierIds = SupplierAccess::whereIn('supplier_id', $request->ids)
                ->where('deleted_flag', 'N')
                ->where('purchaser_id', $purchaserId)
                ->whereIn('status', ['APPROVING'])
                ->pluck('id');
        check(!empty($supplierIds), '处于审核中或已审核的准入不能删除');
        DB::beginTransaction();
        SupplierAccess::whereIn('supplier_id', $request->ids)
                ->where('purchaser_id', $purchaserId)
                ->update(['deleted_flag' => 'Y']);
        DB::commit();
        return true;
    }

    public function getTotal(Request $request) {
        $query = Supplier::where('deleted_flag', 'N');
        $this->getWhere($query, $request);
        return $query->count();
    }

    public function rolesWhere(&$query) {
        $scope = $this->getScopes('/admin/supplier/access');
        if (empty($scope['scopes'])) {
            $query->whereRaw('1=-1');
        }
        $userId = $scope['user_id'];
        $teamId = $scope['team_id'];
        if (in_array('ALL', $scope['scopes'])) {
            $query->where(function($q)use($userId, $teamId) {
                $q->where(function($qp)use($userId, $teamId) {
                    $qp->where('sa.purchaser_id', $teamId);
                });
            });
            return;
        }
        if (in_array('DEPARTMENT', $scope['scopes']) || in_array('GROUP', $scope['scopes'])) {
            $access = (new SupplierAccess())->getTable();
            $query->where(function($q)use($teamId, $access, $userId) {
                $q->where(function($qs)use($teamId, $access) {
                            $qs->where(function($qt)use($teamId, $access) {
                                $qt->where('sa.purchaser_id', $teamId)
                                ->orWhereRaw('EXISTS(SELECT sa.id FROM ' . $access . '  as sa where sa.supplier_id=s.id '
                                        . 'AND sa.deleted_flag=\'N\' AND sa.purchaser_id=' . $teamId . ')');
                            })
                            ->where('s.status', '<>', 'REVIEW');
                        })
                        ->orWhere(function($qp)use($userId, $teamId) {
                            $qp->where('sa.purchaser_id', $teamId);
                            $qp->where('s.created_by', $userId)
                            ->where('s.status', 'REVIEW');
                        });
            });
            return;
        }

        $query->where('sa.purchaser_id', $teamId);
        $query->where('s.checked_by', $userId);
    }

    /**
     * @param $query
     * @param Request $request
     * @param bool $statusFlag
     */
    protected function getWhere(&$query, Request $request) {
        $query->where('s.deleted_flag', 'N');
        $query->where('sa.deleted_flag', 'N');
        $query->where('s.status', 'APPROVED');
        $query->whereIn('sa.status', ['APPROVED', 'APPROVING', 'INVALID']);
        if (!empty($request->keyword)) {
            $keyword = trim($request->keyword);
            $query->where(function ($q)use($keyword) {
                $q->where('u.realname', 'like', '%' . $keyword . '%')
                        ->orWhere('s.name', 'like', '%' . $keyword . '%');
            });
        }
        if (!empty($request->status)) {
            $status = $request->status;
            $statusies = is_array($status) ? $status : explode(',', trim($status));
            $query->whereIn('s.status', $statusies);
        }
        if (!empty($request->source)) {
            $source = $request->source;
            switch ($source) {
                case 'BOSS':
                case 'REGISTER':
                    $query->whereIn('s.source', ['REGISTER', 'BOSS']);
                    break;
                case 'PURCHASER':
                    $query->where('s.source', 'PURCHASER');
                    break;
            }
        }
        if (!empty($request->statusies)) {
            $query->whereIn('s.status', $request->statusies);
        }
        if (!empty($request->enable) || $request->enable === '0') {
            $enable = $request->enable;
            $enables = is_array($enable) ? $enable : explode(',', trim($enable));
            $query->whereIn('s.enable', $enables);
        }
        if (!empty($request->createtype)) {
            $createAts = $this->getTimeByType($request->createtype);
            $query->whereBetween('s.filled_at', $createAts);
        } elseif (!empty($request->createtime)) {
            $createtime = $request->createtime;
            $createAts = is_array($createtime) ? $createtime : explode(',', $createtime);
            !empty($createAts[1]) ? $createAts[1] = date('Y-m-d 23:59:59', strtotime($createAts[1])) : $createAts[1] = date('Y-m-d H:i:s');
            $query->whereBetween('s.filled_at', $createAts);
        }
    }

    protected function getPage(&$qurey, Request $request) {
        $condition = $request->all();
        $pageSize = 50;
        if (isset($condition['pagesize'])) {
            $pageSize = intval($condition['pagesize']) > 0 ? intval($condition['pagesize']) : 50;
        } elseif (isset($condition['limit'])) {
            $pageSize = intval($condition['limit']) > 0 ? intval($condition['limit']) : 50;
        }
        $page = !empty($request->page) && (int) $request->page > 0 ? ((int) $request->page - 1) * $pageSize : 0;
        $qurey->offset($page)->limit($pageSize);
    }

    public function updateData(array $supplierIds, int $purchaserId) {
        $dataList = $this->getAccess($supplierIds, $purchaserId);
        if (!empty($dataList)) {
            SupplierAccess::upsert($dataList, ['supplier_id', 'purchaser_id'], ['status', 'updated_by', 'updated_at', 'deleted_flag']);
        }
    }

    public function getAccess(array $supplierIds, int $purchaserId) {
        if (empty($supplierIds) || empty($purchaserId)) {
            return;
        }
        $admin = Auth::guard('admin')->user();
        $dataList = [];
        foreach ($supplierIds as $supplierId) {
            $dataList[] = [
                'supplier_id' => $supplierId,
                'purchaser_id' => $purchaserId,
                'status' => 'APPROVED',
                'created_by' => $admin->user_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_by' => $admin->user_id,
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted_flag' => 'N'
            ];
        }


        return $dataList;
    }

    /**
     * 审核
     *
     * @return array
     */
    public function verify(Request $request) {
        $auditId = $request->post('audit_id');
        $supplierId = $request->post('supplier_id');
        if (empty($auditId) && empty($supplierId)) {
            check(false, '供应商ID和供应商审核ID不能都为空');
        }
        $purchaserId = $this->getPPurchaserId();
        $purchaserName = Purchaser::where('id', $purchaserId)->value('name');
        $status = $request->post('status');
        $remark = $request->post('remark');
        $query = SupplierAudit::lockForUpdate()
                ->where('deleted_flag', 'N')
                ->where('audit_type', 'ACCESS')
                ->where('status', 'REVIEW')
                ->where('purchaser_id', $purchaserId);
        if ($auditId) {
            $query->where('id', $auditId);
        } else {
            $query->where('supplier_id', $supplierId);
        }
        $audit = $query->first();
        check(!empty($audit), Lang::get('response.no_data'));
        check($audit->status == Supplier::AUDIT_STATUS_AUDIT, Lang::get('supplier_audit.text_reviewed'));
        $admin = Auth::guard('admin')->user();
        SupplierAudit::where('id', $audit->id)->update([
            'user_id' => $admin->user_id,
            'remark' => $remark,
            'audit_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'status' => $status == 'PASS' ? 'PASS' : 'REJECTED',
        ]);

        SupplierAccess::where('purchaser_id', $purchaserId)
                ->where('supplier_id', $supplierId)
                ->update([
                    'status' => $status == 'PASS' ? 'APPROVED' : 'INVALID',
                    'checked_at' => date('Y-m-d H:i:s'),
                    'checked_by' => $admin->user_id,
                    'access_at' => $status == 'PASS' ? date('Y-m-d H:i:s') : null,
        ]);
        $bossUrl = env('BOSS_URL', 'https://ecpboss2.erui.com');
        $email = $this->getSupplierUserEmail($supplierId);
        $messageId = Message::where('content_id', $supplierId)
                ->where('purchaser_id', $purchaserId)
                ->where('content_operate', 'SUPPLIER_ACCESS')
                ->orderBy('id', 'DESC')
                ->value('id');
        if (!empty($messageId)) {
            MessageReceiver::where('message_id', $messageId)->update(['read_flag' => 'Y']);
        }
        switch ($status) {
            case 'PASS':
                !empty($email) ? (new SendLogRepo)->SupplierAccessPass($email, $purchaserName, $supplierId, $purchaserId) : null;
                $this->sendMessage('您向【' . $purchaserName . '】提交的准入准入申请已审核通过', '您向【' . $purchaserName . '】提交的准入申请已审核通过，【'
                        . $remark . '】。', $bossUrl . '/#/supplierClient/applyInfo?purchaser_id=' . $purchaserId, $supplierId);
            case 'REJECTED':
                $flag = !empty($email) ? (new SendLogRepo)->SupplierAccessRefuse($email, $purchaserName, $supplierId, $purchaserId) : null;
                $this->sendMessage('您向【' . $purchaserName . '】提交的准入申请审核未通过', '您向【' . $purchaserName . '】提交的准入申请审核未通过，【' . $remark . '】。', $bossUrl . '/#/supplierClient/apply?purchaser_id=' . $purchaserId, $supplierId);
                return $flag;
        }

        return true;
    }

    public function getSupplierUserEmail($supplierId) {
        $userSupplier = (new UserSupplier)->getTable();
        $user = (new User)->getTable();
        return UserSupplier::from($userSupplier . ' as us')
                        ->join($user . ' as u', function($join) {
                            $join->on('us.user_id', 'u.user_id');
                        })
                        ->where('us.supplier_id', $supplierId)
                        ->where('us.deleted_flag', 'N')
                        ->where('u.deleted_flag', 'N')
                        ->where(['us.is_manager' => '1'])
                        ->where('u.user_type', 'SUPPLIER')
                        ->value('email');
    }

    public function sendMessage($messageTitle, $message, $contentUrl, $supplierId) {
        $purchaserId = $this->getPPurchaserId();

        $messageId = Message::insertGetId([
                    'receiver_type' => 'SUPPLIER',
                    'content_url' => $contentUrl,
                    'sender_id' => $purchaserId,
                    'message_type' => 'SYSTEM',
                    'message_title' => $messageTitle,
                    'message' => $message,
                    'created_at' => date('Y-m-d H:i:s'),
        ]);
        $userIdList = UserSupplier::where('deleted_flag', 'N')->where('supplier_id', $supplierId)->pluck('user_id');
        $dataList = [];
        foreach ($userIdList as $userId) {
            $dataList[] = [
                'message_id' => $messageId,
                'receiver_id' => $userId,
                'supplier_id' => $supplierId,
                'read_flag' => 'N',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        if (empty($dataList)) {
            return $messageId;
        }
        MessageReceiver::insert($dataList);
        return $messageId;
    }

    public function info(int $supplierId, Request $request) {
        $purchaserId = $this->getPPurchaserId();

        $data = (new SupplierBaseRepo)->info($supplierId, $request);
        $data['access'] = [];
        $data['list'] = [];
        $accessSetting = (new AccessSetting)->getTable();
        $qurey = AccessSetting::from($accessSetting . ' as ae')
                ->leftJoin((new SupplierExtend)->getTable() . ' as se', function($join)use($supplierId) {
                    $join->on('se.purchaser_id', '=', 'ae.purchaser_id')
                    ->on('se.access_setting_id', '=', 'ae.id')
                    ->where('se.supplier_id', $supplierId)
                    ->where('se.deleted_flag', 'N');
                })
                ->selectRaw('ae.name,ae.type,ae.range,ae.remark,ae.required_flag,ae.sort,'
                . 'se.extend_value,ae.access_tpl_id,ae.id AS access_setting_id');
        $qurey->where('ae.purchaser_id', $purchaserId);
        $qurey->where('ae.deleted_flag', 'N');
        $obj = $qurey
                ->orderBy('ae.sort', 'ASC')
                ->get();
        $data['access'] = SupplierAccess::where('supplier_id', $supplierId)
                ->where('purchaser_id', $purchaserId)
                ->whereIn('status', ['APPROVED', 'APPROVING', 'INVALID'])
                ->first();
        if (empty($data['access'])) {
            $data['access'] = [];
        }
        if (empty($obj)) {
            return $data;
        }
        $list = $obj->toArray();
        foreach ($list as &$item) {
            if (in_array($item['type'], ['CHECKBOX', 'RADIO']) && !empty($item['range'])) {
                $item['range'] = json_decode($item['range'], true);
            } elseif (in_array($item['type'], ['CHECKBOX', 'RADIO'])) {
                $item['range'] = [];
            }
            if ($item['type'] === 'CHECKBOX' && !empty($item['extend_value'])) {
                $item['extend_value'] = json_decode($item['extend_value'], true);
            } elseif ($item['type'] === 'CHECKBOX') {
                $item['extend_value'] = [];
            }
        }
        $data['list'] = $list;
        return $data;
    }

    public function comments(Request $request) {
        $purchaserId = $this->getPPurchaserId();
        $supplierId = $request->supplier_id;
        if (empty($request->supplier_id)) {
            check(false, '供应商ID不能为空');
        }
        if (empty($supplierId)) {
            return ['data' => [], 'total' => 0];
        }
        $userTable = (new User())->getTable();
        $auditModel = new SupplierAudit();
        $query = $auditModel->selectRaw('a.status,a.audit_type,a.user_id,a.remark,'
                        . 'a.created_at,a.updated_at,a.audit_at,s.supplier_no,a.supplier_id,'
                        . 'if(u.realname="" or isnull(u.realname),u.username,u.realname) as user_name')
                ->from($auditModel->getTable() . ' as a')
                ->leftJoin($userTable . ' as u', function ($join) {
                    $join->on('u.user_id', '=', 'a.user_id')
                    ->where('u.deleted_flag', 'N');
                })
                ->join('supplier as s', 'a.supplier_id', '=', 's.id')
                ->where('a.deleted_flag', 'N')
                ->where('s.deleted_flag', 'N')
                ->where('a.audit_type', 'ACCESS')
                ->where('a.supplier_id', $supplierId)
                ->where('a.purchaser_id', $purchaserId);
        $clone = $query->clone();
        $total = $clone->count();
        $this->getPage($query, $request);
        $query->orderBy('a.created_at', 'desc');
        $object = $query->get();
        if (empty($object)) {
            return ['data' => [], 'total' => $total];
        }
        $list['total'] = $total;
        $list['data'] = $object->toArray();
        $repo = new SupplierAuditRepo();
        foreach ($list['data'] as &$item) {
            $item['audit_type_txt'] = $repo->getType($item['audit_type']);
            $item['status_txt'] = $repo->getStatus($item['status']);
        }
        return $list;
    }

    public function detail(int $supplierId, Request $request) {
        $purchaserId = $this->getPPurchaserId();
        if (empty($supplierId)) {
            check(false, '供应商ID不能为空');
        }
        $supplierAccess = (new SupplierAccess)->getTable();
        $accessTpl = (new AccessTpl)->getTable();
        $user = (new User)->getTable();
        $query = SupplierAccess::from($supplierAccess . ' AS sa')
                ->leftJoin($accessTpl . ' AS at', function($join) {
                    $join->on('at.purchaser_id', '=', 'sa.purchaser_id')
                    ->on('at.id', '=', 'sa.access_tpl_id')
                    ->where('at.deleted_flag', 'N');
                })
                ->leftJoin($user . ' as u', function($join) {
                    $join->on('u.user_id', 'sa.created_by')
                    ->where('u.deleted_flag', 'N');
                })
                ->selectRaw('sa.supplier_id,sa.purchaser_id,sa.access_at,u.realname as user_name,'
                        . 'u.phone,u.email')
                ->where('sa.supplier_id', $supplierId);
        if ($purchaserId != '100000') {
            $query->where('sa.purchaser_id', $purchaserId);
        }
        $query->where('sa.status', 'APPROVED')
                ->where('sa.deleted_flag', 'N');
        $obj = $query->get();
        if (empty($obj)) {
            return [];
        }
        $list = $obj->toArray();
        (new PurchaserRepo)->setPurchasers($list);
        return $list;
    }

    /**
     * 新增人员类型
     * @return
     */
    public function batchAdd(int $supplierId, Request $request) {
        $purchaserIds = $request->purchaser_ids;
        if (empty($purchaserIds)) {
            check(false, '请选择采购商');
        }
        $userId = UserSupplier::where('supplier_id', $supplierId)
                ->where('is_manager', '1')
                ->value('user_id');
        $admin = Auth::guard('admin')->user();
        $dataList = [];
        $auditList = [];
        foreach ($purchaserIds as $purchaserId) {
            $dataList[] = [
                'status' => 'APPROVED',
                'created_by' => $userId,
                'checked_at' => date('Y-m-d H:i:s'),
                'checked_by' => $admin->user_id,
                'access_at' => date('Y-m-d H:i:s'),
                'purchaser_id' => $purchaserId,
                'supplier_id' => $supplierId,
                'checked_by' => $admin->user_id,
                'deleted_flag' => 'N'
            ];
            $auditList[] = [
                'supplier_id' => $supplierId,
                'audit_type' => 'ACCESS',
                'status' => 'PASS',
                'purchaser_id' => $purchaserId,
                'created_at' => date('Y-m-d H:i:s'),
                'deleted_flag' => 'N'
            ];
        }
        if (!empty($dataList)) {
            SupplierAccess::upsert($dataList, ['purchaser_id', 'supplier_id'], ['status', 'access_at', 'created_by', 'deleted_flag']);
        }
        if (!empty($auditList)) {
            SupplierAudit::insert($auditList);
        }
        return true;
    }

    /**
     * 新增人员类型
     * @return
     */
    public function batchAdds(Request $request) {
        $purchaserIds = $request->purchaser_ids;
        $supplierIds = $request->supplier_ids;
        if (empty($purchaserIds)) {
            check(false, '请选择采购商');
        }
        if (empty($supplierIds)) {
            check(false, '请选择供应商');
        }
        $suserObj = UserSupplier::whereIn('supplier_id', $supplierIds)
                ->where('is_manager', '1')
                ->selectRaw('user_id,supplier_id')
                ->get();
        $sUserList = !empty($supplierIds) ? $suserObj->toArray() : [];
        $suserArr = array_column($sUserList, 'user_id', 'supplier_id');
        $admin = Auth::guard('admin')->user();
        $dataList = [];
        $auditList = [];
        foreach ($purchaserIds as $purchaserId) {
            foreach ($supplierIds as $supplierId) {
                $userId = !empty($suserArr[$supplierId]) ? $suserArr[$supplierId] : 0;
                $dataList[] = [
                    'status' => 'APPROVED',
                    'created_by' => $userId,
                    'checked_at' => date('Y-m-d H:i:s'),
                    'checked_by' => $admin->user_id,
                    'access_at' => date('Y-m-d H:i:s'),
                    'purchaser_id' => $purchaserId,
                    'supplier_id' => $supplierId,
                    'checked_by' => $admin->user_id,
                    'deleted_flag' => 'N'
                ];
                $auditList[] = [
                    'supplier_id' => $supplierId,
                    'audit_type' => 'ACCESS',
                    'status' => 'PASS',
                    'purchaser_id' => $purchaserId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'deleted_flag' => 'N'
                ];
            }
        }
        if (!empty($dataList)) {
            SupplierAccess::upsert($dataList, ['purchaser_id', 'supplier_id'], ['status', 'access_at', 'created_by', 'deleted_flag']);
        }
        if (!empty($auditList)) {
            SupplierAudit::insert($auditList);
        }
        return true;
    }

}
