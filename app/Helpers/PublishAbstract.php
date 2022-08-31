<?php

namespace App\Helpers;

use App\Models\Product\ToNtEbayApi;
use App\Models\Product\ToNtEbayQueue;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

abstract class PublishAbstract
{
    /**
     * 刊登接口地址.
     *
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $platform;

    /**
     * 字段映射.
     *
     * @var string[]
     */
    protected $fieldMap = [
        'sku_name' => 'title', // 子产品名称
        'sku_bt_image' => 'design_image', // sku变种图
        'content' => 'description', // 产品文案
        // "" => "variety_attrs", // 变种属性， 产品池暂不可修改此相关字段
        'sale_state' => 'sell_status', // 销售状态
        'arr_trans' => 'shipping_attrs', // 运输特性/产品属性
        'pack_weight' => 'package_weight', // 包装重量
        'buy_price' => 'buy_price', // 采购价
        'length_width_height' => 'length_width_height', // 内箱长*宽*高
        'is_tort' => 'is_tort', // 内箱长*宽*高
    ];

    /**
     * 推送.
     *
     * @param string $sku
     * @param array  $changes
     * @param string $createId
     * @param bool   $cache
     *
     * @return bool
     */
    public function push($sku, $changes, $createId, $cache = false)
    {
        if (empty($this->url)) {
            return false;
        }
        $postData = [];
        foreach ($changes as $key => $change) {
            if (!array_key_exists($key, $this->fieldMap)) {
                continue;
            }
            // 文章
            if ('content' == $key) {
                if (empty($change['old_str'])) {
                    $change['old_str'] = '';
                }
                if (empty($change['new_str'])) {
                    $change['new_str'] = '';
                }
            }
            // 采购价格
            if ('buy_price' == $key) {
                $change['old_str'] = floatval($change['old_str']);
            }

            $postData[$this->fieldMap[$key]] = [
                'change_before' => $change['old_str'],
                'change_after' => $change['new_str'],
            ];

            // 销售状态
            if ('sale_state' == $key) {
                if ('在售' == $change['old_str']) {
                    $postData[$this->fieldMap[$key]]['change_before'] = '正常';
                }
                if ('在售' == $change['new_str']) {
                    $postData[$this->fieldMap[$key]]['change_after'] = '正常';
                }
            }
        }

        if (!empty($postData)) {
            foreach ($postData as $key => $item) {
                $postData[$key] = json_encode($item, JSON_UNESCAPED_UNICODE);
            }
            $postData['sku'] = $sku;
            $postData['staff_id'] = $createId;
            $postData['modify_date'] = date('Y-m-d H:i:s');

            if ($cache) {
                $this->enqueue($sku, $postData);

                return true;
            }

            return $this->request($postData, $sku);
        }

        return false;
    }

    /**
     * 请求推送接口.
     *
     * @param array  $postData
     * @param string $sku
     *
     * @return bool
     */
    public function request($postData, $sku)
    {
        $client = new Client();

        try {
            $response = $client->request('POST', $this->url, [RequestOptions::JSON => $postData]);

            // 记录日志
            $toNtEbayApi = new ToNtEbayApi();
            $toNtEbayApi->post_data = substr(json_encode($postData, JSON_UNESCAPED_UNICODE), 0, 60000);
            $toNtEbayApi->return_msg = json_encode(
                json_decode($response->getBody()->getContents(), true),
                JSON_UNESCAPED_UNICODE
            );
            $toNtEbayApi->http_code = $response->getStatusCode();
            $toNtEbayApi->sku = $sku;
            $toNtEbayApi->platform = $this->platform;
            $toNtEbayApi->save();

            return true;
        } catch (Exception | GuzzleException $exception) {
        }

        return false;
    }

    /**
     * 推送数据暂存数据库.
     *
     * @param string $sku
     * @param array  $data
     */
    public function enqueue($sku, $data)
    {
        $toNtEbayQueue = new ToNtEbayQueue();
        $toNtEbayQueue->sku = $sku;
        $toNtEbayQueue->change_text = json_encode($data, JSON_UNESCAPED_UNICODE);
        $toNtEbayQueue->create_at = date('Y-m-d H:i:s');
        $toNtEbayQueue->is_send = 0;
        $toNtEbayQueue->save();
    }
}
