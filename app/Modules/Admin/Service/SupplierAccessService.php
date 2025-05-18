<?php

namespace App\Modules\Admin\Service;

use App\Common\Contracts\Service;
use App\Modules\Admin\Repository\SupplierAccessRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class SupplierAccessService extends Service {

    protected $guard = 'admin';
    public $middleware = [];
    public $beforeEvent = [];
    public $afterEvent = [
    ];

    public function getRules() {
        return [
            'verify' => [
                'supplier_id' => 'required',
                'status' => 'required|in:REJECTED,PASS',
                'remark' => 'required_if:status,REJECTED',
            ],
            'audit' => [
                'supplier_id' => 'required',
                'status' => 'required|in:REJECTED,PASS',
                'remark' => 'required_if:status,REJECTED',
            ]
        ];
    }

    public function getMessages() {
        return [
            'verify' => [
                'supplier_id.required' => Lang::get('supplier_audit.error_id_required'),
                'status.required' => Lang::get('supplier_audit.error_status_required'),
                'status.in' => Lang::get('supplier_audit.error_status_in'),
                'remark.required_if' => Lang::get('supplier_audit.error_remark_required_if'),
            ],
            'audit' => [
                'supplier_id.required' => Lang::get('supplier.message_supplier_id_required'),
                'status.required' => Lang::get('supplier_audit.error_status_required'),
                'status.in' => Lang::get('supplier_audit.error_status_in'),
                'remark.required_if' => Lang::get('supplier_audit.error_remark_required_if'),
            ]
        ];
    }

    protected $model;

    public function __construct() {
        parent::__construct();
    }

    /**
     * 新增人员类型
     * @return
     */
    public function getList(Request $request) {
        return (new SupplierAccessRepo)->getList($request);
    }

    /**
     * 新增人员类型
     * @return
     */
    public function deleteData(Request $request) {
        return (new SupplierAccessRepo)->deleteData($request);
    }

    /**
     * 新增人员类型
     * @return
     */
    public function info(int $supplierId, Request $request) {
        return (new SupplierAccessRepo)->info($supplierId, $request);
    }

    /**
     * 新增人员类型
     * @return
     */
    public function detail(int $supplierId, Request $request) {
        return (new SupplierAccessRepo)->detail($supplierId, $request);
    }

    /**
     * 新增人员类型
     * @return
     */
    public function batchAdd(Request $request) {
        return (new SupplierAccessRepo)->batchAdd($request->purchaser_id, $request);
    }

    /**
     * 新增人员类型
     * @return
     */
    public function verify(Request $request) {
        return (new SupplierAccessRepo)->verify($request);
    }

    /**
     * 列表页
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function comments(Request $request) {
        return (new SupplierAccessRepo)->comments($request);
    }

    /**
     * 新增人员类型
     * @return
     */
    public function batchAdds(Request $request) {
        return (new SupplierAccessRepo)->batchAdds($request);
    }

}
