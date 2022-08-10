<?php

namespace App\Models;

use App\Helpers\OaModel;
use App\Models\Product\PriceChangePush;
use App\Models\Product\SkuLog;
use App\Models\Product\SkuMid;
use App\Models\Product\SkuTransAttr;
use App\Models\Product\Spu;
use App\Models\Product\SpuSub;
use App\Models\Product\StopSalePush;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sku extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string[]
     */
    public static $labelTypes = ['CE', 'UKCA', '无需贴标', '铭牌'];

    /**
     * @var string[]
     */
    public static $spotCheckTypes = ['精检', '普检'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nt_sku';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'sku';

    /**
     * 更新修改后的产品信息.
     *
     * @param string $sku
     * @param array  $data
     * @param string $staffId
     * @param string $staffName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public static function updateBaseInfo($sku, $data, $staffId = '', $staffName = '')
    {
        $skuModel = Sku::find($sku);
        if (is_null($skuModel)) {
            throw new Exception('获取sku基本信息错误!');
        }

        // 记录sku字段修改日志
        $arrSkuChanges = [];
        $skuList = Sku::where('spu', $skuModel->spu)->get();
        foreach ($skuList as $v) {
            $arrSkuChanges[$v->sku] = [];
        }

        $supplier = Supplier::find($data['sku_mid']['supplier_id']);
        if ($skuModel->supplier_id != $data['sku_mid']['supplier_id']) {
            $data['pool_info']['supplier_name'] = $supplier->supplier_name ?? '';
            $data['pool_info']['supplier_link'] = $supplier->supplier_link ?? '';
        }
        unset($data['pool_info']['supplier_id']);

        // 启动事务
        DB::beginTransaction();

        try {
            $product = ProductPool::find($skuModel->sku);
            if (!is_null($product)) {
                $product->update($data['pool_info']);
            }

            // sku修改日志
            $arrSkuChanges[$skuModel->sku] = array_merge(
                $arrSkuChanges[$skuModel->sku],
                SkuLog::skuChangeLog($skuModel->sku, $data['sku_mid'], $skuModel->spu)
            );

            // 修改sku表中的数据
            $skuModel->update($data['sku_mid']);
            $skuMid = SkuMid::find($skuModel->spu);
            if (isset($data['sku_mid']['category_id']) && !empty($data['sku_mid']['category_id'])) {
                if (!is_null($skuMid) && empty($skuMid->category_id)) {
                    $skuMid->category_id = $data['sku_mid']['category_id'];
                    $skuMid->save();
                }
            }

            // 需要同步到整个款式的sku信息
            if (isset($data['data_sku_all']) && !empty($data['data_sku_all'])) {
                // 更新产品池中字段
                if (isset($data['pool_info_all']) && !empty($data['pool_info_all'])) {
                    foreach ($skuList as $v) {
                        if ($v->sku == $skuModel->sku) {
                            continue;
                        }

                        // sku修改日志
                        $arrSkuChanges[$v->sku] = array_merge(
                            $arrSkuChanges[$v->sku],
                            SkuLog::skuChangeLog($v->sku, $data['data_sku_all'], $v->spu)
                        );

                        $poolInfoAll = $data['pool_info_all'];
                        // 检查供应商是否修改 supplier_id
                        if (isset($poolInfoAll['supplier_id'])) {
                            if ($v->supplier_id != $poolInfoAll['supplier_id']) {
                                $poolInfoAll['supplier_name'] = $supplier->supplier_name ?? '';
                                $poolInfoAll['supplier_link'] = $supplier->supplier_link ?? '';
                            }
                            unset($poolInfoAll['supplier_id']);
                        }

                        $poolInfoAll['is_sync'] = 1;
                        $product = ProductPool::find($v->sku);
                        if (!is_null($product)) {
                            $product->update($poolInfoAll);
                        }
                        $v->update($data['data_sku_all']);
                    }
                }
            }

            // 物流基础信息 是否同步
            if (0 == $data['update_sku_trans']) {
                // sku修改日志
                $arrSkuChanges[$skuModel->sku] = array_merge(
                    $arrSkuChanges[$skuModel->sku],
                    SkuLog::skuChangeLog($skuModel->sku, ['arr_trans' => $data['arr_trans']], $skuModel->spu)
                );

                // 更新sku运输特性
                SkuTransAttr::where('sku', $skuModel->sku)->delete();
                foreach ($data['arr_trans'] as $trans) {
                    $skuTransAttr = new SkuTransAttr();
                    $skuTransAttr->sku = $skuModel->sku;
                    $skuTransAttr->attr_name = $trans;
                    $skuTransAttr->save();
                }

                $product = ProductPool::find($skuModel->sku);
                if (!is_null($product)) {
                    $product->attr_trans = join(',', $data['arr_trans']);
                    $product->save();
                }
            } else {
                // 更新全款sku的运输特性
                foreach ($skuList as $v) {
                    // sku 修改日志
                    $arrSkuChanges[$v->sku] = array_merge(
                        $arrSkuChanges[$v->sku],
                        SkuLog::skuChangeLog($v->sku, ['arr_trans' => $data['arr_trans']], $v->spu)
                    );

                    // sku运输特性
                    SkuTransAttr::where('sku', $v->sku)->delete();
                    foreach ($data['arr_trans'] as $trans) {
                        $skuTransAttr = new SkuTransAttr();
                        $skuTransAttr->sku = $v->sku;
                        $skuTransAttr->attr_name = $trans;
                        $skuTransAttr->save();
                    }

                    $product = ProductPool::find($v->sku);
                    if (!is_null($product)) {
                        $product->attr_trans = join(',', $data['arr_trans']);
                        $product->save();
                    }
                }
            }

            $spuChanges = array_merge($data['spu_info'], $data['spu']);
            $spuChanges['quality_type'] = $data['quality_type'];
            $spuChanges['recommend_platform'] = $data['recommend_platform'] ?? [];
            $spuChanges['forbidden_platform'] = $data['forbidden_platform'];
            // 属于spu的字段值的修改
            $logSpu = SkuLog::skuChangeLog($skuModel->sku, $spuChanges, $skuModel->spu);

            // 组合所有款式sku修改日志
            foreach ($skuList as $v) {
                $skuLogs = array_merge($arrSkuChanges[$v->sku], $logSpu);
                if (empty($skuLogs)) {
                    continue;
                }
                // 是否主管编辑
                $logTypeId = (1 == $data['update_sku_purchase']) ? 20 : 21;

                // 写入sku日志表
                SkuLog::saveLog($v->sku, $logTypeId, $skuLogs, $staffId, $staffName);
            }

            // 删除spu_sub表中旧的属性值
            SpuSub::where('spu', $skuModel->spu)->delete();
            foreach ($data['spu_sub'] as $v) {
                SpuSub::create($v);
            }

            $spuInfo = SpuInfo::find($skuModel->spu);
            if (!is_null($spuInfo)) {
                $spuInfo->update($data['spu_info']);
            }

            $spuModel = Spu::find($skuModel->spu);
            if (!is_null($spuModel)) {
                $spuModel->update($data['spu']);
            }

            // 检查是否有未进入产品池的子复制品
            $spuInfos = SpuInfo::where('origin_spu', $skuModel->spu)->get();
            if ($spuInfos->isNotEmpty()) {
                foreach ($spuInfos as $v) {
                    $spuModel = Spu::find($v->spu);
                    if (!is_null($spuModel) && $spuModel->status < 100) {
                        $dataSpuCopy = [
                            'spu_name' => $data['spu_info']['spu_name'],
                            'cat_id_one' => $data['spu_info']['cat_id_one'] ?? 0,
                            'cat_id_two' => $data['spu_info']['cat_id_two'] ?? 0,
                            'quality_std' => $data['spu_info']['quality_std'],
                            're_url_one' => $data['spu_info']['re_url_one'],
                            're_url_two' => $data['spu_info']['re_url_two'],
                            're_url_three' => $data['spu_info']['re_url_three'],
                            're_url_four' => $data['spu_info']['re_url_four'],
                            're_url_five' => $data['spu_info']['re_url_five'],
                            're_url_six' => $data['spu_info']['re_url_six'],
                            'is_check_tort' => $data['spu_info']['is_check_tort'],
                            'develop_annex' => $data['spu_info']['develop_annex'],
                            'recommend_sale_group' => $data['spu_info']['recommend_sale_group'],
                            'sales_suggestion' => $data['spu_info']['sales_suggestion'],
                            'is_early_stock' => $data['spu_info']['is_early_stock'],
                            'core_word' => $data['spu_info']['core_word'],
                            'product_attr' => $data['spu_info']['product_attr'],
                            'is_bulky' => $data['spu_info']['is_bulky'],
                            'currency' => $data['spu_info']['currency'],
                        ];
                        if (isset($data['spu_info']['depart_id'])) {
                            $dataSpuCopy['depart_id'] = $data['spu_info']['depart_id'];
                        }
                        if (isset($data['spu_info']['depart_id'])) {
                            $dataSpuCopy['depart_name'] = $data['spu_info']['depart_name'];
                        }
                        $v->update($dataSpuCopy);

                        // 删除spu_sub表复制品旧属性
                        SpuSub::where('spu', $v->spu)->delete();
                        // 更新复制品spu_sub表
                        foreach ($data['spu_sub'] as $el) {
                            $el['spu'] = $v->spu;
                            SpuSub::create($el);
                        }
                    }
                }
            }

            // 提交事务
            DB::commit();

            if (1 == $data['save_sensitive_info']) {
                //销售价格改变
                if (1 == $data['update_sku_trans']) {
                    if (0 != bccomp($skuModel->buy_price, $data['pool_info_all']['buy_price'], 2)
                        && !empty($data['spu']['developer'])) {
                        $skuArr = $skuList->pluck('sku');

                        $listingData = [];
                        $listingInfo = SpuPublished::whereIn('sku', $skuArr)->get();
                        if ($listingInfo->isNotEmpty()) {
                            foreach ($listingInfo as $v) {
                                $listingData[$v->sku][] = [
                                    'sku' => $v->sku,
                                    'shop_name' => $v->shop_name,
                                    'publish_time' => $v->publish_time,
                                    'shop_user' => $v->shop_user,
                                    'staff_name' => StaffList::where('staff_id', $v->shop_user)->first()->staff_name
                                        ?? '',
                                ];
                            }
                        }

                        foreach ($skuList as $v) {
                            if (0 != bccomp($data['pool_info_all']['buy_price'], $v->buy_price, 2)) {
                                if (isset($listingData[$v->sku])) {
                                    $sendData = [];
                                    foreach ($listingData[$v->sku] as $el) {
                                        $sendData[] = [
                                            'sku' => $v->sku,
                                            'sku_name' => $v->sku_name,
                                            'update_by' => $staffName,
                                            'update_time' => date('Y-m-d H:i:s'),
                                            'old_price' => $v->buy_price,
                                            'new_price' => $data['pool_info_all']['buy_price'],
                                            'developer' => $data['spu']['dev_name'],
                                            'depart_name' => $data['spu_info']['depart_name'],
                                            'listing_shop' => $el['shop_name'],
                                            'listing_time' => $el['publish_time'],
                                            'staff_id' => $el['shop_user'],
                                            'staff_name' => $el['staff_name'],
                                        ];
                                    }

                                    foreach ($sendData as $key => $it) {
                                        $res = OaModel::sendPriceChangeToSeller($it);
                                        $it['send_status'] = isset($res['code']) ? 1 : 2;
                                        $it['error_reason'] = $res['msg'];
                                        unset($it['staff_id'], $it['staff_name']);

                                        $sendData[$key] = $it;
                                    }

                                    $sendInfoDev = $sendData[0];
                                    $sendInfoDev['developer_id'] = $data['spu']['developer'] ?? '';
                                    OaModel::sendPriceChangeToDev($sendInfoDev);
                                    foreach ($sendData as $it) {
                                        PriceChangePush::create($it);
                                    }
                                } else {
                                    OaModel::sendPriceChangeToDev([
                                        'sku' => $v->sku,
                                        'sku_name' => $v->sku_name,
                                        'update_by' => $staffName,
                                        'update_time' => date('Y-m-d H:i:s'),
                                        'old_price' => $v->buy_price,
                                        'new_price' => $data['pool_info_all']['buy_price'],
                                        'developer' => $data['spu']['dev_name'] ?? '',
                                        'depart_name' => $data['spu_info']['depart_name'] ?? '',
                                        'developer_id' => $data['spu']['developer'] ?? '',
                                    ]);
                                }
                            }
                        }
                    }
                } else {
                    if (0 != bccomp($skuModel->buy_price, $data['sku_mid']['buy_price'], 2)
                        && !empty($data['spu']['developer'])) {
                        $listingInfo = SpuPublished::where('sku', $skuModel->sku)->get();
                        if ($listingInfo->isNotEmpty()) {
                            $sendData = [];
                            foreach ($listingInfo as $v) {
                                $sendData[] = [
                                    'sku' => $skuModel->sku,
                                    'sku_name' => $skuModel->sku_name,
                                    'update_by' => $staffName,
                                    'update_time' => date('Y-m-d H:i:s'),
                                    'old_price' => $skuModel->buy_price,
                                    'new_price' => $data['sku_mid']['buy_price'],
                                    'developer' => $data['spu']['dev_name'],
                                    'depart_name' => $data['spu_info']['depart_name'],
                                    'listing_shop' => $v->shop_name,
                                    'listing_time' => $v->publish_time,
                                    'staff_id' => $v->shop_user,
                                    'staff_name' => StaffList::where('staff_id', $v->shop_user)->first()->staff_name
                                        ?? '',
                                ];
                            }

                            foreach ($sendData as $key => $v) {
                                $res = OaModel::sendPriceChangeToSeller($v);
                                $v['send_status'] = isset($res['code']) ? 1 : 2;
                                $v['error_reason'] = $res['msg'];
                                unset($v['staff_id'], $v['staff_name']);

                                $sendData[$key] = $v;
                            }

                            $sendInfoDev = $sendData[0];
                            $sendInfoDev['developer_id'] = $data['spu']['developer'] ?? '';
                            OaModel::sendPriceChangeToDev($sendInfoDev);
                            foreach ($sendData as $v) {
                                PriceChangePush::create($v);
                            }
                        } else {
                            OaModel::sendPriceChangeToDev([
                                'sku' => $skuModel->sku,
                                'sku_name' => $skuModel->sku_name,
                                'update_by' => $staffName,
                                'update_time' => date('Y-m-d H:i:s'),
                                'old_price' => $skuModel->buy_price,
                                'new_price' => $data['sku_mid']['buy_price'],
                                'developer' => $data['spu']['dev_name'] ?? '',
                                'depart_name' => $data['spu_info']['depart_name'] ?? '',
                                'developer_id' => $data['spu']['developer'] ?? '',
                            ]);
                        }
                    }
                }

                // 销售状态改变
                if (1 == $data['update_sku_sale']) {
                    if (1 != $data['sku']['sale_state']
                        && !empty($data['sku']['stop_sale_reason'])
                        && !empty($data['spu']['developer'])) {
                        $sendData = [];
                        foreach ($skuList as $v) {
                            $sendData[] = [
                                'sku' => $v->sku,
                                'developer' => $data['spu']['dev_name'],
                                'developer_id' => $data['spu']['developer'],
                                'depart_name' => $data['spu_info']['depart_name'],
                                'update_by' => $staffName,
                                'update_time' => date('Y-m-d H:i:s'),
                                'stop_sale_reason' => $data['sku']['stop_sale_reason'],
                            ];
                        }

                        foreach ($sendData as $el) {
                            $res = OaModel::sendSaleStateToDev($el);
                            $el['send_status'] = isset($res['code']) ? 1 : 2;
                            $el['error_reason'] = $res['msg'];
                            unset($el['developer_id']);
                            StopSalePush::create($el);
                        }
                    } else {
                        if (1 == $skuModel->sale_state
                            && 1 != $data['sku']['sale_state']
                            && !empty($data['sku']['stop_sale_reason'])
                            && !empty($data['spu']['developer'])) {
                            $sendInfo = [
                                'sku' => $skuModel->sku,
                                'developer' => $data['spu']['dev_name'],
                                'developer_id' => $data['spu']['developer'],
                                'depart_name' => $data['spu_info']['depart_name'],
                                'update_by' => $staffName,
                                'update_time' => date('Y-m-d H:i:s'),
                                'stop_sale_reason' => $data['sku']['stop_sale_reason'],
                            ];
                            $res = OaModel::sendSaleStateToDev($sendInfo);
                            $sendInfo['send_status'] = isset($res['code']) ? 1 : 2;
                            $sendInfo['error_reason'] = $res['msg'];
                            unset($sendInfo['developer_id']);
                            StopSalePush::create($sendInfo);
                        }
                    }
                }
            }

            return true;
        } catch (Exception $exception) {
            // 回滚事务
            DB::rollBack();

            throw $exception;
        }
    }
}
