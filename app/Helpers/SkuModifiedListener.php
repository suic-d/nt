<?php

namespace App\Helpers;

use App\Models\Product\SkuLog;
use App\Models\Sku;
use App\Models\SpuPublished;
use App\Models\StaffList;
use App\Repositories\PurchaseEvaluationRepository;
use App\Repositories\StockRepository;
use Exception;

class SkuModifiedListener
{
    /**
     * @param string $sku
     * @param array  $changes
     * @param int    $logType
     * @param string $createId
     * @param string $createName
     * @param bool   $cache
     */
    public function handle($sku, $changes, $logType, $createId, $createName, $cache)
    {
        try {
            // 记录日志
            $this->log($sku, $logType, $changes, $createId, $createName);
            // 推送自动化刊登
            if (isset($changes['sale_state']['old_str'], $changes['sale_state']['new_str'])
                && $changes['sale_state']['old_str'] != $changes['sale_state']['new_str']) {
                $skuModel = Sku::find($sku);
                if (!is_null($skuModel)) {
                    PurchaseEvaluationRepository::pushSaleStateChangeSpu($skuModel->spu);
                }
            }
            // 推送刊登
            $this->push($sku, $changes, $createId, $cache);
            // 钉钉推送通知
            $this->pushMessage($sku, $changes);
        } catch (Exception $exception) {
        }
    }

    /**
     * 推送刊登.
     *
     * @param string $sku
     * @param array  $changes
     * @param string $createId
     * @param bool   $cache
     */
    public function push($sku, $changes, $createId, $cache)
    {
        if ($cache) {
            // 推送ebay
            (new EBay())->push($sku, $changes, $createId, $cache);

            return;
        }

        $changesAmazon = $changes;
        if (isset($changes['sale_state']['old_str'], $changes['sale_state']['new_str'])) {
            if ('在售' != $changes['sale_state']['old_str']
                || '停售' != $changes['sale_state']['new_str']
                || 0 != (new StockRepository())->getDirectHairWarehouseStock($sku)) {
                unset($changesAmazon['sale_state']);
            }
        }

        $pushEbay = false;
        $pushAmazon = false;
        $pushAliExpress = false;
        $pushLazada = false;
        $pushShopee = false;
        $eBay = new EBay();
        $amazon = new Amazon();
        $aliExpress = new AliExpress();
        $lazada = new Lazada();
        $shopee = new Shopee();
        for ($i = 0; $i < 5; ++$i) {
            // 推送ebay
            if (!$pushEbay) {
                $pushEbay = $eBay->push($sku, $changes, $createId);
            }
            // 推送amazon
            if (!$pushAmazon) {
                $pushAmazon = $amazon->push($sku, $changesAmazon, $createId);
            }
            // 推送aliexpress
            if (!$pushAliExpress) {
                $pushAliExpress = $aliExpress->push($sku, $changes, $createId);
            }
            // 推送lazada
            if (!$pushLazada) {
                $pushLazada = $lazada->push($sku, $changes, $createId);
            }
            // 推送shopee
            if (!$pushShopee) {
                $pushShopee = $shopee->push($sku, $changes, $createId);
            }
        }
    }

    /**
     * @param string $sku
     * @param int    $logType
     * @param array  $changes
     * @param string $createId
     * @param string $createName
     */
    public function log($sku, $logType, $changes, $createId, $createName)
    {
        $remark = '';
        if (is_string($changes)) {
            $remark = $changes;
        } else {
            foreach ($changes as $key => $change) {
                // 内箱长宽高、文案不存本地日志
                if ('content' == $key || 'length_width_height' == $key) {
                    continue;
                }
                if ('sku_bt_image' == $key) {
                    $change['old_str'] = join(',', $change['old_str']);
                    $change['new_str'] = join(',', $change['new_str']);
                }
                if ('buy_price' == $key) {
                    $change['old_str'] = floatval($change['old_str']);
                }
                $remark .= sprintf('%s:由(%s)修改为(%s);', $change['desc'], $change['old_str'], $change['new_str']);
            }
        }
        // 写入日志
        $log = new SkuLog();
        $log->sku = $sku;
        $log->log_type_id = $logType;
        $log->remark = $remark;
        $log->create_id = $createId;
        $log->create_name = $createName;
        $log->create_at = date('Y-m-d H:i:s');
        $log->save();
    }

    /**
     * 钉钉推送通知.
     *
     * @param string $sku
     * @param array  $changes
     * @param string $updaterName
     */
    public function pushMessage($sku, $changes, $updaterName = '')
    {
        $spuPublishedModels = SpuPublished::where('sku', $sku)->get();
        if ($spuPublishedModels->isEmpty()) {
            return;
        }

        $staffMap = StaffList::whereIn('staff_id', $spuPublishedModels->pluck('shop_user'))
            ->get(['staff_id', 'staff_name'])
            ->pluck('staff_name', 'staff_id')
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
                        $staffMap[$user] ?? '',
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
