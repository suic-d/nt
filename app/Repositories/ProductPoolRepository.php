<?php

namespace App\Repositories;

use App\Helpers\DingTalk;
use App\Models\Product\SkuLog;
use App\Models\Product\SkuMid;
use App\Models\Product\SkuStepPrice;
use App\Models\ProductPool;
use App\Models\Sku;
use App\Models\SpuPublished;
use App\Models\StaffList;

class ProductPoolRepository
{
    /**
     * @param string $sku
     * @param float  $price
     * @param int    $priceType
     * @param string $creatorId
     * @param string $creatorName
     */
    public static function syncBuyPrice($sku, $price, $priceType, $creatorId, $creatorName)
    {
        $productPool = ProductPool::find($sku);
        if (is_null($productPool)) {
            return;
        }

        $skuModel = Sku::find($sku);
        $skuMid = SkuMid::find($productPool->spu);
        $remark = '';

        switch ($priceType) {
            case 1:
                $priceOld = $productPool->buy_price;
                $productPool->buy_price = $price;
                $productPool->save();
                $declaredValue = floor(((($price / 7) * (0.3 * 10)) / 10) * 100) / 100;
                if (!is_null($skuModel)) {
                    $skuModel->buy_price = $price;
                    $skuModel->declared_value = $declaredValue;
                    $skuModel->save();
                }
                if (!is_null($skuMid)) {
                    $skuMid->buy_price = $price;
                    $skuMid->declared_value = $declaredValue;
                    $skuMid->save();
                }
                self::updateStepPrice($sku, $price);

                self::pushMessage(
                    $sku,
                    ['buy_price' => ['old_str' => $priceOld, 'new_str' => $price]],
                    $creatorName
                );
                $remark = sprintf('非含税RMB：由(%s)修改为(%s);', $priceOld, $price);

                break;

            case 2:
                $priceOld = $productPool->tax_price;
                $productPool->tax_price = $price;
                $productPool->save();
                if (!is_null($skuModel)) {
                    $skuModel->tax_price = $price;
                    $skuModel->save();
                }
                if (!is_null($skuMid)) {
                    $skuMid->tax_price = $price;
                    $skuMid->save();
                }

                self::pushMessage(
                    $sku,
                    ['tax_price' => ['old_str' => $priceOld, 'new_str' => $price]],
                    $creatorName
                );
                $remark = sprintf('含税RMB：由(%s)修改为(%s);', $priceOld, $price);

                break;

            case 3:
                $priceOld = $productPool->usd_price;
                $productPool->usd_price = $price;
                $productPool->save();
                if (!is_null($skuModel)) {
                    $skuModel->usd_price = $price;
                    $skuModel->save();
                }
                if (!is_null($skuMid)) {
                    $skuMid->usd_price = $price;
                    $skuMid->save();
                }

                self::pushMessage(
                    $sku,
                    ['usd_price' => ['old_str' => $priceOld, 'new_str' => $price]],
                    $creatorName
                );
                $remark = sprintf('USD：由(%s)修改为(%s);', $priceOld, $price);

                break;
        }

        if (isset($priceOld)) {
            self::skuLog($sku, $remark, $creatorId, $creatorName);
        }
    }

    /**
     * @param string $sku
     * @param string $remark
     * @param string $creatorId
     * @param string $creatorName
     */
    public static function skuLog($sku, $remark, $creatorId, $creatorName)
    {
        $log = new SkuLog();
        $log->sku = $sku;
        $log->log_type_id = 38;
        $log->remark = $remark;
        $log->create_at = date('Y-m-d H:i:s');
        $log->create_id = $creatorId;
        $log->create_name = $creatorName;
        $log->save();
    }

    /**
     * @param string $sku
     * @param float  $price
     */
    public static function updateStepPrice($sku, $price)
    {
        $stepPrice = SkuStepPrice::where('sku', $sku)->where('field_num', 1)->first();
        if (is_null($stepPrice)) {
            return;
        }

        $stepPrice->step_price = $price;
        $stepPrice->save();
    }

    public static function pushMessage($sku, $changes, $updaterName = '')
    {
        $spuPublishedModels = SpuPublished::where('sku', $sku)->get();
        if ($spuPublishedModels->isEmpty()) {
            return;
        }

        $staffMap = StaffList::whereIn('staff_id', $spuPublishedModels->pluck('shop_user'))
            ->get()
            ->keyBy(function ($item) {
                return $item->staff_id;
            })
        ;

        $skuModel = Sku::find($sku);

        $spuPublishedGroup = $spuPublishedModels->reduce(function ($carry, $item) {
            $shop = $item->shop_name;
            if ('lazada' == strtolower($item->platform)
                && preg_match('/lazada-[0-9]+/i', $item->shop_name, $matches)
                && isset($matches[0])) {
                $shop = $matches[0];
            }
            $carry[$item->shop_user][$shop] = $item;

            return $carry;
        }, []);
        foreach ($spuPublishedGroup as $user => $items) {
            foreach ($items as $shop => $item) {
                $titles = [];
                $contents = [];
                // 运输特性
                if (isset($changes['arr_trans']) && !empty($changes['arr_trans'])) {
                    $titles[] = '运输特性';
                    $contents[] = sprintf(
                        '运输特性由 %s 修改为 %s ',
                        $changes['arr_trans']['old_str'] ?? '',
                        $changes['arr_trans']['new_str'] ?? ''
                    );
                }
                // 产品包装重量
                if (isset($changes['pack_weight']) && !empty($changes['pack_weight'])) {
                    $titles[] = '产品包装实重(G)';
                    $contents[] = sprintf(
                        '产品包装实重(G)由 %s 修改为 %s ',
                        $changes['pack_weight']['old_str'] ?? '',
                        $changes['pack_weight']['new_str'] ?? ''
                    );
                }
                // 非含税RMB
                if (isset($changes['buy_price']) && !empty($changes['buy_price'])) {
                    $titles[] = '非含税RMB';
                    $contents[] = sprintf(
                        '非含税RMB由 %s 修改为 %s ',
                        $changes['buy_price']['old_str'] ?? '',
                        $changes['buy_price']['new_str'] ?? ''
                    );
                }
                // 是否侵权
                if ('lazada' != strtolower($item->platform)) {
                    if (isset($changes['is_tort']) && !empty($changes['is_tort'])) {
                        $titles[] = '侵权状态';
                        $contents[] = sprintf(
                            '侵权状态由 %s 修改为 %s ',
                            $changes['is_tort']['old_str'] ?? '',
                            $changes['is_tort']['new_str'] ?? ''
                        );
                    }
                }
                // 销售状态
                if (isset($changes['sale_state']) && !empty($changes['sale_state'])) {
                    $titles[] = '销售状态';
                    $contents[] = sprintf(
                        '销售状态由 %s 修改为 %s ',
                        $changes['sale_state']['old_str'] ?? '',
                        $changes['sale_state']['new_str'] ?? ''
                    );
                }
                // 含税RMB
                if (isset($changes['tax_price']) && !empty($changes['tax_price'])) {
                    $titles[] = '含税RMB';
                    $contents[] = sprintf(
                        '含税RMB由 %s 修改为 %s ',
                        $changes['tax_price']['old_str'] ?? '',
                        $changes['tax_price']['new_str'] ?? ''
                    );
                }
                // USD
                if (isset($changes['usd_price']) && !empty($changes['usd_price'])) {
                    $titles[] = 'USD';
                    $contents[] = sprintf(
                        'USD由 %s 修改为 %s ',
                        $changes['usd_price']['old_str'] ?? '',
                        $changes['usd_price']['new_str'] ?? ''
                    );
                }

                if (!empty($titles) && !empty($contents)) {
                    $message = sprintf(
                        '尊敬的：%s\n\n亲，您在 %s 上架至 %s 的SKU：%s\n\n品名：%s\n\n%s\n\n更新时间：%s\n\n修改人：%s\n\n请知悉！',
                        $staffMap[$user]->staff_name ?? '',
                        $item->publish_time,
                        $shop,
                        $item->sku,
                        $skuModel->sku_name ?? '',
                        join('\n\n', $contents),
                        date('Y-m-d H:i:s'),
                        $updaterName
                    );
                    (new DingTalk())->push(join('|', $titles).'变更通知', $message, $user);
                }
            }
        }
    }
}
