<?php

namespace App\Modules\Admin\Repository;

use App\Common\Contracts\Repository;
use App\Common\Helpers\Excel;
use App\Common\Models\DivisionLevel;
use App\Modules\Admin\Repository\CountryRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\{
    IOFactory,
    Style\Alignment,
    Style\Border,
    Style\Font
};

class DivisionLevelRepo extends Repository {

    protected $model;

    public function __construct() {
        $this->model = new DivisionLevel();
        parent::__construct($this->model);
    }

    /**
     * @param Request $request
     * @param string $filed
     * @return array
     */
    public function getList(Request $request, $filed = 'id,country_id,number,enable,is_city,name') {
        $query = $this->model->selectRaw($filed);
        $this->getWhere($query, $request);
        $clone = $query->clone();
        $total = $clone->count();
        $this->getPage($query, $request);
        $object = $query->orderBy('create_time', 'DESC')->get();
        if (empty($object)) {
            return ['data' => [], 'total' => $total];
        }
        $data = $object->toArray();
        (new CountryRepo)->setCountrys($data, 'country_id', ['country' => 'name', 'country_number' => 'number']);
        $list = [];
        $list['total'] = $total;
        $list['data'] = $data;
        return $list;
    }

    /**
     * @param Request $request
     * @param string $filed
     * @return array
     */
    public function info($id) {
        $query = $this->model->selectRaw('id,country_id,number,enable,is_city,name');
        $query->where('id', $id);
        $object = $query->first();
        if (empty($object)) {
            return [];
        }
        $data = $object->toArray();
        (new CountryRepo)->setCountry($data, 'country_id', ['country' => 'name', 'country_number' => 'number']);
        return $data;
    }

    /**
     * @param int $id
     * @param Request $request
     * 
     * @return array
     */
    public function edited($id, Request $request) {
        $admin = Auth::guard('admin')->user();
        return DivisionLevel::where('id', $id)->update([
                    'number' => trim($request->number),
                    'enable' => trim($request->enable),
                    'status' => trim($request->status),
                    'modifier_id' => $admin->user_id,
                    'modify_time' => date('Y-m-d H:i:s'),
                    'country_id' => intval($request->country_id),
                    'name' => trim($request->name),
                    'is_city' => intval($request->is_city),
        ]);
    }

    /**
     * @param Request $request
     * @param string $filed
     * @return array
     */
    public function add(Request $request) {
        $admin = Auth::guard('admin')->user();
        return DivisionLevel::insertGetId([
                    'number' => !empty(trim($request->number)) ? trim($request->number) : $this->getDivisionLevelNo(),
                    'enable' => $request->enable,
                    'status' => 'C',
                    'creator_id' => $admin->user_id,
                    'create_time' => date('Y-m-d H:i:s'),
                    'country_id' => intval($request->country_id),
                    'name' => trim($request->name),
                    'is_city' => intval($request->is_city),
        ]);
    }

    /**
     * @param Array $ids
     * @param string $enable
     * @return array
     */
    public function enable(Request $request) {
        $admin = Auth::guard('admin')->user();
        $ids = $request->ids;
        return DivisionLevel::whereIn('id', $ids)->update([
                    'enable' => 1,
                    'modifier_id' => $admin->user_id,
                    'modify_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param Array $ids
     * @param string $enable
     * @return array
     */
    public function disable(Request $request) {
        $admin = Auth::guard('admin')->user();
        $ids = $request->ids;
        return DivisionLevel::whereIn('id', $ids)->update([
                    'enable' => 0,
                    'disabler_id' => $admin->user_id,
                    'disable_date' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param Array $ids
     * @param string $enable
     * @return array
     */
    public function deleteData(Request $request) {
        $ids = $request->ids;
        return DivisionLevel::whereIn('id', $ids)->delete();
    }

    /**
     * @param $query
     * @param Request $request
     * @param bool $statusFlag
     */
    protected function getWhere(&$query, Request $request) {
        if (!empty($request->keyword)) {
            $keyword = trim($request->keyword);
            $query->where(function ($q)use($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('number', 'like', '%' . $keyword . '%');
            });
        }
        if (!empty($request->country_id)) {
            $countryId = $request->country_id;
            $countryIds = is_array($countryId) ? $countryId : explode(',', trim($countryId));
            $query->whereIn('country_id', $countryIds);
        }
        if (!empty($request->status)) {
            $status = $request->status;
            $statusies = is_array($status) ? $status : explode(',', trim($status));
            $query->whereIn('status', $statusies);
        }
        if (!empty($request->enable) || $request->enable === '0') {
            $enable = $request->enable;
            $enables = is_array($enable) ? $enable : explode(',', trim($enable));
            $query->whereIn('enable', $enables);
        } else {
            $query->where('enable', 1);
        }

        if (!empty($request->createtype)) {
            $createAts = $this->getTimeByType($request->createtype);
            $query->whereBetween('create_time', $createAts);
        } elseif (!empty($request->createtime)) {
            $createtime = $request->createtime;
            $createAts = is_array($createtime) ? $createtime : explode(',', $createtime);
            !empty($createAts[1]) ? $createAts[1] = date('Y-m-d 23:59:59', strtotime($createAts[1])) : $createAts[1] = date('Y-m-d H:i:s');
            $query->whereBetween('create_time', $createAts);
        }
    }

    /**
     * Description of 获取创建人姓名
     * @param array $arr
     * @author  zhongyg
     * @date    2017-8-2 13:07:21
     * @version V2.0
     * @desc
     */
    public function setDivisionLevels(array &$list, string $field = 'divisionlv_id', $fieldKey = 'division_level') {
        if (empty($list)) {
            return;
        }
        $countryDivisionLevelIds = [];
        foreach ($list as &$val) {
            $val[$fieldKey] = '';
            if (isset($val[$field]) && $val[$field]) {
                $countryId = $val['country_id'];
                $countryDivisionLevelIds[$countryId][] = $val[$field];
            }
        }

        if (empty($countryDivisionLevelIds)) {
            return $list;
        }
        foreach ($countryDivisionLevelIds as $countryId => $divisionLevelIds) {
            $qurey = $this->model->select('id', 'name');
            $qurey->whereIn('level', $divisionLevelIds);
            $qurey->where('country_id', $countryId);
            $divisionLevelObjects = $qurey->get();
            if (empty($divisionLevelObjects)) {
                return $list;
            }
            $divisionLevels = $divisionLevelObjects->toArray();
            $divisionLevelArr = [];
            foreach ($divisionLevels as $divisionLevel) {
                $divisionLevelArr[$divisionLevel['id']] = $divisionLevel['name'];
            }
            foreach ($list as &$val) {
                if (isset($val[$field]) && isset($divisionLevelArr[$val[$field]])) {
                    $val['division_level'] = $divisionLevelArr[$val[$field]];
                }
            }
        }
    }

    /**
     * Description of 获取创建人姓名
     * @param array $arr
     * @author  zhongyg
     * @date    2017-8-2 13:07:21
     * @version V2.0
     * @desc 获取行业
     */
    public function setDivisionLevel(array &$arr, string $field = 'divisionlv_id', $fieldKey = 'division_level') {
        if (empty($arr)) {
            return;
        }
        $divisionLevelId = '';
        if (isset($arr[$field]) && $arr[$field]) {
            $divisionLevelId = $arr[$field];
            $countryId = $arr['country_id'];
        }
        $arr[$fieldKey] = '';
        if (empty($divisionLevelId)) {
            return $arr;
        }
        $arr[$fieldKey] = $this->model
                ->where('id', $divisionLevelId)
                ->where('country_id', $countryId)
                ->value('name');
    }

    /**
     * Description of 获取创建人姓名
     * @param array $arr
     * @author  zhongyg
     * @date    2017-8-2 13:07:21
     * @version V2.0
     * @desc
     */
    public function setDivisionLevelIds(array &$list, string $field = 'divisionlv', $fieldKey = 'divisionlv_id') {
        if (empty($list)) {
            return;
        }
        $lvCountryNames = [];
        foreach ($list as &$val) {
            $val[$fieldKey] = 0;
            $countryId = $val['country_id'];
            if (isset($val[$field]) && $val[$field]) {
                $lvCountryNames[$countryId][] = $val[$field];
            }
        }

        if (empty($lvCountryNames)) {
            return $list;
        }
        foreach ($lvCountryNames as $countryId => $lvNames) {
            $qurey = $this->model->select('id', 'name');
            $qurey->whereIn('name', $lvNames);
            $qurey->where('country_id', $countryId);
            $divisionLevelObjects = $qurey->get();
            if (empty($divisionLevelObjects)) {
                return $list;
            }
            $divisionLevels = $divisionLevelObjects->toArray();
            $divisionLevelArr = [];
            foreach ($divisionLevels as $divisionLevel) {
                $divisionLevelArr[$divisionLevel['name']] = $divisionLevel['id'];
            }
            foreach ($list as &$val) {
                if (isset($val[$field]) && isset($divisionLevelArr[$val[$field]])) {
                    $val[$fieldKey] = $divisionLevelArr[$val[$field]];
                }
            }
        }
    }

    /**
     * Description of 获取创建人姓名
     * @param array $arr
     * @author  zhongyg
     * @date    2017-8-2 13:07:21
     * @version V2.0
     * @desc 获取行业
     */
    public function setDivisionLevelId(array &$arr, string $field = 'divisionlv', $fieldKey = 'divisionlv_id') {
        if (empty($arr)) {
            return;
        }
        $divisionLevelName = '';
        if (isset($arr[$field]) && $arr[$field]) {
            $divisionLevelName = $arr[$field];
            $countryId = $arr['country_id'];
        }
        $arr[$fieldKey] = '';
        if (empty($divisionLevelName)) {
            return $arr;
        }
        $arr[$fieldKey] = $this->model
                ->where('name', $divisionLevelName)
                ->where('country_id', $countryId)
                ->value('id');
    }

    /**
     * 导出
     * @param $request
     * @return array
     */
    public function export(Request $request) {
        $query = $this->model->selectRaw('*');
        $this->getWhere($query, $request);
        $object = $query->get();
        if (empty($object)) {
            return [];
        }
        $data = $object->toArray();
        (new CountryRepo)->setCountrys($data, 'country_id', ['country' => 'name', 'country_number' => 'number']);
        (new UserRepo)->setUsers($data, 'modifier_id', 'modifier');
        $headName = $this->getHeadName();
        $xlsName = "DivisionLevel_" . date("YmdHis", time()) . uniqid(); //文件名称
        return $this->downloadExcel($xlsName, $data, $headName);
    }

    private $styleArray = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'font' => [
            'name' => 'Arial',
            'bold' => false,
            'italic' => false,
            'size' => 9,
            'underline' => Font::UNDERLINE_NONE,
            'strikethrough' => false,
            'color' => [
                'rgb' => '000000'
            ]
        ],
        'numberFormat' => ['formatCode' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT],
        'borders' => [
            'outline' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '00000000'],
            ],
        ],
    ];

    public function setExcelRow($sheet, $col, $row, $value, $width) {
        $sheet->setCellValue($col . $row, $value);
        $sheet->getStyle($col . $row)->applyFromArray($this->styleArray);
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    /**
     * 导出
     * @param type $request Description
     * @param $name
     * @param array $data
     * @param array $head
     * @return array
     */
    public function downloadExcel($name, $data = [], $head = []) {
        $count = count($head);  //计算表头数量
        $spreadsheet = Excel::newSpreadsheet();
        $styleArray = $this->styleArray;
        $sheet = $spreadsheet->getSpreadsheet()->getActiveSheet();
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->applyFromArray($styleArray);
        $sheet->setCellValue('A1', '行政级别');
        for ($i = 65; $i < $count + 65; $i++) {     //数字转字母从65开始
            $this->setExcelRow($sheet, strtoupper(chr($i)), 2, $head[$i - 65], 20);
        }
        $row = 3;
        foreach ($data as $item) {
            //数字转字母从65开始：
            $this->setExcelRow($sheet, 'A', $row, ' ' . $item['number'], 17);
            $sheet->getStyle('A' . $row)->applyFromArray($styleArray);

            $this->setExcelRow($sheet, 'B', $row, $item['country'], 24);
            $sheet->getStyle('B' . $row)->applyFromArray($styleArray);
            $this->setExcelRow($sheet, 'C', $row, $item['country_number'], 24);
            $sheet->getStyle('C' . $row)->applyFromArray($styleArray);
            $this->setExcelRow($sheet, 'D', $row, $item['name'], 24);
            $sheet->getStyle('D' . $row)->applyFromArray($styleArray);
            $this->setExcelRow($sheet, 'E', $row, $item['level'], 24);
            $sheet->getStyle('E' . $row)->applyFromArray($styleArray);
            $this->setExcelRow($sheet, 'F', $row, $item['is_city'] == '1' ? '是' : '否', 24);
            $sheet->getStyle('F' . $row)->applyFromArray($styleArray);
            $this->setExcelRow($sheet, 'G', $row, $item['modifier'], 24);
            $sheet->getStyle('G' . $row)->applyFromArray($styleArray);
            $this->setExcelRow($sheet, 'H', $row, $item['modify_time'], 24);
            $sheet->getStyle('H' . $row)->applyFromArray($styleArray);
            $this->setExcelRow($sheet, 'I', $row, $item['enable'] == 1 ? '可用' : '禁用', 24);
            $sheet->getStyle('I' . $row)->applyFromArray($styleArray);
            $row++;
        }
        $spreadsheet
                ->getSpreadsheet()
                ->getActiveSheet()
                ->getStyle('A1:I2')
                ->applyFromArray($styleArray);
        $realtive = "/download/" . date("Ymd") . '/';
        $filename = $name . '.xlsx';
        $filedir = base_path() . '/public' . $realtive;
        @mkdir($filedir, 0777, true);
        $filepath = $filedir . $filename;
        $spreadsheet->save($filepath);
        $url = env('APP_URL') . $realtive . $filename;
        return ['file_url' => $url, 'attach_name' => $filename];
    }

    /**
     * 获取headName
     * @param $data
     * @return array
     */
    public function getHeadName() {
        return [
            '编码',
            '国家',
            '国家编码',
            '名称',
            '级次',
            '城市',
            '最后更新人',
            '最后更新时间',
            '使用状态',
        ];
    }

    /**
     * @desc 处理业务SKU参数
     *
     * @param array $importData 规格属性
     * @return bool
     * @author zhongyg
     * @time 2019-06-14
     */
    public function importItemHandler($importData) {
        array_shift($importData); //去掉第二行数据(excel文件的标题)
        array_shift($importData);
        if (empty($importData)) {
            return ['code' => '-104', 'message' => '没有要导入的数据'];
        }
        $admin = Auth::guard('admin')->user();
        $data = $this->dataTrim($importData);
        $list = [];
        $newNumber = null;
        foreach ($data as $v) {
            $item['number'] = !empty(trim($v[0])) ? trim($v[0]) : $this->getDivisionLevelNo($newNumber); //执行标准
            $item['name'] = trim($v[3]); //执行标准
            $item['country'] = trim($v[1]); //质保期
            $item['level'] = trim($v[4]); //质保期
            $item['is_city'] = trim($v[5]) === '是' ? '1' : '0'; //质保期 
            $item['modifier_id'] = !empty($admin->user_id) ? $admin->user_id : 0; //申报要素          
            $item['creator_id'] = !empty($admin->user_id) ? $admin->user_id : 0; //申报要素     
            $item['create_time'] = date('Y-m-d H:i:s'); //申报要素          
            $item['modify_time'] = date('Y-m-d H:i:s'); //申报要素     
            $item['status'] = 'C'; //申报要素   
            $item['enable'] = trim($v[8]) === '可用' ? '1' : 0; //质保期 
            $list[] = $item;
        }
        (new CountryRepo)->setCountryIds($data);
        foreach ($list as &$item) {
            unset($item['country']);
        }
        return DivisionLevel::upsert($list, ['number'], ['name', 'country_id', 'level', 'is_city', 'modifier_id', 'modify_time', 'enable']);
    }

    /**
     * @desc 去掉数据两侧的空格
     *
     * @param mixed $data
     * @return mixed
     * @author liujf
     * @time 2018-02-02
     */
    function dataTrim($data) {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->dataTrim($v);
            }
            return $data;
        }
        if (is_object($data)) {
            foreach ($data as $k => $v) {
                $data->$k = $this->dataTrim($v);
            }
            return $data;
        }
        if (is_string($data)) {
            return trim($data);
        }
        return $data;
    }

    private function RecursiveMkdir($path) {
        if (!file_exists($path)) {
            $this->RecursiveMkdir(dirname($path));
            @mkdir($path, 0777);
        }
    }

    /**
     * 远程文件现在到本地临时目录处理完毕后自动删除)
     * @param $remoteFile 远程文件地址
     *
     * @return string 本地的临时地址
     */
    public function download2local($tmpSavePath, $remoteFile, $attach_name) {
        //设置本地临时保存目录
        $localFullFileName = $tmpSavePath . mb_convert_encoding(urldecode(basename($attach_name)), 'GB2312', 'UTF-8');
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $file = fopen($remoteFile, 'rb', null, $context);
        if ($file) {
            $newf = fopen($localFullFileName, 'wb');
            if ($newf) {
                while (!feof($file)) {
                    fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
                }
            }
        }
        if ($file) {
            fclose($file);
        }
        if ($newf) {
            fclose($newf);
        }
        return $localFullFileName;
    }

    public function import(Request $request) {
        $remoteFile = $request->file_url;
        $attachName = $request->attach_name;
        $ds = DIRECTORY_SEPARATOR;
        $tmpDir = app()->basePath() . $ds . 'resources' . $ds . 'tmp' . $ds . uniqid() . $ds;
        RecursiveMkdir($tmpDir);
        $localFile = $this->download2local($tmpDir, $remoteFile, $attachName);
        $importData = $this->ready2import($localFile, 0);
        return $this->importItemHandler($importData);
    }

    public function ready2import($localFile, $pIndex = 0) {
        //获取文件类型
        $fileType = IOFactory::identify($localFile);
        //创建PHPExcel读取对象
        $objReader = IOFactory::createReader($fileType);
        //加载文件并读取
        $officeSheet = $objReader->load($localFile);
        $data = $officeSheet->getSheet($pIndex)->toArray();
        return $data;
    }

    /**
     * 获取生成的询价单流水号
     * @author liujf 2017-06-20
     * @return string $inquirySerialNo 询价单流水号
     */
    public function getDivisionLevelNo(&$newNumber = null) {
        $prefix = 'AL';
        $qurey = $this->model->selectRaw('*');
        $number = $newNumber ? $newNumber : $qurey
                        ->where('number', 'like', $prefix . '%')
                        ->orderBy('number', 'DESC')
                        ->value('number');
        if (!empty($number)) {
            $date = substr($number, 2, 8);
            $serialSetp = substr($number, 10, 5);
            $step = intval($serialSetp);
            $step ++;
            $newNumber = $this->createSerialNo($step, $prefix, $date);
            return $newNumber;
        }
        $newNumber = $this->createSerialNo(1, $prefix, '');
        return $newNumber;
    }

    /**
     * 生成流水号
     * @param string $step 需要补零的字符
     * @param string $prefix 前缀
     * @author liujf 2019-03-11
     * @return string $code
     */
    private function createSerialNo($step = 1, $prefix = '', $date = '') {
        $time = date('Ymd');
        if (empty($date) || $date < $time) {
            $step = 1;
        }
        $pad = str_pad($step, 5, '0', STR_PAD_LEFT);
        return$prefix . $time . $pad;
    }

}
