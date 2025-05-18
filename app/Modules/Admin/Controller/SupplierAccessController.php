<?php

namespace App\Modules\Admin\Controller;

use App\Common\Contracts\Controller;
use App\Modules\Admin\Admin;
use Illuminate\Http\Request;

class SupplierAccessController extends Controller {

    public function getRules() {
        return [];
    }

    /**
     * 列表页
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getList(Request $request) {
        return Admin::service('SupplierAccessService')->getList($request);
    }

    /**
     * 列表页
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request) {
        return Admin::service('SupplierAccessService')->deleteData($request);
    }

    /**
     * 列表页
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function info(int $supplier_id, Request $request) {
        return Admin::service('SupplierAccessService')->info($supplier_id, $request);
    }

    public function detail(int $supplier_id, Request $request) {
        return Admin::service('SupplierAccessService')->detail($supplier_id, $request);
    }

    /**
     * 列表页
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request) {
        return Admin::service('SupplierAccessService')->pass($request->all())->runTransaction('verify');
    }

    /**
     * 列表页
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function comments(Request $request) {
        return Admin::service('SupplierAccessService')->comments($request);
    }

    public function batchAdd(int $purchaser_id, Request $request) {
        $request->merge(['purchaser_id' => $purchaser_id]);
        return Admin::service('SupplierAccessService')->pass($request)->runTransaction('batchAdd');
    }

    public function batchAdds(Request $request) {
        return Admin::service('SupplierAccessService')->pass($request)->runTransaction('batchAdds');
    }

}
