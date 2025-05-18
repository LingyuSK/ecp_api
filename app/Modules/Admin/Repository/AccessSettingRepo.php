<?php

namespace App\Modules\Admin\Repository;

use App\Common\Contracts\Repository;
use App\Common\Models\{
    AccessSetting,
    AccessTpl,
    Purchaser
};
use Illuminate\Http\Request,
    Illuminate\Support\Facades\Auth;

class AccessSettingRepo extends Repository {

    protected $model;
    protected $sorts = [
    ];

    public function __construct() {
        $this->model = new AccessSetting();
        parent::__construct($this->model);
    }

    /**
     * 获取合同列表
     * @param Request $request
     * @param string $filed
     * @return array
     */
    public function getList(int $purchaserId) {
        $table = $this->model->getTable();
        $accessTpl = (new AccessTpl)->getTable();
        $query = $this->model
                ->from($table . ' as as')
                ->join($accessTpl . ' AS at', function($join) {
                    $join->on('as.access_tpl_id', '=', 'at.id');
                })
                ->selectRaw('`as`.*,`at`.name AS tpl_name')
                ->where('as.deleted_flag', 'N')
                ->where('as.purchaser_id', $purchaserId)
                ->orderBy('as.sort', 'ASC');
        $object = $query->get();
        if (empty($object)) {
            return [];
        }
        $data = $object->toArray();
        foreach ($data as &$item) {
            $item['range'] = json_decode($item['range'], true);
            switch ($item['type']) {
                case 'TEXT':
                    $item['type_name'] = '文本';
                    break;
                case 'RADIO':
                    $item['type_name'] = '单选';
                    break;
                case 'CHECKBOX':
                    $item['type_name'] = '多选';
                    break;
                case 'DATE':
                    $item['type_name'] = '时间';
                    break;
            }
        }
        $userRepo = new UserRepo();
        $userRepo->setUsers($data, 'created_by', 'created_name');
        $userRepo->setUsers($data, 'updated_by', 'updated_name');
        return $data;
    }

    /**
     * 获取合同列表
     * @param int $noticeId
     * @param Request $request
     * 
     * @return array
     */
    public function edited(Request $request) {
        if (empty($request->purchaser_id)) {
            check(false, '采购商ID不能为空');
        }
        $admin = Auth::guard('admin')->user();
        $data = $request->all();
        if (empty($data['list'])) {
            check(false, '供应商准入配置不能为空');
        }
        $exist = AccessTpl::where('purchaser_id', $request->purchaser_id)->first();
        $purchaserName = Purchaser::where('id', $request->purchaser_id)->value('name');
        if (empty($exist)) {
            $accessTplId = AccessTpl::insertGetId([
                        'purchaser_id' => $request->purchaser_id,
                        'name' => $purchaserName . '准入模板',
                        'created_by' => $admin->user_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_by' => $admin->user_id,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'deleted_flag' => 'N',
            ]);
        } else {
            $accessTplId = $exist->id;
        }
        AccessSetting::where('purchaser_id', $request->purchaser_id)
                ->update(['deleted_flag' => 'Y']);
        $dataList = [];
        foreach ($data['list'] as $key => $item) {
            if (empty($item['type'])) {
                check(false, '供应商准入配置类型不能为空');
            }
            if (!in_array($item['type'], ['DATE', 'CHECKBOX', 'RADIO', 'TEXT'])) {
                check(false, '供应商准入配置类型不正确');
            }
            if (empty($item['name'])) {
                check(false, '供应商准入配置名称不能为空');
            }

            $dataList[] = [
                'id' => !empty($item['id']) ? $item['id'] : null,
                'access_tpl_id' => $accessTplId,
                'purchaser_id' => $request->purchaser_id,
                'name' => !empty($item['name']) ? $item['name'] : null,
                'type' => !empty($item['type']) ? $item['type'] : null,
                'range' => !empty($item['range']) ? json_encode($item['range']) : null,
                'remark' => !empty($item['remark']) ? $item['remark'] : null,
                'required_flag' => !empty($item['required_flag']) ? $item['required_flag'] : 'N',
                'sort' => $key + 1,
                'created_by' => $admin->user_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_by' => $admin->user_id,
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted_flag' => 'N',
            ];
        }
        if (empty($dataList)) {
            check(false, '供应商准入配置不能为空');
        }
        return AccessSetting::upsert($dataList, ['id'], ['purchaser_id',
                    'name',
                    'type',
                    'required_flag',
                    'range',
                    'remark',
                    'sort',
                    'updated_by',
                    'updated_at',
                    'deleted_flag'
        ]);
    }

}
