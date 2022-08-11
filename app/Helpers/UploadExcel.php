<?php

namespace App\Helpers;

use App\Models\Assess\DeptList;
use App\Models\Product\Category;
use App\Models\Product\Dictionary;
use App\Models\Product\SkuLog;
use App\Models\Product\SkuTransAttr;
use App\Models\Product\Spu;
use App\Models\Product\SpuSub;
use App\Models\Product\StopSalePush;
use App\Models\ProductCategory;
use App\Models\ProductPool;
use App\Models\ProductUser;
use App\Models\Sku;
use App\Models\SpuInfo;
use App\Models\StaffList;
use App\Models\Supplier;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UploadExcel
{
    /**
     * @var array
     */
    protected static $types;

    /**
     * @var array[]
     */
    private static $brandTypes = [
        ['label' => '无品牌', 'value' => 1],
        ['label' => '境内自主品牌', 'value' => 2],
        ['label' => '境内收购品牌', 'value' => 3],
        ['label' => '境外品牌（贴牌生产）', 'value' => 4],
        ['label' => '境外品牌（其他）', 'value' => 5],
    ];

    /**
     * @var array[]
     */
    private static $exportDiscounts = [
        ['label' => '出口货物在最终目的国（地区）不享受优惠关税', 'value' => 1],
        ['label' => '出口货物在最终目的国（地区）享受优惠关税', 'value' => 2],
        ['label' => '出口货物不能确定在最终目的国（地区）享受优惠关税', 'value' => 3],
        ['label' => '不适用于进口报关单', 'value' => 4],
    ];

    /**
     * 批量更新产品池中产品信息.
     *
     * @param string $file
     * @param string $staffId
     * @param string $staffName
     */
    public static function updateFields($file, $staffId = '', $staffName = '')
    {
        ini_set('memory_limit', '512M');
        set_time_limit(1200);

        // 上传的产品Excel数据
        $dataExcel = ExcelHelper::import($file);

        // 属于spu的字段
        $arrFieldSpu = [
            'cat_id_two',
            'cat_id_one',
            're_url_one',
            'quality_std',
            'core_word',
            'content',
            'spu_name',
            'quality_type',
        ];
        // 根据type提取相关参数
        $arrType = self::filterTypeByHeaders($dataExcel[1] ?? []);

        // 需存入的表格和字段
        $tables = $arrType['tables'];
        // 是否需要更新到普源
        $isSync = $arrType['is_sync'];
        // 是否spu下所有sku是否更新到普源
        $isSyncSpuPool = $arrType['is_sync_spu_pool'];
        // 存在的字段信息
        $headFields = $arrType['head_fields'];

        // 保存错误信息
        $strError = '';
        // 存放过滤后的数据
        $data = [];
        // 检验数据
        foreach ($dataExcel as $line => $item) {
            // 第一行为标题栏，跳过
            if (1 == $line) {
                continue;
            }
            // 记录本行遇到的错误
            $errorLine = '';
            // 记录一个sku数据
            $dataLine = [];
            // 过滤一行的字段
            foreach ($item as $key => $fieldValue) {
                //当前字段名
                $field = $headFields[$key][0];
                // 字段值
                $fieldValue = trim($fieldValue);
                // 如果字段值为空，则不更新
                if (is_null($fieldValue) || '' == $fieldValue) {
                    if (!in_array($field, ['ali_attr_type', 'adjust_reason'])) {
                        continue;
                    }
                }
                // 需要做验证的字段
                switch ($field) {
                    case 'sku': // sku验证
                        $productPool = ProductPool::find($fieldValue);
                        if (is_null($productPool)) {
                            $errorLine .= 'sku('.$fieldValue.')不存在产品池中;';

                            break;
                        }
                        $dataLine['sku'] = $fieldValue;

                        break;

                    case 'sale_state': // 销售状态
                        if ('在售' == $fieldValue) {
                            $dataLine['sale_state'] = 1;
                            $dataLine['stop_sale_reason'] = '';
                        } elseif ('停售' == $fieldValue) {
                            $dataLine['sale_state'] = 0;
                        } elseif (empty($fieldValue)) {
                            $dataLine['sale_state'] = 1;
                        } else {
                            $errorLine .= '销售状态('.$fieldValue.')请填写在售或停售;';

                            break;
                        }

                        break;

                    case 'stop_sale_reason': // 停售原因
                        $dataLine['stop_sale_reason'] = $fieldValue;

                        break;

                    case 'sku_name':
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(子产品名称)不能为空;';

                            break;
                        }
                        $dataLine['sku_name'] = $fieldValue;

                        break;

                    case 'cat_one': // 验证一级品类
                        $catOneInfo = ProductCategory::where('category_name', $fieldValue)
                            ->where('parent_id', 0)
                            ->where('status', 1)
                            ->first()
                        ;
                        if (is_null($catOneInfo)) {
                            $errorLine .= '('.$fieldValue.')不符合一级品类;';

                            break;
                        }
                        $dataLine['cat_id_one'] = $catOneInfo->id;
                        $dataLine['cat_id_one_name'] = $catOneInfo->category_name;

                        break;

                    case 'cat_two': // 验证二级品类
                        $catTwoInfo = ProductCategory::where('category_name', $fieldValue)
                            ->where('status', 1)
                            ->first()
                        ;
                        if (is_null($catTwoInfo)) {
                            $errorLine .= '('.$fieldValue.')不符合二级品类;';

                            break;
                        }
                        $dataLine['cat_id_two'] = $catTwoInfo->id;
                        $dataLine['cat_two_pid'] = $catTwoInfo->parent_id;

                        break;

                    case 'pack_weight':
                        if (is_numeric($fieldValue)) {
                            $errorLine .= '字段(产品包装重量G)需传入数字;';

                            break;
                        }
                        $dataLine['pack_weight'] = $fieldValue;

                        break;

                    case 'buy_price':
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(采购价)需传入数字;';

                            break;
                        }
                        $dataLine['buy_price'] = $fieldValue;

                        break;

                    case 'currency':
                        $dicInfo = Dictionary::where('pid', 161)->where('name', strtoupper($fieldValue))->first();
                        if (is_null($dicInfo)) {
                            $errorLine .= '币种('.$fieldValue.')不存在;';

                            break;
                        }
                        $dataLine['currency'] = $dicInfo->name;

                        break;

                    case 'supplier_name': // 验证 供应商名称
                        $supplierInfo = Supplier::where('supplier_name', $fieldValue)->where('status', 2)->first();
                        if (is_null($supplierInfo)) {
                            $errorLine .= '供应商('.$fieldValue.')不存在;';

                            break;
                        }
                        $dataLine['supplier_id'] = $supplierInfo->id;
                        $dataLine['supplier_name'] = $supplierInfo->supplier_name;

                        break;

                    case 'supplier_link':
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(采购链接)不能为空;';

                            break;
                        }
                        $dataLine['supplier_link'] = $fieldValue;

                        break;

                    case 'declare_name_zh': // 中文申报名
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(中文申报名)不能为空;';

                            break;
                        }
                        $dataLine['declare_name_zh'] = $fieldValue;

                        break;

                    case 'declare_name_en': // 英文申报名
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(英文申报名)不能为空;';

                            break;
                        }
                        $dataLine['declare_name_en'] = $fieldValue;

                        break;

                    case 'customs_code': // 海关编码
                        $dataLine['customs_code'] = $fieldValue;

                        break;

                    case 'in_long': // 内箱长(cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(内箱长)需传入数字;';

                            break;
                        }
                        $dataLine['in_long'] = $fieldValue;

                        break;

                    case 'in_wide': // 内箱宽((cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(内箱宽)需传入数字;';

                            break;
                        }
                        $dataLine['in_wide'] = $fieldValue;

                        break;

                    case 'in_height': // 内箱高((cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(内箱高)需传入数字;';

                            break;
                        }
                        $dataLine['in_height'] = $fieldValue;

                        break;

                    case 'content': // 产品文案
                        $dataLine['content'] = str_replace(["\r\n", "\n"], '<br/>', $fieldValue);

                        break;

                    case 'product_long': // 产品长(cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(产品长)需传入数字;';

                            break;
                        }
                        $dataLine['product_long'] = $fieldValue;

                        break;

                    case 'product_wide': // 产品宽(cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(产品宽)需传入数字;';

                            break;
                        }
                        $dataLine['product_wide'] = $fieldValue;

                        break;

                    case 'product_height': // 产品高(cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(产品高)需传入数字;';

                            break;
                        }
                        $dataLine['product_height'] = $fieldValue;

                        break;

                    case 'out_long': // 外箱长(cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(外箱长)需传入数字;';

                            break;
                        }
                        $dataLine['out_long'] = $fieldValue;

                        break;

                    case 'out_wide': // 外箱宽(cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(外箱宽)需传入数字;';

                            break;
                        }
                        $dataLine['out_wide'] = $fieldValue;

                        break;

                    case 'out_height': // 外箱高(cm)
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(外箱高)需传入数字;';

                            break;
                        }
                        $dataLine['out_height'] = $fieldValue;

                        break;

                    case 'model': // 型号
                        $dataLine['model'] = $fieldValue;

                        break;

                    case 'brand': // 品牌
                        $dataLine['brand'] = $fieldValue;

                        break;

                    case 'application': // 用途
                        $dataLine['application'] = $fieldValue;

                        break;

                    case 'material': // 材质
                        $dataLine['material'] = $fieldValue;

                        break;

                    case 'declared_value': // 申报价值$
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(申报价值)需传入数字;';

                            break;
                        }
                        $dataLine['declared_value'] = $fieldValue;

                        break;

                    case 'weight': // 产品净重
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(产品净重)需传入数字;';

                            break;
                        }
                        $dataLine['weight'] = $fieldValue;

                        break;

                    case 'attr_trans': // 验证 运输特性
                        $fieldValue = explode(',', str_replace('，', ',', $fieldValue));
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(运输特性)不能为空;';

                            break;
                        }

                        $transAttr = [];
                        foreach ($fieldValue as $v) {
                            $transAttrInfo = Dictionary::where('pid', 105)->where('name', $v)->first();
                            if (is_null($transAttrInfo)) {
                                $errorLine .= '运输特性('.$v.')未定义;';
                            } else {
                                $transAttr[] = $transAttrInfo->name;
                            }
                        }
                        $dataLine['sku_trans_attr'] = $transAttr;
                        $dataLine['attr_trans'] = join(',', $transAttr);

                        break;

                    case 'core_word': // 核心关键词
                        if (preg_match('/[\x7f-\xff]/', $fieldValue) || preg_match('/[^\x00-\x80]/', $fieldValue)) {
                            $errorLine .= '字段(核心关键词)不能含有中文字符;';

                            break;
                        }
                        $dataLine['core_word'] = $fieldValue;

                        break;

                    case 'quality_std': // 质检标准
                        $dataLine['quality_std'] = $fieldValue;

                        break;

                    case 'quality_type': // 质检类型
                        $fieldValue = trim($fieldValue, ',，');
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(质检类型)不能为空;';

                            break;
                        }

                        $fieldValue = explode(',', str_replace('，', ',', $fieldValue));
                        $qualityTypeArr = [];
                        foreach ($fieldValue as $v) {
                            $typeInfo = Dictionary::where('pid', 104)->where('name', $v)->first();
                            if (is_null($typeInfo)) {
                                $errorLine .= '质检类型('.$v.')未定义;';
                            } else {
                                $qualityTypeArr[] = [
                                    'sub_name' => 'quality_type',
                                    'dic_id' => $typeInfo->id,
                                    'sub_value' => $typeInfo->name,
                                ];
                            }
                        }
                        $dataLine['quality_type'] = $qualityTypeArr;

                        break;

                    case 'spu_name': // 产品主名称
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(产品主名称)不能为空;';

                            break;
                        }
                        $dataLine['spu_name'] = $fieldValue;

                        break;

                    case 're_url_one': // 反向链接1
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(反向链接1)不能为空;';

                            break;
                        }
                        $dataLine['re_url_one'] = $fieldValue;

                        break;

                    case 'dev_name': //开发人
                        if (empty($fieldValue)) {
                            $errorLine .= '字段(开发负责人)不能为空，为必填项;';

                            break;
                        }

                        $staffInfo = ProductUser::where('staff_name', $fieldValue)->first();
                        if (is_null($staffInfo)) {
                            $errorLine .= '用户('.$fieldValue.')不是开发人;';

                            break;
                        }

                        $deptArr = explode(',', $staffInfo->department);
                        if (empty($deptArr)) {
                            $errorLine .= '获取用户所在组错误;';

                            break;
                        }

                        $deptInfo = DeptList::where('dept_id', $deptArr[0])->first();
                        if (is_null($deptInfo)) {
                            $errorLine .= '获取用户所在组信息错误;';

                            break;
                        }
                        $dataLine['dev_name'] = $staffInfo->staff_name;
                        $dataLine['developer'] = $staffInfo->staff_id;
                        $dataLine['developer_name'] = $staffInfo->staff_name;
                        $dataLine['depart_name'] = $deptInfo->dept_name;
                        $dataLine['depart_id'] = $deptInfo->dept_id;

                        break;

                    case 'purchaser': // 采购人
                        $purchaserInfo = StaffList::where('staff_name', $fieldValue)->first();
                        if (is_null($purchaserInfo)) {
                            $errorLine .= '采购人('.$fieldValue.')不存在;';

                            break;
                        }
                        $dataLine['purchaser'] = $purchaserInfo->staff_id;

                        break;

                    case 'maintainer': // 产品维护人
                        $maintainer = StaffList::where('staff_name', $fieldValue)->first();
                        if (is_null($maintainer)) {
                            $errorLine .= '产品维护人('.$fieldValue.')不存在;';

                            break;
                        }
                        $dataLine['maintainer_id'] = $maintainer->staff_id;
                        $dataLine['maintainer_name'] = $maintainer->staff_name;

                        break;

                    case 'performance_attributor_three': // 业绩归属人
                        $staffInfo = StaffList::where('staff_name', $fieldValue)->first();
                        if (is_null($staffInfo)) {
                            $errorLine .= '业绩归属人('.$fieldValue.')不存在;';

                            break;
                        }
                        $dataLine['performance_attributor_three'] = $staffInfo->staff_id;

                        break;

                    case 'performance_attributor_two': // 业绩归属人2
                        $staffInfo = StaffList::where('staff_name', $fieldValue)->first();
                        if (is_null($staffInfo)) {
                            $errorLine .= '业绩归属人2('.$fieldValue.')不存在;';

                            break;
                        }
                        $dataLine['performance_attributor_two'] = $staffInfo->staff_id;

                        break;

                    case 'performance_importer': // 业绩导入人
                        $staffInfo = StaffList::where('staff_name', $fieldValue)->first();
                        if (is_null($staffInfo)) {
                            $errorLine .= '业绩导入人('.$fieldValue.')不存在;';

                            break;
                        }
                        $dataLine['performance_importer'] = $staffInfo->staff_id;

                        break;

                    case 'performance_ratio': // 业绩提成比例
                        $fieldValue = (int) $fieldValue;
                        if ($fieldValue > 100) {
                            $errorLine .= '业绩提成比例('.$fieldValue.')不可大于100;';

                            break;
                        }
                        if ($fieldValue < 0) {
                            $errorLine .= '业绩提成比例('.$fieldValue.')不可小于0;';

                            break;
                        }
                        $dataLine['performance_ratio'] = $fieldValue;

                        break;

                    case 'is_naked_packaging': // 产品是否裸包装
                        if (!in_array($fieldValue, ['是', '否'])) {
                            $errorLine .= '产品是否裸包装 格式错误;';

                            break;
                        }
                        $dataLine['is_naked_packaging'] = ('是' == $fieldValue) ? 1 : 0;

                        break;

                    case 'is_warehouse_combination': // 产品是否需要到仓组合
                        if (!in_array($fieldValue, ['是', '否'])) {
                            $errorLine .= '产品是否需要到仓组合 格式错误;';

                            break;
                        }
                        $dataLine['is_warehouse_combination'] = ('是' == $fieldValue) ? 1 : 0;

                        break;

                    case 'is_fragile': // 产品是否易碎品
                        if (!in_array($fieldValue, ['是', '否'])) {
                            $errorLine .= '产品是否易碎品 格式错误;';

                            break;
                        }
                        $dataLine['is_fragile'] = ('是' == $fieldValue) ? 1 : 0;

                        break;

                    case 'tax_price': // 含税RMB
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(含税RMB)需传入数字;';

                            break;
                        }
                        $dataLine['tax_price'] = $fieldValue;

                        break;

                    case 'usd_price': // USD
                        if (!is_numeric($fieldValue)) {
                            $errorLine .= '字段(USD)需传入数字;';

                            break;
                        }
                        $dataLine['usd_price'] = $fieldValue;

                        break;

                    case 'purchase_in_arrears':
                        if (!in_array($fieldValue, ['是', '否'])) {
                            $errorLine .= '是否欠单购买 格式错误;';

                            break;
                        }
                        $dataLine['purchase_in_arrears'] = ('是' == $fieldValue) ? 1 : 0;

                        break;

                    case 'ali_attr_type':
                        $dataLine['ali_attr_type'] = $fieldValue;

                        break;

                    case 'label_type':
                        if (!in_array(strtoupper($fieldValue), Sku::$labelTypes)) {
                            $errorLine .= '贴标类型错误;';

                            break;
                        }
                        $dataLine['label_type'] = strtoupper($fieldValue);

                        break;

                    case 'adjust_reason':
                        if (empty($fieldValue)) {
                            $errorLine .= '调价原因不可为空;';

                            break;
                        }
                        $dataLine['adjust_reason'] = $fieldValue;

                        break;

                    case 'qty_pack':
                        $fieldValue = filter_var($fieldValue, FILTER_VALIDATE_INT);
                        if (empty($fieldValue)) {
                            $errorLine .= '装箱数(PCS/箱)不可为空;';

                            break;
                        }
                        $dataLine['qty_pack'] = $fieldValue;

                        break;

                    case 'invoiced_name':
                        $dataLine['invoiced_name'] = $fieldValue;

                        break;

                    case 'op_principle':
                        $dataLine['op_principle'] = $fieldValue;

                        break;

                    case 'brand_type':
                        $brandTypeMap = array_reduce(self::$brandTypes, function ($carry, $item) {
                            $carry[$item['label']] = $item['value'];

                            return $carry;
                        }, []);
                        if (!isset($brandTypeMap[$fieldValue])) {
                            $errorLine .= '品牌类型不存在;';

                            break;
                        }
                        $dataLine['brand_type'] = $brandTypeMap[$fieldValue];

                        break;

                    case 'specific_use':
                        $dataLine['specific_use'] = $fieldValue;

                        break;

                    case 'tax_refund':
                        if (!in_array($fieldValue, ['是', '否'])) {
                            $errorLine .= '是否退税 格式错误;';

                            break;
                        }
                        $dataLine['tax_refund'] = ('是' == $fieldValue) ? 1 : 0;

                        break;

                    case 'export_benefit':
                        $exportBenefitMap = array_reduce(self::$exportDiscounts, function ($carry, $item) {
                            $carry[$item['label']] = $item['value'];

                            return $carry;
                        }, []);
                        if (!isset($exportBenefitMap[$fieldValue])) {
                            $errorLine .= '出口享惠情况不存在;';

                            break;
                        }
                        $dataLine['export_benefit'] = $exportBenefitMap[$fieldValue];

                        break;

                    case 'customs_declared_code':
                        $dataLine['customs_declared_code'] = $fieldValue;

                        break;

                    case 'total_declared_tax':
                        $dataLine['total_declared_tax'] = round($fieldValue, 2);

                        break;

                    case 'customs_declared_name':
                        $dataLine['customs_declared_name'] = $fieldValue;

                        break;

                    case 'customs_declared_value':
                        $dataLine['customs_declared_value'] = round($fieldValue, 2);

                        break;

                    case 'declared_coefficient':
                        $dataLine['declared_coefficient'] = round($fieldValue, 2);

                        break;

                    case 'customs_declared_price':
                        $dataLine['customs_declared_price'] = round($fieldValue, 2);

                        break;

                    case 'customs_declared_attr':
                        $dataLine['customs_declared_attr'] = $fieldValue;

                        break;

                    case 'export_country':
                        $dataLine['export_country'] = $fieldValue;

                        break;

                    case 'category_id': // 产品类目
                        if (empty($cid = self::parseCategory($fieldValue))) {
                            $errorLine .= '产品类目不存在;';

                            break;
                        }
                        $dataLine['category_id'] = $cid;

                        break;

                    case 'purchase_memo':
                        $dataLine['purchase_memo'] = $fieldValue;

                        break;

                    case 'spot_check_type':
                        $validator = Validator::make(
                            ['spot_check_type' => $fieldValue],
                            ['spot_check_type' => 'required|in:'.join(',', Sku::$spotCheckTypes)],
                            [],
                            ['spot_check_type' => '抽检类型']
                        );
                        if ($validator->fails()) {
                            $errorLine .= $validator->errors()->first();

                            break;
                        }
                        $dataLine['spot_check_type'] = $fieldValue;

                        break;

                    case 'spot_check_percent':
                        $validator = Validator::make(
                            ['spot_check_percent' => 'required|integer|max:100|min:0'],
                            ['spot_check_percent' => $fieldValue],
                            [],
                            ['spot_check_percent' => '标准精检抽检比例']
                        );
                        if ($validator->fails()) {
                            $errorLine .= $validator->errors()->first();

                            break;
                        }
                        $dataLine['spot_check_percent'] = $fieldValue;

                        break;

                    case 'spot_check_amount':
                        $validator = Validator::make(
                            ['spot_check_amount' => 'required|integer|max:100|min:0'],
                            ['spot_check_amount' => $fieldValue],
                            [],
                            ['spot_check_amount' => '功能精检抽检数量']
                        );
                        if ($validator->fails()) {
                            $errorLine .= $validator->errors()->first();

                            break;
                        }
                        $dataLine['spot_check_amount'] = $fieldValue;

                        break;

                    case 'content_title': // 文案标题
                        $validator = Validator::make(
                            ['content_title' => 'required|size:255'],
                            ['content_title' => $fieldValue],
                            [],
                            ['content_title' => '文案标题']
                        );
                        if ($validator->fails()) {
                            $errorLine .= $validator->errors()->first();

                            break;
                        }
                        $dataLine['content_title'] = $fieldValue;

                        break;

                    case 'content_short': // 短描述
                        $dataLine['content_short'] = str_replace(["\r\n", "\n"], '<br/>', $fieldValue);

                        break;
                }
            }
            if (empty($dataLine)) {
                continue;
            }

            if (isset($dataLine['ali_attr_type']) && empty($dataLine['ali_attr_type'])) {
                $errorLine .= '1688款式类型为空;';
            }

            if (!empty($errorLine)) {
                $strError .= '第'.$line.'行有错误：'.$errorLine;
            } else {
                $data[] = $dataLine;
            }
        }

        if (!empty($strError)) {
            throw new Exception($strError);
        }

        $purchaserErrorSku = [];
        $purchaserMap = [];
        foreach ($data as $row) {
            if (!isset($row['sku']) || !isset($row['purchaser'])) {
                continue;
            }

            $skuModel = Sku::findOrEmpty($row['sku']);
            if (!$skuModel->isExists()) {
                continue;
            }

            // 相同供应商需保持同一采购员
            if (isset($row['purchaser'])) {
                if (!isset($purchaserMap[$skuModel->supplier_id])) {
                    $purchaserMap[$skuModel->supplier_id] = $row['purchaser'];
                } else {
                    if ($purchaserMap[$skuModel->supplier_id] != $row['purchaser']) {
                        $purchaserErrorSku[] = $row['sku'];
                    }
                }
            }
        }
        unset($purchaserMap);

        if (0 != count($purchaserErrorSku)) {
            throw new Exception('相同供应商需保持同一采购员，同步失败SKU('.join(',', $purchaserErrorSku).')');
        }

        // 删除文件
        unlink($file);

        $logTypeId = 26;

        $skuPoolInfo = ProductPool::whereIn('sku', array_unique(array_column($data, 'sku')))
            ->get()
            ->keyBy(function ($item) {
                return $item->sku;
            })
        ;

        // 启动事务， 保存上传的数据
        DB::beginTransaction();

        try {
            foreach ($data as $item) {
                if (!isset($skuPoolInfo[$item['sku']])) {
                    throw new Exception('sku('.$item['sku'].')不存在产品池中;');
                }

                $infoPoolSku = $skuPoolInfo[$item['sku']];
                $sku = Sku::find($item['sku']);
                $product = ProductPool::find($item['sku']);
                $spu = Spu::find($infoPoolSku['spu']);
                $spuInfo = SpuInfo::find($infoPoolSku['spu']);

                // 记录产品修改日志
                if (!empty($logSku = SkuLog::skuChangeLog($item['sku'], $item, $infoPoolSku['spu']))) {
                    SkuLog::saveLog($item['sku'], $logTypeId, $logSku, $staffId, $staffName, true);
                }

                // spu字段修改日志记录
                $fieldSpu = [];
                foreach ($arrFieldSpu as $field) {
                    if (isset($item[$field])) {
                        $fieldSpu[$field] = $item[$field];
                        unset($item[$field]);
                    }
                }
                if (!empty($fieldSpu)) {
                    if (isset($fieldSpu['quality_type']) && !empty($fieldSpu['quality_type'])) {
                        $qualityTypeSwap = [];
                        foreach ($fieldSpu['quality_type'] as $qt) {
                            $qualityTypeSwap[] = $qt['dic_id'];
                        }
                        $fieldSpu['quality_type'] = $qualityTypeSwap;
                    }
                    if (!empty($logSpu = SkuLog::skuChangeLog($item['sku'], $fieldSpu, $infoPoolSku['spu']))) {
                        $arrSku = Sku::where('spu', $infoPoolSku['spu'])->get();
                        if ($arrSku->isEmpty()) {
                            throw new Exception('spu('.$infoPoolSku['spu'].')的sku表信息获取错误;');
                        }
                        foreach ($arrSku as $s) {
                            SkuLog::saveLog($s->sku, $logTypeId, $logSpu, $staffId, $staffName, true);
                        }
                    }
                }

                // 更新 sku 表中信息
                if (!is_null($sku)) {
                    if (isset($tables['sku']) && !empty($tables['sku'])) {
                        $dataSku = [];
                        foreach ($tables['sku'] as $field) {
                            if (isset($item[$field])) {
                                $dataSku[$field] = $item[$field];
                            }
                        }
                        if (!empty($dataSku)) {
                            $sku->update($dataSku);
                        }
                    }
                }

                // 更新 product_pool 表
                if (!is_null($product)) {
                    $dataPool = [];
                    if (isset($tables['product_pool']) && !empty($tables['product_pool'])) {
                        foreach ($tables['product_pool'] as $field) {
                            if (isset($item[$field])) {
                                $dataPool[$field] = $item[$field];
                            }
                        }
                        if (!empty($dataPool)) {
                            if ($isSync) {
                                $dataPool['is_sync'] = 1;
                            }
                            $product->update($dataPool);
                        }
                    } else {
                        $dataPool['is_sync'] = 1;
                        $product->update($dataPool);
                    }
                }

                // 更新 sku_trans_attr 表
                if (isset($tables['sku_trans_attr'])
                    && !empty($tables['sku_trans_attr'])
                    && isset($item['sku_trans_attr'])) {
                    SkuTransAttr::where('sku', $item['sku'])->delete();
                    foreach ($item['sku_trans_attr'] as $attr) {
                        $skuTransAttr = new SkuTransAttr();
                        $skuTransAttr->sku = $item['sku'];
                        $skuTransAttr->attr_name = $attr;
                        $skuTransAttr->save();
                    }
                }

                // 更新 spu 表
                if (!is_null($spu)) {
                    if (isset($tables['spu']) && !empty($tables['spu'])) {
                        $dataSpu = [];
                        foreach ($tables['spu'] as $field) {
                            if (isset($item[$field])) {
                                $dataSpu[$field] = $item[$field];
                            }
                        }
                        if (!empty($dataSpu)) {
                            $spu->update($dataSpu);
                        }
                    }
                }

                // 更新 spu_info 表
                if (!is_null($spuInfo)) {
                    if (isset($tables['spu_info']) && !empty($tables['spu_info'])) {
                        $dataSpuInfo = [];
                        foreach ($tables['spu_info'] as $field) {
                            if (isset($item[$field])) {
                                $dataSpuInfo[$field] = $item[$field];
                            }
                        }
                        if (!empty($dataSpuInfo)) {
                            $spuInfo->update($dataSpuInfo);
                        }
                    }
                }

                // 更新 spu_sub 表
                if (isset($tables['spu_sub']) && !empty($tables['spu_sub'])) {
                    foreach ($tables['spu_sub'] as $subName) {
                        if (!isset($item[$subName])) {
                            continue;
                        }

                        SpuSub::where('spu', $infoPoolSku['spu'])->delete();
                        foreach ($item[$subName] as $sn) {
                            $spuSub = new SpuSub();
                            $spuSub->sub_name = $subName;
                            $spuSub->spu = $infoPoolSku['spu'];
                            $spuSub->dic_id = $sn['dic_id'];
                            $spuSub->sub_value = $sn['sub_value'];
                            $spuSub->save();
                        }
                    }
                }

                // 更新 product_pool 表中的属于spu的字段
                if (isset($tables['spu_pool']) && !empty($tables['spu_pool'])) {
                    $dataSpuPool = [];
                    foreach ($tables['spu_pool'] as $field) {
                        if (isset($item[$field])) {
                            $dataSpuPool[$field] = $item[$field];
                        }
                    }
                    if (!empty($dataSpuPool)) {
                        if ($isSyncSpuPool) {
                            $dataSpuPool['is_sync'] = 1;
                        }
                        $productPools = ProductPool::where('spu', $infoPoolSku['spu'])->get();
                        foreach ($productPools as $v) {
                            $v->update($dataSpuPool);
                        }
                    }
                } else {
                    $dataSpuPool['is_sync'] = 1;
                    $productPools = ProductPool::where('spu', $infoPoolSku['spu'])->get();
                    foreach ($productPools as $v) {
                        $v->update($dataSpuPool);
                    }
                }

                //销售状态变更
                if (isset($item['sale_state']) && 1 != $item['sale_state']) {
                    $sendInfo = [
                        'sku' => $item['sku'],
                        'developer' => $infoPoolSku['developer_name'] ?? '',
                        'developer_id' => $infoPoolSku['developer'] ?? '',
                        'depart_name' => $infoPoolSku['depart_name'] ?? '',
                        'update_by' => $staffName,
                        'update_time' => date('Y-m-d H:i:s'),
                        'stop_sale_reason' => $iteme['stop_sale_reason'] ?? '',
                    ];
                    $res = OaModel::sendSaleStateToDev($sendInfo);
                    $sendInfo['send_status'] = isset($res['code']) ? 1 : 2;
                    $sendInfo['error_reason'] = $res['msg'];
                    unset($sendInfo['developer_id']);
                    StopSalePush::create($sendInfo);
                }
            }

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    /**
     * @return array
     */
    public static function getTypes()
    {
        if (empty(self::$types)) {
            // 批量更新产品字段的参数配置
            $index = 1;
            self::$types[$index++] = [
                'desc' => '子产品名称',
                'head_fields' => [
                    ['sku_name', '子产品名称'],
                ],
                'tables' => [
                    'sku' => ['sku_name'],
                    'product_pool' => ['sku_name'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '产品品类',
                'head_fields' => [
                    ['cat_one', '一级品类'],
                    ['cat_two', '二级品类'],
                ],
                'tables' => [
                    'spu_info' => ['cat_id_two', 'cat_id_one'],
                    'product_pool' => ['cat_id_one', 'cat_id_one_name'],
                    'spu_pool' => ['cat_id_one', 'cat_id_one_name'], // 属于此spu产品池所有产品需更新
                ],
                'is_sync' => true,
                'is_sync_spu_pool' => true, // 此spu维度产品池字段是否同步普源
            ];
            self::$types[$index++] = [
                'desc' => '产品包装重量G',
                'head_fields' => [
                    ['pack_weight', '产品包装重量G'],
                ],
                'tables' => [
                    'sku' => ['pack_weight'],
                    'product_pool' => ['pack_weight'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '非含税RMB',
                'head_fields' => [
                    ['buy_price', '非含税RMB'],
                    //                ["currency","币种"],
                ],
                'tables' => [
                    //                "spu_info" => ["currency"],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '供应商名称',
                'head_fields' => [
                    ['supplier_name', '供应商名称'],
                ],
                'tables' => [
                    'sku' => ['supplier_id'],
                    'product_pool' => ['supplier_name'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '海关编码',
                'head_fields' => [
                    ['customs_code', '海关编码'],
                ],
                'tables' => [
                    'sku' => ['customs_code'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '中文申报名',
                'head_fields' => [
                    ['declare_name_zh', '中文申报名'],
                ],
                'tables' => [
                    'sku' => ['declare_name_zh'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '英文申报名', // 类型
                'head_fields' => [ // 需存入的字段
                    ['declare_name_en', '英文申报名'],
                ],
                'tables' => [ // 需要更新的表格和字段
                    'sku' => ['declare_name_en'],
                ],
                'is_sync' => true, // 是否更新到普源
            ];
            self::$types[$index++] = [
                'desc' => '内箱长宽高',
                'head_fields' => [
                    ['in_long', '内箱长(cm)'],
                    ['in_wide', '内箱宽(cm)'],
                    ['in_height', '内箱高(cm)'],
                ],
                'tables' => [
                    'sku' => ['in_long', 'in_wide', 'in_height'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '销售状态',
                'head_fields' => [
                    ['sale_state', '销售状态'],
                    ['stop_sale_reason', '停售原因'],
                ],
                'tables' => [
                    'sku' => ['sale_state', 'stop_sale_reason'],
                    'product_pool' => ['sale_state'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '采购链接',
                'head_fields' => [
                    ['supplier_link', '采购链接'],
                ],
                'tables' => [
                    'sku' => ['supplier_link'],
                    'product_pool' => ['supplier_link'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '产品长宽高',
                'head_fields' => [
                    ['product_long', '产品长(cm)'],
                    ['product_wide', '产品宽(cm)'],
                    ['product_height', '产品高(cm)'],
                ],
                'tables' => [
                    'sku' => ['product_long', 'product_wide', 'product_height'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '外箱长宽高',
                'head_fields' => [
                    ['out_long', '外箱长(cm)'],
                    ['out_wide', '外箱宽(cm)'],
                    ['out_height', '外箱高(cm)'],
                ],
                'tables' => [
                    'sku' => ['out_long', 'out_wide', 'out_height'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '型号',
                'head_fields' => [
                    ['model', '型号'],
                ],
                'tables' => [
                    'sku' => ['model'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '品牌',
                'head_fields' => [
                    ['brand', '品牌'],
                ],
                'tables' => [
                    'sku' => ['brand'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '用途',
                'head_fields' => [
                    ['application', '用途'],
                ],
                'tables' => [
                    'sku' => ['application'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '材质',
                'head_fields' => [
                    ['material', '材质'],
                ],
                'tables' => [
                    'sku' => ['material'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '申报价值$',
                'head_fields' => [
                    ['declared_value', '申报价值$'],
                ],
                'tables' => [
                    'sku' => ['declared_value'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '产品净重',
                'head_fields' => [
                    ['weight', '产品净重'],
                ],
                'tables' => [
                    'sku' => ['weight'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '运输特性',
                'head_fields' => [
                    ['attr_trans', '运输特性'],
                ],
                'tables' => [
                    'sku_trans_attr' => ['sku_trans_attr'],
                    'product_pool' => ['attr_trans'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '核心关键词',
                'head_fields' => [
                    ['core_word', '核心关键词'],
                ],
                'tables' => [
                    'spu_info' => ['core_word'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '质检标准',
                'head_fields' => [
                    ['quality_std', '质检标准'],
                ],
                'tables' => [
                    'spu_info' => ['quality_std'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '质检类型',
                'head_fields' => [
                    ['quality_type', '质检类型'],
                ],
                'tables' => [
                    'spu_sub' => ['quality_type'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '产品主名称',
                'head_fields' => [
                    ['spu_name', '产品主名称'],
                ],
                'tables' => [
                    'spu_info' => ['spu_name'],
                    // "product_pool"=>["spu_name"],
                    // "spu_pool"=>["spu_name"], // 属于此spu产品池所有产品需更新
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '反向链接1',
                'head_fields' => [
                    ['re_url_one', '反向链接1'],
                ],
                'tables' => [
                    'spu_info' => ['re_url_one'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '长描述',
                'head_fields' => [
                    ['content', '长描述'],
                ],
                'tables' => [
                    'spu_info' => ['content'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '开发人',
                'head_fields' => [
                    ['dev_name', '开发人'],
                ],
                'tables' => [
                    'spu' => ['dev_name', 'developer'],
                    'spu_info' => ['depart_name', 'depart_id'],
                    'product_pool' => ['developer', 'depart_name', 'depart_id', 'developer_name'],

                    'spu_pool' => ['developer', 'depart_name', 'depart_id', 'developer_name'],
                ],
                'is_sync' => true,
                'is_sync_spu_pool' => true, // 此spu维度产品池字段是否同步普源
            ];
            self::$types[$index++] = [
                'desc' => '采购人',
                'head_fields' => [
                    ['purchaser', '采购人'],
                ],
                'tables' => [
                    'sku' => ['purchaser'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '业绩归属人',
                'head_fields' => [
                    ['performance_attributor_three', '业绩归属人'],
                ],
                'tables' => [
                    'sku' => ['performance_attributor_three'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '业绩归属人2',
                'head_fields' => [
                    ['performance_attributor_two', '业绩归属人2'],
                ],
                'tables' => [
                    'sku' => ['performance_attributor_two'],
                ],
                'is_sync' => true,
            ];
            self::$types[$index++] = [
                'desc' => '业绩导入人',
                'head_fields' => [
                    ['performance_importer', '业绩导入人'],
                ],
                'tables' => [
                    'sku' => ['performance_importer'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '业绩提成比例',
                'head_fields' => [
                    ['performance_ratio', '业绩提成比例'],
                ],
                'tables' => [
                    'sku' => ['performance_ratio'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '产品维护人',
                'head_fields' => [
                    ['maintainer', '产品维护人'],
                ],
                'tables' => [
                    'spu' => ['maintainer_id'],
                    'product_pool' => ['maintainer_id', 'maintainer_name'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '产品是否裸包装',
                'head_fields' => [['is_naked_packaging', '产品是否裸包装']],
                'tables' => ['spu_info' => ['is_naked_packaging']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '产品是否需要到仓组合',
                'head_fields' => [['is_warehouse_combination', '产品是否需要到仓组合']],
                'tables' => ['spu_info' => ['is_warehouse_combination']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '产品是否易碎品',
                'head_fields' => [['is_fragile', '产品是否易碎品']],
                'tables' => ['spu_info' => ['is_fragile']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '含税RMB',
                'head_fields' => [
                    ['tax_price', '含税RMB'],
                ],
                'tables' => [
                    'sku' => ['tax_price'],
                    'product_pool' => ['tax_price'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => 'USD',
                'head_fields' => [
                    ['usd_price', 'USD'],
                ],
                'tables' => [
                    'sku' => ['usd_price'],
                    'product_pool' => ['usd_price'],
                ],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '是否欠单购买',
                'head_fields' => [['purchase_in_arrears', '是否欠单购买']],
                'tables' => ['sku' => ['purchase_in_arrears']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '1688款式类型',
                'head_fields' => [['ali_attr_type', '1688款式类型']],
                'tables' => ['sku' => ['ali_attr_type']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '贴标类型',
                'head_fields' => [['label_type', '贴标类型']],
                'tables' => ['sku' => ['label_type']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '装箱数(PCS/箱)',
                'head_fields' => [['qty_pack', '装箱数(PCS/箱)']],
                'tables' => ['sku' => ['qty_pack']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '开票品名',
                'head_fields' => [['invoiced_name', '开票品名']],
                'tables' => ['spu_info' => ['invoiced_name']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '工作原理',
                'head_fields' => [['op_principle', '工作原理']],
                'tables' => ['spu_info' => ['op_principle']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '品牌类型',
                'head_fields' => [['brand_type', '品牌类型']],
                'tables' => ['spu_info' => ['brand_type']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '具体用途',
                'head_fields' => [['specific_use', '具体用途']],
                'tables' => ['spu_info' => ['specific_use']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '是否退税',
                'head_fields' => [['tax_refund', '是否退税']],
                'tables' => ['spu_info' => ['tax_refund']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '出口享惠情况',
                'head_fields' => [['export_benefit', '出口享惠情况']],
                'tables' => ['sku' => ['export_benefit']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '物流海关报关编码',
                'head_fields' => [['customs_declared_code', '物流海关报关编码']],
                'tables' => ['sku' => ['customs_declared_code']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '申报税费总额',
                'head_fields' => [['total_declared_tax', '申报税费总额']],
                'tables' => ['sku' => ['total_declared_tax']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '物流报关中/英文申报名',
                'head_fields' => [['customs_declared_name', '物流报关中/英文申报名']],
                'tables' => ['sku' => ['customs_declared_name']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '报关申报价值',
                'head_fields' => [['customs_declared_value', '报关申报价值']],
                'tables' => ['sku' => ['customs_declared_value']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '产品销售链接申报系数',
                'head_fields' => [['declared_coefficient', '产品销售链接申报系数']],
                'tables' => ['sku' => ['declared_coefficient']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '报关售价',
                'head_fields' => [['customs_declared_price', '报关售价']],
                'tables' => ['sku' => ['customs_declared_price']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '报关物流属性',
                'head_fields' => [['customs_declared_attr', '报关物流属性']],
                'tables' => ['sku' => ['customs_declared_attr']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '出口国家',
                'head_fields' => [['export_country', '出口国家']],
                'tables' => ['sku' => ['export_country']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '产品类目',
                'head_fields' => [['category_id', '产品类目']],
                'tables' => ['sku' => ['category_id']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '采购备注',
                'head_fields' => [['purchase_memo', '采购备注']],
                'tables' => ['sku' => ['purchase_memo']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '抽检类型',
                'head_fields' => [['spot_check_type', '抽检类型']],
                'tables' => ['sku' => ['spot_check_type']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '标准精检抽检比例',
                'head_fields' => [['spot_check_percent', '标准精检抽检比例']],
                'tables' => ['sku' => ['spot_check_percent']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '功能精检抽检数量',
                'head_fields' => [['spot_check_amount', '功能精检抽检数量']],
                'tables' => ['sku' => ['spot_check_amount']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '文案标题',
                'head_fields' => [['content_title', '文案标题']],
                'tables' => ['spu_info' => ['content_title']],
                'is_sync' => false,
            ];
            self::$types[$index++] = [
                'desc' => '短描述',
                'head_fields' => [['content_short', '短描述']],
                'tables' => ['spu_info' => ['content_short']],
                'is_sync' => false,
            ];
        }

        return self::$types;
    }

    /**
     * @param array $headers
     *
     * @return array
     */
    public static function filterTypeByHeaders($headers)
    {
        // 存放的字段信息
        $headFields[] = ['sku', 'SKU'];
        // 需存入的表格和字段
        $tables = [];
        // 是否需要更新到普源
        $isSync = false;
        // 是否spu下所有sku是否更新到普源
        $isSyncSpuPool = false;

        $headers = collect($headers)->reduce(function ($carry, $item) {
            $carry[$item] = $item;

            return $carry;
        }, []);
        foreach (self::getTypes() as $value) {
            if (!isset($value['head_fields'][0][1])) {
                continue;
            }
            $nameCN = $value['head_fields'][0][1];
            if (!isset($headers[$nameCN])) {
                continue;
            }

            $headFields = array_merge($headFields, $value['head_fields']);
            foreach ($value['tables'] as $key => $row) {
                if (isset($tables[$key])) {
                    $tables[$key] = array_merge($tables[$key], $row);
                } else {
                    $tables[$key] = $row;
                }
            }
            $isSync = $isSync || $value['is_sync'];
            // 是否spu下所有sku是否更新到普源
            $value['is_sync_spu_pool'] = isset($value['is_sync_spu_pool']) && !empty($value['is_sync_spu_pool']);
            $isSyncSpuPool = $isSyncSpuPool || $value['is_sync_spu_pool'];
        }

        if (isset($tables['sku']) && is_array($tables['sku'])) {
            foreach ($tables['sku'] as $value) {
                if (in_array($value, ['buy_price', 'tax_price', 'usd_price'])) {
                    if (isset($tables['spu'])) {
                        $tables['spu'][] = 'adjust_reason';
                    } else {
                        $tables['spu'] = ['adjust_reason'];
                    }

                    $headFields[] = ['adjust_reason', '调价原因'];

                    break;
                }
            }
        }

        return [
            'is_sync' => $isSync,
            'is_sync_spu_pool' => $isSyncSpuPool,
            'tables' => $tables,
            'head_fields' => $headFields,
        ];
    }

    /**
     * @param string $name
     *
     * @return int
     */
    public static function parseCategory($name)
    {
        $category = Category::where('full_name', $name)->findOrEmpty();

        return $category->id ?? 0;
    }
}
