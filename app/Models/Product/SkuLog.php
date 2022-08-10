<?php

namespace App\Models\Product;

use App\Helpers\SkuModifiedListener;
use App\Models\DeptList;
use App\Models\ProductCategory;
use App\Models\ProductPool;
use App\Models\Sku;
use App\Models\SpuInfo;
use App\Models\StaffList;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkuLog extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nt_sku_log';

    /**
     * @param string $sku
     * @param array  $data
     * @param string $spu
     *
     * @return array
     */
    public static function skuChangeLog($sku, $data, $spu)
    {
        // 存放字段的修改记录
        $logs = [];

        // sku表中需要比较的字段
        $arrFieldSku = [
            'sku_name' => 'sku名称',
            'declare_name_zh' => '中文申报名',
            'declare_name_en' => '英文申报名',
            'weight' => '产品净重',
            'pack_weight' => '产品包装重量',
            'buy_price' => '产品采购价',
            'declared_value' => '申报价值',
            'customs_code' => '海关编码',
            'material' => '材质',
            'application' => '用途',
            'brand' => '品牌',
            'model' => '型号',
            'out_long' => '外箱长',
            'out_wide' => '外箱宽',
            'out_height' => '外箱高',
            'in_long' => '内箱长',
            'in_wide' => '内箱宽',
            'in_height' => '内箱高',
            'product_long' => '产品长',
            'product_wide' => '产品宽',
            'product_height' => '产品高',
            'is_tort' => '是否侵权',
            'sale_state' => '销售状态',
            'stop_sale_reason' => '停售原因',
            'head_product' => '是否头部产品',
            'invoice_tax_rate' => '开票税率',
            'style_one_cn' => '款式一中文属性值',
            // "style_one_en" => "款式一英文属性值",
            'style_two_cn' => '款式二中文属性值',
            // "style_two_en" => "款式二英文属性值",
            'supplier_id' => '默认供应商',
            'supplier_link' => '采购链接',
            'supplier_back_one' => '备用供应商一',
            'supplier_back_link_one' => '备用供应商链接一',
            'supplier_back_two' => '备用供应商二',
            'supplier_back_link_two' => '备用供应商链接二',
            'step_count_one' => '采购阶梯一数量',
            'step_count_two' => '采购阶梯二数量',
            'step_one_price' => '阶梯一价格',
            'step_two_price' => '阶梯二价格',
            'include_ship_cost' => '是否含运费',
            'qty_pack' => '装箱数',
            'purchase_memo' => '采购备注',
            'is_season_product' => '是否季节性产品',
            'hot_sale_months' => '热销月份',
            'image_url' => '变种图主图',
            'performance_importer' => '业绩导入人',
            'performance_ratio' => '业绩提成比例',
            'tax_price' => '含税RMB',
            'usd_price' => 'USD',
            'purchase_in_arrears' => '是否欠单购买',
            'category_id' => '产品类目',
        ];

        // spu_info表中需要比较的字段
        $arrFieldSpuInfo = [
            'spu_name' => '产品主名称',
            'cat_id_one' => '一级品类',
            'cat_id_two' => '二级品类',
            'depart_id' => '开发人部门',
            'quality_std' => '质检标准',
            're_url_one' => '反向链接一',
            're_url_two' => '反向链接二',
            're_url_three' => '反向链接三',
            'sales_suggestion' => '产品销售建议',
            'develop_annex' => '开发调研附件',
            // "pic_claim_annex" => "作图要求附件",
            'content' => '长描述',
            // "picture_claim" => "作图要求",
            // "is_urgent" => "是否加急",
            // "image_url" => "spu产品主图",
            // "recommend_sale_group" => "推荐销售组",
            // "style_one" => "款式一",
            // "style_two" => "款式二",
            'is_early_stock' => '期初是否备货',
            'content_title' => '文案标题',
            'content_short' => '短描述',
        ];

        $skuModel = Sku::find($sku);
        $spuModel = Spu::find($spu);
        $spuInfo = SpuInfo::find($spu);
        $spuSubGroup = SpuSub::where('spu', $spu)
            ->get()
            ->reduce(function ($carry, $item) {
                if (!isset($carry[$item->sub_name])) {
                    $carry[$item->sub_name] = [];
                }
                $carry[$item->sub_name][] = $item->dic_id;

                return $carry;
            }, [])
        ;
        foreach ($data as $field => $newStr) {
            // 初始化$desc, 用于判断是否有需要比较的字段
            $desc = '';

            // spu 表中字段
            if ('developer' == $field) {
                //产品开发人
                $oldStr = $spuModel->developer ?? '';
                $desc = '产品开发人';
            }
            // spu_sub中存放的字段
            if ('quality_type' == $field || 'recommend_platform' == $field || 'forbidden_platform' == $field) {
                $oldStr = $spuSubGroup[$field] ?? [];
                sort($oldStr);
                sort($newStr);

                switch ($field) {
                    case 'quality_type':
                        $desc = '质检类型';

                        break;

                    case 'recommend_platform':
                        $desc = '推荐上架平台';

                        break;

                    case 'forbidden_platform':
                        $desc = '禁止上架平台';

                        break;
                }
            }

            // spu_info表中字段
            if (isset($arrFieldSpuInfo[$field])) {
                $oldStr = $spuInfo->{$field} ?? '';
                $desc = $arrFieldSpuInfo[$field];
            }

            // sku 表中字段
            if (isset($arrFieldSku[$field])) {
                $oldStr = $skuModel->{$field} ?? '';
                $desc = $arrFieldSku[$field];

                switch ($field) {
                    case 'category_id':
                        $oldStr = Category::find($oldStr)->full_name ?? '';
                        $newStr = Category::find($newStr)->full_name ?? '';

                        break;
                }
            }

            if ('maintainer_id' == $field) {
                $product = ProductPool::find($sku);
                if (!is_null($product) && $product->maintainer_id != $newStr) {
                    $oldStr = $product->maintainer_name;
                    $newStr = StaffList::where('staff_id', $newStr)->first()->staff_name ?? '';
                    $desc = '产品维护人';
                }
            }

            // sku_trans_attr 表中字段
            if ('arr_trans' == $field) {
                // 运输特性
                $arrTrans = SkuTransAttr::where('sku', $sku)->get()->pluck('attr_name')->toArray();
                sort($arrTrans);
                sort($newStr);
                $oldStr = join(',', $arrTrans);
                $newStr = join(',', $newStr);
                $desc = '运输特性';
            }

            if (empty($desc)) {
                continue;
            }

            $log = SkuLog::fieldLog($field, $oldStr, $newStr, $desc);
            if (!empty($log)) {
                $logs[$field] = $log;
            }
        }

        // 内箱长*宽*高
        if (isset($data['in_long'], $data['in_wide'], $data['in_height'])) {
            $logs['length_width_height']['old_str'] = join(
                '*',
                [$skuModel->in_long ?? '', $skuModel->in_wide ?? '', $skuModel->in_height ?? '']
            );
            $logs['length_width_height']['new_str'] = join(
                '*',
                [$data['in_long'], $data['in_wide'], $data['in_height']]
            );
        }

        return $logs;
    }

    /**
     * @param string $field
     * @param string $oldStr
     * @param string $newStr
     * @param string $desc
     *
     * @return array|false
     */
    public static function fieldLog($field, $oldStr, $newStr, $desc)
    {
        // 比较字段值是否变化
        if ($oldStr == $newStr) {
            return false;
        }

        $compFields = ['buy_price', 'tax_price', 'usd_price'];
        if (in_array($field, $compFields)) {
            if (0 == bccomp($oldStr, $newStr, 2)) {
                return false;
            }
        }

        // 字段值为ID数组的字段
        $arrFieldArray = [
            'quality_type',
            'recommend_platform',
            'forbidden_platform',
        ];
        if (in_array($field, $arrFieldArray)) {
            // 修改前的字段值
            if (empty($oldStr)) {
                $oldStr = '';
            } else {
                $oldDictionaries = Dictionary::whereIn('id', $oldStr)->get();
                $oldStr = '';
                foreach ($oldDictionaries as $v) {
                    $oldStr .= ','.$v->name;
                }
                $oldStr = trim($oldStr, ',');
            }
            // 修改后的字段值
            if (empty($newStr)) {
                $newStr = '';
            } else {
                $newDictionaries = Dictionary::whereIn('id', $newStr)->get();
                $newStr = '';
                foreach ($newDictionaries as $v) {
                    $newStr .= ','.$v->name;
                }
                $newStr = trim($newStr);
            }
        }

        // 0,1转换为是否的字段
        $arrFieldRadio = [
            'sale_state',
            'head_product',
            'include_ship_cost',
            'is_season_product',
            'is_tort',
            'is_early_stock',
            'purchase_in_arrears',
        ];
        if (in_array($field, $arrFieldRadio)) {
            if ('sale_state' == $field) {
                $oldStr = (1 == $oldStr) ? '在售' : '停售';
                $newStr = (1 == $newStr) ? '在售' : '停售';
            } else {
                $oldStr = (1 == $oldStr) ? '是' : '否';
                $newStr = (1 == $newStr) ? '是' : '否';
            }
        }

        // 需将ID转换为name的字段
        $arrFieldTrans = [
            'developer',
            'supplier_id',
            'performance_importer',
            'cat_id_one',
            'cat_id_two',
            'depart_id',
        ];
        if (in_array($field, $arrFieldTrans)) {
            switch ($field) {
                case 'supplier_id': //默认供应商
                    $oldStr = Supplier::find($oldStr)->supplier_name ?? '';
                    $newStr = Supplier::find($newStr)->supplier_name ?? '';

                    break;

                case 'developer': //产品开发人
                case 'performance_importer': // 业绩导入人
                    $oldStr = StaffList::where('staff_id', $oldStr)->first()->staff_name ?? '';
                    $newStr = StaffList::where('staff_id', $newStr)->first()->staff_name ?? '';

                    break;

                case 'cat_id_one': //一级品类
                case 'cat_id_two': //二级品类
                    $oldStr = ProductCategory::find($oldStr)->category_name ?? '';
                    $newStr = ProductCategory::find($newStr)->category_name ?? '';

                    break;

                case 'depart_id': // 开发人部门
                    $oldStr = DeptList::where('dept_id', $oldStr)->first()->dept_name ?? '';
                    $newStr = DeptList::where('dept_id', $newStr)->first()->dept_name ?? '';

                    break;
            }
        }

        return ['old_str' => $oldStr, 'new_str' => $newStr, 'desc' => $desc];
    }

    /**
     * @param string $sku
     * @param int    $logTypeId
     * @param array  $skuLogs
     * @param string $createId
     * @param string $createName
     * @param false  $isCache
     */
    public static function saveLog($sku, $logTypeId, $skuLogs, $createId, $createName, $isCache = false)
    {
        (new SkuModifiedListener())->handle($sku, $skuLogs, $logTypeId, $createId, $createName, $isCache);
    }
}
